<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bantex_spj', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('arsip_spj')) {
            DB::table('arsip_spj')->select('lokasi')->whereNotNull('lokasi')->distinct()->orderBy('lokasi')
                ->each(fn ($row) => DB::table('bantex_spj')->insertOrIgnore([
                    'nama' => $row->lokasi, 'aktif' => true, 'created_at' => now(), 'updated_at' => now(),
                ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bantex_spj');
    }
};
