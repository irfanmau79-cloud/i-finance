<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use App\Services\InventarisasiSpjService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Kolom Uraian di SEMUA tabel NPD menampilkan Uraian Lampiran - kalimat utuh
 * "Transfer Pembayaran Belanja … dalam rangka …" yang sama dengan yang
 * tercetak di dokumen dan dengan kotak Keterangan Lampiran di halaman Edit -
 * bukan isian mentahnya ("Untuk mengikuti …" / nama pelatihan). Tujuannya
 * supaya kalimatnya bisa langsung disalin dari tabel.
 */
class NpdUraianDaftarTest extends TestCase
{
    use RefreshDatabase;

    private const URAIAN_PD = 'Transfer Pembayaran Belanja Perjalanan Dinas Biasa (uang harian, akomodasi dan transport)'
        .' terhitung tanggal 03 Agustus 2026 s.d 04 Agustus 2026'
        .' dalam rangka Untuk mengikuti Rapat Koordinasi Pengawasan'
        .', berdasarkan Surat Perintah Nomor: 120/PW.02.01/Sekre tanggal 28 Juli 2026 an. AGUS SURYANA';

    private int $urut = 0;

    private function anggaran(): MasterAnggaran
    {
        $this->urut++;

        return MasterAnggaran::create([
            'program' => 'Program Uji Uraian',
            'kegiatan' => 'Kegiatan Uji Uraian',
            'sub_kegiatan' => '6.01.02.1.01.0001 Sub Kegiatan Uji Uraian',
            'kode_rekening' => sprintf('5.1.02.04.01.%04d', $this->urut),
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    private function npd(string $jenis, array $detail, array $lain = []): Npd
    {
        return Npd::create(array_replace([
            'jenis' => $jenis, 'master_anggaran_id' => $this->anggaran()->id, 'keu' => '2', 'bulan' => 8, 'tahun' => 2026,
            'tanggal_npd' => '2026-08-06', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah', 'status' => 'Draft NPD - PPTK', 'detail_json' => $detail,
        ], $lain));
    }

    private function npdPd(array $detail = [], array $lain = []): Npd
    {
        $npd = $this->npd('pd', array_replace([
            'nomor_sp' => '120/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-28',
            'uraian_sp' => 'Untuk mengikuti Rapat Koordinasi Pengawasan',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Kota Bogor',
            'tanggal_berangkat' => '2026-08-03',
            'tanggal_pulang' => '2026-08-04',
            'keterangan_lampiran' => null,
        ], $detail), $lain);

        $anggota = $npd->tim()->create([
            'nama' => 'AGUS SURYANA', 'bbm_liter' => 0, 'bbm_tarif' => 0, 'tol' => 50_000, 'tiket' => 0,
            'representatif' => 0, 'is_penerima' => true,
        ]);
        $anggota->paket()->create([
            'cluster' => 'C', 'wilayah' => 'Kota Bogor', 'lama_hari' => 2, 'tarif_uh' => 350_000, 'malam' => 1, 'tarif_akom' => 500_000,
        ]);

        return $npd;
    }

    /** Uraian baris pertama Lampiran, persis seperti yang dipakai saat mencetak. */
    private function uraianLampiran(Npd $npd): string
    {
        $npd = Npd::with(['masterAnggaran', 'tim.paket', 'peserta', 'narasumber'])->findOrFail($npd->id);

        $metode = fn (string $nama) => (new ReflectionMethod(NpdController::class, $nama))->invoke(app(NpdController::class), $npd);

        return match ($npd->jenis) {
            'pd', 'tr' => $metode('bangunLampiranPd')['keterangan'],
            'kd' => $metode('bangunLampiranKontribusiDiklat')['rows'][0]['keterangan'],
            'ns' => $metode('introNarasumber'),
        };
    }

    public function test_perjalanan_dinas_menampilkan_kalimat_lampiran_utuh_bukan_uraian_sp_saja(): void
    {
        $npd = $this->npdPd();
        $uraian = $npd->fresh()->uraianRingkas();

        $this->assertSame(self::URAIAN_PD, $uraian);
        $this->assertNotSame('Untuk mengikuti Rapat Koordinasi Pengawasan', $uraian);
        // Sama persis dengan yang tercetak di Lampiran.
        $this->assertSame($this->uraianLampiran($npd), $uraian);
    }

    public function test_kontribusi_diklat_menampilkan_kalimat_lampiran_di_kedua_mode(): void
    {
        $detail = [
            'nama_pelatihan' => 'Diklat Penjenjangan Auditor Ahli Muda',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-05',
            'penerima_index' => 0,
            'penerima_transfer' => [['nama' => 'FAJAR LAZUARDI', 'rekening' => '123', 'nominal' => 1_000_000]],
            'keterangan_lampiran' => null,
        ];

        $perjalanan = $this->npd('kd', $detail, ['mode_kd' => 'perjalanan']);
        $perjalanan->peserta()->create(['nama' => 'FAJAR LAZUARDI', 'hari_uh' => 2, 'tarif_uh' => 500_000]);

        $kontribusi = $this->npd('kd', $detail, ['mode_kd' => 'kontribusi']);
        $kontribusi->peserta()->create(['nama' => 'FAJAR LAZUARDI', 'volume_kontribusi' => 1, 'tarif_kontribusi' => 1_000_000]);

        $this->assertSame(
            'Transfer Pembayaran Belanja Perjalanan Dinas dalam rangka Mengikuti Diklat Penjenjangan Auditor Ahli Muda'
            .' terhitung tanggal 01 Agustus 2026 s.d 05 Agustus 2026 an. FAJAR LAZUARDI',
            $perjalanan->fresh()->uraianRingkas()
        );
        $this->assertStringStartsWith('Transfer Pembayaran Belanja Kontribusi Diklat dalam rangka Mengikuti Diklat', $kontribusi->fresh()->uraianRingkas());

        foreach ([$perjalanan, $kontribusi] as $npd) {
            $this->assertNotSame('Diklat Penjenjangan Auditor Ahli Muda', $npd->fresh()->uraianRingkas());
            $this->assertSame($this->uraianLampiran($npd), $npd->fresh()->uraianRingkas());
        }
    }

    public function test_narasumber_menampilkan_kalimat_pembayaran_honorarium(): void
    {
        $npd = $this->npd('ns', [
            'uraian_kegiatan' => 'Sosialisasi Pengendalian Gratifikasi',
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-10',
        ]);

        $uraian = $npd->fresh()->uraianRingkas();

        $this->assertStringStartsWith('Pembayaran Honorarium Narasumber', $uraian);
        $this->assertStringContainsString('dalam rangka Sosialisasi Pengendalian Gratifikasi pada tanggal 10 Agustus 2026', $uraian);
        $this->assertSame($this->uraianLampiran($npd), $uraian);
    }

    public function test_uraian_lampiran_yang_diketik_manual_dipakai_apa_adanya(): void
    {
        $npd = $this->npdPd(['keterangan_lampiran' => 'Uraian khusus yang diketik petugas']);

        $this->assertSame('Uraian khusus yang diketik petugas', $npd->fresh()->uraianRingkas());
        $this->assertSame($this->uraianLampiran($npd), $npd->fresh()->uraianRingkas());
    }

    public function test_npd_impor_lama_dan_barang_jasa_tidak_berubah(): void
    {
        // Impor lama: uraian lengkapnya sudah tersimpan.
        $historis = $this->npd('pd', [
            'uraian' => 'Pembayaran Belanja Perjalanan Dinas Biasa Sub Kegiatan Pengawasan Umum',
            'uraian_sp' => 'Untuk mengikuti sesuatu',
        ], ['sumber_data' => 'import_historis']);
        $this->assertSame('Pembayaran Belanja Perjalanan Dinas Biasa Sub Kegiatan Pengawasan Umum', $historis->fresh()->uraianRingkas());

        // Barang/Jasa tidak punya uraian tingkat dokumen: tetap dari keterangan penerima.
        $bj = $this->npd('bj', []);
        $bj->penerima()->create(['nama' => 'CV Uji', 'bruto' => 1_000_000, 'keterangan' => 'Pembayaran Belanja Alat Tulis Kantor']);
        $this->assertSame('Pembayaran Belanja Alat Tulis Kantor', $bj->fresh()->uraianRingkas());
    }

    /**
     * Uraian lengkapnya harus muncul di SEMUA tabel NPD, utuh - tidak
     * dipotong - supaya bisa disalin.
     */
    public function test_semua_tabel_npd_menampilkan_uraian_lengkap(): void
    {
        $superadmin = User::create(['username' => 'uraian-sa', 'nama' => 'Superadmin', 'role' => 'superadmin', 'password' => 'rahasia-uji']);
        $draft = $this->npdPd();
        $selesai = $this->npdPd([], ['status' => 'Selesai', 'nomor_lengkap' => '07/NPD-Keu.2.IBC/8/2026']);

        // Pembuatan NPD, Persetujuan, Verifikasi, Dashboard NPD: barisnya
        // ditulis langsung sebagai HTML.
        // Tiap antrean hanya memuat NPD di mejanya, jadi masing-masing
        // diberi satu NPD berstatus yang sesuai.
        $this->npdPd([], ['status' => 'Draft NPD - BPP']);
        $this->npdPd([], ['status' => 'Verifikasi - Verifikator']);

        foreach (['npd.index', 'npd.persetujuan', 'npd.verifikasi', 'dashboard.npd.index'] as $rute) {
            $isi = $this->actingAs($superadmin)->get(route($rute))->assertOk()->getContent();

            $this->assertStringContainsString(e(self::URAIAN_PD), $isi, "Uraian lengkap tidak tampil di {$rute}.");
        }

        // Data NPD: barisnya dikirim sebagai data lalu digambar skrip, jadi
        // yang diperiksa data yang diserahkan ke halamannya.
        $barisDataNpd = $this->actingAs($superadmin)->get(route('npd.data'))->assertOk()->viewData('baris');
        $this->assertSame(
            self::URAIAN_PD,
            collect($barisDataNpd)->firstWhere('url', route('npd.show', $draft))['uraian'],
            'Uraian lengkap tidak tampil di Data NPD.'
        );

        // Inventarisasi SPJ (hanya NPD Selesai).
        $rincian = app(InventarisasiSpjService::class)->rincianNpd($selesai->fresh());
        $this->assertSame(self::URAIAN_PD, $rincian['uraian']);
    }
}
