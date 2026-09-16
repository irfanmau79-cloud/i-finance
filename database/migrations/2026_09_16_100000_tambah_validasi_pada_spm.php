<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "sudah divalidasi" pada SPM.
 *
 * Gunanya BUKAN alur persetujuan berlapis seperti NPD - SPM adalah catatan
 * dokumen yang sudah terbit di BPKAD, bukan dokumen yang diajukan lewat
 * aplikasi ini. Validasi di sini berarti satu hal saja: angkanya sudah
 * dicocokkan dengan berkas SP2D aslinya, jadi barisnya TIDAK BOLEH lagi
 * dihapus (lihat SpmController::destroy). Realisasi anggaran dihitung dari
 * baris-baris ini, dan baris yang sudah dicocokkan lalu hilang berarti angka
 * realisasi berubah tanpa jejak.
 *
 * Dua kolom, bukan satu boolean: yang perlu dipertanggungjawabkan bukan
 * hanya "sudah" tapi juga OLEH SIAPA dan KAPAN - pertanyaan pertama saat
 * angka realisasi dipersoalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->foreignId('divalidasi_oleh')->nullable()->after('dibuat_oleh')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_at')->nullable()->after('divalidasi_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->dropConstrainedForeignId('divalidasi_oleh');
            $table->dropColumn('divalidasi_at');
        });
    }
};
