<?php

use App\Models\ClusterUh;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua cluster Dalam Kota (DK1 < 8 jam, DK2 > 8 jam) menyusul masuk.
     *
     * Kolom cluster dibuat sebagai ENUM ['A','B','C','D','LP'] saat tabel ini
     * lahir, mengikuti CLUSTER_UH di ClusterData.gs. Ternyata formulir GAS
     * memakai `var CLUSTER` di index.html yang memuat dua cluster Dalam Kota
     * juga, sehingga perjalanan dalam Kota Bandung tidak punya tempat di sini
     * dan cuma bisa dipaksa lewat LP (Luar Provinsi) - salah arti.
     *
     * Daftar nilainya diambil dari ClusterUh::KODE supaya constraint basis
     * data tidak bisa menyimpang dari daftar yang dipakai validasi.
     */
    public function up(): void
    {
        $this->ubahClusterEnum(ClusterUh::KODE);
    }

    public function down(): void
    {
        $dalamKota = ClusterUh::KODE_DALAM_KOTA;

        // Paket Dalam Kota tidak punya padanan pada daftar lama. Menurunkannya
        // ke LP akan mengubah arti dokumen yang sudah ditandatangani (Luar
        // Provinsi), jadi rollback ditolak selama masih ada barisnya - biarkan
        // Irfan memutuskan sendiri nasib baris itu.
        $jumlah = DB::table('npd_tim_paket')->whereIn('cluster', $dalamKota)->count();

        if ($jumlah > 0) {
            throw new RuntimeException(
                "Masih ada {$jumlah} paket perjalanan dengan cluster Dalam Kota ("
                .implode('/', $dalamKota).'). Pindahkan atau hapus dulu barisnya sebelum rollback.'
            );
        }

        $this->ubahClusterEnum(array_values(array_diff(ClusterUh::KODE, $dalamKota)));
    }

    /**
     * MySQL/MariaDB membutuhkan MODIFY untuk mengganti daftar ENUM. Driver
     * lain (terutama SQLite in-memory pada test) harus melalui schema builder
     * agar Laravel membangun ulang constraint kolom secara portabel. Pola ini
     * sama dengan 2026_07_18_150000_add_diterima_pptk_status_to_surat_perintah.
     */
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
