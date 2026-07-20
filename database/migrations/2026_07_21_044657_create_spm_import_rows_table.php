<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu baris data pada file Excel yang diupload, sudah
     * dievaluasi (baru/update/ditolak) tapi belum disimpan ke spm.
     * Kolom sub_kegiatan/kode_rekening/tagging_nama/master_anggaran_id dan
     * sisa_sebelum/sisa_sesudah hanya relevan untuk jenis LS (NULL untuk
     * UP/GU, sama seperti master_anggaran_id di tabel spm sendiri).
     */
    public function up(): void
    {
        Schema::create('spm_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('spm_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('nomor_dokumen', 100)->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->string('nomor_sp2d', 100)->nullable();
            $table->date('tanggal_sp2d')->nullable();

            // Hanya untuk LS - dipakai mencocokkan ke master_anggaran, tidak pernah membuat baris baru.
            $table->string('sub_kegiatan', 255)->nullable();
            $table->string('kode_rekening', 50)->nullable();
            $table->text('tagging_nama')->nullable();
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();

            $table->decimal('nominal', 18, 2)->nullable();
            $table->decimal('ppn', 18, 2)->nullable();
            $table->decimal('pph1', 18, 2)->nullable();
            $table->string('jenis_pph1', 50)->nullable();
            $table->decimal('pph2', 18, 2)->nullable();
            $table->string('jenis_pph2', 50)->nullable();
            $table->string('penerima', 255)->nullable();
            $table->text('uraian')->nullable();

            // Dampak anggaran (khusus LS) - sisa tersedia berjalan dalam
            // batch ini SEBELUM dan SESUDAH baris ini diterapkan, dihitung
            // saat parse/konfirmasi untuk ditampilkan di preview.
            $table->decimal('sisa_sebelum', 18, 2)->nullable();
            $table->decimal('sisa_sesudah', 18, 2)->nullable();

            $table->foreignId('spm_id')->nullable()->constrained('spm')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_import_rows');
    }
};
