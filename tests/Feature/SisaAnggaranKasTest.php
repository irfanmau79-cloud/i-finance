<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\RakBulanan;
use App\Models\Tagging;
use App\Models\User;
use App\Services\AnggaranRealisasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sisa Anggaran Kas pada formulir Pembuatan NPD.
 *
 * Sisa Anggaran biasa dihitung per TAGGING terhadap pagu. Sisa Anggaran Kas
 * dihitung per KODE REKENING dalam satu Sub Kegiatan terhadap RAK kumulatif
 * s.d. bulan berjalan, dan pemakaiannya mencakup SEMUA tagging pada kode
 * rekening itu.
 */
class SisaAnggaranKasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bulan berjalan = Agustus 2026, jadi RAK kumulatifnya Januari-Agustus.
        Carbon::setTestNow('2026-08-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function anggaran(string $kode, ?string $tagging, float $pagu = 100_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Kas',
            'kegiatan' => 'Kegiatan Uji Kas',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Kas',
            'kode_rekening' => $kode,
            'tagging_id' => $tagging ? Tagging::firstOrCreate(['nama' => $tagging], ['aktif' => true])->id : null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    /** RAK Januari-Desember, tiap bulan sebesar $perBulan. */
    private function rakSetahun(MasterAnggaran $anggaran, float $perBulan): void
    {
        foreach (range(1, 12) as $bulan) {
            RakBulanan::create([
                'sub_kegiatan' => $anggaran->sub_kegiatan_lengkap,
                'kode_rekening' => $anggaran->kode_rekening_bersih,
                'tahun' => 2026,
                'bulan' => $bulan,
                'target' => $perBulan,
            ]);
        }
    }

    private function npd(MasterAnggaran $anggaran, float $nominal, string $status = 'Draft NPD - PPTK'): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 8,
            'tahun' => 2026,
            'tanggal_npd' => '2026-08-10',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'uji',
            'status' => $status,
        ]);
    }

    private function superadmin(): User
    {
        return User::create([
            'username' => 'kas-superadmin',
            'nama' => 'Uji Kas',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia',
        ]);
    }

    public function test_sisa_kas_adalah_rak_kumulatif_dikurangi_pemakaian_seluruh_tagging(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', 'Tagging A');
        $b = $this->anggaran('5.1.02.01.01.0001', 'Tagging B');
        $this->rakSetahun($a, 10_000_000);

        $this->npd($a, 5_000_000);                 // draft tetap mengikat
        $this->npd($b, 3_000_000, 'Selesai');
        $this->npd($b, 9_000_000, 'Dibatalkan');   // batal tidak dihitung

        $hasil = app(AnggaranRealisasiService::class)
            ->sisaAnggaranKas(MasterAnggaran::query()->get());

        // RAK Jan-Agu = 8 x 10 juta; terpakai 5 + 3 juta lintas tagging.
        foreach ([$a, $b] as $master) {
            $this->assertEqualsWithDelta(80_000_000, $hasil[$master->id]['rak'], 0.01);
            $this->assertEqualsWithDelta(8_000_000, $hasil[$master->id]['terpakai'], 0.01);
            $this->assertEqualsWithDelta(72_000_000, $hasil[$master->id]['sisa'], 0.01);
        }

        // Sisa Anggaran per tagging TIDAK ikut berubah maknanya.
        $this->assertEqualsWithDelta(95_000_000, $a->fresh()->sisaTersedia(), 0.01);
        $this->assertEqualsWithDelta(97_000_000, $b->fresh()->sisaTersedia(), 0.01);
    }

    public function test_pemakaian_dihitung_dari_semua_tagging_walau_daftarnya_dipersempit(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', 'Tagging A');
        $b = $this->anggaran('5.1.02.01.01.0001', 'Tagging B');
        $this->rakSetahun($a, 10_000_000);
        $this->npd($b, 3_000_000);

        // Hanya Tagging A yang dikirim - pemakaian Tagging B tetap terhitung.
        $hasil = app(AnggaranRealisasiService::class)
            ->sisaAnggaranKas(MasterAnggaran::query()->whereKey($a->id)->get());

        $this->assertSame([$a->id], array_keys($hasil));
        $this->assertEqualsWithDelta(77_000_000, $hasil[$a->id]['sisa'], 0.01);
    }

    public function test_kode_rekening_lain_tidak_saling_mengurangi_dan_bisa_minus(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', null);
        $lain = $this->anggaran('5.1.02.01.01.0002', null);
        $this->rakSetahun($a, 1_000_000);
        $this->rakSetahun($lain, 2_000_000);
        $this->npd($a, 10_000_000);

        $hasil = app(AnggaranRealisasiService::class)
            ->sisaAnggaranKas(MasterAnggaran::query()->get());

        // 8 juta RAK - 10 juta terpakai: minus apa adanya, tidak dijepit ke nol.
        $this->assertEqualsWithDelta(-2_000_000, $hasil[$a->id]['sisa'], 0.01);
        $this->assertEqualsWithDelta(16_000_000, $hasil[$lain->id]['sisa'], 0.01);
    }

    public function test_tanpa_rak_hasilnya_null_bukan_perkiraan_dari_pagu(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', null);
        $this->npd($a, 1_000_000);

        $hasil = app(AnggaranRealisasiService::class)
            ->sisaAnggaranKas(MasterAnggaran::query()->get());

        $this->assertNull($hasil[$a->id]['rak']);
        $this->assertNull($hasil[$a->id]['sisa']);
        $this->assertEqualsWithDelta(1_000_000, $hasil[$a->id]['terpakai'], 0.01);
    }

    public function test_npd_yang_sedang_disunting_tidak_menghitung_dirinya_sendiri(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', null);
        $this->rakSetahun($a, 10_000_000);
        $this->npd($a, 2_000_000);
        $disunting = $this->npd($a, 5_000_000);

        $service = app(AnggaranRealisasiService::class);
        $masters = MasterAnggaran::query()->get();

        $this->assertEqualsWithDelta(73_000_000, $service->sisaAnggaranKas($masters)[$a->id]['sisa'], 0.01);
        $this->assertEqualsWithDelta(78_000_000, $service->sisaAnggaranKas($masters, $disunting)[$a->id]['sisa'], 0.01);
    }

    public function test_formulir_keempat_jenis_npd_memuat_sisa_anggaran_kas(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', 'Tagging A');
        $tanpaRak = $this->anggaran('5.1.02.01.01.0002', null);
        $this->rakSetahun($a, 10_000_000);
        $this->npd($a, 5_000_000);

        $user = $this->superadmin();

        foreach (['bj', 'pd', 'ns', 'kd'] as $jenis) {
            $halaman = $this->actingAs($user)->get(route("npd.{$jenis}.create"))->assertOk();

            $halaman->assertSee('Sisa Anggaran Kas')
                ->assertSee('RAK s.d. Agustus 2026')
                ->assertSee('id="ma-sisa-kas"', false)
                ->assertSee('window.NpdSisaKas.tampil(m)', false);

            $data = collect($this->dataMasterAnggaran($halaman->getContent()))->keyBy('id');

            $this->assertEqualsWithDelta(75_000_000, $data[$a->id]['sisa_kas'], 0.01, "Formulir {$jenis}.");
            $this->assertEqualsWithDelta(80_000_000, $data[$a->id]['rak_kas'], 0.01, "Formulir {$jenis}.");
            $this->assertNull($data[$tanpaRak->id]['sisa_kas'], "Formulir {$jenis}: tanpa RAK harus null.");
            // Sisa Anggaran per tagging tetap seperti semula.
            $this->assertEqualsWithDelta(95_000_000, $data[$a->id]['sisa'], 0.01, "Formulir {$jenis}.");
        }
    }

    /**
     * Sisa kas hanya INFORMASI: NPD yang melebihinya tetap tersimpan selama
     * masih di bawah Sisa Anggaran (sisa_tersedia).
     */
    public function test_sisa_kas_yang_habis_tidak_menolak_penyimpanan_npd(): void
    {
        $a = $this->anggaran('5.1.02.01.01.0001', null);
        $this->rakSetahun($a, 100_000); // RAK s.d. Agustus hanya 800 ribu

        $this->actingAs($this->superadmin())->post(route('npd.bj.store'), [
            'master_anggaran_id' => $a->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-08-10',
            'bulan' => 8,
            'tahun' => 2026,
            'penerima' => [[
                'nama' => 'CV Uji Kas',
                'bruto' => 5_000_000,
                'keterangan' => 'Belanja uji',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Npd::count());
    }

    /**
     * Ambil larik data mata anggaran yang ditanam formulir ke dalam skripnya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dataMasterAnggaran(string $html): array
    {
        $this->assertSame(1, preg_match('/const masterAnggaranData = (\[.*?\]);\r?\n/s', $html, $cocok), 'Data mata anggaran tidak ditemukan di halaman.');

        return json_decode($cocok[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
