<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unggahan PDF tidak wajib untuk SP berjenis Reimburse Transportasi -
 * prosesInputSP() di GAS memang hanya mengunggah berkas bila ada, dan
 * dokumen reimburse memakai berkas SP induknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->string('file_url', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris tanpa berkas tidak bisa dijadikan NOT NULL apa adanya.
        DB::table('surat_perintah')->whereNull('file_url')->update(['file_url' => '']);

        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->string('file_url', 500)->nullable(false)->change();
        });
    }
};
