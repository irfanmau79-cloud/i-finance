<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->string('sumber_data', 30)->default('aplikasi')->after('detail_json');
            $table->foreignId('import_historis_id')->nullable()->after('sumber_data')
                ->constrained('npd_historis_imports')->nullOnDelete();
            $table->unsignedInteger('import_baris')->nullable()->after('import_historis_id');
            $table->char('import_identity_hash', 64)->nullable()->after('import_baris');
            $table->string('tagging_snapshot')->nullable()->after('import_identity_hash');
            $table->unique('import_identity_hash', 'npd_import_identity_unique');
            $table->index(['import_historis_id', 'import_baris']);
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropUnique('npd_import_identity_unique');
            $table->dropIndex(['import_historis_id', 'import_baris']);
            $table->dropForeign(['import_historis_id']);
            $table->dropColumn(['sumber_data', 'import_historis_id', 'import_baris', 'import_identity_hash', 'tagging_snapshot']);
        });
    }
};
