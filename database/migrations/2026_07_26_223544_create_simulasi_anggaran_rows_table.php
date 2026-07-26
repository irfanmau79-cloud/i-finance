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
        Schema::create('simulasi_anggaran_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulasi_anggaran_id')->constrained('simulasi_anggaran')->cascadeOnDelete();
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();
            // Snapshot semua label tampilan di sini (bukan join live ke master_anggaran)
            // supaya simulasi yang sudah tersimpan tetap konsisten walau baris
            // master_anggaran aslinya kelak berubah/dihapus.
            $table->string('program', 255);
            $table->string('kegiatan', 255);
            $table->string('sub_kegiatan', 255);
            $table->string('sub_kegiatan_kunci', 255);
            $table->string('kode_rekening', 50);
            $table->string('uraian_rekening', 255)->nullable();
            $table->string('tagging_nama', 255)->nullable();
            $table->decimal('pagu_eksisting', 18, 2);
            $table->decimal('pagu_simulasi', 18, 2);
            $table->decimal('selisih', 18, 2)->default(0);
            $table->timestamps();

            $table->index('simulasi_anggaran_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulasi_anggaran_rows');
    }
};
