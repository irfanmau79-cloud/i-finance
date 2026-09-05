<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah lima role login Irban per unit kerja: irban1..irban4 & irban_inv.
 *
 * Berbeda dari 'inspektur_pembantu' yang generik, kelima role ini MENGIKAT
 * pemakainya ke satu unit kerja. Modul Estimasi Kebutuhan Kegiatan
 * Pengawasan memakai ikatan itu untuk mengunci Unit Kerja pada formulir dan
 * menyaring data yang boleh dilihat/dihapus - tanpa pernah menanyakan unitnya
 * ke pengguna, sehingga tidak bisa dipalsukan dari sisi browser. Pemetaan
 * role -> unit ada di App\Support\BidangOrganisasi::unitRole().
 *
 * users.role adalah ENUM di MySQL/MariaDB, jadi nilainya harus ikut
 * didaftarkan di skema - kalau tidak, penyimpanan usernya ditolak database.
 * Pola driver-aware di bawah mengikuti migrasi 2026_09_01_110000.
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
    ];

    private const ROLE_IRBAN = ['irban1', 'irban2', 'irban3', 'irban4', 'irban_inv'];

    public function up(): void
    {
        $this->ubahRoleEnum([...self::ROLE_LAMA, ...self::ROLE_IRBAN]);
    }

    /**
     * User berrole Irban dipulangkan ke 'inspektur_pembantu' - padanan
     * terdekat yang tetap ada - sebelum nilainya hilang dari enum, supaya
     * MySQL tidak mengosongkan kolom role-nya. Akunnya TIDAK dinonaktifkan:
     * yang hilang cuma ikatan unitnya, bukan haknya masuk sistem.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('users')
                ->whereIn('role', self::ROLE_IRBAN)
                ->update(['role' => 'inspektur_pembantu']);
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
