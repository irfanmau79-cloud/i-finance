<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->unique(
                ['jenis_spm', 'nomor_dokumen', 'tanggal_dokumen'],
                'spm_jenis_nomor_tanggal_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('spm', function (Blueprint $table) {
            $table->dropUnique('spm_jenis_nomor_tanggal_unique');
        });
    }
};
