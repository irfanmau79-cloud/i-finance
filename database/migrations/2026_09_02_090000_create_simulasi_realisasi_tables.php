<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulasi Realisasi: memperkirakan capaian anggaran sampai akhir tahun.
 *
 * Bedanya dengan Simulasi Pergeseran (tabel simulasi_anggaran) yang mengubah
 * PAGU tiap mata anggaran: di sini pagunya tetap, yang direncanakan adalah
 * belanja yang BELUM terjadi. Tiap mata anggaran bisa diisi beberapa rencana
 * bernama - misalnya pada tagging "On Call": "Perjalanan dinas ke Cirebon"
 * Rp1.000.000 lalu "Rapat koordinasi" Rp500.000 - sehingga proyeksinya
 * terbaca sebagai daftar rencana, bukan satu angka gelondongan.
 *
 * Realisasi (Estimasi) = realisasi berjalan + Proyeksi
 *
 * Realisasi berjalan TIDAK disimpan di sini; ia selalu dihitung ulang dari
 * transaksi (NPD Selesai + SPM LS, neto pengembalian) mengikuti aturan pokok
 * aplikasi. Yang di-snapshot hanya label dan pagu, supaya simulasi lama tetap
 * terbaca utuh walau master anggarannya kelak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulasi_realisasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_pagu', 18, 2)->default(0);
            $table->decimal('total_proyeksi', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('simulasi_realisasi_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulasi_realisasi_id')->constrained('simulasi_realisasi')->cascadeOnDelete();
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->nullOnDelete();

            // Snapshot label tampilan - sama alasannya dengan simulasi_anggaran_rows.
            $table->string('program', 255);
            $table->string('kegiatan', 255);
            $table->string('sub_kegiatan', 255);
            $table->string('sub_kegiatan_kunci', 255);
            $table->string('kode_rekening', 50);
            $table->string('uraian_rekening', 255)->nullable();
            $table->string('tagging_nama', 255)->nullable();

            $table->decimal('pagu', 18, 2)->default(0);
            // Jumlah seluruh rencana pada baris ini - inilah kolom Proyeksi di
            // layar. Disimpan sebagai ringkasan supaya daftar dan export tidak
            // perlu menjumlah ulang tiap kali.
            $table->decimal('proyeksi_total', 18, 2)->default(0);

            $table->timestamps();
            $table->index('simulasi_realisasi_id');
        });

        Schema::create('simulasi_realisasi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulasi_realisasi_row_id')->constrained('simulasi_realisasi_rows')->cascadeOnDelete();
            $table->string('nama', 255);
            $table->decimal('nominal', 18, 2)->default(0);
            // Urutan tampil mengikuti urutan pengisian, bukan abjad.
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->index(['simulasi_realisasi_row_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi_realisasi_items');
        Schema::dropIfExists('simulasi_realisasi_rows');
        Schema::dropIfExists('simulasi_realisasi');
    }
};
