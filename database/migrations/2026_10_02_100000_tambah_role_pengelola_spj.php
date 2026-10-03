<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah role login "Pengelola SPJ".
 *
 * Pengelola SPJ memegang Inventarisasi SPJ secara penuh: menambah bantex/box,
 * menyunting dan memulihkan rincian arsip, serta mengunggah berkas SPJ. Role
 * lain yang membuka menu itu - selain superadmin - hanya membaca. Di luar
 * Inventarisasi SPJ aksesnya sebatas menu yang terbuka untuk semua role
 * (lihat config/akses.php).
 *
 * users.role adalah ENUM di MySQL/MariaDB, jadi nilainya harus ikut
 * didaftarkan di skema - kalau tidak, penyimpanan usernya ditolak database.
 * Pola driver-aware di bawah mengikuti migrasi 2026_09_05_090000.
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
        'pengawas',
        'layanan',
        'irban1',
        'irban2',
        'irban3',
        'irban4',
        'irban_inv',
    ];

    private const ROLE_BARU = 'pengelola_spj';

    public function up(): void
    {
        $this->ubahRoleEnum([...self::ROLE_LAMA, self::ROLE_BARU]);
    }

    /**
     * Sama seperti migrasi role Kepegawaian & Pengawas: sisa user berrole
     * Pengelola SPJ dinonaktifkan dan dipindah ke role dengan hak ubah paling
     * sempit sebelum nilainya hilang dari enum - MySQL akan mengosongkan
     * kolom role-nya kalau dibiarkan, dan akun tanpa role tidak boleh tetap
     * bisa masuk.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->where('role', self::ROLE_BARU)
                ->update(['role' => 'pengawas', 'aktif' => false]);
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
