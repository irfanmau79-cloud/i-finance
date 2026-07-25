<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen pendukung opsional untuk halaman admin "Data Tunjangan
     * Keluarga" — beda dari lampiran_tunjangan (yang terikat ke satu
     * pengajuan). Ini satu dokumen per pegawai, ditimpa setiap diunggah ulang.
     */
    public function up(): void
    {
        Schema::table('tunjangan_keluarga', function (Blueprint $table) {
            $table->string('dokumen_pendukung_path', 500)->nullable()->after('catatan');
            $table->string('dokumen_pendukung_nama', 255)->nullable()->after('dokumen_pendukung_path');
        });
    }

    public function down(): void
    {
        Schema::table('tunjangan_keluarga', function (Blueprint $table) {
            $table->dropColumn(['dokumen_pendukung_path', 'dokumen_pendukung_nama']);
        });
    }
};
