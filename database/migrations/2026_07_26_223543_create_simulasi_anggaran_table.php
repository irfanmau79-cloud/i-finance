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
        Schema::create('simulasi_anggaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_pagu_eksisting', 18, 2)->default(0);
            $table->decimal('total_pagu_simulasi', 18, 2)->default(0);
            $table->decimal('total_selisih', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulasi_anggaran');
    }
};
