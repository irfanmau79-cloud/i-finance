<?php

namespace Tests\Feature;

use App\Models\ClusterUh;
use App\Models\ClusterWilayah;
use Database\Seeders\ClusterUhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data cluster uang harian perjalanan dinas (SHS Jabar 2026).
 *
 * Isinya harus tetap sama persis dengan `var CLUSTER` di
 * "i-finance gas/index.html" — tarif inilah yang dipakai menghitung uang
 * harian, jadi satu angka meleset berarti nominal NPD Perjalanan Dinas ikut
 * meleset. Test ini menyalin angkanya secara mandiri (bukan membaca seeder)
 * supaya perubahan yang tidak disengaja pada seeder ketahuan.
 *
 * Patokannya index.html, BUKAN CLUSTER_UH di ClusterData.gs: dua cluster
 * Dalam Kota (DK1/DK2) hanya ada di index.html, dan itulah yang dipakai
 * formulir GAS saat mengisi NPD.
 *
 * Asal perjalanan Kota Bandung. Cluster A-D memuat 26 kabupaten/kota tujuan
 * di luar Kota Bandung; Kota Bandung sendiri masuk lewat DK1/DK2 (perjalanan
 * dalam kota, asal = tujuan), sehingga seluruh 27 kabupaten/kota Jawa Barat
 * terwakili.
 */
class ClusterUhSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Baris cluster_wilayah: 26 tujuan A-D + Kota Bandung pada DK1 dan DK2. */
    private const JUMLAH_BARIS_WILAYAH = 28;

    /** @return array<string, array{tarif: int, jarak: string, wilayah: array<int, string>}> */
    private function dataGas(): array
    {
        return [
            'A' => [
                'tarif' => 200_000,
                'jarak' => '4 km s.d. 30 km',
                'wilayah' => ['Kota Cimahi', 'Kabupaten Bandung Barat', 'Kabupaten Bandung'],
            ],
            'B' => [
                'tarif' => 275_000,
                'jarak' => '31 km s.d. 100 km',
                'wilayah' => [
                    'Kabupaten Sumedang', 'Kabupaten Subang', 'Kabupaten Garut',
                    'Kabupaten Cianjur', 'Kabupaten Purwakarta', 'Kabupaten Majalengka',
                    'Kota Sukabumi',
                ],
            ],
            'C' => [
                'tarif' => 350_000,
                'jarak' => '101 km s.d. 150 km',
                'wilayah' => [
                    'Kota Tasikmalaya', 'Kabupaten Karawang', 'Kabupaten Bekasi',
                    'Kabupaten Ciamis', 'Kabupaten Tasikmalaya', 'Kota Bogor',
                    'Kota Bekasi', 'Kota Banjar', 'Kabupaten Bogor',
                ],
            ],
            'D' => [
                'tarif' => 430_000,
                'jarak' => '> 150 km',
                'wilayah' => [
                    'Kabupaten Sukabumi', 'Kota Depok', 'Kabupaten Kuningan',
                    'Kabupaten Indramayu', 'Kota Cirebon', 'Kabupaten Cirebon',
                    'Kabupaten Pangandaran',
                ],
            ],
            // Dalam Kota Bandung: tarifnya tetap (bukan manual), yang
            // membedakan hanya lama perjalanan di bawah/di atas 8 jam.
            'DK1' => [
                'tarif' => 100_000,
                'jarak' => 'Dalam Kota (< 8 Jam)',
                'wilayah' => ['Kota Bandung'],
            ],
            'DK2' => [
                'tarif' => 170_000,
                'jarak' => 'Dalam Kota (> 8 Jam)',
                'wilayah' => ['Kota Bandung'],
            ],
            // Luar Provinsi: tarif dan kotanya diketik manual di formulir.
            'LP' => ['tarif' => 0, 'jarak' => 'Luar Provinsi', 'wilayah' => []],
        ];
    }

    public function test_seeder_menghasilkan_data_cluster_yang_sama_dengan_gas(): void
    {
        $this->seed(ClusterUhSeeder::class);

        $gas = $this->dataGas();

        $this->assertSame(count($gas), ClusterUh::count(), 'Jumlah cluster berbeda dari CLUSTER di index.html.');

        foreach ($gas as $kode => $harusnya) {
            $cluster = ClusterUh::where('kode', $kode)->first();

            $this->assertNotNull($cluster, "Cluster {$kode} tidak ada.");
            $this->assertEquals($harusnya['tarif'], (float) $cluster->tarif, "Tarif cluster {$kode} berbeda.");
            $this->assertSame($harusnya['jarak'], $cluster->jarak, "Keterangan jarak cluster {$kode} berbeda.");
            $this->assertTrue((bool) $cluster->aktif, "Cluster {$kode} harus aktif.");

            $this->assertSame(
                $harusnya['wilayah'],
                $cluster->wilayah()->pluck('nama_wilayah')->all(),
                "Daftar wilayah cluster {$kode} berbeda dari CLUSTER di index.html."
            );
        }

        $this->assertSame(self::JUMLAH_BARIS_WILAYAH, ClusterWilayah::count());

        // 26 tujuan A-D + Kota Bandung = 27 kabupaten/kota Jawa Barat. Kota
        // Bandung memang terdaftar dua kali (DK1 & DK2), jadi yang diperiksa
        // adalah tidak ada wilayah yang dobel DI DALAM satu cluster.
        $this->assertSame(27, ClusterWilayah::distinct()->count('nama_wilayah'));

        foreach (ClusterUh::with('wilayah')->get() as $cluster) {
            $nama = $cluster->wilayah->pluck('nama_wilayah')->all();

            $this->assertSame(
                array_values(array_unique($nama)),
                $nama,
                "Ada wilayah yang terdaftar dobel di cluster {$cluster->kode}."
            );
        }
    }

    public function test_daftar_kode_pada_model_sama_dengan_isi_seeder(): void
    {
        $this->seed(ClusterUhSeeder::class);

        // ClusterUh::KODE dipakai memvalidasi isian NPD Perjalanan Dinas, jadi
        // ia tidak boleh menyimpang dari cluster yang benar-benar ada.
        $this->assertSame(ClusterUh::KODE, array_keys($this->dataGas()));
        $this->assertSame(ClusterUh::KODE, ClusterUh::orderBy('kode')->pluck('kode')->all());
    }

    public function test_label_pilihan_sama_bunyinya_dengan_dropdown_gas(): void
    {
        $this->seed(ClusterUhSeeder::class);

        $harusnya = [
            'A' => 'A (4 km s.d. 30 km)',
            'B' => 'B (31 km s.d. 100 km)',
            'C' => 'C (101 km s.d. 150 km)',
            'D' => 'D (> 150 km)',
            'DK1' => 'Dalam Kota (< 8 Jam)',
            'DK2' => 'Dalam Kota (> 8 Jam)',
            'LP' => 'Luar Provinsi',
        ];

        foreach ($harusnya as $kode => $label) {
            $this->assertSame($label, ClusterUh::where('kode', $kode)->first()->labelPilihan());
        }
    }

    public function test_seeder_boleh_dijalankan_ulang_tanpa_menggandakan_data(): void
    {
        $this->seed(ClusterUhSeeder::class);
        $this->seed(ClusterUhSeeder::class);

        $this->assertSame(count(ClusterUh::KODE), ClusterUh::count());
        $this->assertSame(self::JUMLAH_BARIS_WILAYAH, ClusterWilayah::count());
    }
}
