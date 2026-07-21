<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->string('bidang_snapshot', 100)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->dropColumn('bidang_snapshot');
        });
    }
};
