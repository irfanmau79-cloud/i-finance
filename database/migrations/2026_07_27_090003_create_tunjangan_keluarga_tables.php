<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunjangan_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->unique()->constrained('pegawai')->cascadeOnDelete();
            $table->text('catatan')->nullable();
            $table->foreignId('diperbarui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('anggota_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tunjangan_keluarga_id')->constrained('tunjangan_keluarga')->cascadeOnDelete();
            $table->string('hubungan', 20);
            $table->string('nama', 150);
            $table->date('tanggal_lahir')->nullable();
            $table->boolean('status_tunjangan')->default(false);
            $table->boolean('perpanjangan_kuliah')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['tunjangan_keluarga_id', 'hubungan']);
        });

        Schema::create('pengajuan_perubahan_tunjangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('nama_pegawai', 150);
            $table->string('nip', 30)->nullable();
            $table->json('payload');
            $table->text('keterangan');
            $table->string('status', 20)->default('diajukan');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('diajukan_at');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->text('catatan_proses')->nullable();
            $table->timestamps();
            $table->index(['status', 'diajukan_at']);
        });

        Schema::create('lampiran_tunjangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_perubahan_tunjangan')->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('nama_asli', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_tunjangan');
        Schema::dropIfExists('pengajuan_perubahan_tunjangan');
        Schema::dropIfExists('anggota_keluarga');
        Schema::dropIfExists('tunjangan_keluarga');
    }
};
