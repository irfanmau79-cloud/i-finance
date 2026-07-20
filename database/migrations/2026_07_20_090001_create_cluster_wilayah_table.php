<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kabupaten/kota per cluster_uh, port dari `wilayah` array di gas-lama CLUSTER. */
    public function up(): void
    {
        Schema::create('cluster_wilayah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('cluster_uh')->cascadeOnDelete();
            $table->string('nama_wilayah', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_wilayah');
    }
};
