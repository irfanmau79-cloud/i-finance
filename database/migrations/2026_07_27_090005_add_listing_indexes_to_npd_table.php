<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->index(['status', 'tanggal_npd'], 'npd_status_tanggal_index');
            $table->index(['jenis', 'tanggal_npd'], 'npd_jenis_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropIndex('npd_status_tanggal_index');
            $table->dropIndex('npd_jenis_tanggal_index');
        });
    }
};
