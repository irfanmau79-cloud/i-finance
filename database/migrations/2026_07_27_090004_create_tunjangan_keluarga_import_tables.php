<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunjangan_keluarga_imports', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('preview');
            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('baris_valid')->default(0);
            $table->unsignedInteger('baris_invalid')->default(0);
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tunjangan_keluarga_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('tunjangan_keluarga_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->boolean('valid');
            $table->json('pesan')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunjangan_keluarga_import_rows');
        Schema::dropIfExists('tunjangan_keluarga_imports');
    }
};
