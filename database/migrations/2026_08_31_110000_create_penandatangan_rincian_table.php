<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar penandatangan Surat Keterangan Penghasilan.
 *
 * Sebelumnya daftar ini konstanta di config('gaji_tunjangan.penandatangan')
 * (di GAS: GT_PENANDATANGAN), sehingga menambah pejabat harus menyunting
 * berkas. Sekarang superadmin bisa menambahnya sendiri dari Data Pegawai;
 * role lain hanya memilih dari daftar yang sudah disediakan.
 *
 * Identitas penandatangan TETAP dibekukan sebagai snapshot di
 * rincian_penghasilan saat dokumen dibuat, jadi menghapus atau menonaktifkan
 * baris di sini tidak mengubah dokumen yang sudah pernah dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penandatangan_rincian', function (Blueprint $table) {
            $table->id();

            // Asal pegawainya, sekadar rujukan. Nama/jabatan/pangkat tetap
            // disimpan sendiri di baris ini supaya redaksi pada surat bisa
            // berbeda dari Data Pegawai bila diperlukan, dan tidak ikut
            // berubah diam-diam saat data pegawai disunting.
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();

            // Dipakai sebagai nilai <option> dan disalin ke kolom
            // rincian_penghasilan.penandatangan_kunci (varchar 40).
            $table->string('kunci', 40)->unique();

            $table->string('nama', 255);
            $table->string('jabatan', 255);
            $table->string('pangkat', 100);

            // Penandatangan lama cukup dinonaktifkan, tidak perlu dihapus.
            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->index('aktif');
        });

        // Pindahkan dua penandatangan yang selama ini di-hardcode supaya
        // daftarnya tidak kosong dan kunci dokumen lama tetap cocok.
        $sekarang = now();

        foreach (config('gaji_tunjangan.penandatangan', []) as $kunci => $orang) {
            DB::table('penandatangan_rincian')->insert([
                'pegawai_id' => null,
                'kunci' => $kunci,
                'nama' => $orang['nama'],
                'jabatan' => $orang['jabatan'],
                'pangkat' => $orang['pangkat'],
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penandatangan_rincian');
    }
};
