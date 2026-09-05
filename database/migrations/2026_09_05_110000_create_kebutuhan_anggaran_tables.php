<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estimasi Kebutuhan Kegiatan Pengawasan.
 *
 * Padanan sheet "Data Kebutuhan Anggaran" di GAS (CodeKebutuhan.gs), dengan
 * satu perbedaan yang disengaja: di GAS seluruh rincian per jenis anggota
 * ditumpuk sebagai satu string JSON di kolom terakhir. Di sini rinciannya
 * jadi TABEL SENDIRI. Alasannya bukan kerapian semata - rincian itulah yang
 * ingin dijumlahkan lintas unit saat menyusun pagu, dan angka di dalam JSON
 * tidak bisa dijumlahkan oleh basis data maupun diekspor per baris.
 *
 * Kolom total di tabel induk tetap disimpan (bukan dihitung ulang tiap kali)
 * karena tarif uang harian bisa berubah antar tahun: yang direkam adalah
 * angka pada saat kebutuhan itu disusun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kebutuhan_anggaran', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('tahun')->index();
            $table->string('unit_kerja', 100)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kegiatan yang TIDAK ada di PKPT tetap boleh diusulkan - yang
            // wajib waktu itu keterangannya, bukan nomor PKPT-nya.
            $table->boolean('dalam_pkpt')->default(true);
            $table->string('nomor_pkpt', 50)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('jenis_kegiatan', 255)->nullable();
            $table->text('keterangan')->nullable();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // Tarif yang dipakai kegiatan ini, digabung "100.000; 170.000"
            // bila rinciannya memakai tarif berbeda-beda.
            $table->string('tarif_uh_dalam', 100)->nullable();
            $table->string('tarif_uh_luar', 100)->nullable();

            $table->decimal('total_uh_dalam', 18, 2)->default(0);
            $table->decimal('total_uh_luar', 18, 2)->default(0);
            $table->decimal('total_akomodasi', 18, 2)->default(0);
            $table->decimal('total_transport', 18, 2)->default(0);
            $table->decimal('total_estimasi', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['tahun', 'unit_kerja']);
        });

        Schema::create('kebutuhan_anggaran_rincian', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kebutuhan_anggaran_id')->constrained('kebutuhan_anggaran')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);

            $table->string('jenis_anggota', 100)->nullable();
            $table->unsignedSmallInteger('jumlah_orang')->default(0);

            $table->unsignedSmallInteger('hari_dalam')->default(0);
            $table->decimal('tarif_uh_dalam', 18, 2)->default(0);
            $table->decimal('jumlah_uh_dalam', 18, 2)->default(0);

            $table->unsignedSmallInteger('hari_luar')->default(0);
            $table->decimal('tarif_uh_luar', 18, 2)->default(0);
            $table->decimal('jumlah_uh_luar', 18, 2)->default(0);

            $table->unsignedSmallInteger('jumlah_malam')->default(0);
            $table->decimal('tarif_akomodasi', 18, 2)->default(0);
            $table->decimal('total_akomodasi', 18, 2)->default(0);

            $table->decimal('estimasi_kebutuhan', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['kebutuhan_anggaran_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebutuhan_anggaran_rincian');
        Schema::dropIfExists('kebutuhan_anggaran');
    }
};
