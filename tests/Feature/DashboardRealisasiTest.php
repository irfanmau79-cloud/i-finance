<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\RakBulanan;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\User;
use App\Services\AnggaranRealisasiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-21 10:00:00');
        $this->user = User::create([
            'username' => 'pptk-dashboard',
            'nama' => 'PPTK Dashboard',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_draft_hanya_menaikkan_dana_terikat_npd_selesai_dan_spm_ls_menaikkan_realisasi_aktual(): void
    {
        $anggaran = $this->anggaran('Sub Dashboard', '5.1.01', 10_000_000);
        $this->npd($anggaran, 2_000_000, 'Draft NPD - PPTK', '2026-01-10');
        $this->npd($anggaran, 3_000_000, 'Selesai', '2026-02-10');
        Spm::buatLs([
            'nomor_dokumen' => '001/DB-LS/2026',
            'tanggal_dokumen' => '2026-03-10',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
        ]);

        $dashboard = app(AnggaranRealisasiService::class)->dashboard([], 2026, 7);

        $this->assertSame(10_000_000.0, $dashboard['total']['pagu']);
        $this->assertSame(5_000_000.0, $dashboard['total']['dana_terikat_npd']);
        $this->assertSame(2_000_000.0, $dashboard['dana_terikat_belum_selesai']);
        $this->assertSame(3_000_000.0, $dashboard['total']['realisasi_npd']);
        $this->assertSame(1_000_000.0, $dashboard['total']['realisasi_ls']);
        $this->assertSame(4_000_000.0, $dashboard['total']['realisasi_aktual']);
        $this->assertSame(4_000_000.0, $dashboard['total']['sisa_tersedia']);

        $this->actingAs($this->user)->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Realisasi SP2D')
            ->assertSee('Realisasi SPJ3')
            ->assertSee('Chart.js/4.4.1');
    }

    public function test_target_rak_dan_deviasi_per_sub_tidak_digandakan_oleh_tagging(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);
        $a = $this->anggaran('Sub RAK Bersama', '5.1.02', 6_000_000, $tagA);
        $b = $this->anggaran('Sub RAK Bersama', '5.1.02', 4_000_000, $tagB);
        $this->npd($a, 600_000, 'Selesai', '2026-01-10');
        $this->npd($b, 400_000, 'Selesai', '2026-02-10');
        foreach (range(1, 7) as $bulan) {
            $this->rak($a, $bulan, 100_000);
        }

        $dashboard = app(AnggaranRealisasiService::class)->dashboard([], 2026, 7);
        $row = $dashboard['rows']->first();

        $this->assertSame(700_000.0, $dashboard['target_rak_sd_bulan']);
        $this->assertSame(700_000.0, $row['target_rak']);
        $this->assertSame(1_000_000.0, $row['realisasi_sd_bulan']);
        $this->assertSame(300_000.0, $row['deviasi_rupiah']);
        $this->assertSame(3.0, $row['deviasi_persen']);
    }

    public function test_filter_sorting_data_kosong_dan_rak_belum_tersedia_ditampilkan_jujur(): void
    {
        $a = $this->anggaran('Sub Kecil', '5.1.03', 2_000_000);
        $this->anggaran('Sub Besar', '5.1.04', 8_000_000);

        $this->actingAs($this->user)->get(route('dashboard.index', ['sort' => 'pagu', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Sub Besar', 'Sub Kecil'])
            ->assertSee('tidak menggunakan perkiraan pagu/12')
            ->assertViewHas('dashboard', fn (array $data) => $data['rows']->first()['nama'] === 'Sub Besar'
                && $data['target_rak_sd_bulan'] === null);

        $this->actingAs($this->user)->get(route('dashboard.index', [
            'sub_kegiatan' => $a->sub_kegiatan_kunci,
            'kode_rekening' => $a->kode_rekening_bersih,
        ]))->assertOk()
            ->assertViewHas('dashboard', fn (array $data) => $data['rows']->count() === 1
                && $data['rows']->first()['nama'] === 'Sub Kecil')
            ->assertViewHas('pilihan', fn (array $pilihan) => $pilihan['kode_rekening']->all() === ['5.1.03']);

        $this->actingAs($this->user)->get(route('dashboard.index', ['sub_kegiatan' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada data anggaran aktif untuk filter ini.')
            ->assertViewHas('dashboard', fn (array $data) => $data['kosong'] === true);
    }

    public function test_kartu_realisasi_sp2d_menjumlahkan_spm_ls_terfilter_dan_spm_up_gu_nasional(): void
    {
        $anggaran = $this->anggaran('Sub SP2D', '5.1.05', 10_000_000);
        $anggaranLain = $this->anggaran('Sub SP2D Lain', '5.1.06', 20_000_000);

        // SPM LS multi mata anggaran (spm_detail): satu baris milik $anggaran,
        // satu baris milik $anggaranLain - membuktikan realisasi_ls tetap
        // difilter per mata anggaran (bukan seluruh dokumen) meski nominalnya
        // datang dari spm_detail, bukan kolom master_anggaran_id di spm.
        Spm::buatLs([
            'nomor_dokumen' => '900/SP2D-LS/2026', 'tanggal_dokumen' => '2026-07-05',
            'baris' => [
                ['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000],
                ['master_anggaran_id' => $anggaranLain->id, 'nominal' => 4_000_000],
            ],
        ]);
        // Dua dokumen SPM UP/GU - membuktikan totalSpmUpGu() menjumlahkan
        // seluruh dokumen 'up_gu', bukan hanya satu.
        Spm::buatUpGu(['nomor_dokumen' => '001/SP2D-UP/2026', 'tanggal_dokumen' => '2026-07-01', 'nominal' => 2_000_000]);
        Spm::buatUpGu(['nomor_dokumen' => '002/SP2D-GU/2026', 'tanggal_dokumen' => '2026-07-02', 'nominal' => 3_000_000]);

        $service = app(AnggaranRealisasiService::class);

        // Tanpa filter: realisasi_ls mencakup KEDUA mata anggaran (5jt),
        // ditambah seluruh SPM UP/GU nasional (5jt) = 10jt.
        $tanpaFilter = $service->dashboard([], 2026, 7);
        $this->assertFalse($tanpaFilter['filter_aktif']);
        $this->assertSame(5_000_000.0, $tanpaFilter['total']['realisasi_ls']);
        $this->assertSame(5_000_000.0, $tanpaFilter['spm_up_gu_total']);
        $this->assertSame(10_000_000.0, $tanpaFilter['realisasi_sp2d']['nominal']);

        // Dengan filter ke $anggaran saja: realisasi_ls HARUS menyempit jadi
        // 1jt (baris $anggaranLain tidak ikut), tapi SPM UP/GU/TU tetap total
        // nasional penuh (5jt, sesuai keputusan produk - lihat AskUserQuestion
        // di percakapan tugas ini) sehingga nominalnya 1jt + 5jt = 6jt,
        // dibagi pagu YANG SUDAH DIFILTER (10jt) = 60%.
        $terfilter = $service->dashboard([
            'sub_kegiatan' => $anggaran->sub_kegiatan_kunci,
            'kode_rekening' => $anggaran->kode_rekening_bersih,
        ], 2026, 7);
        $this->assertTrue($terfilter['filter_aktif']);
        $this->assertSame(10_000_000.0, $terfilter['total']['pagu']);
        $this->assertSame(1_000_000.0, $terfilter['total']['realisasi_ls']);
        $this->assertSame(5_000_000.0, $terfilter['spm_up_gu_total']);
        $this->assertSame(6_000_000.0, $terfilter['realisasi_sp2d']['nominal']);
        $this->assertSame(60.0, $terfilter['realisasi_sp2d']['persentase']);

        $this->actingAs($this->user)->get(route('dashboard.index', [
            'sub_kegiatan' => $anggaran->sub_kegiatan_kunci,
            'kode_rekening' => $anggaran->kode_rekening_bersih,
        ]))->assertOk()->assertSee('bersifat nasional');
    }

    public function test_kartu_realisasi_spj3_dan_sisa_anggaran_berbeda_dari_sisa_tersedia_operasional(): void
    {
        $anggaran = $this->anggaran('Sub SPJ3', '5.1.07', 10_000_000);
        $this->npd($anggaran, 2_000_000, 'Draft NPD - PPTK', '2026-01-10');
        $this->npd($anggaran, 3_000_000, 'Selesai', '2026-02-10');
        Spm::buatLs([
            'nomor_dokumen' => '901/SPJ3-LS/2026', 'tanggal_dokumen' => '2026-03-10',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
        ]);

        $dashboard = app(AnggaranRealisasiService::class)->dashboard([], 2026, 7);

        // sisa_tersedia (dipakai validasi NPD & Rincian Realisasi) TIDAK
        // berubah: pagu - dana_terikat_npd (termasuk draft 2jt) - realisasi_ls.
        $this->assertSame(4_000_000.0, $dashboard['total']['sisa_tersedia']);
        // Realisasi SPJ3 (kartu 3) = NPD Selesai + SPM LS = 3jt + 1jt.
        $this->assertSame(4_000_000.0, $dashboard['total']['realisasi_aktual']);
        $this->assertSame(40.0, $dashboard['total']['persentase_realisasi']);
        // Sisa Anggaran (kartu 4) = Pagu - Realisasi SPJ3 = 10jt - 4jt = 6jt,
        // SENGAJA berbeda dari sisa_tersedia (4jt) karena tidak mengurangi
        // draft NPD yang belum selesai.
        $this->assertSame(6_000_000.0, $dashboard['sisa_anggaran_spj3']['nominal']);
        $this->assertSame(60.0, $dashboard['sisa_anggaran_spj3']['persentase']);
    }

    public function test_kartu_realisasi_aman_saat_pagu_nol(): void
    {
        $this->anggaran('Sub Pagu Nol', '5.1.08', 0);

        $dashboard = app(AnggaranRealisasiService::class)->dashboard([], 2026, 7);

        $this->assertSame(0.0, $dashboard['total']['pagu']);
        $this->assertSame(0.0, $dashboard['total']['persentase_realisasi']);
        $this->assertSame(0.0, $dashboard['realisasi_sp2d']['nominal']);
        $this->assertSame(0.0, $dashboard['realisasi_sp2d']['persentase']);
        $this->assertSame(0.0, $dashboard['sisa_anggaran_spj3']['nominal']);
        $this->assertSame(0.0, $dashboard['sisa_anggaran_spj3']['persentase']);

        $this->actingAs($this->user)->get(route('dashboard.index'))->assertOk()->assertSee('0,00 %');
    }

    public function test_dropdown_kode_rekening_menampilkan_kode_dan_uraian(): void
    {
        $this->anggaran('Sub Label Kode', '5.1.09', 1_000_000);

        $this->actingAs($this->user)->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewHas('pilihan', function (array $pilihan) {
                $opsi = $pilihan['kode_rekening_berlabel']->firstWhere('value', '5.1.09');

                return $opsi !== null && $opsi['label'] === '5.1.09 Belanja Dashboard';
            })
            ->assertSee('5.1.09 Belanja Dashboard');
    }

    public function test_route_dashboard_mengikuti_config_akses_menu_di_backend(): void
    {
        $this->actingAs($this->user)->get(route('dashboard.index'))->assertOk();

        config(['akses.menu.pptk' => array_values(array_diff(config('akses.menu.pptk'), ['dashboard']))]);

        $this->actingAs($this->user)->get(route('dashboard.index'))->assertForbidden();
    }

    private function anggaran(
        string $subKegiatan,
        string $kodeRekening,
        float $pagu,
        ?Tagging $tagging = null,
    ): MasterAnggaran {
        return MasterAnggaran::create([
            'program' => 'Program Dashboard',
            'kegiatan' => 'Kegiatan Dashboard',
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => MasterAnggaran::gabungKodeUraian($kodeRekening, 'Belanja Dashboard'),
            'tagging_id' => $tagging?->id,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function npd(MasterAnggaran $anggaran, float $nominal, string $status, string $tanggal): Npd
    {
        $date = Carbon::parse($tanggal);

        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => $date->month,
            'tahun' => $date->year,
            'tanggal_npd' => $date,
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'nilai dashboard rupiah',
            'status' => $status,
        ]);
    }

    private function rak(MasterAnggaran $anggaran, int $bulan, float $target): RakBulanan
    {
        return RakBulanan::create([
            'sub_kegiatan' => $anggaran->sub_kegiatan_lengkap,
            'kode_rekening' => $anggaran->kode_rekening_bersih,
            'tahun' => 2026,
            'bulan' => $bulan,
            'target' => $target,
        ]);
    }
}
