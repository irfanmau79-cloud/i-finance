<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Spm;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DashboardNpdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNpdTest extends TestCase
{
    use RefreshDatabase;

    private const KODE_PD_BIASA = '5.1.02.04.001.00001';

    private const KODE_PD_DALAM_KOTA = '5.1.02.04.001.00003';

    private const KODE_BARANG = '5.1.02.01.01.0026';

    private int $urut = 0;

    private function user(string $role): User
    {
        return User::create(['username' => 'dnpd-'.$role.'-'.User::count(), 'nama' => 'Akun '.$role, 'role' => $role, 'password' => 'rahasia-uji']);
    }

    private function anggaran(string $kode): MasterAnggaran
    {
        $this->urut++;

        return MasterAnggaran::create([
            'program' => 'Program Dashboard NPD',
            'kegiatan' => 'Kegiatan Dashboard NPD',
            'sub_kegiatan' => '6.01.01.2.0'.$this->urut.' Sub Dashboard '.$this->urut,
            'kode_rekening' => $kode,
            'pagu' => 500_000_000,
            'aktif' => true,
        ]);
    }

    private function npd(string $jenis, string $kode, float $nominal, string $status, array $lain = []): Npd
    {
        return Npd::create(array_merge([
            'jenis' => $jenis,
            'master_anggaran_id' => $this->anggaran($kode)->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-15',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'uji',
            'status' => $status,
            'nomor_lengkap' => $status === 'Selesai' ? 'NPD/'.uniqid() : null,
        ], $lain));
    }

    private function pegawai(string $nama, string $bidang): Pegawai
    {
        $this->urut++;

        return Pegawai::create(['nama' => $nama, 'nip' => sprintf('1980010120050110%02d', $this->urut), 'jabatan' => 'Auditor', 'bidang' => $bidang, 'aktif' => true]);
    }

    private function data(array $filters = []): array
    {
        return app(DashboardNpdService::class)->ringkasan($filters + ['status' => '', 'bulan' => '', 'unit' => '', 'cari' => ''], 2026);
    }

    public function test_kelompok_ditentukan_kode_rekening_dan_unit_kerja_dari_data_pegawai(): void
    {
        $budi = $this->pegawai('Budi Santoso', 'Inspektur Pembantu II');
        $rekanan = Vendor::create(['nama' => 'CV Rekanan Uji', 'aktif' => true]);

        // Barang/Jasa ke rekanan: bukan pegawai -> Pihak Ketiga.
        $bj = $this->npd('bj', self::KODE_BARANG, 1_000_000, 'Selesai');
        $bj->penerima()->create(['vendor_id' => $rekanan->id, 'nama' => 'CV Rekanan Uji', 'bruto' => 1_000_000, 'keterangan' => 'Belanja ATK']);

        // Narasumber pegawai, dikenali dari NAMA (tanpa pegawai_id).
        $ns = $this->npd('ns', self::KODE_BARANG, 2_000_000, 'Draft NPD - BPP', ['detail_json' => ['uraian_kegiatan' => 'Bimtek audit']]);
        $ns->narasumber()->create(['nama' => 'budi  santoso', 'jumlah_jp' => 2, 'tarif_jp' => 1_000_000]);

        // Kontribusi Diklat pada rekening non-perjalanan -> Barang/Jasa.
        $kd = $this->npd('kd', self::KODE_BARANG, 3_000_000, 'Selesai', ['mode_kd' => 'kontribusi', 'detail_json' => ['nama_pelatihan' => 'Diklat Auditor', 'penerima_index' => 0]]);
        $kd->peserta()->create(['pegawai_id' => $budi->id, 'nama' => 'Budi Santoso', 'volume_kontribusi' => 1, 'tarif_kontribusi' => 3_000_000]);

        // Perjalanan Dinas Biasa, Dalam Kota, dan Transport -> Perjalanan Dinas.
        $pd = $this->npd('pd', self::KODE_PD_BIASA, 4_000_000, 'Selesai', ['detail_json' => ['uraian_sp' => 'Audit kinerja']]);
        $pd->tim()->create(['pegawai_id' => $budi->id, 'nama' => 'Budi Santoso', 'is_penerima' => true]);
        $dk = $this->npd('pd', self::KODE_PD_DALAM_KOTA, 500_000, 'Verifikasi - Verifikator', ['detail_json' => ['uraian_sp' => 'Dalam kota']]);
        $dk->tim()->create(['nama' => 'Orang Luar', 'is_penerima' => true]);
        $tr = $this->npd('tr', self::KODE_PD_BIASA, 250_000, 'Selesai', ['detail_json' => ['uraian_sp' => 'Transport audit']]);
        $tr->tim()->create(['nama' => 'Budi Santoso', 'bidang_snapshot' => 'Sekretariat', 'is_penerima' => true]);

        // Dibatalkan dan tahun lain tidak ikut.
        $this->npd('bj', self::KODE_BARANG, 9_000_000, 'Dibatalkan');
        $this->npd('bj', self::KODE_BARANG, 9_000_000, 'Selesai', ['tahun' => 2025, 'tanggal_npd' => '2025-07-15']);

        $data = $this->data();
        $kelompok = collect($data['kelompok'])->keyBy('kunci');

        $this->assertSame([$bj->id, $ns->id, $kd->id], collect($kelompok['bj']['rows'])->pluck('id')->sort()->values()->all());
        $this->assertSame([$pd->id, $dk->id, $tr->id], collect($kelompok['pd']['rows'])->pluck('id')->sort()->values()->all());
        $this->assertSame(6_000_000.0, $kelompok['bj']['nominal']);
        $this->assertSame(4_750_000.0, $kelompok['pd']['nominal']);

        $unit = collect($data['kelompok'])->flatMap(fn ($k) => $k['rows'])->pluck('unit_kerja', 'id');
        $this->assertSame('Pihak Ketiga', $unit[$bj->id]);
        $this->assertSame('Inspektur Pembantu II', $unit[$ns->id]);
        $this->assertSame('Inspektur Pembantu II', $unit[$kd->id]);
        $this->assertSame('Inspektur Pembantu II', $unit[$pd->id]);
        $this->assertSame('Pihak Ketiga', $unit[$dk->id]);
        // Unit saat perjalanan dilakukan menang atas pencocokan nama.
        $this->assertSame('Sekretariat', $unit[$tr->id]);

        $this->assertSame(['jumlah' => 4, 'nominal' => 8_250_000.0], $data['kpi']['selesai']);
        $this->assertSame(['jumlah' => 2, 'nominal' => 2_500_000.0], $data['kpi']['proses']);
    }

    public function test_sisa_uang_persediaan_adalah_sp2d_up_gu_dikurangi_npd_selesai(): void
    {
        Spm::buatUpGu(['nomor_dokumen' => '001/SPM-UP/2026', 'tanggal_dokumen' => '2026-01-10', 'nominal' => 50_000_000]);
        Spm::buatUpGu(['nomor_dokumen' => '002/SPM-GU/2026', 'tanggal_dokumen' => '2026-03-10', 'nominal' => 20_000_000]);
        // Tahun lain tidak ikut.
        Spm::buatUpGu(['nomor_dokumen' => '009/SPM-UP/2025', 'tanggal_dokumen' => '2025-02-10', 'nominal' => 99_000_000]);

        $this->npd('bj', self::KODE_BARANG, 12_000_000, 'Selesai');
        $this->npd('pd', self::KODE_PD_BIASA, 3_000_000, 'Selesai');
        // Belum Selesai: uangnya belum keluar dari kas.
        $this->npd('bj', self::KODE_BARANG, 7_000_000, 'NPD Disetujui - BPP');
        $this->npd('bj', self::KODE_BARANG, 5_000_000, 'Dibatalkan');

        $this->assertSame(
            ['sp2d' => 70_000_000.0, 'npd_selesai' => 15_000_000.0, 'sisa' => 55_000_000.0],
            $this->data()['kpi']['sisa_up']
        );

        // Saringan halaman tidak mengubah posisi kas.
        $this->assertSame(55_000_000.0, $this->data(['status' => 'proses', 'cari' => 'tidak-ada'])['kpi']['sisa_up']['sisa']);
    }

    public function test_kartu_status_menyaring_rincian_tanpa_menolkan_kartu_lain(): void
    {
        $selesai = $this->npd('bj', self::KODE_BARANG, 1_000_000, 'Selesai');
        $proses = $this->npd('bj', self::KODE_BARANG, 2_000_000, 'Draft NPD - PPTK');
        $pdAgustus = $this->npd('pd', self::KODE_PD_BIASA, 3_000_000, 'Selesai', ['tanggal_npd' => '2026-08-02', 'bulan' => 8]);

        $hanyaSelesai = $this->data(['status' => 'selesai']);
        $this->assertSame([$selesai->id, $pdAgustus->id], collect($hanyaSelesai['kelompok'])->flatMap(fn ($k) => $k['rows'])->pluck('id')->sort()->values()->all());
        // Kartu Dalam Proses tetap menghitung yang sedang berjalan.
        $this->assertSame(1, $hanyaSelesai['kpi']['proses']['jumlah']);
        $this->assertSame(2, $hanyaSelesai['kpi']['selesai']['jumlah']);

        $hanyaProses = $this->data(['status' => 'proses']);
        $this->assertSame([$proses->id], collect($hanyaProses['kelompok'])->flatMap(fn ($k) => $k['rows'])->pluck('id')->all());

        // Saringan bulan mempersempit kartu maupun rincian.
        $agustus = $this->data(['bulan' => '8']);
        $this->assertSame(1, $agustus['kpi']['selesai']['jumlah']);
        $this->assertSame(0, $agustus['kpi']['proses']['jumlah']);
        $this->assertSame([$pdAgustus->id], collect($agustus['kelompok'])->flatMap(fn ($k) => $k['rows'])->pluck('id')->all());
    }

    public function test_halaman_menampilkan_kartu_kelompok_dan_tautan_lihat_npd(): void
    {
        $npd = $this->npd('bj', self::KODE_BARANG, 1_500_000, 'Selesai');
        $npd->penerima()->create(['nama' => 'Toko Dashboard', 'bruto' => 1_500_000, 'keterangan' => 'Belanja uji dashboard']);
        $bpp = $this->user('bpp');

        $halaman = $this->actingAs($bpp)->get(route('dashboard.npd.index'))->assertOk();
        $halaman->assertSee('Dashboard Nota Pencairan Dana')
            ->assertSee('NPD Selesai')
            ->assertSee('NPD Dalam Proses')
            ->assertSee('Sisa Uang Persediaan (IBC)')
            ->assertSee('NPD Barang/Jasa')
            ->assertSee('NPD Perjalanan Dinas')
            ->assertSee('Toko Dashboard')
            ->assertSee('Pihak Ketiga')
            ->assertSee('Belanja uji dashboard')
            // "Lihat NPD" = seluruh dokumen dalam satu berkas, versi terkini
            // tanpa coretan (tanpa ?versi=draft).
            ->assertSee('href="'.route('npd.cetak-gabungan', $npd).'"', false)
            ->assertDontSee('versi=draft', false)
            // Kartu adalah tautan saringan status.
            ->assertSee('status=selesai', false)
            ->assertSee('status=proses', false)
            // Sub menunya ada di grup Dashboard.
            ->assertSee('href="'.route('dashboard.npd.index').'">Dashboard Nota Pencairan Dana</a>', false);

        // Tanpa saringan kelompoknya tertutup; dengan saringan langsung terbuka.
        $halaman->assertDontSee('data-dnpd-kel open', false);
        $this->actingAs($bpp)->get(route('dashboard.npd.index', ['status' => 'selesai']))->assertOk()
            ->assertSee('data-dnpd-kel open', false)
            ->assertSee('Sedang disaring');

        $this->actingAs($bpp)->get(route('npd.cetak-gabungan', $npd))->assertOk();
    }

    public function test_dashboard_hanya_untuk_pemantau_npd(): void
    {
        foreach (['superadmin', 'bendahara_pengeluaran', 'bpp', 'pptk', 'verifikator', 'inspektur', 'irban1', 'pengawas'] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard.npd.index'))->assertOk();
        }

        foreach (['perencanaan', 'kepegawaian', 'pengelola_spj'] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard.npd.index'))->assertForbidden();
        }
    }
}
