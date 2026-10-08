<?php

namespace Tests\Feature;

use App\Http\Requests\StoreSuratPerintahRequest;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Input Surat Perintah — aturan diselaraskan dengan prosesInputSP() dan
 * _spNormalisasiAnggota() di gas-lama/CodeSuratPerintah.gs.
 */
class SuratPerintahInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Halaman layanan kini di balik gerbang kata sandi bersama. Yang diuji
        // di berkas ini isi halamannya, bukan gerbangnya - gerbangnya punya
        // GerbangLayananTest sendiri.
        $this->lolosGerbangLayanan();
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'uji-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function pegawai(string $nama, array $override = []): Pegawai
    {
        return Pegawai::create(array_replace([
            'nama' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'golongan' => 'III/c',
            'pangkat' => 'Penata',
            'jabatan' => 'Auditor Ahli Muda',
            'bidang' => 'Inspektur Pembantu I',
            'rekening' => '100200300',
            'aktif' => true,
        ], $override));
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_replace([
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'nomor_sp' => '087/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim Uji',
            'tujuan_transfer' => 'Koordinator Uji',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'komponen' => ['Uang Harian', 'Akomodasi'],
            'jenis_pembayaran' => ['Dalam Daerah/Luar Daerah'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ], $override);
    }

    // ---------------- Komponen Pembayaran ----------------

    /**
     * Unit Kerja pada Input SP hanya enam - sama dengan GAS. "Subbagian Tata
     * Usaha" sempat ikut terdaftar padahal itu milik pengelompokan bidang
     * SPJ, bukan unit penerbit Surat Perintah.
     */
    public function test_unit_kerja_hanya_enam_dan_sama_dengan_gas(): void
    {
        $this->assertSame([
            'Inspektur Pembantu I',
            'Inspektur Pembantu II',
            'Inspektur Pembantu III',
            'Inspektur Pembantu IV',
            'Inspektur Pembantu Investigasi',
            'Sekretariat',
        ], StoreSuratPerintahRequest::UNIT_KERJA);

        $this->assertNotContains('Subbagian Tata Usaha', StoreSuratPerintahRequest::UNIT_KERJA);
    }

    public function test_komponen_pembayaran_mengisi_kolom_pengajuan_dan_wajib_dipilih(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');

        $anggota = [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']];

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'komponen' => [],
            'jenis_pembayaran' => ['Dalam Daerah/Luar Daerah'],
            'anggota' => $anggota,
        ]))->assertSessionHasErrors('komponen');

        $this->assertSame(0, SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'komponen' => ['Uang Harian', 'Transport'],
            'jenis_pembayaran' => ['Dalam Daerah/Luar Daerah'],
            'anggota' => $anggota,
        ]))->assertRedirect(route('surat-perintah.index'));

        $this->assertSame('Uang Harian, Transport', SuratPerintah::sole()->pengajuan);
    }

    // ---------------- Anggota ----------------

    public function test_anggota_wajib_minimal_satu_orang(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user('pptk'))
            ->post(route('surat-perintah.store'), $this->payload(['anggota' => []]))
            ->assertSessionHasErrors('anggota');

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_nama_di_luar_master_hanya_diterima_lewat_isi_manual(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');

        // Tanpa flag manual: ditolak, sama seperti GAS.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['nama' => 'Orang Luar Instansi', 'jabatan_sp' => 'Anggota']],
        ]))->assertSessionHasErrors('anggota');

        $this->assertSame(0, SuratPerintah::count());

        // Dengan Isi Manual: diterima beserta identitas ketikan pengguna.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [[
                'nama' => 'Orang Luar Instansi',
                'manual' => '1',
                'nip' => '198001012005011003',
                'golongan' => 'IV/a',
                'pangkat' => 'Pembina',
                'jabatan' => 'Auditor Madya',
                'rekening' => '900800700',
                'jabatan_sp' => 'Anggota',
            ]],
        ]))->assertRedirect(route('surat-perintah.index'));

        $anggota = SuratPerintah::sole()->anggota->sole();
        $this->assertNull($anggota->pegawai_id, 'Anggota manual tidak boleh dikaitkan ke master Pegawai.');
        $this->assertTrue($anggota->manual);
        $this->assertSame('Orang Luar Instansi', $anggota->nama);
        $this->assertSame('Auditor Madya', $anggota->jabatan);
        $this->assertSame('IV/a', $anggota->golongan);
    }

    public function test_nama_anggota_ganda_dan_jabatan_tim_tidak_sah_ditolak(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [
                ['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim'],
                ['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Anggota'],
            ],
        ]))->assertSessionHasErrors('anggota');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Komandan']],
        ]))->assertSessionHasErrors('anggota.0.jabatan_sp');

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_jabatan_dalam_tim_bersifat_opsional_dan_mengenal_wakil_penanggungjawab(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $satu = $this->pegawai('Tanpa Jabatan Tim');
        $dua = $this->pegawai('Wakil Penanggung');

        // Daftar jabatan mengikuti SP_JABATAN_TIM di GAS, termasuk ejaannya.
        $this->assertSame([
            'Penanggungjawab',
            'Wakil Penanggungjawab',
            'Pengendali Teknis',
            'Ketua Tim',
            'Anggota',
        ], SuratPerintah::JABATAN_ANGGOTA);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [
                ['pegawai_id' => $satu->id, 'nama' => $satu->nama],
                ['pegawai_id' => $dua->id, 'nama' => $dua->nama, 'jabatan_sp' => 'Wakil Penanggungjawab'],
            ],
        ]))->assertRedirect(route('surat-perintah.index'));

        $anggota = SuratPerintah::sole()->anggota;
        $this->assertNull($anggota[0]->jabatan_sp);
        $this->assertSame('Wakil Penanggungjawab', $anggota[1]->jabatan_sp);
    }

    public function test_snapshot_anggota_tidak_ikut_berubah_saat_master_pegawai_diperbarui(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso', ['jabatan' => 'Auditor Ahli Pertama']);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]));

        $orang->update(['jabatan' => 'Auditor Ahli Madya', 'nip' => '111111111111111111']);

        $anggota = SuratPerintah::sole()->anggota->sole();
        $this->assertSame('Auditor Ahli Pertama', $anggota->jabatan, 'Dokumen historis tidak boleh ikut berubah.');
        $this->assertNotSame('111111111111111111', $anggota->nip);
    }

    public function test_nomor_sp_ganda_ditolak(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');
        $anggota = [['pegawai_id' => $orang->id, 'nama' => $orang->nama]];

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload(['anggota' => $anggota]));
        $this->assertSame(1, SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload(['anggota' => $anggota]))
            ->assertSessionHasErrors('nomor_sp');

        $this->assertSame(1, SuratPerintah::count());
    }

    // ---------------- Reimburse Transportasi (sudah dihapus) ----------------

    /**
     * Jenis "Reimburse Transportasi" dulu khusus melayani NPD Transport.
     * Pembuatan NPD Transport sudah dihapus - transport dibayar lewat NPD
     * Perjalanan Dinas dengan mencentang komponen Transport - jadi jenis ini
     * tidak boleh lagi bisa diinput, lewat formulir maupun langsung.
     */
    public function test_jenis_reimburse_transportasi_tidak_lagi_bisa_diinput(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Ketua Induk');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]))->assertRedirect(route('surat-perintah.index'));
        $induk = SuratPerintah::sole();

        // Kiriman lama (menunjuk SP induk) ditolak...
        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertSessionHasErrors('jenis_permintaan');

        // ...begitu juga lewat formulir publik.
        $this->post(route('sp.input.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertSessionHasErrors('jenis_permintaan');

        $this->assertSame(1, SuratPerintah::count());
        $this->assertSame(0, SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->count());

        // Formulirnya tidak lagi menawarkan pilihan jenis maupun SP induk.
        foreach ([route('surat-perintah.create'), route('sp.input.create')] as $alamat) {
            $this->actingAs($pptk)->get($alamat)
                ->assertOk()
                ->assertDontSee('name="jenis_permintaan"', false)
                ->assertDontSee('name="sp_induk_id"', false)
                ->assertDontSee('Jenis Permintaan Pembayaran')
                // Komponen Transport tetap ada: itulah jalur pembayaran transport sekarang.
                ->assertSee('Transport');
        }
    }

    public function test_sp_baru_tanpa_jenis_selalu_tersimpan_sebagai_uang_harian_akomodasi(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');

        $payload = $this->payload([
            'komponen' => ['Uang Harian', 'Transport'],
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama]],
        ]);
        unset($payload['jenis_permintaan']);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $payload)
            ->assertRedirect(route('surat-perintah.index'));

        $sp = SuratPerintah::sole();
        $this->assertSame(SuratPerintah::JENIS_UANG_HARIAN, $sp->jenis_permintaan);
        $this->assertSame('Uang Harian, Transport', $sp->pengajuan);
        $this->assertNull($sp->sp_induk_id);
    }

    // ---------------- Form publik ----------------

    public function test_form_publik_menerima_input_tanpa_login(): void
    {
        Storage::fake('local');
        $orang = $this->pegawai('Budi Santoso');

        $this->post(route('sp.input.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]))->assertRedirect(route('surat-perintah.monitoring'));

        $sp = SuratPerintah::sole();
        $this->assertTrue($sp->dipantau);
        $this->assertTrue($sp->sumber_npd, 'SP baru otomatis menjadi sumber data NPD.');
        $this->assertSame(SuratPerintah::JENIS_UANG_HARIAN, $sp->jenis_permintaan);
    }

    // ---------------- Jenis Pembayaran (adopsi GAS #77c) ----------------

    public function test_jenis_pembayaran_tersimpan_dan_boleh_dua_duanya(): void
    {
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Ketua Tim');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'jenis_pembayaran' => ['Dalam Daerah/Luar Daerah', 'Dalam Kota'],
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama]],
        ]))->assertRedirect(route('surat-perintah.index'));

        $sp = SuratPerintah::sole();

        $this->assertSame('Dalam Daerah/Luar Daerah, Dalam Kota', $sp->jenis_pembayaran);
        $this->assertSame(['Dalam Daerah/Luar Daerah', 'Dalam Kota'], $sp->jenisPembayaranArray());
    }

    public function test_jenis_pembayaran_wajib_dipilih_minimal_satu(): void
    {
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Ketua Tim');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'jenis_pembayaran' => [],
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama]],
        ]))->assertSessionHasErrors(['jenis_pembayaran']);

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_jenis_pembayaran_di_luar_daftar_ditolak(): void
    {
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Ketua Tim');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'jenis_pembayaran' => ['Luar Negeri'],
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama]],
        ]))->assertSessionHasErrors(['jenis_pembayaran.0']);

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_sp_lama_tanpa_jenis_pembayaran_tetap_terbaca_sebagai_kosong(): void
    {
        // Kolomnya nullable: SP yang dibuat sebelum fitur ini tidak boleh
        // ditebak jenis pembayarannya, cukup tampil kosong.
        $sp = SuratPerintah::create([
            'nomor_sp' => '001/SP/LAMA/2026', 'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat', 'lokasi' => 'Bandung',
            'nama_pengirim' => 'P', 'tujuan_transfer' => 'T', 'irban_dibayar' => false,
            'rincian_tgl_bayar' => '1 Juli 2026', 'keterangan' => 'K',
            'file_url' => 'sp/lama.pdf', 'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true, 'dipantau' => true,
        ]);

        $this->assertNull($sp->jenis_pembayaran);
        $this->assertSame([], $sp->jenisPembayaranArray());
    }
}
