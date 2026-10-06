<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak penyuntingan NPD oleh BPP dan Verifikator. Satu baris = satu kali
 * formulir Edit NPD disimpan SETELAH NPD meninggalkan meja PPTK.
 *
 * potret_sebelum menyimpan isi NPD beserta seluruh rinciannya tepat sebelum
 * suntingan itu. Potret pada baris PERTAMA sebuah NPD adalah draft awal
 * buatan PPTK - itulah yang dicetak "Cetak Draft NPD". perubahan menyimpan
 * daftar bagian yang berubah (bagian, semula, menjadi) APA ADANYA saat itu,
 * bukan dihitung ulang, supaya histori tidak ikut bergeser bila label atau
 * data induknya berubah kemudian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('peran', 40);
            $table->string('status_saat', 60);
            $table->longText('potret_sebelum');
            $table->longText('perubahan');
            $table->timestamps();

            $table->index(['npd_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_revisi');
    }
};
