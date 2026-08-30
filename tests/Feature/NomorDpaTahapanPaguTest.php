<?php

namespace Tests\Feature;

use App\Helpers\PejabatResolver;
use App\Models\DataTambahan;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use App\Models\VersiPagu;
use App\Models\VersiPaguDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nomor DPA menempel pada Tahapan Pagu dan ikut tercetak di NPD.
 *
 * Sebelumnya nomor itu diambil dari data_tambahan (per program, warisan GAS)
 * yang tidak pernah terisi, sehingga kolom "No. DPA" pada PDF NPD selalu
 * kosong. Sekarang sumbernya tahapan pagu yang sedang BERLAKU.
 */
class NomorDpaTahapanPaguTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::create([
            'username' => 'uji-superadmin-dpa',
            'nama' => 'Penguji Superadmin',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia-uji',
        ]);
    }

    private function master(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Uji DPA',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji DPA',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Uji DPA',
            'kode_rekening' => '5.1.02.01.01.0001',
            'rekening' => 'Belanja Uji DPA',
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    private function tahapan(string $nama, ?string $nomorDpa, string $status, MasterAnggaran $master): VersiPagu
    {
        $versi = VersiPagu::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nama' => $nama,
            'nomor_dpa' => $nomorDpa,
            'status' => $status,
        ]);

        VersiPaguDetail::create([
            'versi_pagu_id' => $versi->id,
            'master_anggaran_id' => $master->id,
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);

        $versi->segarkanRingkasan();

        return $versi->fresh();
    }

    private function npd(MasterAnggaran $master): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $master->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'tanggal_npd' => config('anggaran.tahun_aktif').'-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);
    }

    public function test_npd_memakai_nomor_dpa_tahapan_yang_berlaku(): void
    {
        $master = $this->master();
        // Tahapan arsip punya nomor lain: yang tercetak harus milik yang berlaku.
        $this->tahapan('DPA Murni', '027/DPA/2026', VersiPagu::STATUS_ARSIP, $master);
        $this->tahapan('DPA Pergeseran 1', '099/DPA-P1/2026', VersiPagu::STATUS_AKTIF, $master);

        $pejabat = PejabatResolver::untukNpd($this->npd($master));

        $this->assertSame('099/DPA-P1/2026', $pejabat['no_dpa']);
    }

    public function test_tahapan_draf_belum_mempengaruhi_nomor_dpa_yang_tercetak(): void
    {
        $master = $this->master();
        $this->tahapan('DPA Murni', '027/DPA/2026', VersiPagu::STATUS_AKTIF, $master);
        $this->tahapan('DPA Pergeseran 1', '099/DPA-P1/2026', VersiPagu::STATUS_DRAFT, $master);

        $this->assertSame('027/DPA/2026', PejabatResolver::untukNpd($this->npd($master))['no_dpa']);
    }

    public function test_data_tambahan_lama_tetap_jadi_cadangan_bila_tahapan_belum_bernomor(): void
    {
        $master = $this->master();
        $this->tahapan('DPA Murni', null, VersiPagu::STATUS_AKTIF, $master);

        DataTambahan::create([
            'program' => $master->program_lengkap,
            'no_dpa' => '001/DPA-LAMA/2026',
            'kpa' => 'KPA Lama',
            'pptk' => 'PPTK Lama',
            'bpp' => 'BPP Lama',
        ]);

        $this->assertSame('001/DPA-LAMA/2026', PejabatResolver::untukNpd($this->npd($master))['no_dpa']);
    }

    public function test_nomor_dpa_muncul_di_pdf_npd(): void
    {
        $master = $this->master();
        $this->tahapan('DPA Murni', '027/DPA/2026', VersiPagu::STATUS_AKTIF, $master);
        $npd = $this->npd($master);

        $ditangkap = [];
        \Illuminate\Support\Facades\View::composer('npd.pdf.npd', function ($view) use (&$ditangkap) {
            $ditangkap = $view->getData();
        });

        $this->actingAs($this->superadmin())->get(route('npd.cetak-npd', $npd))->assertOk();

        $this->assertSame('027/DPA/2026', $ditangkap['noDpa']);
    }

    public function test_nomor_dpa_dapat_dilengkapi_tanpa_impor_ulang(): void
    {
        // Tahapan yang sudah aktif sebelum kolom ini ada tidak boleh menuntut
        // impor ulang seluruh dokumen DPA hanya untuk mengisi nomornya.
        $master = $this->master();
        $tahapan = $this->tahapan('DPA Pergeseran 6', null, VersiPagu::STATUS_AKTIF, $master);

        $this->actingAs($this->superadmin())
            ->patch(route('versi-pagu.nomor-dpa', $tahapan), ['nomor_dpa' => '  188/DPA-P6/2026  '])
            ->assertRedirect(route('versi-pagu.index'));

        $this->assertSame('188/DPA-P6/2026', $tahapan->fresh()->nomor_dpa);
        $this->assertSame('188/DPA-P6/2026', PejabatResolver::untukNpd($this->npd($master))['no_dpa']);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'Ubah Nomor DPA Tahapan Pagu']);
    }

    public function test_nomor_dpa_boleh_dikosongkan_kembali(): void
    {
        $master = $this->master();
        $tahapan = $this->tahapan('DPA Murni', '027/DPA/2026', VersiPagu::STATUS_AKTIF, $master);

        $this->actingAs($this->superadmin())
            ->patch(route('versi-pagu.nomor-dpa', $tahapan), ['nomor_dpa' => ''])
            ->assertRedirect(route('versi-pagu.index'));

        $this->assertNull($tahapan->fresh()->nomor_dpa);
    }

    public function test_pptk_tidak_boleh_mengubah_nomor_dpa(): void
    {
        $master = $this->master();
        $tahapan = $this->tahapan('DPA Murni', '027/DPA/2026', VersiPagu::STATUS_AKTIF, $master);

        $pptk = User::create([
            'username' => 'uji-pptk-dpa',
            'nama' => 'Penguji PPTK',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia-uji',
        ]);

        $this->actingAs($pptk)
            ->patch(route('versi-pagu.nomor-dpa', $tahapan), ['nomor_dpa' => '000/PALSU/2026'])
            ->assertForbidden();

        $this->assertSame('027/DPA/2026', $tahapan->fresh()->nomor_dpa);
    }

    public function test_istilah_tahapan_pagu_dipakai_di_halaman_import_dan_daftar(): void
    {
        $superadmin = $this->superadmin();

        $import = $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.create'));
        $import->assertOk();
        $import->assertSee('>Tahapan Pagu</label>', false);
        $import->assertSee('name="versi_nomor_dpa"', false);
        $import->assertDontSee('Nama Versi Pagu', false);

        $daftar = $this->actingAs($superadmin)->get(route('versi-pagu.index'));
        $daftar->assertOk();
        $daftar->assertSee('<th>Tahapan</th>', false);
        $daftar->assertSee('<th>Nomor DPA</th>', false);
        $daftar->assertDontSee('<th>Versi</th>', false);
    }
}
