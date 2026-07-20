<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Multi-tujuan per anggota tim (paket cluster/hari/malam), port dari struktur `paket[]` di CodePerjalanan.gs. */
    public function up(): void
    {
        Schema::create('npd_tim_paket', function (Blueprint $table) {
            $table->id();

            $table->foreignId('npd_tim_id')->constrained('npd_tim')->cascadeOnDelete();

            $table->enum('cluster', ['A', 'B', 'C', 'D', 'LP']);
            $table->string('wilayah', 100);

            $table->integer('lama_hari')->default(0);
            $table->decimal('tarif_uh', 18, 2)->default(0);

            $table->integer('malam')->default(0);
            $table->decimal('tarif_akom', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_tim_paket');
    }
};
