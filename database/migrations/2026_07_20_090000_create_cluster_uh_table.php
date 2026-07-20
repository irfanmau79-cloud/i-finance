<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif Uang Harian per cluster jarak, port dari `var CLUSTER` di
     * gas-lama/index.html (baris ~4400). LP (Luar Provinsi) tarifnya 0 —
     * diisi manual per NPD, bukan dari tabel ini.
     */
    public function up(): void
    {
        Schema::create('cluster_uh', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->decimal('tarif', 18, 2)->default(0);
            $table->string('jarak', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_uh');
    }
};
