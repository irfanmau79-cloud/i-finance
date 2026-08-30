<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penerima SP2D LS diperinci: selain namanya, dicatat juga bank tujuan
     * dan nomor rekening pencairan.
     *
     * Kolom `penerima` yang sudah ada TETAP menjadi nama penerima (snapshot),
     * jadi data lama dan jalur impor SPM tidak perlu diubah. Yang baru hanya
     * tautan opsional ke Data Pegawai/Vendor - dipakai formulir untuk mengisi
     * otomatis - serta bank dan nomor rekeningnya. Semuanya nullable karena
     * penerima memang boleh dikosongkan dan SPM UP/GU tidak memakainya.
     */
    public function up(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->foreignId('penerima_pegawai_id')->nullable()->after('penerima')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('penerima_vendor_id')->nullable()->after('penerima_pegawai_id')->constrained('vendor')->nullOnDelete();
            $table->string('bank_tujuan', 100)->nullable()->after('penerima_vendor_id');
            $table->string('nomor_rekening', 50)->nullable()->after('bank_tujuan');
        });
    }

    public function down(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->dropColumn(['bank_tujuan', 'nomor_rekening']);
            $table->dropConstrainedForeignId('penerima_vendor_id');
            $table->dropConstrainedForeignId('penerima_pegawai_id');
        });
    }
};
