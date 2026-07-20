<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header batch import SPM (UP/GU atau LS - satu batch selalu satu jenis,
     * sama seperti pemisahan export SpmUpGuExport/SpmLsExport di Prompt 09).
     * Sama seperti master_anggaran_imports: baris hasil parse tersimpan di
     * spm_import_rows (staging) dan TIDAK pernah menyentuh tabel spm sampai
     * Konfirmasi Simpan (lihat SpmImport::konfirmasi()).
     */
    public function up(): void
    {
        Schema::create('spm_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('jenis_spm', ['up_gu', 'ls']);
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
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_imports');
    }
};
