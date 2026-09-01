<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\User;
use App\Services\AnggaranRealisasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data Realisasi Anggaran per rentang tanggal (Manajemen Data).
 *
 * Yang dijaga di sini: angkanya benar-benar dibatasi rentang tanggal, bukan
 * total sepanjang tahun, dan susunannya turun sampai Tagging.
 */
class RealisasiPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role): User
    {
        return User::create([
            'username' => 'periode-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatAnggaran(float $pagu = 100_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Periode',
            'kegiatan' => 'Kegiatan Uji Periode',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Periode',
            'kode_rekening' => '5.1.02.99.99.0001 Belanja Pengujian Periode',
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatNpd(MasterAnggaran $anggaran, float $nominal, string $tanggal, string $status = 'Selesai'): Npd
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
            'terbilang' => 'uji',
            'status' => $status,
        ]);
    }

    private function service(): AnggaranRealisasiService
    {
        return app(AnggaranRealisasiService::class);
    }

    // ---------------- Pembatasan rentang tanggal ----------------

    public function test_hanya_transaksi_di_dalam_rentang_yang_dihitung(): void
    {
        $anggaran = $this->buatAnggaran();

        $this->buatNpd($anggaran, 1_000_000, '2026-01-15');
        $this->buatNpd($anggaran, 2_000_000, '2026-08-10');
        $this->buatNpd($anggaran, 4_000_000, '2026-09-05');

        // Agustus saja: hanya NPD 10 Agustus yang ikut.
        $agustus = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');
        $this->assertSame(2_000_000.0, $agustus['total']['realisasi_npd']);

        // Januari s.d. Agustus: dua NPD pertama.
        $delapanBulan = $this->service()->realisasiPeriode('2026-01-01', '2026-08-31');
        $this->assertSame(3_000_000.0, $delapanBulan['total']['realisasi_npd']);

        // Setahun penuh: ketiganya.
        $setahun = $this->service()->realisasiPeriode('2026-01-01', '2026-12-31');
        $this->assertSame(7_000_000.0, $setahun['total']['realisasi_npd']);
    }

    public function test_tanggal_batas_ikut_terhitung(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 1_500_000, '2026-08-01');
        $this->buatNpd($anggaran, 2_500_000, '2026-08-31');

        $hasil = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');

        $this->assertSame(4_000_000.0, $hasil['total']['realisasi_npd'], 'Tanggal awal dan akhir harus inklusif.');
    }

    public function test_npd_belum_selesai_tidak_pernah_dihitung_sebagai_realisasi(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 3_000_000, '2026-08-10', 'Draft NPD - PPTK');
        $this->buatNpd($anggaran, 1_000_000, '2026-08-11', 'Selesai');

        $hasil = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');

        $this->assertSame(1_000_000.0, $hasil['total']['realisasi_npd']);
    }

    public function test_realisasi_ls_dibatasi_tanggal_spm(): void
    {
        $anggaran = $this->buatAnggaran();

        Spm::buatLs([
            'nomor_dokumen' => '001/SPM-LS/2026',
            'tanggal_dokumen' => '2026-08-12',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 5_000_000]],
        ]);
        Spm::buatLs([
            'nomor_dokumen' => '002/SPM-LS/2026',
            'tanggal_dokumen' => '2026-09-12',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 7_000_000]],
        ]);

        $agustus = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');
        $this->assertSame(5_000_000.0, $agustus['total']['realisasi_ls']);
        $this->assertSame(5_000_000.0, $agustus['total']['realisasi_aktual']);

        $duaBulan = $this->service()->realisasiPeriode('2026-08-01', '2026-09-30');
        $this->assertSame(12_000_000.0, $duaBulan['total']['realisasi_ls']);
    }

    public function test_realisasi_aktual_menjumlahkan_npd_dan_ls(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 2_000_000, '2026-08-05');
        Spm::buatLs([
            'nomor_dokumen' => '003/SPM-LS/2026',
            'tanggal_dokumen' => '2026-08-06',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 3_000_000]],
        ]);

        $hasil = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');

        $this->assertSame(2_000_000.0, $hasil['total']['realisasi_npd']);
        $this->assertSame(3_000_000.0, $hasil['total']['realisasi_ls']);
        $this->assertSame(5_000_000.0, $hasil['total']['realisasi_aktual']);
        $this->assertSame(5.0, $hasil['total']['persentase_realisasi'], 'Rp5 juta dari pagu Rp100 juta = 5%.');
    }

    // ---------------- Susunan Program sampai Tagging ----------------

    public function test_pohon_turun_dari_program_sampai_tagging(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 1_000_000, '2026-08-09');

        $hasil = $this->service()->realisasiPeriode('2026-08-01', '2026-08-31');

        $program = $hasil['tree']->first();
        $this->assertStringContainsString('Program Uji Periode', $program['nama']);

        $kegiatan = $program['kegiatan']->first();
        $this->assertStringContainsString('Kegiatan Uji Periode', $kegiatan['nama']);

        $sub = $kegiatan['sub']->first();
        $this->assertStringContainsString('Sub Kegiatan Uji Periode', $sub['nama']);

        $rekening = $sub['rekening']->first();
        $this->assertSame('5.1.02.99.99.0001', $rekening['nama']);

        $tagging = $rekening['tagging']->first();
        $this->assertSame('Tanpa Tagging', $tagging['nama']);
        $this->assertSame(1_000_000.0, $tagging['angka']['realisasi_aktual']);

        // Angka tiap level adalah jumlah level di bawahnya.
        foreach ([$program, $kegiatan, $sub, $rekening] as $simpul) {
            $this->assertSame(1_000_000.0, $simpul['angka']['realisasi_aktual']);
        }
    }

    // ---------------- Halaman, Excel, dan PDF ----------------

    public function test_halaman_dapat_dibuka_dan_menampilkan_periodenya(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 1_000_000, '2026-08-09');

        $this->actingAs($this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN))
            ->get(route('manajemen-data.realisasi-periode.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Data Realisasi Anggaran')
            ->assertSee('01 Agustus 2026')
            ->assertSee('31 Agustus 2026')
            ->assertSee('Program Uji Periode')
            ->assertSee('Kegiatan Uji Periode');
    }

    public function test_tanggal_akhir_tidak_boleh_mendahului_tanggal_awal(): void
    {
        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->get(route('manajemen-data.realisasi-periode.index', ['dari' => '2026-08-31', 'sampai' => '2026-08-01']))
            ->assertSessionHasErrors('sampai');
    }

    public function test_excel_dan_pdf_dapat_diunduh(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 1_000_000, '2026-08-09');
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $periode = ['dari' => '2026-08-01', 'sampai' => '2026-08-31'];

        $this->actingAs($superadmin)->get(route('manajemen-data.realisasi-periode.excel', $periode))
            ->assertOk()
            ->assertDownload('realisasi-anggaran-2026-08-01-sd-2026-08-31.xlsx');

        $pdf = $this->actingAs($superadmin)->get(route('manajemen-data.realisasi-periode.pdf', $periode));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent(), 'Isi respons harus berkas PDF sungguhan.');
    }

    // ---------------- Otorisasi ----------------

    public function test_hanya_pemegang_manajemen_data_yang_boleh_membuka(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_BENDAHARA_PENGELUARAN] as $role) {
            $this->actingAs($this->buatUser($role))
                ->get(route('manajemen-data.realisasi-periode.index'))->assertOk();
        }

        foreach ([User::ROLE_PPTK, User::ROLE_BPP, User::ROLE_VERIFIKATOR, User::ROLE_PENGAWAS] as $role) {
            $user = $this->buatUser($role);

            foreach (['index', 'excel', 'pdf'] as $aksi) {
                $this->actingAs($user)
                    ->get(route('manajemen-data.realisasi-periode.'.$aksi))
                    ->assertForbidden("Role {$role} seharusnya ditolak di aksi {$aksi}.");
            }
        }
    }
}
