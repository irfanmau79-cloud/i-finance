<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->timestamp('spj_verified_at')->nullable()->after('status');
            $table->foreignId('spj_verified_by')->nullable()->after('spj_verified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spj_verified_by');
            $table->dropColumn('spj_verified_at');
        });
    }
};
