<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header batch import RAK Bulanan. File yang diupload berformat LEBAR
     * (satu baris per mata anggaran, kolom Januari..Desember) untuk
     * kemudahan isi data, tapi di-explode ke rak_bulanan_import_rows
     * (staging, satu baris per bulan) saat parse - lihat catatan lengkap di
     * migration create_rak_bulanan_table dan RakBulananImport::buatDariUpload().
     */
    public function up(): void
    {
        Schema::create('rak_bulanan_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('nama_file', 255);
            $table->enum('status', ['staged', 'committed'])->default('staged');

            // Jumlah dihitung dari baris HASIL EXPLODE per bulan, bukan
            // jumlah baris mentah pada file (satu baris file = sampai 12
            // baris staging).
            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_update')->default(0);
            $table->unsignedInteger('jumlah_ditolak')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rak_bulanan_imports');
    }
};
