<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekonsiliasi Gaji Induk.
 *
 * Data Tunjangan Keluarga bersifat HIDUP - anggota keluarga bisa ditambah,
 * diubah, atau dihapus kapan saja. Karena itu status per awal bulan tidak
 * bisa dihitung ulang belakangan: harus dipotret pada saat itu juga.
 *
 * `rekonsiliasi_kunci` adalah potret satu periode (satu bulan), dan
 * `rekonsiliasi_kunci_baris` menyimpan status tiap pegawai pada potret itu.
 * Identitas pegawai ikut disalin ke barisnya supaya log tetap terbaca walau
 * pegawainya kelak dihapus dari Data Pegawai.
 *
 * Potret hanya boleh dibuat, disunting, dan dihapus oleh superadmin - inilah
 * pengaman supaya status tidak bisa dirapikan belakangan agar cocok dengan
 * gaji yang sudah terlanjur dibayarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekonsiliasi_kunci', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // Hari kerja pertama bulan itu - lihat config gaji_tunjangan.
            // hari_libur. Disimpan, bukan dihitung ulang saat ditampilkan,
            // karena daftar hari libur di config bisa berubah kemudian.
            $table->date('tanggal_penggajian');

            $table->foreignId('dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dikunci_oleh_nama', 150)->nullable();
            $table->timestamp('dikunci_at');

            $table->timestamps();

            // Satu periode hanya boleh punya satu potret.
            $table->unique(['tahun', 'bulan']);
        });

        Schema::create('rekonsiliasi_kunci_baris', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kunci_id')->constrained('rekonsiliasi_kunci')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();

            $table->string('nama', 150);
            $table->string('nip', 30)->nullable();

            // Status ringkas (mis. "K/1") beserta angka pembentuknya, supaya
            // selisih terhadap penggajian bisa dihitung tanpa mengurai teks.
            $table->string('status_tk', 10);
            $table->unsignedTinyInteger('jumlah_pasangan')->default(0);
            $table->unsignedTinyInteger('jumlah_anak')->default(0);

            // Terisi bila superadmin menyunting baris ini setelah dikunci.
            $table->text('catatan_suntingan')->nullable();
            $table->foreignId('disunting_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disunting_at')->nullable();

            $table->timestamps();

            $table->index(['kunci_id', 'nama']);
            $table->index('nip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekonsiliasi_kunci_baris');
        Schema::dropIfExists('rekonsiliasi_kunci');
    }
};
