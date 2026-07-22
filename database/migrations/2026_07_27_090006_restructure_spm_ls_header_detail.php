<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 22: SPM LS bisa mencakup beberapa mata anggaran sekaligus dalam
     * satu dokumen (satu penerima, satu angka PPN/PPh untuk seluruh
     * dokumen), jadi master_anggaran_id pindah dari header spm ke tabel
     * detail baru spm_detail (satu baris per mata anggaran per SPM). Kolom
     * `nominal` TETAP di header tapi sekarang hanya berarti untuk jenis_spm
     * 'up_gu' (SPM LS tidak pernah mengisinya - nominal LS = SUM(spm_detail.nominal),
     * lihat Spm::totalNominal()). Data spm/spm_detail masih kosong di semua
     * lingkungan saat prompt ini ditulis, jadi tidak ada migrasi data lama.
     */
    public function up(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->dropForeign(['master_anggaran_id']);
            $table->dropColumn('master_anggaran_id');
            $table->decimal('nominal', 18, 2)->nullable()->change();
        });

        Schema::create('spm_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spm_id')->constrained('spm')->cascadeOnDelete();
            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->restrictOnDelete();
            $table->decimal('nominal', 18, 2);

            $table->timestamps();

            $table->unique(['spm_id', 'master_anggaran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_detail');

        Schema::table('spm', function (Blueprint $table) {
            $table->decimal('nominal', 18, 2)->nullable(false)->default(0)->change();
            $table->foreignId('master_anggaran_id')->nullable()->after('nomor_sp2d')->constrained('master_anggaran')->restrictOnDelete();
        });
    }
};
