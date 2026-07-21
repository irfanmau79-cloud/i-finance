<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_historis_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file');
            $table->char('file_hash', 64);
            $table->string('format_sumber', 50)->default('npd_historis_v1');
            $table->enum('status', ['staged', 'committed', 'failed'])->default('staged');
            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_valid')->default(0);
            $table->unsignedInteger('jumlah_warning')->default(0);
            $table->unsignedInteger('jumlah_error')->default(0);
            $table->unsignedInteger('jumlah_duplikat')->default(0);
            $table->unsignedInteger('jumlah_berhasil')->default(0);
            $table->unsignedInteger('jumlah_dilewati')->default(0);
            $table->decimal('total_nominal', 20, 2)->default(0);
            $table->decimal('total_ppn', 20, 2)->default(0);
            $table->decimal('total_pph', 20, 2)->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('file_hash');
        });

        Schema::create('npd_historis_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('npd_historis_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('hasil', ['valid', 'warning', 'error', 'duplicate']);
            $table->json('pesan')->nullable();
            $table->char('identity_hash', 64)->nullable();
            $table->date('tanggal_npd')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->string('nomor_npd', 150)->nullable();
            $table->string('jenis_input', 100)->nullable();
            $table->string('jenis_kode', 5)->nullable();
            $table->string('sub_kegiatan')->nullable();
            $table->string('sub_kegiatan_kunci')->nullable();
            $table->string('program')->nullable();
            $table->string('kegiatan')->nullable();
            $table->string('kode_rekening', 50)->nullable();
            $table->string('tagging_nama')->nullable();
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();
            $table->foreignId('tagging_id')->nullable()->constrained('taggings')->nullOnDelete();
            $table->foreignId('rak_bulanan_id')->nullable()->constrained('rak_bulanan')->nullOnDelete();
            $table->string('penerima')->nullable();
            $table->string('rekening_penerima')->nullable();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendor')->nullOnDelete();
            $table->boolean('penerima_manual')->default(false);
            $table->decimal('nominal_bruto', 18, 2)->nullable();
            $table->text('uraian')->nullable();
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('pph1', 18, 2)->default(0);
            $table->string('jenis_pph1', 50)->nullable();
            $table->decimal('pph2', 18, 2)->default(0);
            $table->string('jenis_pph2', 50)->nullable();
            $table->string('status_input', 50)->nullable();
            $table->string('status_target', 50)->nullable();
            $table->decimal('pagu', 20, 2)->nullable();
            $table->decimal('rak_bulan', 20, 2)->nullable();
            $table->decimal('realisasi_sebelum', 20, 2)->nullable();
            $table->decimal('realisasi_proyeksi', 20, 2)->nullable();
            $table->decimal('sisa_proyeksi', 20, 2)->nullable();
            $table->string('mapping_status', 50)->nullable();
            $table->foreignId('npd_id')->nullable()->constrained('npd')->nullOnDelete();
            $table->timestamps();
            $table->unique(['import_id', 'nomor_baris']);
            $table->index(['import_id', 'hasil']);
            $table->index('identity_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_historis_import_rows');
        Schema::dropIfExists('npd_historis_imports');
    }
};
