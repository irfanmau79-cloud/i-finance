<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah role login "Pengawas".
 *
 * Pengawas melihat hampir seluas superadmin tetapi TIDAK boleh mengubah apa
 * pun: tanpa pembuatan/persetujuan/verifikasi NPD, tanpa Manajemen Data
 * beserta seluruh impornya, tanpa Manajemen Users, dan tanpa Pelimpahan.
 * Cakupan lengkapnya ada di config('akses.menu') dan pemisahan rute baca
 * versus tulisnya di routes/web.php.
 *
 * users.role adalah ENUM di MySQL/MariaDB, jadi nilainya harus ikut
 * didaftarkan di skema - kalau tidak, penyimpanan usernya ditolak database.
 * Pola driver-aware di bawah mengikuti migrasi
 * 2026_07_22_090005_migrate_bendahara_role_to_superadmin.
 */
return new class extends Migration
{
    private const ROLE_LAMA = [
        'superadmin',
        'bendahara_pengeluaran',
        'pptk',
        'bpp',
        'verifikator',
        'sekretaris',
        'kasubbag',
        'inspektur',
        'inspektur_pembantu',
        'perencanaan',
        'kepegawaian',
        'layanan',
    ];

    private const ROLE_BARU = [
        'superadmin',
        'bendahara_pengeluaran',
        'pptk',
        'bpp',
        'verifikator',
        'sekretaris',
        'kasubbag',
        'inspektur',
        'inspektur_pembantu',
        'perencanaan',
        'kepegawaian',
        'pengawas',
        'layanan',
    ];

    public function up(): void
    {
        $this->ubahRoleEnum(self::ROLE_BARU);
    }

    /**
     * Sama seperti migrasi role Kepegawaian: sisa user berrole Pengawas
     * dipindahkan lebih dulu ke Perencanaan dan dinonaktifkan, supaya MySQL
     * tidak mengosongkan kolom role-nya saat nilai itu hilang dari enum.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->where('role', 'pengawas')
                ->update(['role' => 'perencanaan', 'aktif' => false]);
        });

        $this->ubahRoleEnum(self::ROLE_LAMA);
    }

    /** @param  array<int, string>  $roles */
    private function ubahRoleEnum(array $roles): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $enum = collect($roles)
                ->map(fn (string $role) => DB::getPdo()->quote($role))
                ->implode(', ');

            DB::statement("ALTER TABLE users MODIFY role ENUM({$enum}) NOT NULL");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->change();
        });
    }
};
