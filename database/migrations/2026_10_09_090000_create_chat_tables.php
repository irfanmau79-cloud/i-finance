<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat internal antar AKUN (pengguna yang punya username & kata sandi).
     * Pengguna Layanan tidak punya akun, jadi tidak ikut.
     *
     * Dua jenis ruang:
     *
     * - 'pribadi': tepat dua akun. user_a_id selalu id yang LEBIH KECIL, jadi
     *   pasangan yang sama hanya bisa punya satu ruang (indeks unik).
     * - 'role': satu ruang per role ("Ruang BPP", "Ruang Verifikator"). Semua
     *   akun boleh membuka dan menulis di sana - itu cara menyapa seluruh
     *   pemegang sebuah role sekaligus.
     *
     * chat_baca mencatat pesan terakhir yang sudah dibaca tiap akun di tiap
     * ruang. Dari situ jumlah "belum dibaca" dihitung, tanpa menyimpan
     * penanda per pesan per orang.
     */
    public function up(): void
    {
        Schema::create('chat_ruang', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10);
            $table->string('role', 40)->nullable();
            $table->foreignId('user_a_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->nullable()->constrained('users')->cascadeOnDelete();
            // Id pesan terakhir: mengurutkan daftar ruang dan menghitung
            // belum-dibaca tanpa menyapu tabel pesan.
            $table->unsignedBigInteger('pesan_terakhir_id')->nullable();
            $table->timestamp('pesan_terakhir_at')->nullable();
            $table->timestamps();

            $table->unique('role', 'chat_ruang_role_unique');
            $table->unique(['user_a_id', 'user_b_id'], 'chat_ruang_pasangan_unique');
            $table->index('pesan_terakhir_at');
        });

        Schema::create('chat_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_ruang_id')->constrained('chat_ruang')->cascadeOnDelete();
            // Akun yang dihapus tidak ikut menghapus pesannya di ruang role;
            // namanya disimpan supaya pesan lama tetap terbaca siapa penulisnya.
            $table->foreignId('pengirim_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pengirim_nama', 150);
            $table->text('isi');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_ruang_id', 'id']);
        });

        Schema::create('chat_baca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_ruang_id')->constrained('chat_ruang')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('dibaca_sampai_id')->default(0);
            $table->timestamps();

            $table->unique(['chat_ruang_id', 'user_id'], 'chat_baca_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_baca');
        Schema::dropIfExists('chat_pesan');
        Schema::dropIfExists('chat_ruang');
    }
};
