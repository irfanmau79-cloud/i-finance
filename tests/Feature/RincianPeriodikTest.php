<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pengembalian;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Realisasi Periodik: sub menu kedua Rincian Realisasi. Satu baris per mata
 * anggaran, dua belas kolom bulan berisi realisasi SPJ3 (NPD Selesai + SPM
 * LS) bulan itu.
 *
 * Yang diuji di sini adalah hal-hal yang TIDAK terlihat dari angka total:
 * penempatan tiap transaksi pada bulan yang benar, apa saja yang tidak boleh
 * ikut terhitung, dan bahwa jumlah dua belas bulan tetap sama dengan angka
 * realisasi yang ditampilkan Realisasi Tahunan.
 */
class RincianPeriodikTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'pptk-periodik',
            'nama' => 'PPTK Periodik',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia',
        ]);
    }

    public function test_tiap_transaksi_masuk_ke_kolom_bulan_tanggalnya_masing_masing(): void
    {
        $anggaran = $this->anggaran('Sub Periodik', '5.1.01', null, 10_000_000);

        $this->npd($anggaran, 1_000_000, 'Selesai', '2026-01-10');
        $this->npd($anggaran, 2_500_000, 'Selesai', '2026-03-31');
        $this->spmLs($anggaran, 500_000, '2026-03-05');
        $this->spmLs($anggaran, 750_000, '2026-12-01');

        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertSee('Realisasi Periodik')
            ->assertSee('Januari')
            ->assertSee('Desember')
            ->assertViewHas('baris', function (Collection $baris) {
                $row = $baris->first();

                return $baris->count() === 1
                    // Januari NPD; Maret = NPD 2,5jt + LS 500rb; Desember LS.
                    && $row['bulanan'] === [
                        1_000_000.0, 0.0, 3_000_000.0, 0.0, 0.0, 0.0,
                        0.0, 0.0, 0.0, 0.0, 0.0, 750_000.0,
                    ]
                    && $row['pagu'] === 10_000_000.0
                    && $row['realisasi'] === 4_750_000.0;
            });
    }

    /**
     * Hanya NPD "Selesai" yang jadi realisasi - draft/proses itu dana
     * terikat, dan yang batal bukan apa-apa. SPM UP/GU/TU juga tidak masuk:
     * itu isi ulang kas, bukan belanja atas pagu.
     */
    public function test_npd_belum_selesai_npd_batal_dan_spm_up_gu_tidak_dihitung(): void
    {
        $anggaran = $this->anggaran('Sub Periodik', '5.1.01', null, 10_000_000);

        $this->npd($anggaran, 1_000_000, 'Selesai', '2026-05-02');
        $this->npd($anggaran, 4_000_000, 'Draft NPD - PPTK', '2026-05-03');
        $this->npd($anggaran, 3_000_000, 'Verifikasi - Verifikator', '2026-05-04');
        $this->npd($anggaran, 2_000_000, 'Dibatalkan', '2026-05-05');

        Spm::buatUpGu([
            'nomor_dokumen' => '001/RP-UP/2026',
            'tanggal_dokumen' => '2026-05-06',
            'nominal' => 8_000_000,
        ]);

        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertViewHas('baris', fn (Collection $baris) => $baris->first()['bulanan'][4] === 1_000_000.0
                && $baris->first()['realisasi'] === 1_000_000.0);
    }

    /**
     * Pengembalian yang disetujui mengurangi BULAN PENGEMBALIAN, bukan bulan
     * dokumen asalnya - angka bulan yang sudah dilaporkan tidak boleh berubah
     * belakangan. Draft tidak berpengaruh sama sekali.
     */
    public function test_pengembalian_disetujui_mengurangi_bulan_pengembaliannya_draft_tidak(): void
    {
        $bendahara = User::create([
            'username' => 'bp-periodik',
            'nama' => 'BP Periodik',
            'role' => 'bendahara_pengeluaran',
            'password' => 'rahasia',
        ]);

        $anggaran = $this->anggaran('Sub Periodik', '5.1.01', null, 10_000_000);
        $npd = $this->npd($anggaran, 1_000_000, 'Selesai', '2026-02-10');
        $npdDraftKembali = $this->npd($anggaran, 800_000, 'Selesai', '2026-02-11');

        Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-04-20',
            'dokumen_pendukung' => 'pengembalian/uji-bukti.pdf',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 300_000]],
        ], $bendahara->id)->setujui($bendahara);

        // Draft (tanpa setujui) - tidak boleh mengubah angka bulan mana pun.
        Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npdDraftKembali->id,
            'tanggal_pengembalian' => '2026-05-20',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 800_000]],
        ], $bendahara->id);

        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertViewHas('baris', function (Collection $baris) {
                $bulanan = $baris->first()['bulanan'];

                return $bulanan[1] === 1_800_000.0   // Februari utuh
                    && $bulanan[3] === -300_000.0     // April negatif: cuma pengembalian
                    && $bulanan[4] === 0.0            // Mei: draft diabaikan
                    && $baris->first()['realisasi'] === 1_500_000.0;
            });
    }

    public function test_transaksi_tahun_lain_tidak_ikut_terhitung(): void
    {
        // Pagu dibuat longgar (20jt) supaya transaksi tahun lalu di bawah
        // tidak kena penjagaan sisa tersedia Spm::buatLs() - yang diuji di
        // sini pemilahan TAHUN, bukan penjagaan pagu.
        $anggaran = $this->anggaran('Sub Periodik', '5.1.01', null, 20_000_000);

        $this->npd($anggaran, 1_000_000, 'Selesai', '2026-06-10');
        $this->npd($anggaran, 9_000_000, 'Selesai', '2025-06-10');
        $this->spmLs($anggaran, 400_000, '2025-12-31');

        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertViewHas('baris', fn (Collection $baris) => $baris->first()['realisasi'] === 1_000_000.0
                && $baris->first()['bulanan'][5] === 1_000_000.0)
            ->assertViewHas('tahun', (int) config('anggaran.tahun_aktif'));
    }

    /**
     * Baris dipisah sampai tingkat Tagging - satu kode rekening dengan dua
     * tagging tetap dua baris, seperti di pohon Realisasi Tahunan. Kolom
     * Program/Sub Kegiatan/Kodering-nya yang digabung di tampilan.
     */
    public function test_satu_baris_per_mata_anggaran_sampai_tagging_dan_total_menjumlahkan_semuanya(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);

        $a = $this->anggaran('Sub Periodik', '5.1.01', $tagA, 6_000_000);
        $b = $this->anggaran('Sub Periodik', '5.1.01', $tagB, 4_000_000);
        $c = $this->anggaran('Sub Periodik', '5.1.02', null, 2_000_000);

        $this->npd($a, 1_000_000, 'Selesai', '2026-07-01');
        $this->npd($b, 500_000, 'Selesai', '2026-07-02');
        $this->spmLs($c, 250_000, '2026-08-03');

        $response = $this->actingAs($this->user)->get(route('rincian.periodik'))->assertOk();

        $response->assertViewHas('baris', function (Collection $baris) {
            return $baris->count() === 3
                && $baris->pluck('tagging')->all() === ['Tag A', 'Tag B', 'Tanpa Tagging']
                && $baris->pluck('kodering')->all() === ['5.1.01', '5.1.01', '5.1.02'];
        });

        $response->assertViewHas('total', function (array $total) {
            return $total['pagu'] === 12_000_000.0
                && $total['bulanan'][6] === 1_500_000.0
                && $total['bulanan'][7] === 250_000.0
                && $total['realisasi'] === 1_750_000.0;
        });
    }

    /** Jumlah dua belas bulan HARUS sama dengan realisasi aktual di Realisasi Tahunan. */
    public function test_jumlah_dua_belas_bulan_sama_dengan_realisasi_aktual_realisasi_tahunan(): void
    {
        $anggaran = $this->anggaran('Sub Periodik', '5.1.01', null, 10_000_000);
        $this->npd($anggaran, 1_250_000, 'Selesai', '2026-01-15');
        $this->npd($anggaran, 2_000_000, 'Draft NPD - PPTK', '2026-02-15');
        $this->spmLs($anggaran, 3_000_000, '2026-09-15');

        $tahunan = $this->actingAs($this->user)->get(route('rincian.index'))->assertOk();
        $periodik = $this->actingAs($this->user)->get(route('rincian.periodik'))->assertOk();

        $realisasiTahunan = $tahunan->viewData('total')['realisasi_aktual'];
        $realisasiPeriodik = $periodik->viewData('total')['realisasi'];

        $this->assertSame(4_250_000.0, $realisasiTahunan);
        $this->assertSame($realisasiTahunan, $realisasiPeriodik);
    }

    public function test_penyaring_sub_kegiatan_kodering_dan_pencarian_membatasi_baris_dan_total(): void
    {
        $satu = $this->anggaran('Sub Kegiatan Satu', '5.1.01', null, 6_000_000, 'Belanja Kertas');
        $dua = $this->anggaran('Sub Kegiatan Dua', '5.1.02', null, 4_000_000, 'Belanja Tinta');

        $this->npd($satu, 1_000_000, 'Selesai', '2026-03-10');
        $this->npd($dua, 400_000, 'Selesai', '2026-03-11');

        // Sub Kegiatan.
        $this->actingAs($this->user)
            ->get(route('rincian.periodik', ['sub_kegiatan' => $satu->sub_kegiatan_kunci]))
            ->assertOk()
            ->assertViewHas('baris', fn (Collection $baris) => $baris->count() === 1
                && $baris->first()['sub_kegiatan'] === $satu->subKegiatanNormal())
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 6_000_000.0
                && $total['realisasi'] === 1_000_000.0);

        // Kodering.
        $this->actingAs($this->user)
            ->get(route('rincian.periodik', ['kode_rekening' => '5.1.02']))
            ->assertOk()
            ->assertViewHas('baris', fn (Collection $baris) => $baris->count() === 1
                && $baris->first()['kodering'] === '5.1.02')
            ->assertViewHas('total', fn (array $total) => $total['realisasi'] === 400_000.0);

        // Pencarian bebas (uraian rekening).
        $this->actingAs($this->user)
            ->get(route('rincian.periodik', ['q' => 'Tinta']))
            ->assertOk()
            ->assertViewHas('baris', fn (Collection $baris) => $baris->count() === 1
                && $baris->first()['kodering'] === '5.1.02');
    }

    /** Kedua sub menu berbagi kunci akses 'rincian' - yang tidak punya, ditolak di backend. */
    public function test_role_tanpa_kunci_rincian_ditolak_di_kedua_sub_menu(): void
    {
        $tanpaAkses = User::create([
            'username' => 'kepeg-periodik',
            'nama' => 'Kepegawaian Tanpa Rincian',
            'role' => User::ROLE_KEPEGAWAIAN,
            'password' => 'rahasia',
        ]);

        // Dibaca dari config, bukan diasumsikan: kalau suatu saat
        // 'kepegawaian' diberi kunci 'rincian', uji ini harus GAGAL DI SINI
        // dengan pesan yang jelas, bukan gagal di assertForbidden di bawah
        // dan menyesatkan ke arah bug otorisasi.
        $this->assertNotContains('rincian', config('akses.menu')[User::ROLE_KEPEGAWAIAN] ?? []);

        $this->actingAs($tanpaAkses)->get(route('rincian.index'))->assertForbidden();
        $this->actingAs($tanpaAkses)->get(route('rincian.periodik'))->assertForbidden();
    }

    /** Sidebar menampilkan kedua sub menu, bukan lagi satu tautan tunggal. */
    public function test_sidebar_menampilkan_dua_sub_menu_rincian(): void
    {
        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertSee('Realisasi Tahunan')
            ->assertSee('Realisasi Periodik')
            ->assertSee(route('rincian.index'), false)
            ->assertSee(route('rincian.periodik'), false);
    }

    /**
     * Pohon buka-tutup: Sub Kegiatan > Kodering > Tagging, dengan agregat
     * tiap level dihitung di SERVER. Baris induk harus tetap menunjukkan
     * angka penuh walau anaknya tertutup - itu seluruh gunanya.
     */
    public function test_pohon_menjumlahkan_bulan_di_tiap_tingkat(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);

        $a = $this->anggaran('Sub Periodik', '5.1.01', $tagA, 6_000_000);
        $b = $this->anggaran('Sub Periodik', '5.1.01', $tagB, 4_000_000);
        $c = $this->anggaran('Sub Periodik', '5.1.02', null, 2_000_000);

        $this->npd($a, 1_000_000, 'Selesai', '2026-04-01');
        $this->npd($b, 250_000, 'Selesai', '2026-04-02');
        $this->npd($b, 300_000, 'Selesai', '2026-10-02');
        $this->spmLs($c, 500_000, '2026-04-03');

        $this->actingAs($this->user)->get(route('rincian.periodik'))
            ->assertOk()
            ->assertViewHas('pohon', function (Collection $pohon) {
                $sub = $pohon->first();
                $kode = $sub['kodering']->firstWhere('kodering', '5.1.01');

                return $pohon->count() === 1
                    && $sub['kodering']->count() === 2
                    // Sub Kegiatan: April = 1jt + 250rb + 500rb, Oktober 300rb.
                    && $sub['bulanan'][3] === 1_750_000.0
                    && $sub['bulanan'][9] === 300_000.0
                    && $sub['pagu'] === 12_000_000.0
                    && $sub['realisasi'] === 2_050_000.0
                    // Kodering 5.1.01 saja: dua tagging di bawahnya.
                    && $kode['tagging']->count() === 2
                    && $kode['bulanan'][3] === 1_250_000.0
                    && $kode['pagu'] === 10_000_000.0
                    && $kode['realisasi'] === 1_550_000.0;
            });
    }

    /**
     * Kolom Program sudah dibuang, dan anak-anak baris Sub Kegiatan tertutup
     * saat halaman dibuka - kalau tidak, 266 baris tercetak sekaligus dan
     * gagasan buka-tutupnya hilang.
     */
    public function test_kolom_program_hilang_dan_anak_tertutup_saat_dibuka(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);
        $a = $this->anggaran('Sub Periodik', '5.1.01', $tagA, 6_000_000);
        $this->anggaran('Sub Periodik', '5.1.01', $tagB, 4_000_000);
        $this->npd($a, 1_000_000, 'Selesai', '2026-04-01');

        $isi = $this->actingAs($this->user)->get(route('rincian.periodik'))->assertOk()->getContent();

        // Kepala tabel: tidak ada lagi kolom Program.
        $kepala = substr($isi, (int) strpos($isi, '<thead>'));
        $kepala = substr($kepala, 0, (int) strpos($kepala, '</thead>'));
        $this->assertStringNotContainsString('Program', $kepala);
        $this->assertStringContainsString('Sub Kegiatan / Kodering', $kepala);

        // Baris Sub Kegiatan tampil, baris anaknya tertutup (hidden).
        $this->assertMatchesRegularExpression('/<tr class="rp-lvl0" data-simpul="s0">/', $isi);
        $this->assertMatchesRegularExpression('/<tr class="rp-lvl1"[^>]*hidden>/', $isi);
        $this->assertMatchesRegularExpression('/<tr class="rp-lvl2"[^>]*hidden>/', $isi);
        $this->assertStringContainsString('aria-expanded="false"', $isi);
        $this->assertStringContainsString('id="rp-buka"', $isi);
        $this->assertStringContainsString('id="rp-tutup"', $isi);
    }

    private function anggaran(
        string $subKegiatan,
        string $kodeRekening,
        ?Tagging $tagging,
        float $pagu,
        string $uraian = 'Belanja Pengujian',
    ): MasterAnggaran {
        return MasterAnggaran::create([
            'program' => 'Program Periodik',
            'kegiatan' => 'Kegiatan Periodik',
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => MasterAnggaran::gabungKodeUraian($kodeRekening, $uraian),
            'tagging_id' => $tagging?->id,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function npd(MasterAnggaran $anggaran, float $nominal, string $status, string $tanggal): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => (int) substr($tanggal, 5, 2),
            'tahun' => (int) substr($tanggal, 0, 4),
            'tanggal_npd' => $tanggal,
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'nilai pengujian rupiah',
            'status' => $status,
        ]);
    }

    private function spmLs(MasterAnggaran $anggaran, float $nominal, string $tanggal): Spm
    {
        static $urut = 0;
        $urut++;

        return Spm::buatLs([
            'nomor_dokumen' => sprintf('%03d/RP-LS/2026', $urut),
            'tanggal_dokumen' => $tanggal,
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => $nominal]],
        ]);
    }
}
