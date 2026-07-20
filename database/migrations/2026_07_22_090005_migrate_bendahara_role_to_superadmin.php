<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_LAMA = [
        'bendahara',
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
        'layanan',
    ];

    public function up(): void
    {
        $this->ubahRoleEnum(array_values(array_unique(array_merge(self::ROLE_LAMA, self::ROLE_BARU))));

        DB::transaction(function () {
            DB::table('users')->where('role', 'bendahara')->update(['role' => 'superadmin']);
            DB::table('audit_log')->where('role', 'bendahara')->update(['role' => 'superadmin']);
        });

        $this->ubahRoleEnum(self::ROLE_BARU);
    }

    public function down(): void
    {
        $this->ubahRoleEnum(array_values(array_unique(array_merge(self::ROLE_LAMA, self::ROLE_BARU))));

        DB::transaction(function () {
            DB::table('users')->whereIn('role', ['superadmin', 'bendahara_pengeluaran'])->update(['role' => 'bendahara']);
            DB::table('audit_log')->whereIn('role', ['superadmin', 'bendahara_pengeluaran'])->update(['role' => 'bendahara']);
        });

        $this->ubahRoleEnum(self::ROLE_LAMA);
    }

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
