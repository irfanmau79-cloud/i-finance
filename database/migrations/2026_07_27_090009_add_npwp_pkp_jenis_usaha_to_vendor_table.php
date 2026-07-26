<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->string('npwp', 30)->nullable()->after('rekening');
            $table->boolean('pkp')->default(false)->after('npwp');
            $table->string('jenis_usaha', 100)->nullable()->after('pkp');
        });
    }

    public function down(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'pkp', 'jenis_usaha']);
        });
    }
};
