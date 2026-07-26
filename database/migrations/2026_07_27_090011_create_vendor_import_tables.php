<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch import Vendor dengan alur preview/dry-run, pola sama seperti
 * Pegawai/Master Anggaran - file di-parse ke staging dulu, TIDAK menyentuh
 * tabel vendor sampai user menekan Konfirmasi Simpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file', 255);
            $table->enum('status', ['staged', 'committed'])->default('staged');

            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_update')->default(0);
            $table->unsignedInteger('jumlah_ditolak')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('vendor_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('vendor_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('nama', 255)->nullable();
            $table->string('rekening', 100)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->boolean('pkp')->default(false);
            $table->string('jenis_usaha', 100)->nullable();
            $table->boolean('aktif')->default(true);

            $table->foreignId('vendor_id')->nullable()->constrained('vendor')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_import_rows');
        Schema::dropIfExists('vendor_imports');
    }
};
