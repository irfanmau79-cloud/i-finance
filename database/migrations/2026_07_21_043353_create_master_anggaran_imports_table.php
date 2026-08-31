<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header batch import Pagu/Master Anggaran. File yang diupload di-parse
     * langsung ke master_anggaran_import_rows (staging) - baris di tabel ini
     * TIDAK pernah menyentuh master_anggaran sampai user menekan Konfirmasi
     * Simpan (lihat MasterAnggaranImport::konfirmasi()).
     */
    public function up(): void
    {
        Schema::create('master_anggaran_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file', 255);
            $table->enum('status', ['staged', 'committed'])->default('staged');

            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_update')->default(0);
            $table->unsignedInteger('jumlah_ditolak')->default(0);

            // Staging kedaluwarsa setelah durasi tertentu (lihat
            // App\Models\Concerns\StagingKedaluwarsa) supaya tidak menumpuk
            // dan tidak dikonfirmasi dari data yang sudah basi.
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_anggaran_imports');
    }
};
