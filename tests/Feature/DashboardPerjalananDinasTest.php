<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\PerjalananDinasDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPerjalananDinasTest extends TestCase
{
    use RefreshDatabase;

    private MasterAnggaran $anggaran;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'dash-pd', 'nama' => 'Dash PD', 'role' => 'superadmin', 'password' => 'rahasia']);
        $this->anggaran = MasterAnggaran::create([
            'program' => 'Program PD', 'kegiatan' => 'Kegiatan PD', 'sub_kegiatan' => '6.01.02.1.01 Pengawasan',
            'kode_rekening' => '5.1.02.04.01.0001', 'pagu' => 100_000_000, 'aktif' => true,
        ]);
    }

    public function test_agregasi_pd_dan_transport_memakai_formula_yang_sama_dengan_nominal_npd(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $pd = $this->npd('pd', '2026-01-15', 760_000);
        $tim = $pd->tim()->create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'jabatan' => $pegawai->jabatan, 'bidang_snapshot' => 'Irban I',
            'nip' => $pegawai->nip, 'bbm_liter' => 10, 'bbm_tarif' => 10_000, 'tol' => 20_000, 'tiket' => 40_000, 'representatif' => 50_000,
        ]);
        $tim->paket()->create(['cluster' => 'A', 'wilayah' => 'Bandung', 'lama_hari' => 2, 'tarif_uh' => 100_000, 'malam' => 1, 'tarif_akom' => 350_000]);

        $tr = $this->npd('tr', '2026-02-10', 190_000);
        $tr->tim()->create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'jabatan' => $pegawai->jabatan, 'bidang_snapshot' => 'Irban I',
            'nip' => $pegawai->nip, 'bbm_liter' => 10, 'bbm_tarif' => 10_000, 'tol' => 30_000, 'tiket' => 60_000,
        ]);

        $hasil = app(PerjalananDinasDashboardService::class)->ringkasan(['metrik' => 'diterima'], 2026);

        $this->assertSame(2.0, $hasil['total']['hari']);
        $this->assertSame(200_000.0, $hasil['total']['uang_harian']);
        $this->assertSame(350_000.0, $hasil['total']['akomodasi']);
        $this->assertSame(350_000.0, $hasil['total']['transport']);
        $this->assertSame(50_000.0, $hasil['total']['representatif']);
        $this->assertSame((float) $pd->nominal + (float) $tr->nominal, $hasil['total']['diterima']);
        $this->assertSame(760_000.0, $hasil['bulan'][0]['nilai']);
        $this->assertSame(190_000.0, $hasil['bulan'][1]['nilai']);
    }

    public function test_filter_bidang_dan_pegawai_serta_snapshot_tidak_berubah_saat_master_berubah(): void
    {
        $satu = Pegawai::create(['nama' => 'Satu', 'nip' => '11', 'jabatan' => 'Auditor', 'bidang' => 'Inspektur Pembantu II', 'aktif' => true]);
        $dua = Pegawai::create(['nama' => 'Dua', 'nip' => '22', 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat', 'aktif' => true]);
        foreach ([[$satu, 'Inspektur Pembantu II', 100_000], [$dua, 'Sekretariat', 200_000]] as [$pegawai, $bidang, $nominal]) {
            $npd = $this->npd('pd', '2026-03-10', $nominal);
            $npd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bidang_snapshot' => $bidang, 'tiket' => $nominal]);
        }
        $satu->update(['bidang' => 'Sekretariat']);

        $service = app(PerjalananDinasDashboardService::class);
        $bidang = $service->ringkasan(['bidang' => 'Inspektur Pembantu II'], 2026);
        $pegawai = $service->ringkasan(['pegawai' => 'pegawai:'.$dua->id], 2026);

        $this->assertSame(100_000.0, $bidang['total']['diterima']);
        $this->assertSame('Inspektur Pembantu II', $bidang['bidang'][0]['bidang']);
        $this->assertSame(200_000.0, $pegawai['total']['diterima']);
        $this->actingAs($this->user)->get(route('dashboard.perjalanan.index'))->assertOk()->assertSee('Dashboard Perjalanan Dinas');
    }

    public function test_npd_belum_selesai_dan_role_tanpa_menu_tidak_masuk_dashboard(): void
    {
        $draft = $this->npd('pd', '2026-04-01', 500_000, 'Draft NPD - PPTK');
        $draft->tim()->create(['nama' => 'Draft', 'bidang_snapshot' => 'Sekretariat', 'tiket' => 500_000]);
        $hasil = app(PerjalananDinasDashboardService::class)->ringkasan([], 2026);
        $this->assertTrue($hasil['kosong']);

        $pptk = User::create(['username' => 'dash-pd-no', 'nama' => 'No', 'role' => 'pptk', 'password' => 'rahasia']);
        $this->actingAs($pptk)->get(route('dashboard.perjalanan.index'))->assertForbidden();
    }

    private function npd(string $jenis, string $tanggal, float $nominal, string $status = 'Selesai'): Npd
    {
        return Npd::create([
            'jenis' => $jenis, 'master_anggaran_id' => $this->anggaran->id, 'keu' => '2', 'bulan' => (int) substr($tanggal, 5, 2),
            'tahun' => 2026, 'tanggal_npd' => $tanggal, 'nominal' => $nominal, 'terbilang' => 'uji', 'status' => $status,
        ]);
    }
}
