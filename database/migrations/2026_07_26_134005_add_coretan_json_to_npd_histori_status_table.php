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
        Schema::table('npd_histori_status', function (Blueprint $table) {
            $table->longText('coretan_json')->nullable()->after('catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npd_histori_status', function (Blueprint $table) {
            $table->dropColumn('coretan_json');
        });
    }
};
