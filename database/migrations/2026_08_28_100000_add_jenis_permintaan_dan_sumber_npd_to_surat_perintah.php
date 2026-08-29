<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan Surat Perintah dengan modul GAS (CodeSuratPerintah.gs kolom
 * P dan Q pada sheet "Monitoring SP"):
 *
 * - jenis_permintaan: "Uang Harian/Akomodasi" atau "Reimburse Transportasi".
 *   SP Reimburse adalah BARIS SP TERSENDIRI yang menunjuk SP induk berjenis
 *   Uang Harian/Akomodasi; data dan anggotanya disalin dari induk lalu
 *   dikunci, nomornya "{nomor induk} (Reimburse)", dan hanya boleh satu per
 *   induk (ditegakkan lewat unique pada sp_induk_id).
 *
 * - sumber_npd: flag TERPISAH dari 'dipantau'. Keduanya sengaja tidak
 *   digabung karena mengatur hal berbeda dan rolenya berbeda pula:
 *   'dipantau' mengatur tampil/tidaknya SP di halaman Monitoring SP
 *   (PPTK/Bendahara), sedangkan 'sumber_npd' mengatur muncul/tidaknya SP
 *   sebagai sumber data di Pembuatan NPD Perjalanan Dinas dan daftar
 *   Reimburse Transport (PPTK/BPP/Bendahara). Baris lama dianggap 'ya',
 *   sama seperti perilaku _isSumberNPD() di GAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->string('jenis_permintaan', 40)->default('Uang Harian/Akomodasi')->after('tanggal_sp');
            $table->foreignId('sp_induk_id')->nullable()->after('jenis_permintaan')
                ->constrained('surat_perintah')->nullOnDelete();
            $table->boolean('sumber_npd')->default(true)->after('dipantau');
        });

        // Baris lama: jenis Uang Harian/Akomodasi, ikut jadi sumber NPD.
        DB::table('surat_perintah')->update([
            'jenis_permintaan' => 'Uang Harian/Akomodasi',
            'sumber_npd' => true,
        ]);

        Schema::table('surat_perintah', function (Blueprint $table) {
            // Satu SP induk hanya boleh punya SATU entri Reimburse. NULL tidak
            // saling bertabrakan di MySQL maupun SQLite, jadi SP biasa
            // (sp_induk_id NULL) tetap bebas.
            $table->unique('sp_induk_id', 'surat_perintah_induk_unique');
            $table->index(['dipantau', 'sumber_npd'], 'surat_perintah_flag_index');
        });
    }

    public function down(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->dropIndex('surat_perintah_flag_index');
            $table->dropUnique('surat_perintah_induk_unique');
            $table->dropConstrainedForeignId('sp_induk_id');
            $table->dropColumn(['jenis_permintaan', 'sumber_npd']);
        });
    }
};
