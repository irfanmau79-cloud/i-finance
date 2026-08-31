<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pengiriman notifikasi WhatsApp pencairan NPD. Satu baris = satu kali
 * tombol "Buka WhatsApp" ditekan di Data NPD. Isi pesan dan nomor tujuan
 * disimpan APA ADANYA saat itu, bukan dihitung ulang, supaya riwayat tetap
 * jujur meski template atau nomor handphone pegawai berubah kemudian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kanal', 30);
            $table->string('tujuan_nama', 255);
            $table->string('tujuan_nomor', 30);
            $table->text('pesan');
            $table->timestamps();

            $table->index(['npd_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_notifikasi');
    }
};
