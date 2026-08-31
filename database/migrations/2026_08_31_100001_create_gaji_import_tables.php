<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch import Data Gaji & Tunjangan dengan alur preview/dry-run - pola sama
 * seperti import Pegawai & Tunjangan Keluarga: berkas di-parse ke staging
 * dulu, tabel gaji_induk/tpp TIDAK disentuh sampai user menekan Konfirmasi.
 *
 * `jenis`, `bulan`, dan `tahun` melekat pada batch (dipilih di dropdown saat
 * unggah), bukan per baris - satu berkas selalu satu jenis penghasilan untuk
 * satu bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gaji_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file', 255);

            $table->enum('jenis', ['gaji', 'beban', 'kondisi']);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            $table->enum('status', ['preview', 'committed'])->default('preview');

            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('baris_valid')->default(0);
            $table->unsignedInteger('baris_invalid')->default(0);
            // Berapa baris yang akan TERTIMPA bila batch ini dikonfirmasi -
            // ditampilkan sebagai peringatan di halaman preview.
            $table->unsignedInteger('baris_tertimpa')->default(0);

            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'jenis']);
        });

        Schema::create('gaji_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('gaji_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->boolean('valid')->default(true);

            $table->string('nama_pegawai', 255)->nullable();
            $table->string('nip', 30)->nullable();

            /** @var array<int, string> pesan kesalahan per baris */
            $table->json('pesan')->nullable();
            /** @var array<string, mixed> nilai siap simpan, kunci = nama kolom tabel tujuan */
            $table->json('payload');

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaji_import_rows');
        Schema::dropIfExists('gaji_imports');
    }
};
