<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu KOMBINASI (mata anggaran, bulan) hasil explode dari
     * satu baris lebar pada file yang diupload - sudah dievaluasi
     * (baru/update/ditolak) tapi belum disimpan ke rak_bulanan. nomor_baris
     * menunjuk baris asal di file (sama untuk sampai 12 baris staging yang
     * berasal dari baris lebar yang sama) supaya preview & deteksi duplikat
     * bisa merujuk ke baris file yang mudah dipahami user.
     */
    public function up(): void
    {
        Schema::create('rak_bulanan_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('rak_bulanan_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->unsignedTinyInteger('bulan');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            // Hanya untuk mencocokkan ke master_anggaran, tidak pernah membuat baris baru di sana.
            $table->string('sub_kegiatan', 255)->nullable();
            $table->string('kode_rekening', 50)->nullable();
            $table->text('tagging_nama')->nullable();
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();

            $table->decimal('target', 18, 2)->nullable();
            $table->foreignId('rak_bulanan_id')->nullable()->constrained('rak_bulanan')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rak_bulanan_import_rows');
    }
};
