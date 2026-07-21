<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ikut Prompt 12A: staging row RAK tidak lagi mencocokkan ke
     * master_anggaran_id (yang tagging-scoped) - kolom itu dihapus. Kolom
     * sub_kegiatan/kode_rekening yang sudah ada dipakai sebagai identitas;
     * ditambah sub_kegiatan_kunci (hasil normalisasi, sama seperti
     * MasterAnggaran::normalisasiKunci()) supaya pencocokan tidak sensitif
     * whitespace/kapitalisasi. tagging_nama TETAP ada tapi sekarang murni
     * untuk menampilkan peringatan "kolom Tagging file lama diabaikan" -
     * tidak pernah dipakai untuk mencari/membuat RAK.
     */
    public function up(): void
    {
        Schema::table('rak_bulanan_import_rows', function (Blueprint $table) {
            $table->dropForeign(['master_anggaran_id']);
            $table->dropColumn('master_anggaran_id');

            $table->string('sub_kegiatan_kunci', 255)->nullable()->after('sub_kegiatan');
            $table->decimal('target_asli', 18, 2)->nullable()->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('rak_bulanan_import_rows', function (Blueprint $table) {
            $table->dropColumn(['sub_kegiatan_kunci', 'target_asli']);
            $table->foreignId('master_anggaran_id')->nullable()->after('kode_rekening')->constrained('master_anggaran')->nullOnDelete();
        });
    }
};
