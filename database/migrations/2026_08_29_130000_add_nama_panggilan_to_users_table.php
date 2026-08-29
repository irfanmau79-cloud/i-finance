<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama panggilan dipakai untuk sapaan di bilah atas. Nullable: selama belum
 * diisi, yang dipakai tetap nama lengkap - lihat User::namaSapaan().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_panggilan', 60)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nama_panggilan');
        });
    }
};
