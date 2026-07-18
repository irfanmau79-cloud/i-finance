<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penyempurnaan atas sheet Users di gas-lama/CodeAuth.gs (cuma
     * username/password/role/nama): tautan ke pegawai supaya jabatan/
     * pangkat/bidang bisa ditarik otomatis, flag aktif untuk nonaktifkan
     * tanpa hapus, dan jejak login terakhir. Data user yang sudah ada
     * tidak disentuh — kolom baru nullable/default aman.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->unique()->after('username');
            $table->foreignId('pegawai_id')->nullable()->after('nip')->constrained('pegawai')->nullOnDelete();
            $table->boolean('aktif')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pegawai_id');
            $table->dropColumn(['nip', 'aktif', 'last_login_at']);
        });
    }
};
