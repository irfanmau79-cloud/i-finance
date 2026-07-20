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
        Schema::create('npd_narasumber', function (Blueprint $table) {
            $table->id();

            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendor')->nullOnDelete();

            // Salinan nama, jabatan & rekening saat NPD dibuat — tetap walau data master berubah.
            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();
            $table->string('rekening', 100)->nullable();

            $table->unsignedInteger('jumlah_jp')->default(0);
            $table->decimal('tarif_jp', 18, 2)->default(0);
            $table->decimal('transport', 18, 2)->default(0);
            $table->decimal('pph21', 18, 2)->default(0);

            // Keterangan khusus narasumber ini di Lampiran; kalau kosong, jatuh ke uraian kegiatan NPD.
            $table->text('uraian')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npd_narasumber');
    }
};
