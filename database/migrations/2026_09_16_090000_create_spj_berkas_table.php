<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas SPJ hasil pindaian yang ditempelkan ke satu NPD.
 *
 * Ini FITUR PEMBANTU, bukan kewajiban: NPD tanpa berkas SPJ tetap sah dan
 * tetap bisa dicetak seperti sebelumnya. Karena itu tidak ada kolom NOT NULL
 * baru di tabel npd dan tidak ada aturan alur kerja yang bergantung pada ada
 * atau tidaknya baris di sini.
 *
 * TABEL SENDIRI, bukan satu kolom path di tabel npd, karena satu SPJ kerap
 * dipindai jadi BEBERAPA berkas (satu JPG per lembar). Kalau dipaksa satu
 * kolom, kantor harus menggabung sendiri lembarannya di luar aplikasi lebih
 * dulu - padahal justru penggabungan itu yang ingin dibantu di sini (lihat
 * App\Support\PdfGabung dan tombol "Cetak Semua").
 *
 * Berkasnya TIDAK disimpan di dalam basis data, hanya path-nya; isinya di
 * disk 'local' (storage/app/private) yang tidak bisa diakses langsung dari
 * web - sama seperti dokumen pendukung Pengembalian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_berkas', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: NPD yang dihapus permanen tidak boleh
            // meninggalkan baris menggantung. Berkas fisiknya dihapus di
            // SpjBerkasService::hapusMilikNpd() sebelum NPD-nya dihapus -
            // basis data tidak bisa menghapus isi disk.
            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();

            $table->string('path', 255);
            $table->string('nama_asli', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran');

            // Urutan tampil & urutan saat ikut dicetak gabungan. Dipisahkan
            // dari id supaya lembaran yang diunggah menyusul masih bisa
            // ditaruh di tengah tanpa mengunggah ulang semuanya.
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['npd_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_berkas');
    }
};
