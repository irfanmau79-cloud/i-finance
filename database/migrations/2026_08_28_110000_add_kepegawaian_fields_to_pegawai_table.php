<?php

use App\Models\Pegawai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dua data kepegawaian yang selama ini belum tercatat:
 *
 * - periode_kgb: periode Kenaikan Gaji Berkala. Sengaja TEKS, bukan date,
 *   karena di praktiknya ditulis sebagai periode ("April 2026", "01-04-2026
 *   s.d. 31-03-2028") dan bentuknya berbeda-beda antar berkas. Memaksakan
 *   tipe date akan menolak isian yang sebenarnya sah dan merusak alur
 *   export-edit-import.
 *
 * - status_kepegawaian: PNS / PPPK Penuh Waktu / PPPK Paruh Waktu. Menentukan
 *   siapa yang muncul di Data Tunjangan Keluarga - PPPK Paruh Waktu tidak
 *   berhak tunjangan keluarga sehingga tidak ikut didaftar.
 *
 * Baris lama dianggap PNS: itu status mayoritas dan satu-satunya yang ada
 * sebelum kolom ini dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('periode_kgb', 50)->nullable()->after('pangkat');
            $table->string('status_kepegawaian', 30)->default(Pegawai::STATUS_PNS)->after('periode_kgb');
        });

        DB::table('pegawai')->update(['status_kepegawaian' => Pegawai::STATUS_PNS]);

        Schema::table('pegawai', function (Blueprint $table) {
            $table->index('status_kepegawaian', 'pegawai_status_kepegawaian_index');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropIndex('pegawai_status_kepegawaian_index');
            $table->dropColumn(['periode_kgb', 'status_kepegawaian']);
        });
    }
};
