<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Pemberitahuan dari Tim Keuangan" di halaman Monitoring SP. Fungsi
     * getPengumuman/setPengumuman dipanggil dari client di gas-lama
     * (index.html sekitar baris 3469-3512) tapi backend-nya tidak pernah
     * dibuat — dibangun dari nol di sini. Cukup satu baris aktif
     * (pengumuman terkini), di-update di tempat, bukan riwayat.
     */
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->text('teks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
