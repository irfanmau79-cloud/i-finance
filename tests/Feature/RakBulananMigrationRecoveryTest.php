<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RakBulananMigrationRecoveryTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config([
            'database.default' => 'migration_recovery',
            'database.connections.migration_recovery' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('migration_recovery');
        DB::setDefaultConnection('migration_recovery');
        Schema::clearResolvedInstance('db.schema');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('master_anggaran', function (Blueprint $table) {
            $table->id();
            $table->string('sub_kegiatan');
            $table->string('sub_kegiatan_kunci');
            $table->string('kode_rekening', 50);
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('migration_recovery');
        DB::purge('migration_recovery');
        DB::setDefaultConnection($this->originalConnection);
        config(['database.default' => $this->originalConnection]);
        Schema::clearResolvedInstance('db.schema');

        parent::tearDown();
    }

    public function test_clean_old_schema_migrates_to_target_and_preserves_rows(): void
    {
        $this->createOldSchema();
        $masterId = $this->insertMaster('Sub A', 'sub a', '5.1.01');
        DB::table('rak_bulanan')->insert([
            'master_anggaran_id' => $masterId, 'tahun' => 2026, 'bulan' => 1, 'target' => 1000,
        ]);

        $this->migration()->up();

        $this->assertTargetSchema();
        $this->assertSame(1, DB::table('rak_bulanan')->count());
        $row = DB::table('rak_bulanan')->first();
        $this->assertSame('Sub A', $row->sub_kegiatan);
        $this->assertSame('sub a', $row->sub_kegiatan_kunci);
        $this->assertSame('5.1.01', $row->kode_rekening);
        $this->assertSame(1000.0, (float) $row->target);
    }

    public function test_current_partial_schema_with_new_columns_and_old_fk_index_resumes_safely(): void
    {
        $this->createOldSchema(partial: true);
        $masterId = $this->insertMaster('Sub Partial', 'sub partial', '5.1.02');
        DB::table('rak_bulanan')->insert([
            'master_anggaran_id' => $masterId, 'tahun' => 2026, 'bulan' => 2, 'target' => 2000,
        ]);

        $this->assertTrue(Schema::hasForeignKey('rak_bulanan', ['master_anggaran_id']));
        $this->assertTrue(Schema::hasIndex('rak_bulanan', ['master_anggaran_id', 'tahun', 'bulan'], 'unique'));

        $this->migration()->up();

        $this->assertTargetSchema();
        $this->assertSame(1, DB::table('rak_bulanan')->count());
        $this->assertSame('sub partial', DB::table('rak_bulanan')->value('sub_kegiatan_kunci'));
    }

    public function test_existing_equivalent_target_unique_is_not_duplicated(): void
    {
        $this->createOldSchema(partial: true);
        $masterId = $this->insertMaster('Sub Existing', 'sub existing', '5.1.03');
        DB::table('rak_bulanan')->insert([
            'master_anggaran_id' => $masterId,
            'sub_kegiatan' => 'Sub Existing', 'sub_kegiatan_kunci' => 'sub existing', 'kode_rekening' => '5.1.03',
            'tahun' => 2026, 'bulan' => 3, 'target' => 3000,
        ]);
        Schema::table('rak_bulanan', fn (Blueprint $table) => $table->unique(
            ['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan'],
            'rak_existing_equivalent_unique'
        ));

        $this->migration()->up();

        $matches = collect(Schema::getIndexes('rak_bulanan'))->filter(
            fn (array $index) => $index['unique'] && $index['columns'] === ['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan']
        );
        $this->assertCount(1, $matches);
        $this->assertSame('rak_existing_equivalent_unique', $matches->first()['name']);
        $this->assertSame(1, DB::table('rak_bulanan')->count());
    }

    public function test_completed_target_schema_can_run_twice_without_duplicate_columns_or_indexes(): void
    {
        $this->createTargetSchema();
        DB::table('rak_bulanan')->insert([
            'sub_kegiatan' => 'Sub Complete', 'sub_kegiatan_kunci' => 'sub complete', 'kode_rekening' => '5.1.04',
            'tahun' => 2026, 'bulan' => 4, 'target' => 4000,
        ]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertTargetSchema();
        $this->assertSame(1, DB::table('rak_bulanan')->count());
        $this->assertCount(9, Schema::getColumns('rak_bulanan'));
        $targetIndexes = collect(Schema::getIndexes('rak_bulanan'))->filter(
            fn (array $index) => $index['unique'] && $index['columns'] === ['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan']
        );
        $this->assertCount(1, $targetIndexes);
    }

    public function test_identical_duplicates_are_reported_before_old_fk_or_index_is_removed(): void
    {
        $this->assertDuplicateIsBlocked(5000, 5000, 'duplikat identik');
    }

    public function test_conflicting_duplicates_are_reported_before_old_fk_or_index_is_removed(): void
    {
        $this->assertDuplicateIsBlocked(5000, 6000, 'konflik nilai');
    }

    private function assertDuplicateIsBlocked(float $firstTarget, float $secondTarget, string $message): void
    {
        $this->createOldSchema();
        $first = $this->insertMaster('Sub Duplicate', 'sub duplicate', '5.1.05');
        $second = $this->insertMaster('Sub Duplicate', 'sub duplicate', '5.1.05');
        DB::table('rak_bulanan')->insert([
            ['master_anggaran_id' => $first, 'tahun' => 2026, 'bulan' => 5, 'target' => $firstTarget],
            ['master_anggaran_id' => $second, 'tahun' => 2026, 'bulan' => 5, 'target' => $secondTarget],
        ]);

        try {
            $this->migration()->up();
            $this->fail('Migrasi seharusnya menolak duplicate identity.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame(2, DB::table('rak_bulanan')->count());
        $this->assertTrue(Schema::hasColumn('rak_bulanan', 'master_anggaran_id'));
        $this->assertTrue(Schema::hasForeignKey('rak_bulanan', ['master_anggaran_id']));
        $this->assertTrue(Schema::hasIndex('rak_bulanan', ['master_anggaran_id', 'tahun', 'bulan'], 'unique'));
    }

    private function createOldSchema(bool $partial = false): void
    {
        Schema::create('rak_bulanan', function (Blueprint $table) use ($partial) {
            $table->id();
            if ($partial) {
                $table->string('sub_kegiatan')->nullable();
                $table->string('sub_kegiatan_kunci')->nullable();
                $table->string('kode_rekening', 50)->nullable();
            }
            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('target', 18, 2);
            $table->unique(['master_anggaran_id', 'tahun', 'bulan']);
        });
    }

    private function createTargetSchema(): void
    {
        Schema::create('rak_bulanan', function (Blueprint $table) {
            $table->id();
            $table->string('sub_kegiatan');
            $table->string('sub_kegiatan_kunci');
            $table->string('kode_rekening', 50);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('target', 18, 2);
            $table->timestamps();
            $table->unique(['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan'], 'rak_bulanan_identitas_unique');
            $table->index(['sub_kegiatan_kunci', 'kode_rekening'], 'rak_bulanan_sub_kegiatan_kunci_kode_rekening_index');
        });
    }

    private function insertMaster(string $subKegiatan, string $key, string $code): int
    {
        return (int) DB::table('master_anggaran')->insertGetId([
            'sub_kegiatan' => $subKegiatan,
            'sub_kegiatan_kunci' => $key,
            'kode_rekening' => $code,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_26_090000_restructure_rak_bulanan_key.php');
    }

    private function assertTargetSchema(): void
    {
        $this->assertFalse(Schema::hasColumn('rak_bulanan', 'master_anggaran_id'));
        foreach (['sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening'] as $column) {
            $metadata = collect(Schema::getColumns('rak_bulanan'))->firstWhere('name', $column);
            $this->assertNotNull($metadata);
            $this->assertFalse($metadata['nullable']);
        }

        $this->assertFalse(Schema::hasForeignKey('rak_bulanan', ['master_anggaran_id']));
        $this->assertTrue(Schema::hasIndex(
            'rak_bulanan',
            ['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan'],
            'unique'
        ));
    }
}
