<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anggota tim NPD Perjalanan Dinas. Nama/jabatan/nip/rekening disalin
     * (snapshot) saat NPD dibuat — sama seperti npd_penerima — supaya tetap
     * utuh walau data pegawai berubah belakangan.
     */
    public function up(): void
    {
        Schema::create('npd_tim', function (Blueprint $table) {
            $table->id();

            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();

            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('rekening', 100)->nullable();

            $table->decimal('bbm_liter', 10, 2)->default(0);
            $table->decimal('bbm_tarif', 18, 2)->default(0);
            $table->decimal('tol', 18, 2)->default(0);
            $table->decimal('tiket', 18, 2)->default(0);
            $table->decimal('representatif', 18, 2)->default(0);

            $table->boolean('is_penerima')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_tim');
    }
};
