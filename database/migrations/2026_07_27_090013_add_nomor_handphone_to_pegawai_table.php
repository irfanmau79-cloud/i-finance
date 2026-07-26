<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom baru khusus untuk halaman Data Pegawai (Setting) - TIDAK bagian dari
 * Import/Export/Template Pegawai di Manajemen Data, hanya bisa diisi lewat
 * halaman Data Pegawai oleh superadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nomor_handphone', 30)->nullable()->after('rekening');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('nomor_handphone');
        });
    }
};
