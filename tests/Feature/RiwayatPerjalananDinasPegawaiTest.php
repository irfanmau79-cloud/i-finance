<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiwayatPerjalananDinasPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private MasterAnggaran $anggaran;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'riwayat-pd', 'nama' => 'Riwayat PD', 'role' => 'superadmin', 'password' => 'rahasia']);
        $this->anggaran = MasterAnggaran::create([
            'program' => 'Program PD', 'kegiatan' => 'Kegiatan PD', 'sub_kegiatan' => '6.01.02.1.01 Pengawasan',
            'kode_rekening' => '5.1.02.04.01.0001', 'pagu' => 100_000_000, 'aktif' => true,
        ]);
    }

    public function test_riwayat_gabungan_pd_tr_kd_perjalanan_tampil_terurut_tanggal_terbaru_dulu(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);

        $pd = $this->npd('pd', '2026-01-10');
        $tim = $pd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'jabatan' => $pegawai->jabatan, 'tiket' => 100_000]);
        $tim->paket()->create(['cluster' => 'A', 'wilayah' => 'Bandung', 'lama_hari' => 3, 'tarif_uh' => 100_000, 'malam' => 0, 'tarif_akom' => 0]);

        $tr = $this->npd('tr', '2026-02-15');
        $tr->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 50_000]);

        $kd = $this->npd('kd', '2026-03-20', ['mode_kd' => 'perjalanan']);
        $kd->peserta()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'hari_uh' => 2, 'tarif_uh' => 150_000]);

        $riwayat = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class)->riwayat($pegawai, [], 1);

        $this->assertSame(3, $riwayat['ringkasan']['total_npd']);
        $urutan = collect($riwayat['halaman']->items())->pluck('npd_id')->all();
        $this->assertSame([$kd->id, $tr->id, $pd->id], $urutan);
    }

    public function test_nominal_bagian_pegawai_saja_bukan_total_npd_saat_lebih_dari_satu_anggota(): void
    {
        $satu = Pegawai::create(['nama' => 'Satu', 'nip' => '11', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $dua = Pegawai::create(['nama' => 'Dua', 'nip' => '22', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);

        $pd = $this->npd('pd', '2026-01-10', ['nominal' => 400_000]);
        $pd->tim()->create(['pegawai_id' => $satu->id, 'nama' => $satu->nama, 'tiket' => 100_000]);
        $pd->tim()->create(['pegawai_id' => $dua->id, 'nama' => $dua->nama, 'tiket' => 300_000]);

        $riwayat = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class)->riwayat($satu, [], 1);

        $this->assertSame(100_000.0, $riwayat['halaman']->items()[0]['nominal_bagian']);
        $this->assertNotSame((float) $pd->nominal, $riwayat['halaman']->items()[0]['nominal_bagian']);
    }

    public function test_pegawai_id_null_dengan_nama_snapshot_cocok_tetap_muncul_dan_ditandai(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Budi Snapshot', 'nip' => '33', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $pd = $this->npd('pd', '2026-01-10');
        $pd->tim()->create(['pegawai_id' => null, 'nama' => 'Budi Snapshot', 'tiket' => 100_000]);

        $riwayat = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class)->riwayat($pegawai, [], 1);

        $this->assertSame(1, $riwayat['ringkasan']['total_npd']);
        $this->assertTrue($riwayat['halaman']->items()[0]['kecocokan_nama']);
    }

    public function test_nomor_sp_tampil_untuk_yang_tertaut_kosong_untuk_yang_tidak(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $sp = SuratPerintah::create([
            'nomor_sp' => 'SP-001', 'tanggal_sp' => '2026-01-01', 'unit_kerja' => 'Sekretariat', 'lokasi' => 'Bandung',
            'nama_pengirim' => 'A', 'tujuan_transfer' => 'B', 'rincian_tgl_bayar' => '-', 'keterangan' => '-', 'file_url' => 'x',
        ]);

        $pdDenganSp = $this->npd('pd', '2026-01-10', ['surat_perintah_id' => $sp->id]);
        $pdDenganSp->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 100_000]);

        $pdTanpaSp = $this->npd('pd', '2026-01-11');
        $pdTanpaSp->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 100_000]);

        $riwayat = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class)->riwayat($pegawai, [], 1);
        $items = collect($riwayat['halaman']->items())->keyBy('npd_id');

        $this->assertSame('SP-001', $items[$pdDenganSp->id]['nomor_sp']);
        $this->assertNull($items[$pdTanpaSp->id]['nomor_sp']);
    }

    public function test_filter_tanggal_jenis_dan_status_berfungsi(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);

        $pd = $this->npd('pd', '2026-01-10', ['status' => 'Selesai']);
        $pd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 100_000]);

        $tr = $this->npd('tr', '2026-06-15', ['status' => 'Draft NPD - PPTK']);
        $tr->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 50_000]);

        $service = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class);

        $hasilJenis = $service->riwayat($pegawai, ['jenis' => 'tr'], 1);
        $this->assertSame(1, $hasilJenis['ringkasan']['total_npd']);
        $this->assertSame($tr->id, $hasilJenis['halaman']->items()[0]['npd_id']);

        $hasilStatus = $service->riwayat($pegawai, ['status' => 'Selesai'], 1);
        $this->assertSame($pd->id, $hasilStatus['halaman']->items()[0]['npd_id']);

        $hasilTanggal = $service->riwayat($pegawai, ['dari' => '2026-05-01', 'sampai' => '2026-12-31'], 1);
        $this->assertSame(1, $hasilTanggal['ringkasan']['total_npd']);
        $this->assertSame($tr->id, $hasilTanggal['halaman']->items()[0]['npd_id']);
    }

    public function test_pagination_bekerja_dengan_dataset_besar(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        for ($i = 1; $i <= 25; $i++) {
            $pd = $this->npd('pd', sprintf('2026-01-%02d', $i));
            $pd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 10_000]);
        }

        $service = app(\App\Services\RiwayatPerjalananDinasPegawaiService::class);
        $halaman1 = $service->riwayat($pegawai, [], 1);
        $halaman2 = $service->riwayat($pegawai, [], 2);

        $this->assertSame(25, $halaman1['ringkasan']['total_npd']);
        $this->assertCount(20, $halaman1['halaman']->items());
        $this->assertCount(5, $halaman2['halaman']->items());
        $this->assertTrue($halaman1['halaman']->hasPages());
    }

    public function test_role_tanpa_akses_npd_show_tidak_dapat_membuka_detail_dari_tautan(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $pd = $this->npd('pd', '2026-01-10');
        $pd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 100_000]);

        $inspektur = User::create(['username' => 'riwayat-insp', 'nama' => 'Insp', 'role' => 'inspektur', 'password' => 'rahasia']);

        $this->actingAs($inspektur)
            ->get(route('dashboard.perjalanan.pegawai', $pegawai->id))
            ->assertOk()
            ->assertDontSee(route('npd.show', $pd->id));

        $this->actingAs($inspektur)->get(route('npd.show', $pd->id))->assertForbidden();
    }

    public function test_role_dengan_akses_npd_show_melihat_tautan_dan_dapat_membuka_detail(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $pd = $this->npd('pd', '2026-01-10');
        $pd->tim()->create(['pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'tiket' => 100_000]);

        $this->actingAs($this->user)
            ->get(route('dashboard.perjalanan.pegawai', $pegawai->id))
            ->assertOk()
            ->assertSee($pegawai->nama)
            ->assertSee(route('npd.show', $pd->id), false);
    }

    public function test_role_tanpa_menu_dashpd_tidak_bisa_akses_halaman(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Ani Auditor', 'nip' => '1', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);
        $pptk = User::create(['username' => 'riwayat-pptk', 'nama' => 'PPTK', 'role' => 'pptk', 'password' => 'rahasia']);

        $this->actingAs($pptk)->get(route('dashboard.perjalanan.pegawai', $pegawai->id))->assertForbidden();
    }

    private function npd(string $jenis, string $tanggal, array $override = []): Npd
    {
        return Npd::create(array_merge([
            'jenis' => $jenis, 'master_anggaran_id' => $this->anggaran->id, 'keu' => '2', 'bulan' => (int) substr($tanggal, 5, 2),
            'tahun' => 2026, 'tanggal_npd' => $tanggal, 'nominal' => 100_000, 'terbilang' => 'uji', 'status' => 'Selesai',
        ], $override));
    }
}
