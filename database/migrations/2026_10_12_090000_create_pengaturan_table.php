<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan aplikasi yang bisa diubah dari dalam aplikasi, satu baris per
     * kunci. Yang pertama memakainya: kata sandi bersama Pengguna Layanan,
     * yang dulu hanya bisa diganti lewat .env di server (lihat
     * App\Support\SandiLayanan).
     */
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
