<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spm_import_rows') || ! Schema::hasTable('spm') || $this->foreignKeySudahAda()) {
            return;
        }

        Schema::table('spm_import_rows', function (Blueprint $table) {
            $table->foreign('spm_id', 'spm_import_rows_spm_id_foreign')
                ->references('id')->on('spm')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spm_import_rows') || ! $this->foreignKeySudahAda()) {
            return;
        }

        Schema::table('spm_import_rows', function (Blueprint $table) {
            $table->dropForeign('spm_import_rows_spm_id_foreign');
        });
    }

    private function foreignKeySudahAda(): bool
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'spm_import_rows')
                ->where('COLUMN_NAME', 'spm_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists(),
            'sqlite' => collect(DB::select("PRAGMA foreign_key_list('spm_import_rows')"))
                ->contains(fn ($fk) => ($fk->from ?? null) === 'spm_id' && ($fk->table ?? null) === 'spm'),
            default => false,
        };
    }
};
