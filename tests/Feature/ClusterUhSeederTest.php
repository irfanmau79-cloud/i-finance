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
 * Isinya harus tetap sama persis dengan CLUSTER_UH di
 * "i-finance gas/ClusterData.gs" — tarif inilah yang dipakai menghitung uang
 * harian, jadi satu angka meleset berarti nominal NPD Perjalanan Dinas ikut
 * meleset. Test ini menyalin angkanya secara mandiri (bukan membaca seeder)
 * supaya perubahan yang tidak disengaja pada seeder ketahuan.
 *
 * Asal perjalanan Kota Bandung, jadi 26 wilayah di sini + Kota Bandung =
 * seluruh 27 kabupaten/kota Jawa Barat.
 */
class ClusterUhSeederTest extends TestCase
{
    use RefreshDatabase;

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
            // Luar Provinsi: tarif dan kotanya diketik manual di formulir.
            'LP' => ['tarif' => 0, 'jarak' => 'Luar Provinsi', 'wilayah' => []],
        ];
    }

    public function test_seeder_menghasilkan_data_cluster_yang_sama_dengan_gas(): void
    {
        $this->seed(ClusterUhSeeder::class);

        $gas = $this->dataGas();

        $this->assertSame(count($gas), ClusterUh::count(), 'Jumlah cluster berbeda dari ClusterData.gs.');

        foreach ($gas as $kode => $harusnya) {
            $cluster = ClusterUh::where('kode', $kode)->first();

            $this->assertNotNull($cluster, "Cluster {$kode} tidak ada.");
            $this->assertEquals($harusnya['tarif'], (float) $cluster->tarif, "Tarif cluster {$kode} berbeda.");
            $this->assertSame($harusnya['jarak'], $cluster->jarak, "Keterangan jarak cluster {$kode} berbeda.");
            $this->assertTrue((bool) $cluster->aktif, "Cluster {$kode} harus aktif.");

            $this->assertSame(
                $harusnya['wilayah'],
                $cluster->wilayah()->pluck('nama_wilayah')->all(),
                "Daftar wilayah cluster {$kode} berbeda dari ClusterData.gs."
            );
        }

        // 26 tujuan + Kota Bandung sebagai asal = 27 kab/kota Jawa Barat.
        $this->assertSame(26, ClusterWilayah::count());
        $this->assertSame(26, ClusterWilayah::distinct()->count('nama_wilayah'), 'Ada wilayah yang terdaftar dobel.');
    }

    public function test_seeder_boleh_dijalankan_ulang_tanpa_menggandakan_data(): void
    {
        $this->seed(ClusterUhSeeder::class);
        $this->seed(ClusterUhSeeder::class);

        $this->assertSame(5, ClusterUh::count());
        $this->assertSame(26, ClusterWilayah::count());
    }
}
