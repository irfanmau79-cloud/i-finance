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
        Schema::create('master_anggaran', function (Blueprint $table) {
            $table->id();

            $table->string('program', 255);
            $table->string('kegiatan', 255);
            $table->string('sub_kegiatan', 255);
            $table->string('kode_rekening', 50);
            $table->foreignId('tagging_id')->nullable()->constrained('taggings')->nullOnDelete();

            // Nilai anggaran DPA. Realisasi, sisa anggaran, dan realisasi
            // bulanan dihitung dari tabel NPD lewat query, tidak disimpan di sini.
            $table->decimal('pagu', 18, 2);

            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->unique(['sub_kegiatan', 'kode_rekening']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_anggaran');
    }
};
