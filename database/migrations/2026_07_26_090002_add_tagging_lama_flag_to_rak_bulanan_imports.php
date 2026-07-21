<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 12A: file RAK format lama masih boleh punya kolom Tagging
     * (diabaikan sepenuhnya). Flag ini dicatat sekali per batch saat parse
     * supaya halaman preview bisa menampilkan peringatan "kolom Tagging
     * sudah tidak digunakan" tanpa perlu memindai ulang seluruh baris
     * staging.
     */
    public function up(): void
    {
        Schema::table('rak_bulanan_imports', function (Blueprint $table) {
            $table->boolean('ada_kolom_tagging_lama')->default(false)->after('nama_file');
        });
    }

    public function down(): void
    {
        Schema::table('rak_bulanan_imports', function (Blueprint $table) {
            $table->dropColumn('ada_kolom_tagging_lama');
        });
    }
};
