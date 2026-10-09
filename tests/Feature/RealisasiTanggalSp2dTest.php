<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\User;
use App\Services\AnggaranRealisasiService;
use App\Services\DashboardNpdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Realisasi SP2D dihitung menurut TANGGAL SP2D, bukan tanggal SPM
 * (keputusan Irfan, Oktober 2026). SPM lama yang tanggal SP2D-nya masih
 * kosong jatuh ke tanggal SPM-nya; SPM baru wajib punya tanggal SP2D.
 */
class RealisasiTanggalSp2dTest extends TestCase
{
    use RefreshDatabase;

    private function anggaran(string $kodeRekening = '5.1.02.05.01.0001'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji SP2D',
            'kegiatan' => 'Kegiatan Uji SP2D',
            'sub_kegiatan' => '6.01.02.1.01.0001 Sub Kegiatan Uji SP2D',
            'kode_rekening' => $kodeRekening,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    private function ls(MasterAnggaran $anggaran, string $nomor, string $tanggalSpm, ?string $tanggalSp2d, float $nominal): Spm
    {
        return Spm::buatLs([
            'tanggal_dokumen' => $tanggalSpm,
            'nomor_dokumen' => $nomor,
            'tanggal_sp2d' => $tanggalSp2d,
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => $nominal]],
            'penerima' => 'Penerima Uji',
            'uraian' => 'Uji tanggal SP2D',
        ]);
    }

    private function bulan(AnggaranRealisasiService $layanan, MasterAnggaran $anggaran, int $tahun): array
    {
        $baris = $layanan->realisasiBulanan([], $tahun)['baris']
            ->firstWhere('kodering', (string) $anggaran->kode_rekening_bersih);
        $this->assertNotNull($baris, 'Mata anggaran tidak ada di realisasi bulanan.');

        return array_map('floatval', $baris['bulanan']);
    }

    public function test_spm_terbit_juli_dengan_sp2d_agustus_masuk_realisasi_agustus(): void
    {
        $anggaran = $this->anggaran();
        $this->ls($anggaran, '001/SPM-LS/2026', '2026-07-30', '2026-08-04', 3_000_000);

        $layanan = app(AnggaranRealisasiService::class);
        $bulan = $this->bulan($layanan, $anggaran, 2026);

        $this->assertSame(0.0, $bulan[6], 'Juli (bulan tanggal SPM) seharusnya kosong.');
        $this->assertSame(3_000_000.0, $bulan[7], 'Agustus (bulan tanggal SP2D) seharusnya memuat realisasinya.');

        // Rincian periodik: rentang Juli kosong, rentang Agustus memuatnya.
        $this->assertSame(0.0, (float) $layanan->realisasiPeriode('2026-07-01', '2026-07-31')['total']['realisasi_ls']);
        $this->assertSame(3_000_000.0, (float) $layanan->realisasiPeriode('2026-08-01', '2026-08-31')['total']['realisasi_ls']);
    }

    public function test_spm_desember_dengan_sp2d_januari_masuk_tahun_sp2d(): void
    {
        $anggaran = $this->anggaran();
        $this->ls($anggaran, '099/SPM-LS/2025', '2025-12-29', '2026-01-05', 2_000_000);

        $layanan = app(AnggaranRealisasiService::class);

        $this->assertSame(2_000_000.0, $this->bulan($layanan, $anggaran, 2026)[0]);
        $this->assertSame(0.0, array_sum($this->bulan($layanan, $anggaran, 2025)));
    }

    public function test_spm_lama_tanpa_tanggal_sp2d_memakai_tanggal_spm(): void
    {
        $anggaran = $this->anggaran();
        $spm = $this->ls($anggaran, '002/SPM-LS/2026', '2026-06-10', null, 1_500_000);

        $this->assertNull($spm->fresh()->tanggal_sp2d);
        $this->assertSame('2026-06-10', $spm->fresh()->tanggalRealisasi()->format('Y-m-d'));

        $layanan = app(AnggaranRealisasiService::class);
        $this->assertSame(1_500_000.0, $this->bulan($layanan, $anggaran, 2026)[5]);
        $this->assertSame(1_500_000.0, (float) $layanan->realisasiPeriode('2026-06-01', '2026-06-30')['total']['realisasi_ls']);

        // Begitu tanggal SP2D-nya diisi, realisasinya pindah ke bulan itu.
        $spm->update(['tanggal_sp2d' => '2026-07-02']);
        $bulan = $this->bulan($layanan, $anggaran, 2026);
        $this->assertSame([0.0, 1_500_000.0], [$bulan[5], $bulan[6]]);
    }

    public function test_sisa_anggaran_di_npd_menghitung_ls_sampai_tanggal_sp2d(): void
    {
        $anggaran = $this->anggaran();
        // SPM terbit sebelum NPD, tetapi SP2D-nya baru terbit sesudahnya.
        $this->ls($anggaran, '003/SPM-LS/2026', '2026-07-10', '2026-07-25', 4_000_000);

        $npd = Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-15', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah', 'status' => 'Draft NPD - PPTK',
        ]);

        $sebelumSp2d = $anggaran->fresh()->sisaAnggaranSebelum($npd);

        // NPD yang sama, tanggalnya digeser melewati tanggal SP2D.
        $npd->update(['tanggal_npd' => '2026-07-26']);
        $sesudahSp2d = $anggaran->fresh()->sisaAnggaranSebelum($npd->fresh());

        $this->assertSame(4_000_000.0, round($sebelumSp2d - $sesudahSp2d, 2), 'SPM LS baru mengurangi sisa anggaran di NPD setelah tanggal SP2D-nya lewat.');
    }

    public function test_sp2d_up_gu_dihitung_pada_tahun_sp2d_di_sisa_uang_persediaan(): void
    {
        Spm::create([
            'jenis_spm' => 'up_gu', 'tanggal_dokumen' => '2025-12-30', 'nomor_dokumen' => '090/SPM-GU/2025',
            'tanggal_sp2d' => '2026-01-03', 'nominal' => 50_000_000,
        ]);

        $layanan = app(DashboardNpdService::class);

        $this->assertSame(50_000_000.0, $layanan->sisaUangPersediaan(2026)['sp2d']);
        $this->assertSame(0.0, $layanan->sisaUangPersediaan(2025)['sp2d']);
    }

    public function test_tanggal_sp2d_wajib_di_formulir_ls_dan_up_gu(): void
    {
        $bendahara = User::create(['username' => 'sp2d-bp', 'nama' => 'Bendahara', 'role' => 'bendahara_pengeluaran', 'password' => 'rahasia']);
        $anggaran = $this->anggaran();

        $ls = [
            'tanggal_dokumen' => '2026-07-20', 'nomor_dokumen' => '010/SPM-LS/2026',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
            'penerima' => 'CV Uji', 'uraian' => 'Uji wajib SP2D',
        ];
        $this->actingAs($bendahara)->post(route('spm.ls.store'), $ls)->assertSessionHasErrors('tanggal_sp2d');

        $upGu = ['tanggal_dokumen' => '2026-07-20', 'nomor_dokumen' => '011/SPM-GU/2026', 'nominal' => 5_000_000];
        $this->actingAs($bendahara)->post(route('spm.up-gu.store'), $upGu)->assertSessionHasErrors('tanggal_sp2d');

        $this->assertSame(0, Spm::count());

        $this->actingAs($bendahara)->post(route('spm.ls.store'), $ls + ['tanggal_sp2d' => '2026-07-22'])->assertSessionHasNoErrors();
        $this->actingAs($bendahara)->post(route('spm.up-gu.store'), $upGu + ['tanggal_sp2d' => '2026-07-22'])->assertSessionHasNoErrors();
        $this->assertSame(2, Spm::count());

        // Formulirnya tidak lagi menyebut tanggal ini opsional.
        foreach (['spm.ls.create', 'spm.up-gu.create'] as $rute) {
            $this->actingAs($bendahara)->get(route($rute))
                ->assertOk()
                ->assertDontSee('Tanggal SP2D (opsional)')
                ->assertSee('Realisasi dihitung menurut Tanggal SP2D, bukan Tanggal SPM.');
        }
    }

    // ---------------- Penyaring Bulan pada daftar Realisasi SP2D ----------------

    public function test_daftar_realisasi_sp2d_bisa_disaring_per_bulan_menurut_tanggal_sp2d(): void
    {
        $bendahara = User::create(['username' => 'sp2d-saring', 'nama' => 'Bendahara', 'role' => 'bendahara_pengeluaran', 'password' => 'rahasia']);
        $anggaran = $this->anggaran();

        // SPM terbit Juli, SP2D Agustus -> termasuk AGUSTUS.
        $this->ls($anggaran, 'LS-JULI-SP2D-AGUSTUS', '2026-07-30', '2026-08-04', 1_000_000);
        $this->ls($anggaran, 'LS-JULI-SP2D-JULI', '2026-07-10', '2026-07-12', 2_000_000);
        // SPM lama tanpa tanggal SP2D -> mengikuti tanggal SPM-nya (September).
        $this->ls($anggaran, 'LS-LAMA-SEPTEMBER', '2026-09-03', null, 3_000_000);

        $semua = $this->actingAs($bendahara)->get(route('spm.ls.index'))->assertOk();
        $semua->assertSee('LS-JULI-SP2D-AGUSTUS')->assertSee('LS-JULI-SP2D-JULI')->assertSee('LS-LAMA-SEPTEMBER');
        $semua->assertSee('<option value="">Semua bulan</option>', false);
        $semua->assertSee('name="bulan"', false);

        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 8]))
            ->assertOk()
            ->assertSee('LS-JULI-SP2D-AGUSTUS')
            ->assertDontSee('LS-JULI-SP2D-JULI')
            ->assertDontSee('LS-LAMA-SEPTEMBER')
            ->assertSee('<option value="8" selected>Agustus</option>', false);

        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 7]))
            ->assertOk()
            ->assertSee('LS-JULI-SP2D-JULI')
            ->assertDontSee('LS-JULI-SP2D-AGUSTUS');

        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 9]))
            ->assertOk()
            ->assertSee('LS-LAMA-SEPTEMBER');

        // Bulan tanpa data, dan gabungan dengan kotak cari.
        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 3]))
            ->assertOk()
            ->assertSee('Tidak ada data yang cocok dengan pencarian atau penyaring ini.');
        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 7, 'cari' => 'AGUSTUS']))
            ->assertOk()
            ->assertDontSee('LS-JULI-SP2D-JULI');

        // Nilai di luar jangkauan diabaikan, bukan galat.
        $this->actingAs($bendahara)->get(route('spm.ls.index', ['bulan' => 99]))
            ->assertOk()
            ->assertSee('LS-JULI-SP2D-JULI');
    }

    public function test_penyaring_bulan_juga_ada_di_daftar_up_gu_dan_tahun_muncul_bila_lebih_dari_satu(): void
    {
        $bendahara = User::create(['username' => 'sp2d-saring-upgu', 'nama' => 'Bendahara', 'role' => 'bendahara_pengeluaran', 'password' => 'rahasia']);

        $buat = fn (string $nomor, string $spm, string $sp2d) => Spm::create([
            'jenis_spm' => 'up_gu', 'tanggal_dokumen' => $spm, 'nomor_dokumen' => $nomor, 'tanggal_sp2d' => $sp2d, 'nominal' => 5_000_000,
        ]);
        $buat('GU-AGUSTUS-2026', '2026-08-01', '2026-08-03');

        // Satu tahun saja: pilihan Tahun tidak perlu tampil.
        $this->actingAs($bendahara)->get(route('spm.up-gu.index'))
            ->assertOk()
            ->assertSee('name="bulan"', false)
            ->assertDontSee('name="tahun"', false);

        // SPM Desember 2025 dengan SP2D Januari 2026 tetap tahun 2026;
        // yang SP2D-nya 2025 membuat pilihan Tahun muncul.
        $buat('GU-SP2D-JANUARI-2026', '2025-12-30', '2026-01-04');
        $buat('GU-DESEMBER-2025', '2025-12-01', '2025-12-05');

        $this->actingAs($bendahara)->get(route('spm.up-gu.index'))
            ->assertOk()
            ->assertSee('name="tahun"', false)
            ->assertSeeInOrder(['<option value="2026"', '<option value="2025"'], false);

        $this->actingAs($bendahara)->get(route('spm.up-gu.index', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('GU-AGUSTUS-2026')
            ->assertSee('GU-SP2D-JANUARI-2026')
            ->assertDontSee('GU-DESEMBER-2025');

        $this->actingAs($bendahara)->get(route('spm.up-gu.index', ['tahun' => 2025, 'bulan' => 12]))
            ->assertOk()
            ->assertSee('GU-DESEMBER-2025')
            ->assertDontSee('GU-SP2D-JANUARI-2026');
    }
}