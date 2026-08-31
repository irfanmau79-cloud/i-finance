<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor handphone vendor, sejajar dengan pegawai.nomor_handphone. Dipakai
 * fitur Kirim Notifikasi WhatsApp di Data NPD untuk NPD Barang/Jasa yang
 * penerimanya vendor. Vendor tidak punya halaman CRUD sendiri, jadi satu-
 * satunya jalan pengisian adalah Import/Export Vendor di Manajemen Data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->string('nomor_handphone', 30)->nullable()->after('rekening');
        });

        Schema::table('vendor_import_rows', function (Blueprint $table) {
            $table->string('nomor_handphone', 30)->nullable()->after('rekening');
        });

        Schema::table('pegawai_import_rows', function (Blueprint $table) {
            $table->string('nomor_handphone', 30)->nullable()->after('rekening');
        });
    }

    public function down(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->dropColumn('nomor_handphone');
        });

        Schema::table('vendor_import_rows', function (Blueprint $table) {
            $table->dropColumn('nomor_handphone');
        });

        Schema::table('pegawai_import_rows', function (Blueprint $table) {
            $table->dropColumn('nomor_handphone');
        });
    }
};
