<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siaran (broadcast) superadmin yang muncul di lonceng notifikasi semua role.
 *
 * siaran_dibaca mencatat SIAPA sudah membuka siaran mana. Tidak adanya baris
 * berarti belum dibaca - jadi siaran baru otomatis "unread" bagi semua akun
 * tanpa perlu membuat satu baris per pengguna saat siaran dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siaran', function (Blueprint $table) {
            $table->id();
            $table->text('pesan');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('siaran_dibaca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siaran_id')->constrained('siaran')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dibaca_at');

            $table->unique(['siaran_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siaran_dibaca');
        Schema::dropIfExists('siaran');
    }
};
