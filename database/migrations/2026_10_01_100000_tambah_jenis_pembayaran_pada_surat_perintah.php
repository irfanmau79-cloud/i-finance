<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jenis Pembayaran pada Surat Perintah (adopsi GAS #77c).
     *
     * Satu SP bisa menanggung dua-duanya sekaligus, jadi disimpan sebagai
     * daftar yang digabung koma - pola yang sama dengan kolom `pengajuan`
     * yang sudah ada, bukan enum.
     *
     * Dibuat nullable: SP lama tidak punya nilainya dan tidak boleh ditebak.
     * Yang kosong tampil sebagai "-" di layar.
     */
    public function up(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->string('jenis_pembayaran', 100)->nullable()->after('pengajuan');
        });
    }

    public function down(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->dropColumn('jenis_pembayaran');
        });
    }
};
