<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            // Hanya berarti untuk jenis 'kd': kontribusi (dibayar sebelum pelatihan)
            // atau perjalanan (dibayar setelah pelatihan). Kolom baru (ADD, bukan
            // MODIFY), aman untuk MySQL maupun SQLite in-memory pada test suite.
            $table->enum('mode_kd', ['kontribusi', 'perjalanan'])->nullable()->after('jenis_panjar');

            // Untuk jenis 'kd' mode 'perjalanan': referensi opsional ke NPD
            // Kontribusi Diklat (mode 'kontribusi') dari kegiatan yang sama, dipakai
            // untuk menyalin snapshot peserta. Self-FK ke npd, boleh kosong (input manual).
            $table->foreignId('npd_referensi_id')->nullable()->after('surat_perintah_id')
                ->constrained('npd')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('npd_referensi_id');
            $table->dropColumn('mode_kd');
        });
    }
};
