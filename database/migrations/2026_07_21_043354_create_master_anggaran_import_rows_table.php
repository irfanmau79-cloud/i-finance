<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu baris data pada file Excel yang diupload, sudah
     * dievaluasi (baru/update/ditolak) tapi belum disimpan ke master_anggaran.
     * tagging_nama sengaja TEXT (bukan varchar 255 seperti taggings.nama)
     * supaya validasi panjang nama tagging ditegakkan di titik commit
     * (Tagging::firstOrCreate), bukan diam-diam terpotong saat staging.
     */
    public function up(): void
    {
        Schema::create('master_anggaran_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('master_anggaran_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('program', 255)->nullable();
            $table->string('kegiatan', 255)->nullable();
            $table->string('sub_kegiatan', 255)->nullable();
            $table->string('kode_rekening', 50)->nullable();
            $table->string('uraian_rekening', 255)->nullable();
            $table->text('tagging_nama')->nullable();
            $table->boolean('aktif')->default(true);

            $table->decimal('pagu_baru', 18, 2)->nullable();
            $table->decimal('pagu_lama', 18, 2)->nullable();

            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_anggaran_import_rows');
    }
};
