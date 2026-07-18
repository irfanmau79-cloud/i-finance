<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('id');
            $table->string('nama', 150)->after('username');
            $table->enum('role', [
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
            ])->after('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'nama', 'role']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }
};
