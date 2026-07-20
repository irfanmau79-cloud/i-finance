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
        Schema::table('npd', function (Blueprint $table) {
            // Untuk jenis 'tr': induk NPD Perjalanan Dinas (jenis 'pd', status Selesai)
            // yang dijadikan sumber snapshot sumber dana, SP, dan anggota. Self-FK, nullable
            // karena hanya jenis 'tr' yang memakainya.
            $table->foreignId('npd_induk_id')->nullable()->after('npd_referensi_id')
                ->constrained('npd')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('npd_induk_id');
        });
    }
};
