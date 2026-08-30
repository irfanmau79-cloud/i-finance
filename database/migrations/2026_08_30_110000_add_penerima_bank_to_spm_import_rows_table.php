<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging impor SPM ikut membawa Bank Tujuan dan Nomor Rekening penerima
     * (kolom baru pada template SP2D LS), plus hasil pencocokan nama penerima
     * ke Data Pegawai/Vendor.
     *
     * Tautannya ditentukan saat PARSE, bukan saat commit, supaya halaman
     * preview bisa memperlihatkan penerima mana yang berhasil dikenali
     * sebelum apa pun disimpan.
     */
    public function up(): void
    {
        Schema::table('spm_import_rows', function (Blueprint $table) {
            $table->foreignId('penerima_pegawai_id')->nullable()->after('penerima')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('penerima_vendor_id')->nullable()->after('penerima_pegawai_id')->constrained('vendor')->nullOnDelete();
            $table->string('bank_tujuan', 100)->nullable()->after('penerima_vendor_id');
            $table->string('nomor_rekening', 50)->nullable()->after('bank_tujuan');
        });
    }

    public function down(): void
    {
        Schema::table('spm_import_rows', function (Blueprint $table) {
            $table->dropColumn(['bank_tujuan', 'nomor_rekening']);
            $table->dropConstrainedForeignId('penerima_vendor_id');
            $table->dropConstrainedForeignId('penerima_pegawai_id');
        });
    }
};
