<?php

namespace Database\Seeders;

use App\Models\ClusterUh;
use Illuminate\Database\Seeder;

/**
 * Port 1:1 dari `var CLUSTER` di "i-finance gas/index.html".
 *
 * Sumbernya CLUSTER di index.html, BUKAN CLUSTER_UH di ClusterData.gs: hanya
 * versi index.html yang memuat dua cluster Dalam Kota (DK1/DK2), dan itulah
 * yang dipakai formulir GAS saat mengisi NPD.
 */
class ClusterUhSeeder extends Seeder
{
    /** Kota Bandung sekaligus asal perjalanan - lihat ClusterUh::ASAL_PERJALANAN. */
    private const WILAYAH_DALAM_KOTA = ClusterUh::ASAL_PERJALANAN;

    public function run(): void
    {
        $data = [
            'A' => [
                'tarif' => 200000,
                'jarak' => '4 km s.d. 30 km',
                'wilayah' => ['Kota Cimahi', 'Kabupaten Bandung Barat', 'Kabupaten Bandung'],
            ],
            'B' => [
                'tarif' => 275000,
                'jarak' => '31 km s.d. 100 km',
                'wilayah' => [
                    'Kabupaten Sumedang', 'Kabupaten Subang', 'Kabupaten Garut', 'Kabupaten Cianjur',
                    'Kabupaten Purwakarta', 'Kabupaten Majalengka', 'Kota Sukabumi',
                ],
            ],
            'C' => [
                'tarif' => 350000,
                'jarak' => '101 km s.d. 150 km',
                'wilayah' => [
                    'Kota Tasikmalaya', 'Kabupaten Karawang', 'Kabupaten Bekasi', 'Kabupaten Ciamis',
                    'Kabupaten Tasikmalaya', 'Kota Bogor', 'Kota Bekasi', 'Kota Banjar', 'Kabupaten Bogor',
                ],
            ],
            'D' => [
                'tarif' => 430000,
                'jarak' => '> 150 km',
                'wilayah' => [
                    'Kabupaten Sukabumi', 'Kota Depok', 'Kabupaten Kuningan', 'Kabupaten Indramayu',
                    'Kota Cirebon', 'Kabupaten Cirebon', 'Kabupaten Pangandaran',
                ],
            ],
            // Dalam Kota Bandung: asal = tujuan, yang membedakan tarif hanya
            // lama perjalanan. Tarifnya sama dengan uang harian dalam kota di
            // config/kebutuhan.php (standar biaya yang sama).
            'DK1' => [
                'tarif' => 100000,
                'jarak' => 'Dalam Kota (< 8 Jam)',
                'wilayah' => [self::WILAYAH_DALAM_KOTA],
            ],
            'DK2' => [
                'tarif' => 170000,
                'jarak' => 'Dalam Kota (> 8 Jam)',
                'wilayah' => [self::WILAYAH_DALAM_KOTA],
            ],
            'LP' => [
                'tarif' => 0,
                'jarak' => 'Luar Provinsi',
                'wilayah' => [],
            ],
        ];

        foreach ($data as $kode => $row) {
            $cluster = ClusterUh::updateOrCreate(
                ['kode' => $kode],
                ['tarif' => $row['tarif'], 'jarak' => $row['jarak'], 'aktif' => true]
            );

            foreach ($row['wilayah'] as $namaWilayah) {
                $cluster->wilayah()->firstOrCreate(['nama_wilayah' => $namaWilayah]);
            }
        }
    }
}
