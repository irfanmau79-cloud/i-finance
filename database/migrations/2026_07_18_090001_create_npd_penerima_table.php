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
        Schema::create('npd_penerima', function (Blueprint $table) {
            $table->id();

            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendor')->nullOnDelete();

            // Salinan nama & rekening saat NPD dibuat — tetap walau data master berubah.
            $table->string('nama', 255);
            $table->string('rekening', 100)->nullable();

            $table->decimal('bruto', 18, 2)->default(0);
            $table->decimal('pph', 18, 2)->default(0);
            $table->decimal('biaya', 18, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npd_penerima');
    }
};
