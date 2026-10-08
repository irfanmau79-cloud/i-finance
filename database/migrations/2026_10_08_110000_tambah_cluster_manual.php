<?php

use App\Models\ClusterUh;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KODE = 'MN';

    /**
     * Cluster baru "Manual" (MN) pada tarif uang harian perjalanan dinas.
     * Seperti Luar Provinsi, Kab/Kota tujuan dan tarifnya diketik manual per
     * NPD - lihat ClusterUh::KODE_MANUAL.
     *
     * Dua hal dikerjakan sekaligus supaya basis data yang sudah berjalan
     * langsung bisa memakainya tanpa menjalankan seeder:
     *   1. daftar ENUM npd_tim_paket.cluster diperluas mengikuti ClusterUh::KODE;
     *   2. baris cluster-nya ditambahkan ke cluster_uh (tanpa daftar wilayah).
     */
    public function up(): void
    {
        $this->ubahClusterEnum(ClusterUh::KODE);

        if (! DB::table('cluster_uh')->where('kode', self::KODE)->exists()) {
            DB::table('cluster_uh')->insert([
                'kode' => self::KODE,
                'tarif' => 0,
                'jarak' => 'Manual',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Paket cluster Manual tidak punya padanan pada daftar lama;
        // menurunkannya ke cluster lain akan mengubah arti dokumen yang sudah
        // ditandatangani.
        $jumlah = DB::table('npd_tim_paket')->where('cluster', self::KODE)->count();

        if ($jumlah > 0) {
            throw new RuntimeException(
                "Masih ada {$jumlah} paket perjalanan dengan cluster Manual (".self::KODE.'). Pindahkan atau hapus dulu barisnya sebelum rollback.'
            );
        }

        DB::table('cluster_uh')->where('kode', self::KODE)->delete();

        $this->ubahClusterEnum(array_values(array_diff(ClusterUh::KODE, [self::KODE])));
    }

    /** Pola yang sama dengan 2026_10_01_090000_tambah_cluster_dalam_kota_pada_npd_tim_paket. */
    private function ubahClusterEnum(array $nilai): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $enum = collect($nilai)
                ->map(fn (string $kode) => DB::getPdo()->quote($kode))
                ->implode(', ');

            DB::statement("ALTER TABLE npd_tim_paket MODIFY cluster ENUM({$enum}) NOT NULL");

            return;
        }

        Schema::table('npd_tim_paket', function (Blueprint $table) use ($nilai) {
            $table->enum('cluster', $nilai)->change();
        });
    }
};
