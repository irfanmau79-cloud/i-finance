<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RincianRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'pptk-rincian',
            'nama' => 'PPTK Rincian',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia',
        ]);
    }

    public function test_tree_tiga_tingkat_mengagregasi_angka_anak_tanpa_menyimpan_ulang(): void
    {
        $tagA = Tagging::create(['nama' => 'Tag A', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Tag B', 'aktif' => true]);
        $a = $this->anggaran('Sub Kegiatan Satu', '5.1.01', $tagA, 10_000_000);
        $b = $this->anggaran('Sub Kegiatan Satu', '5.1.01', $tagB, 5_000_000);
        $c = $this->anggaran('Sub Kegiatan Satu', '5.1.02', $tagA, 3_000_000);

        $this->npd($a, 2_000_000, 'Selesai');
        $this->npd($b, 1_000_000, 'Draft NPD - PPTK');
        $this->npd($c, 500_000, 'Selesai');

        $this->actingAs($this->user)->get(route('rincian.index'))
            ->assertOk()
            ->assertSee('Tutup Semua')
            ->assertSee('Buka Semua')
            ->assertViewHas('tree', function ($tree) {
                $sub = $tree->first();

                return $tree->count() === 1
                    && $sub['rekening']->count() === 2
                    && $sub['rekening']->first()['tagging']->count() === 2
                    && $sub['angka']['pagu'] === 18_000_000.0
                    && $sub['angka']['dana_terikat_npd'] === 3_500_000.0
                    && $sub['angka']['realisasi_aktual'] === 2_500_000.0
                    && $sub['angka']['sisa_tersedia'] === 14_500_000.0;
            });
    }

    public function test_filter_sub_kegiatan_kode_rekening_tagging_dan_pencarian_membatasi_anak_dan_agregat(): void
    {
        $tagA = Tagging::create(['nama' => 'Prioritas Merah', 'aktif' => true]);
        $tagB = Tagging::create(['nama' => 'Prioritas Biru', 'aktif' => true]);
        $target = $this->anggaran('Sub Target', '5.1.01', $tagA, 10_000_000, 'Belanja Laptop');
        $this->anggaran('Sub Target', '5.1.01', $tagB, 4_000_000, 'Belanja Laptop');
        $this->anggaran('Sub Target', '5.1.02', $tagA, 3_000_000, 'Belanja Laptop');
        $this->anggaran('Sub Lain', '5.1.01', $tagA, 2_000_000, 'Belanja Laptop');
        $this->anggaran('Sub Pencarian', '5.1.03', $tagB, 7_000_000, 'Belanja Kertas');

        $this->actingAs($this->user)->get(route('rincian.index', ['sub_kegiatan' => $target->sub_kegiatan_kunci]))
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 17_000_000.0);
        $this->actingAs($this->user)->get(route('rincian.index', ['kode_rekening' => '5.1.01']))
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 16_000_000.0);
        $this->actingAs($this->user)->get(route('rincian.index', ['tagging' => (string) $tagA->id]))
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 15_000_000.0);
        $this->actingAs($this->user)->get(route('rincian.index', ['q' => 'Kertas']))
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 7_000_000.0);

        $params = [
            'sub_kegiatan' => $target->sub_kegiatan_kunci,
            'kode_rekening' => '5.1.01',
            'tagging' => (string) $tagA->id,
            'q' => 'Laptop',
        ];

        $this->actingAs($this->user)->get(route('rincian.index', $params))
            ->assertOk()
            ->assertSee('Sub Target')
            ->assertViewHas('tree', function ($tree) {
                return $tree->count() === 1
                    && $tree->first()['nama'] === 'Sub Target'
                    && $tree->first()['rekening']->count() === 1
                    && $tree->first()['rekening']->first()['tagging']->count() === 1
                    && $tree->first()['rekening']->first()['tagging']->first()['nama'] === 'Prioritas Merah';
            })
            ->assertViewHas('total', fn (array $total) => $total['pagu'] === 10_000_000.0);
    }

    public function test_pagu_nol_ls_dan_npd_memakai_rumus_terpusat_dan_konsisten_dengan_form_npd(): void
    {
        $zero = $this->anggaran('Sub Pagu Nol', '5.1.00', null, 0);
        $this->assertSame(0.0, $zero->persentaseRealisasiAktual());

        $anggaran = $this->anggaran('Sub Transaksi', '5.1.03', null, 10_000_000);
        $this->npd($anggaran, 2_000_000, 'Draft NPD - PPTK');
        $this->npd($anggaran, 3_000_000, 'Selesai');
        Spm::buatLs([
            'nomor_dokumen' => '001/RINCIAN-LS/2026',
            'tanggal_dokumen' => '2026-07-21',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
        ]);

        $response = $this->actingAs($this->user)->get(route('rincian.index'));
        $response->assertOk()->assertSee('0,00 %');
        $response->assertViewHas('tree', function ($tree) {
            $transaksi = $tree->firstWhere('nama', 'Sub Transaksi')['angka'];

            return $transaksi['dana_terikat_npd'] === 5_000_000.0
                && $transaksi['realisasi_aktual'] === 4_000_000.0
                && $transaksi['sisa_tersedia'] === 4_000_000.0
                && $transaksi['persentase_realisasi'] === 40.0;
        });

        $this->actingAs($this->user)->get(route('npd.bj.create'))
            ->assertOk()
            ->assertSee('"sisa":4000000', false);
    }

    public function test_satu_spm_ls_tiga_baris_menaikkan_realisasi_ls_tiap_mata_anggaran_sesuai_barisnya_sendiri(): void
    {
        $a1 = $this->anggaran('Sub Multi A', '5.1.05.01', null, 10_000_000);
        $a2 = $this->anggaran('Sub Multi B', '5.1.05.02', null, 10_000_000);
        $a3 = $this->anggaran('Sub Multi C', '5.1.05.03', null, 10_000_000);

        Spm::buatLs([
            'nomor_dokumen' => '002/RINCIAN-LS/2026',
            'tanggal_dokumen' => '2026-07-21',
            'baris' => [
                ['master_anggaran_id' => $a1->id, 'nominal' => 1_000_000],
                ['master_anggaran_id' => $a2->id, 'nominal' => 2_000_000],
                ['master_anggaran_id' => $a3->id, 'nominal' => 3_000_000],
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('rincian.index'));
        $response->assertOk();
        $response->assertViewHas('tree', function ($tree) {
            $angka = fn ($nama) => $tree->firstWhere('nama', $nama)['angka'];

            return $angka('Sub Multi A')['realisasi_ls'] === 1_000_000.0
                && $angka('Sub Multi B')['realisasi_ls'] === 2_000_000.0
                && $angka('Sub Multi C')['realisasi_ls'] === 3_000_000.0
                && $angka('Sub Multi A')['sisa_tersedia'] === 9_000_000.0
                && $angka('Sub Multi B')['sisa_tersedia'] === 8_000_000.0
                && $angka('Sub Multi C')['sisa_tersedia'] === 7_000_000.0;
        });
    }

    public function test_backend_route_mengikuti_config_akses_menu(): void
    {
        $this->actingAs($this->user)->get(route('rincian.index'))->assertOk();

        config(['akses.menu.pptk' => array_values(array_diff(config('akses.menu.pptk'), ['rincian']))]);

        $this->actingAs($this->user)->get(route('rincian.index'))->assertForbidden();
    }

    private function anggaran(
        string $subKegiatan,
        string $kodeRekening,
        ?Tagging $tagging,
        float $pagu,
        string $uraian = 'Belanja Pengujian',
    ): MasterAnggaran {
        return MasterAnggaran::create([
            'program' => 'Program Rincian',
            'kegiatan' => 'Kegiatan Rincian',
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'uraian_rekening' => $uraian,
            'tagging_id' => $tagging?->id,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function npd(MasterAnggaran $anggaran, float $nominal, string $status): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-21',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'nilai pengujian rupiah',
            'status' => $status,
        ]);
    }
}
