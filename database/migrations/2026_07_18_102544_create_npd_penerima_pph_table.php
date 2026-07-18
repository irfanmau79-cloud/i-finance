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
        Schema::create('npd_penerima_pph', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_penerima_id')->constrained('npd_penerima')->cascadeOnDelete();
            $table->string('jenis', 50);
            $table->decimal('nilai', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npd_penerima_pph');
    }
};
