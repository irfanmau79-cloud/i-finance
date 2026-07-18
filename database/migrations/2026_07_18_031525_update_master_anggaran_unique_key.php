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
        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->dropUnique(['sub_kegiatan', 'kode_rekening']);
            $table->unique(['sub_kegiatan', 'kode_rekening', 'tagging_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->dropUnique(['sub_kegiatan', 'kode_rekening', 'tagging_id']);
            $table->unique(['sub_kegiatan', 'kode_rekening']);
        });
    }
};
