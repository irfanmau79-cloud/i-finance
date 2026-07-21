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

class AnalisisTrenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-21 10:00:00');

        $this->user = User::create([
            'username' => 'pptk-analisis',
            'nama' => 'PPTK Analisis',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agregasi_bulanan_kumulatif_kpi_dan_deviasi_memakai_npd_selesai_spm_ls_dan_rak_resmi(): void
    {
        $anggaran = $this->anggaran('Sub Analisis', '5.1.01', 10_000_000);
        $this->npd($anggaran, 1_000_000, 'Selesai', '2026-01-10');
        $this->npd($anggaran, 1_000_000, 'Draft NPD - PPTK', '2026-02-10');
        $this->npd($anggaran, 2_000_000, 'Selesai', '2026-03-10');
        $this->npd($anggaran, 7_000_000, 'Dibatalkan', '2026-04-10');
        Spm::buatLs([
            'nomor_dokumen' => '001/AN-LS/2026',
            'tanggal_dokumen' => '2026-02-15',
            'nominal' => 500_000,
            'master_anggaran_id' => $anggaran->id,
        ]);
        Spm::buatUpGu([
            'nomor_dokumen' => '001/AN-UP/2026',
            'tanggal_dokumen' => '2026-02-16',
            'nominal' => 8_000_000,
        ]);

        foreach ([800_000, 700_000, 1_000_000, 0, 0, 0, 0] as $index => $target) {
            $this->rak($anggaran, $index + 1, $target);
        }

        $response = $this->actingAs($this->user)->get(route('analisis.index'));
        $response->assertOk()->assertSee('Bulanan')->assertSee('Kumulatif')->assertSee('Chart.js/4.4.1');
        $response->assertViewHas('analisis', function (array $data) {
            return $data['realisasi_bulanan'] === [1_000_000.0, 500_000.0, 2_000_000.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]
                && $data['realisasi_kumulatif'][2] === 3_500_000.0
                && $data['target_bulanan'][0] === 800_000.0
                && $data['target_kumulatif'][2] === 2_500_000.0
                && $data['target_bulanan'][7] === null
                && $data['target_kumulatif'][7] === null
                && $data['realisasi_aktual'] === 3_500_000.0
                && $data['capaian_tahun'] === 35.0
                && $data['rak_sd_bulan'] === 2_500_000.0
                && $data['deviasi_rupiah'] === 1_000_000.0
                && $data['deviasi_persen'] === 10.0
                && $data['rak_lengkap_sd_bulan'] === true
                && $data['rak_lengkap_tahun'] === false;
        });
    }

    public function test_rak_satu_pasangan_tidak_digandakan_oleh_tagging_dan_filter_saling_menyesuaikan(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);
        $a = $this->anggaran('Sub Bersama', '5.1.01', 6_000_000, $tagA);
        $b = $this->anggaran('Sub Bersama', '5.1.01', 4_000_000, $tagB);
        $this->anggaran('Sub Bersama', '5.1.02', 2_000_000, $tagA);
        $this->anggaran('Sub Lain', '5.1.03', 3_000_000, $tagA);
        $this->npd($a, 1_000_000, 'Selesai', '2026-01-10');
        $this->npd($b, 500_000, 'Selesai', '2026-01-11');
        $this->rak($a, 1, 2_000_000);

        $params = ['sub_kegiatan' => $a->sub_kegiatan_kunci, 'kode_rekening' => '5.1.01'];
        $this->actingAs($this->user)->get(route('analisis.index', $params))
            ->assertOk()
            ->assertViewHas('pilihan', fn (array $pilihan) => $pilihan['kode_rekening']->all() === ['5.1.01', '5.1.02'])
            ->assertViewHas('analisis', fn (array $data) => $data['pagu'] === 10_000_000.0
                && $data['realisasi_aktual'] === 1_500_000.0
                && $data['target_bulanan'][0] === 2_000_000.0);
    }

    public function test_data_kosong_dan_rak_belum_ada_tidak_menggunakan_pagu_per_dua_belas(): void
    {
        $anggaran = $this->anggaran('Sub Tanpa RAK', '5.1.04', 12_000_000);
        $this->npd($anggaran, 600_000, 'Selesai', '2026-01-10');

        $this->actingAs($this->user)->get(route('analisis.index', ['sub_kegiatan' => $anggaran->sub_kegiatan_kunci]))
            ->assertOk()
            ->assertSee('tidak menggunakan perkiraan pagu/12')
            ->assertViewHas('analisis', fn (array $data) => $data['target_bulanan'] === array_fill(0, 12, null)
                && $data['target_kumulatif'] === array_fill(0, 12, null)
                && $data['rak_tersedia'] === false
                && $data['deviasi_rupiah'] === null);

        $this->actingAs($this->user)->get(route('analisis.index', ['sub_kegiatan' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada data anggaran atau transaksi untuk filter ini.')
            ->assertViewHas('analisis', fn (array $data) => $data['kosong'] === true && $data['pagu'] === 0.0);
    }

    public function test_total_analisis_konsisten_dengan_rincian_dan_route_dijaga_config_menu(): void
    {
        $anggaran = $this->anggaran('Sub Konsisten', '5.1.05', 8_000_000);
        $this->npd($anggaran, 2_000_000, 'Selesai', '2026-05-10');
        Spm::buatLs([
            'nomor_dokumen' => '002/AN-LS/2026',
            'tanggal_dokumen' => '2026-06-15',
            'nominal' => 1_000_000,
            'master_anggaran_id' => $anggaran->id,
        ]);

        $service = app(AnggaranRealisasiService::class);
        $analisis = $service->analisis([], 2026, 7);
        $rincian = $service->rincian(['sub_kegiatan' => '', 'kode_rekening' => '', 'tagging' => '', 'q' => '']);

        $this->assertSame($rincian['total']['pagu'], $analisis['pagu']);
        $this->assertSame($rincian['total']['realisasi_aktual'], $analisis['realisasi_aktual']);
        $this->actingAs($this->user)->get(route('analisis.index'))->assertOk();

        config(['akses.menu.pptk' => array_values(array_diff(config('akses.menu.pptk'), ['analisis']))]);
        $this->actingAs($this->user)->get(route('analisis.index'))->assertForbidden();
    }

    private function anggaran(
        string $subKegiatan,
        string $kodeRekening,
        float $pagu,
        ?Tagging $tagging = null,
    ): MasterAnggaran {
        return MasterAnggaran::create([
            'program' => 'Program Analisis',
            'kegiatan' => 'Kegiatan Analisis',
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'uraian_rekening' => 'Belanja Analisis',
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
            'terbilang' => 'nilai pengujian rupiah',
            'status' => $status,
        ]);
    }

    private function rak(MasterAnggaran $anggaran, int $bulan, float $target): RakBulanan
    {
        return RakBulanan::create([
            'sub_kegiatan' => $anggaran->sub_kegiatan,
            'kode_rekening' => $anggaran->kode_rekening,
            'tahun' => 2026,
            'bulan' => $bulan,
            'target' => $target,
        ]);
    }
}
