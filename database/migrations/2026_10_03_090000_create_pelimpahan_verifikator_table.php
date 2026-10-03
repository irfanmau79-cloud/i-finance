<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan Verifikator per Sub Kegiatan (menu Pelimpahan).
 *
 * Sengaja TABEL TERSENDIRI, bukan kolom baru di `pelimpahan`: baris
 * pelimpahan adalah rantai KPA -> BPP -> PPTK yang divalidasi ketat dan
 * dipakai untuk tanda tangan dokumen. Verifikator tidak menandatangani apa
 * pun dan boleh ditetapkan walau rantai KPA/PPTK-nya belum lengkap -
 * mencampurnya akan membuat salah satu menahan yang lain.
 *
 * Yang ditunjuk adalah AKUN (users), bukan pegawai: tujuannya supaya tiap
 * NPD jelas diverifikasi oleh akun Verifikator yang mana.
 *
 * Pola riwayatnya sama dengan pelimpahan: mengganti Verifikator
 * menonaktifkan baris lama dan membuat baris baru, tidak menimpa, sehingga
 * siapa memegang Sub Kegiatan apa pada waktu kapan tetap bisa diaudit.
 * Satu Sub Kegiatan hanya boleh punya SATU baris aktif - dijaga indeks unik
 * pada kolom virtual, pola yang sama dengan pptk_roster.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelimpahan_verifikator', function (Blueprint $table) {
            $table->id();
            $table->string('program_kunci', 255);
            $table->string('sub_kegiatan_kunci', 255);
            $table->string('kode_sub_kegiatan', 255);
            $table->foreignId('verifikator_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamp('dinonaktifkan_at')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->string('sub_aktif_unik', 255)->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN sub_kegiatan_kunci ELSE NULL END');
            $table->unique('sub_aktif_unik', 'pelimpahan_verifikator_satu_aktif_unique');
            $table->index(['program_kunci', 'sub_kegiatan_kunci', 'aktif'], 'pelimpahan_verifikator_lingkup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelimpahan_verifikator');
    }
};
