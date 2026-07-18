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
        Schema::create('data_tambahan', function (Blueprint $table) {
            $table->id();

            $table->string('program', 255)->unique();
            $table->string('no_dpa', 100);
            $table->string('kpa', 255);
            $table->string('pptk', 255);
            $table->string('bpp', 255);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_tambahan');
    }
};
