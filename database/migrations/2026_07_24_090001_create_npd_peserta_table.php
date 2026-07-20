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
        Schema::create('npd_peserta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();

            // Salinan identitas saat NPD dibuat — tetap walau data master berubah,
            // dan (untuk mode perjalanan dengan referensi) disalin dari peserta NPD
            // Kontribusi yang direferensikan, bukan dibaca ulang secara dinamis.
            $table->string('nama', 255);
            $table->string('pangkat', 100)->nullable();
            $table->string('nip', 50)->nullable();
            $table->string('rekening', 100)->nullable();

            // -- Bagian Kontribusi: jumlah_kontribusi = volume_kontribusi * tarif_kontribusi,
            //    jumlah_mooc = volume_mooc * tarif_mooc. Hanya diisi untuk mode 'kontribusi'.
            $table->unsignedInteger('volume_kontribusi')->default(0);
            $table->decimal('tarif_kontribusi', 18, 2)->default(0);
            $table->unsignedInteger('volume_mooc')->default(0);
            $table->decimal('tarif_mooc', 18, 2)->default(0);

            // -- Bagian Perjalanan Dinas (manual, bukan lewat npd_tim/paket seperti
            //    jenis 'pd'): jumlah_harian = hari_uh * tarif_uh, jumlah_akomodasi =
            //    volume_akomodasi * tarif_akomodasi, jumlah_saku = hari_saku * tarif_saku,
            //    transport dibayar at-cost. Hanya diisi untuk mode 'perjalanan'.
            $table->unsignedInteger('hari_uh')->default(0);
            $table->decimal('tarif_uh', 18, 2)->default(0);
            $table->unsignedInteger('volume_akomodasi')->default(0);
            $table->decimal('tarif_akomodasi', 18, 2)->default(0);
            $table->unsignedInteger('hari_saku')->default(0);
            $table->decimal('tarif_saku', 18, 2)->default(0);
            $table->decimal('transport', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npd_peserta');
    }
};
