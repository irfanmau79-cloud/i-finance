<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\PerjalananDinasDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Perjalanan Dinas — perilakunya mengikuti CodeDashboardPD.gs,
 * hanya sumber datanya yang berbeda (diturunkan dari NPD, bukan sheet
 * "Monitoring SPPD" yang diisi manual).
 */
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

    private function service(): PerjalananDinasDashboardService
    {
        return app(PerjalananDinasDashboardService::class);
    }

    public function test_agregasi_pd_dan_transport_memakai_formula_yang_sama_dengan_nominal_npd(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '199001012010012001', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
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

        $hasil = $this->service()->data(['metrik' => 'terima'], 2026);
        $total = $hasil['rekap']['total'];

        // Lima komponen sheet Monitoring SPPD.
        $this->assertSame(2.0, $total['hari']);
        $this->assertSame(200_000.0, $total['uh']);
        $this->assertSame(350_000.0, $total['akom']);
        $this->assertSame(350_000.0, $total['trans']);
        // Jumlah Diterima = nominal bruto NPD, termasuk representatif.
        $this->assertSame((float) $pd->nominal + (float) $tr->nominal, $total['terima']);

        // Dua NPD atas orang yang sama menjadi SATU baris pegawai.
        $this->assertSame(1, $total['pegawai']);

        $bulan = $hasil['tren']['bulan'];
        $this->assertSame(760_000.0, $bulan[0]['nilai']);
        $this->assertSame(190_000.0, $bulan[1]['nilai']);
    }

    public function test_orang_yang_sama_digabung_berdasarkan_nip_meski_nama_ditulis_berbeda(): void
    {
        $nip = '199203032015031002';

        $satu = $this->npd('pd', '2026-05-02', 100_000);
        $satu->tim()->create(['nama' => 'Budi Santoso', 'nip' => $nip, 'bidang_snapshot' => 'Sekretariat', 'tiket' => 100_000]);

        // Nama diketik berbeda (gelar & spasi), NIP diberi pemisah - tetap satu orang.
        $dua = $this->npd('pd', '2026-06-02', 250_000);
        $dua->tim()->create(['nama' => 'Budi  Santoso, S.E.', 'nip' => '1992 0303 2015 03 1002', 'bidang_snapshot' => 'Sekretariat', 'tiket' => 250_000]);

        $rekap = $this->service()->data([], 2026)['rekap'];

        $this->assertSame(1, $rekap['total']['pegawai'], 'NIP yang sama harus menjadi satu baris pegawai.');
        $this->assertSame(350_000.0, $rekap['total']['terima']);

        $anggota = $rekap['rows'][0]['anggota'];
        $this->assertCount(1, $anggota);
        // Identitas mengikuti NPD terbaru.
        $this->assertSame('Budi  Santoso, S.E.', $anggota[0]['nama']);
    }

    public function test_orang_tanpa_nip_tetap_terkelompok_lewat_namanya(): void
    {
        foreach (['2026-05-02', '2026-07-02'] as $tanggal) {
            $npd = $this->npd('pd', $tanggal, 100_000);
            $npd->tim()->create(['nama' => 'Tanpa Nip', 'nip' => '', 'bidang_snapshot' => 'Sekretariat', 'tiket' => 100_000]);
        }

        $rekap = $this->service()->data([], 2026)['rekap'];

        $this->assertSame(1, $rekap['total']['pegawai']);
        $this->assertSame(200_000.0, $rekap['total']['terima']);
    }

    public function test_bidang_tidak_dikenali_masuk_kelompok_tanpa_bidang_bukan_dibuang(): void
    {
        $npd = $this->npd('pd', '2026-05-02', 100_000);
        $npd->tim()->create(['nama' => 'Orang Luar', 'nip' => '9', 'bidang_snapshot' => 'Entah Apa', 'tiket' => 100_000]);

        $rekap = $this->service()->data([], 2026)['rekap'];

        $this->assertSame(100_000.0, $rekap['total']['terima'], 'Datanya tidak boleh hilang hanya karena bidangnya tidak dikenali.');
        $this->assertSame(PerjalananDinasDashboardService::TANPA_BIDANG, $rekap['rows'][0]['bidang']);
    }

    public function test_urutan_bidang_mengikuti_daftar_resmi_dan_bidang_lain_di_belakang(): void
    {
        $data = [
            ['Sekretariat', 'A'],
            ['Inspektur Pembantu I', 'B'],
            ['Entah Apa', 'C'],
            ['Struktural', 'D'],
        ];

        foreach ($data as $i => [$bidang, $nama]) {
            $npd = $this->npd('pd', '2026-05-0'.($i + 1), 100_000);
            $npd->tim()->create(['nama' => $nama, 'nip' => (string) ($i + 1), 'bidang_snapshot' => $bidang, 'tiket' => 100_000]);
        }

        $urut = array_column($this->service()->data([], 2026)['rekap']['rows'], 'bidang');

        $this->assertSame([
            'Struktural',
            'Inspektur Pembantu I',
            'Sekretariat',
            PerjalananDinasDashboardService::TANPA_BIDANG,
        ], $urut);
    }

    public function test_rekap_tidak_terpengaruh_filter_hanya_tren_yang_menyaring(): void
    {
        $satu = Pegawai::create(['nama' => 'Satu', 'nip' => '11', 'jabatan' => 'Auditor', 'bidang' => 'Inspektur Pembantu II', 'aktif' => true]);
        $dua = Pegawai::create(['nama' => 'Dua', 'nip' => '22', 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat', 'aktif' => true]);

        foreach ([[$satu, 'Inspektur Pembantu II', 100_000], [$dua, 'Sekretariat', 200_000]] as [$pegawai, $bidang, $nominal]) {
            $npd = $this->npd('pd', '2026-03-10', $nominal);
            $npd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'nip' => $pegawai->nip, 'bidang_snapshot' => $bidang, 'tiket' => $nominal]);
        }

        $hasil = $this->service()->data(['bidang' => 'Inspektur Pembantu II'], 2026);

        // getPDRekap() di GAS tidak menerima filter: rekap tetap utuh.
        $this->assertSame(300_000.0, $hasil['rekap']['total']['terima']);
        $this->assertSame(2, $hasil['rekap']['total']['pegawai']);

        // Tren mengikuti filter.
        $this->assertSame(100_000.0, $hasil['tren']['total']['terima']);
        $this->assertSame(1, $hasil['tren']['jumlah_pegawai']);
        $this->assertSame('Inspektur Pembantu II', $hasil['tren']['cakupan']);
    }

    public function test_filter_pegawai_memakai_nip_sebagai_kunci(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Dua', 'nip' => '22', 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat', 'aktif' => true]);
        $npd = $this->npd('pd', '2026-03-10', 200_000);
        $npd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'nip' => $pegawai->nip, 'bidang_snapshot' => 'Sekretariat', 'tiket' => 200_000]);

        $hasil = $this->service()->data(['pegawai' => 'nip:22'], 2026);

        $this->assertSame(200_000.0, $hasil['tren']['total']['terima']);
        $this->assertSame('Dua', $hasil['tren']['cakupan']);
        $this->assertSame(['value' => 'nip:22', 'label' => 'Dua', 'bidang' => 'Sekretariat'], $hasil['pilihan']['pegawai'][0]);
    }

    public function test_snapshot_bidang_tidak_ikut_berubah_saat_master_pegawai_diperbarui(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Satu', 'nip' => '11', 'jabatan' => 'Auditor', 'bidang' => 'Inspektur Pembantu II', 'aktif' => true]);
        $npd = $this->npd('pd', '2026-03-10', 100_000);
        $npd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'nip' => $pegawai->nip, 'bidang_snapshot' => 'Inspektur Pembantu II', 'tiket' => 100_000]);

        $pegawai->update(['bidang' => 'Sekretariat']);

        $rekap = $this->service()->data([], 2026)['rekap'];
        $this->assertSame('Inspektur Pembantu II', $rekap['rows'][0]['bidang']);
    }

    public function test_npd_belum_selesai_dan_role_tanpa_menu_tidak_masuk_dashboard(): void
    {
        $draft = $this->npd('pd', '2026-04-01', 500_000, 'Draft NPD - PPTK');
        $draft->tim()->create(['nama' => 'Draft', 'nip' => '5', 'bidang_snapshot' => 'Sekretariat', 'tiket' => 500_000]);

        $hasil = $this->service()->data([], 2026);
        $this->assertSame([], $hasil['rekap']['rows']);
        $this->assertTrue($hasil['tren']['kosong']);

        $pptk = User::create(['username' => 'dash-pd-no', 'nama' => 'No', 'role' => 'pptk', 'password' => 'rahasia']);
        $this->actingAs($pptk)->get(route('dashboard.perjalanan.index'))->assertForbidden();
    }

    public function test_halaman_menampilkan_rekap_metrik_dan_baris_total(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '11', 'jabatan' => 'Auditor Ahli', 'bidang' => 'Sekretariat', 'aktif' => true]);
        $npd = $this->npd('pd', '2026-03-10', 250_000);
        $npd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'jabatan' => $pegawai->jabatan, 'nip' => $pegawai->nip, 'bidang_snapshot' => 'Sekretariat', 'tiket' => 250_000]);

        $this->actingAs($this->user)->get(route('dashboard.perjalanan.index'))
            ->assertOk()
            ->assertSee('Rekapan per Bidang')
            ->assertSee('Tren Bulanan')
            ->assertSee('Total Diterima')
            ->assertSee('TOTAL')
            ->assertSee('Ani Auditor')
            ->assertSee('Semua Bidang')
            // Lima tombol metrik, urut sama dengan GAS.
            ->assertSee('Jumlah Diterima')
            ->assertSee('Uang Harian')
            ->assertSee('Akomodasi')
            ->assertSee('Transportasi')
            ->assertSee('Jumlah Hari');
    }

    private function npd(string $jenis, string $tanggal, float $nominal, string $status = 'Selesai'): Npd
    {
        return Npd::create([
            'jenis' => $jenis, 'master_anggaran_id' => $this->anggaran->id, 'keu' => '2', 'bulan' => (int) substr($tanggal, 5, 2),
            'tahun' => 2026, 'tanggal_npd' => $tanggal, 'nominal' => $nominal, 'terbilang' => 'uji', 'status' => $status,
        ]);
    }
}
