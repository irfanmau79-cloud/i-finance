<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah role login "Kepegawaian".
 *
 * Kolom users.role adalah ENUM di MySQL/MariaDB, jadi menambah role tidak
 * cukup lewat config('akses.menu') dan User::ROLE_OPTIONS - nilainya harus
 * ikut didaftarkan di skema, kalau tidak penyimpanan user barunya ditolak
 * database. Pola driver-aware di bawah mengikuti migrasi
 * 2026_07_22_090005_migrate_bendahara_role_to_superadmin.
 *
 * audit_log.role sengaja tidak disentuh: kolom itu varchar(50), bukan enum.
 *
 * Cakupan akses Kepegawaian (lihat config/akses.php dan routes/web.php):
 * Dashboard Realisasi Anggaran, modul Surat Perintah (lihat, input, cetak -
 * ubah/hapus SP tetap milik PPTK & superadmin), dan modul Data Kepegawaian
 * secara penuh (Data Pegawai, Data Tunjangan Keluarga, Perubahan Data,
 * Monitoring Pengajuan, beserta impornya).
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
        'layanan',
    ];

    public function up(): void
    {
        $this->ubahRoleEnum(self::ROLE_BARU);
    }

    /**
     * Menurunkan migrasi ini hanya aman kalau tidak ada lagi user berrole
     * Kepegawaian - MySQL akan mengosongkan role user yang nilainya hilang
     * dari enum. Jadi sisa user-nya dinonaktifkan dan dikembalikan ke
     * Perencanaan (sama-sama role pemantau, tanpa kewenangan mengubah data)
     * lebih dulu, bukan dibiarkan jadi string kosong.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->where('role', 'kepegawaian')
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
