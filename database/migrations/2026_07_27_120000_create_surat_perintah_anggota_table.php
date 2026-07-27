<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_perintah_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_perintah_id')->constrained('surat_perintah')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('jabatan_sp', 50);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['surat_perintah_id', 'pegawai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_perintah_anggota');
    }
};
