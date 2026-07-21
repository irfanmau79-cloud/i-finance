<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationDependencyOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_spm_parent_exists_and_deferred_import_foreign_key_is_present(): void
    {
        $this->assertTrue(Schema::hasTable('spm'));
        $this->assertTrue(Schema::hasTable('spm_import_rows'));

        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('spm_import_rows')"));
        $this->assertTrue($foreignKeys->contains(fn ($fk) => $fk->from === 'spm_id' && $fk->table === 'spm'));

        $this->assertGreaterThan(0, strcmp(
            '2026_07_26_090003_add_spm_import_rows_spm_foreign_key.php',
            '2026_07_21_090000_create_spm_table.php'
        ));
    }
}
