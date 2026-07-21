<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arsip_spj', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->string('jenis_dokumen', 50);
            $table->string('lokasi', 100);
            $table->text('catatan')->nullable();
            $table->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditetapkan_at');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['npd_id', 'jenis_dokumen', 'aktif']);
            $table->index(['lokasi', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arsip_spj');
    }
};
