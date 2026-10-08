<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Services\KeteranganLampiranService;
use Database\Seeders\ClusterUhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pratinjau Uraian pada kolom Keterangan Lampiran.
 *
 * Dua hal yang dijaga di sini:
 *
 *  1. Pratinjau yang tampil di formulir SAMA PERSIS dengan yang tercetak di
 *     Lampiran PDF. Keduanya memakai KeteranganLampiranService, dan test ini
 *     membandingkan hasil endpoint pratinjau dengan hasil cetak NPD yang
 *     benar-benar disimpan dari isian yang sama.
 *
 *  2. Mode otomatis TIDAK menyimpan teksnya. Isian yang tampil hanyalah
 *     pratinjau; yang tersimpan tetap null supaya uraiannya terus mengikuti
 *     data - persis perilaku sebelum pratinjau ini ada. Mode manual
 *     sebaliknya: teksnya membeku dan tidak ikut berubah.
 */
class PratinjauUraianLampiranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClusterUhSeeder::class);
    }

    private function pptk(): User
    {
        return User::create([
            'username' => 'uraian-pptk',
            'nama' => 'Uraian PPTK',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);
    }

    private function anggaran(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Uraian',
            'kegiatan' => 'Kegiatan Uji Uraian',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Uraian',
            'kode_rekening' => '5.1.02.04.01.0001',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    private function suratPerintah(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '077/SP/URAIAN/2026',
            'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Cirebon',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 - 22 Juli 2026',
            'keterangan' => 'Pemeriksaan reguler',
            'file_url' => 'sp/uraian.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true,
            'dipantau' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadPd(MasterAnggaran $anggaran, SuratPerintah $sp): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'surat_perintah_id' => $sp->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_sp' => 'Pemeriksaan reguler',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Cirebon',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-22',
            'penerima_index' => 0,
            'tim' => [[
                'nama' => 'Anggota Uraian',
                'jabatan' => 'Auditor',
                'nip' => '198001012000011001',
                'rekening' => '111111',
                'tol' => 50_000,
                'paket' => [[
                    'cluster' => 'D',
                    'wilayah' => 'Kota Cirebon',
                    'lama_hari' => 2,
                    'tarif_uh' => 430_000,
                    'malam' => 1,
                    'tarif_akom' => 500_000,
                ]],
            ]],
        ];
    }

    public function test_pratinjau_sama_persis_dengan_uraian_yang_tercetak(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $sp = $this->suratPerintah();
        $payload = $this->payloadPd($anggaran, $sp);

        // Pratinjau dari isian formulir yang BELUM disimpan.
        $pratinjau = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'pd',
                'surat_perintah_id' => $sp->id,
                'uraian_sp' => $payload['uraian_sp'],
                'tanggal_berangkat' => $payload['tanggal_berangkat'],
                'tanggal_pulang' => $payload['tanggal_pulang'],
                'penerima_index' => 0,
                'tim' => $payload['tim'],
            ])
            ->assertOk()
            ->json('teks');

        // Uraian yang benar-benar dipakai dokumen, dari NPD yang tersimpan.
        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload);
        $npd = Npd::with('tim.paket')->sole();

        $metode = new \ReflectionMethod(\App\Http\Controllers\NpdController::class, 'bangunLampiranPd');
        $metode->setAccessible(true);
        $tercetak = $metode->invoke(app(\App\Http\Controllers\NpdController::class), $npd)['keterangan'];

        $this->assertSame($tercetak, $pratinjau);

        // Sekaligus memastikan isinya memang kalimat yang diharapkan, bukan
        // dua string kosong yang kebetulan sama.
        $this->assertStringContainsString('uang harian, akomodasi dan transport', $pratinjau);
        $this->assertStringContainsString('20 Juli 2026 s.d 22 Juli 2026', $pratinjau);
        $this->assertStringContainsString('077/SP/URAIAN/2026', $pratinjau);
        $this->assertStringContainsString('an. Anggota Uraian', $pratinjau);
    }

    public function test_mode_otomatis_tidak_menyimpan_teks_sehingga_uraian_tetap_mengikuti_data(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $payload = $this->payloadPd($anggaran, $this->suratPerintah());

        // Yang dikirim peramban pada mode otomatis: kotaknya berisi pratinjau,
        // tetapi penandanya bilang itu bukan tulisan petugas.
        $payload['keterangan_mode'] = 'otomatis';
        $payload['keterangan_lampiran'] = 'Pratinjau yang tidak boleh tersimpan';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)->assertSessionHasNoErrors();

        $npd = Npd::sole();

        $this->assertNull($npd->detail_json['keterangan_lampiran']);
    }

    public function test_mode_manual_menyimpan_teks_apa_adanya(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $payload = $this->payloadPd($anggaran, $this->suratPerintah());

        $payload['keterangan_mode'] = 'manual';
        $payload['keterangan_lampiran'] = 'Uraian tulisan tangan petugas';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('Uraian tulisan tangan petugas', Npd::sole()->detail_json['keterangan_lampiran']);
    }

    public function test_tanpa_penanda_mode_teks_tetap_tersimpan_seperti_sebelumnya(): void
    {
        // Menjaga kompatibilitas: import, test lama, dan formulir yang belum
        // diperbarui tidak mengirim keterangan_mode sama sekali.
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $payload = $this->payloadPd($anggaran, $this->suratPerintah());
        $payload['keterangan_lampiran'] = 'Uraian tanpa penanda mode';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('Uraian tanpa penanda mode', Npd::sole()->detail_json['keterangan_lampiran']);
    }

    public function test_pratinjau_memakai_nomor_sp_dari_basis_data_bukan_isian(): void
    {
        $pptk = $this->pptk();
        $sp = $this->suratPerintah();

        $teks = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'pd',
                'surat_perintah_id' => $sp->id,
                'nomor_sp' => '999/PALSU/2026',
                'tanggal_sp' => '2020-01-01',
                'tim' => [],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertStringContainsString('077/SP/URAIAN/2026', $teks);
        $this->assertStringNotContainsString('999/PALSU/2026', $teks);
    }

    public function test_pratinjau_formulir_setengah_terisi_tidak_error(): void
    {
        $teks = $this->actingAs($this->pptk())
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), ['jenis' => 'pd'])
            ->assertOk()
            ->json('teks');

        // Bagian yang belum diisi cukup kosong - pratinjau bukan tempat
        // memvalidasi kelengkapan formulir.
        $this->assertStringStartsWith('Transfer Pembayaran Belanja Perjalanan Dinas Biasa', $teks);
    }

    public function test_pratinjau_kontribusi_diklat_mengikuti_mode_dan_daftar_penerima(): void
    {
        $pptk = $this->pptk();

        $kontribusi = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'kd',
                'mode' => 'kontribusi',
                'nama_pelatihan' => 'Diklat Auditor Ahli Muda',
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-08-05',
                'penerima_index' => 0,
                'peserta' => [['nama' => 'Peserta Satu']],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertStringStartsWith('Transfer Pembayaran Belanja Kontribusi Diklat', $kontribusi);
        $this->assertStringContainsString('an. Peserta Satu', $kontribusi);

        $perjalanan = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'kd',
                'mode' => 'perjalanan',
                'nama_pelatihan' => 'Diklat Auditor Ahli Muda',
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-08-05',
                'penerima_index' => 0,
                'peserta' => [['nama' => 'Peserta Satu']],
                'penerima_transfer' => [['nama' => 'Peserta Satu'], ['nama' => 'Peserta Dua'], ['nama' => '']],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertStringStartsWith('Transfer Pembayaran Belanja Perjalanan Dinas', $perjalanan);
        // Tiap penerima mendapat baris Lampiran sendiri yang hanya menyebut
        // namanya; pratinjau menampilkan baris pertama.
        $this->assertStringEndsWith(' an. Peserta Satu', $perjalanan);
        $this->assertStringNotContainsString('Peserta Dua', $perjalanan);
    }

    public function test_pratinjau_transport_mewarisi_uraian_manual_induknya(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $payload = $this->payloadPd($anggaran, $this->suratPerintah());
        $payload['keterangan_mode'] = 'manual';
        $payload['keterangan_lampiran'] = 'Uraian induk yang ditulis tangan';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload);
        $induk = Npd::sole();

        $teks = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'tr',
                'npd_induk_id' => $induk->id,
                'penerima_index' => 0,
                'tim' => [['tol' => 25_000]],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertSame('Uraian induk yang ditulis tangan', $teks);
    }

    public function test_pratinjau_transport_memakai_biaya_timnya_sendiri_bila_induk_otomatis(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $this->suratPerintah()));
        $induk = Npd::sole();

        $teks = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'tr',
                'npd_induk_id' => $induk->id,
                'penerima_index' => 0,
                // Transport hanya menanggung transport - frasa komponennya
                // harus menyempit, tidak ikut uang harian/akomodasi induk.
                'tim' => [['tol' => 25_000]],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertStringContainsString('(transport)', $teks);
        $this->assertStringNotContainsString('uang harian', $teks);
        // Identitas perjalanannya tetap milik induk.
        $this->assertStringContainsString('077/SP/URAIAN/2026', $teks);
    }

    /**
     * BPP dan Verifikator ikut boleh sejak keduanya bisa membuka formulir
     * Edit NPD - formulir itulah yang memanggil pratinjau ini. Pemantau
     * (Bendahara Pengeluaran) tidak pernah membuka formulirnya.
     */
    public function test_hanya_pemegang_formulir_npd_yang_boleh_meminta_pratinjau(): void
    {
        $pemantau = User::create([
            'username' => 'uraian-bp',
            'nama' => 'Uraian BP',
            'role' => 'bendahara_pengeluaran',
            'password' => 'rahasia',
        ]);

        $this->actingAs($pemantau)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), ['jenis' => 'pd'])
            ->assertForbidden();
    }

    public function test_frasa_komponen_mengikuti_biaya_yang_benar_benar_dipakai(): void
    {
        // Dijaga terpisah karena frasa inilah bagian Uraian yang paling mudah
        // berubah tanpa disadari saat perhitungan biaya disentuh.
        $this->assertSame('', KeteranganLampiranService::komponenPd([])['komp_str']);

        $this->assertSame('uang harian', KeteranganLampiranService::komponenPd([
            ['paket' => [['lama_hari' => 1, 'tarif_uh' => 100]]],
        ])['komp_str']);

        $this->assertSame('uang harian dan akomodasi', KeteranganLampiranService::komponenPd([
            ['paket' => [['lama_hari' => 1, 'tarif_uh' => 100, 'malam' => 1, 'tarif_akom' => 200]]],
        ])['komp_str']);

        $this->assertSame('uang harian, akomodasi, transport dan uang representatif', KeteranganLampiranService::komponenPd([
            ['paket' => [['lama_hari' => 1, 'tarif_uh' => 100, 'malam' => 1, 'tarif_akom' => 200]], 'tol' => 50, 'representatif' => 25],
        ])['komp_str']);
    }

    private function anggaranDalamKota(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Dalam Kota',
            'kegiatan' => 'Kegiatan Uji Dalam Kota',
            'sub_kegiatan' => '6.01.01.2.02 Sub Kegiatan Uji Dalam Kota',
            'kode_rekening' => KeteranganLampiranService::KODE_REKENING_DALAM_KOTA,
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    public function test_uraian_mengikuti_nama_mata_anggaran_dalam_kota(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaranDalamKota();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $sp = $this->suratPerintah();
        $payload = $this->payloadPd($anggaran, $sp);

        $pratinjau = $this->actingAs($pptk)
            ->postJson(route('npd.keterangan-lampiran.pratinjau'), [
                'jenis' => 'pd',
                'master_anggaran_id' => $anggaran->id,
                'surat_perintah_id' => $sp->id,
                'uraian_sp' => $payload['uraian_sp'],
                'tanggal_berangkat' => $payload['tanggal_berangkat'],
                'tanggal_pulang' => $payload['tanggal_pulang'],
                'penerima_index' => 0,
                'tim' => $payload['tim'],
            ])
            ->assertOk()
            ->json('teks');

        $this->assertStringStartsWith('Transfer Pembayaran Belanja Perjalanan Dinas Dalam Kota', $pratinjau);
        $this->assertStringNotContainsString('Perjalanan Dinas Biasa', $pratinjau);

        // Dan yang tercetak harus sama persis dengan pratinjaunya.
        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload);
        $npd = Npd::with('tim.paket')->sole();

        $metode = new \ReflectionMethod(\App\Http\Controllers\NpdController::class, 'bangunLampiranPd');
        $metode->setAccessible(true);

        $this->assertSame(
            $metode->invoke(app(\App\Http\Controllers\NpdController::class), $npd)['keterangan'],
            $pratinjau
        );
    }

    public function test_daftar_pembayaran_dan_spd_ikut_menyebut_dalam_kota(): void
    {
        // Ketiga dokumen memakai kalimat yang sama; kalau hanya Lampiran yang
        // berubah, satu NPD akan menyebut dua nama belanja berbeda.
        $pptk = $this->pptk();
        $anggaran = $this->anggaranDalamKota();
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $this->suratPerintah()));
        $npd = Npd::sole();

        $metode = new \ReflectionMethod(\App\Http\Controllers\NpdController::class, 'komponenBiayaPd');
        $metode->setAccessible(true);
        $komponen = $metode->invoke(
            app(\App\Http\Controllers\NpdController::class),
            $npd->load('masterAnggaran', 'tim.paket')
        );

        $this->assertStringStartsWith('Pembayaran Belanja Perjalanan Dinas Dalam Kota', $komponen['uraian_biaya']);

        // Dokumen yang memakai frasa itu tetap tercetak utuh.
        foreach (['npd.cetak-daftar', 'npd.cetak-spd'] as $rute) {
            $this->actingAs($pptk)->get(route($rute, $npd))->assertOk();
        }
    }

    public function test_mata_anggaran_lain_tetap_memakai_frasa_biasa(): void
    {
        $this->assertSame(
            'Belanja Perjalanan Dinas Biasa',
            KeteranganLampiranService::frasaBelanja('5.1.02.04.001.00001')
        );
        $this->assertSame(
            'Belanja Perjalanan Dinas Biasa',
            KeteranganLampiranService::frasaBelanja(null)
        );
        $this->assertSame(
            'Belanja Perjalanan Dinas Dalam Kota',
            KeteranganLampiranService::frasaBelanja(KeteranganLampiranService::KODE_REKENING_DALAM_KOTA)
        );
    }
}
