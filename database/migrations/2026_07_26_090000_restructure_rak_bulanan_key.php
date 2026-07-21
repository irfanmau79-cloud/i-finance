<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'rak_bulanan';

    private const OLD_IDENTITY = ['master_anggaran_id', 'tahun', 'bulan'];

    private const NEW_IDENTITY = ['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan'];

    private const NEW_UNIQUE = 'rak_bulanan_identitas_unique';

    private const LOOKUP_INDEX = 'rak_bulanan_sub_kegiatan_kunci_kode_rekening_index';

    /**
     * Prompt 12A removes Tagging/master_anggaran_id from RAK identity. This
     * migration is deliberately resumable because MySQL DDL may auto-commit
     * before a later statement fails. It accepts the original schema, the
     * known partial schema, and the completed target schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new RuntimeException('Migrasi RAK dihentikan: tabel rak_bulanan tidak ditemukan.');
        }

        $this->addColumnIfMissing('sub_kegiatan', 255, 'id');
        $this->addColumnIfMissing('sub_kegiatan_kunci', 255, 'sub_kegiatan');
        $this->addColumnIfMissing('kode_rekening', 50, 'sub_kegiatan_kunci');

        if (Schema::hasColumn(self::TABLE, 'master_anggaran_id')) {
            $this->backfillFromMasterAnggaran();
        }

        $this->assertNewIdentityValuesComplete();
        $this->assertNoDuplicateNewIdentity();

        if (Schema::hasColumn(self::TABLE, 'master_anggaran_id')) {
            $this->dropExpectedMasterForeignKey();
            $this->dropExpectedOldIdentityIndex();

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('master_anggaran_id');
            });
        } else {
            $this->assertNoForeignKeyUsesMissingMasterColumn();
            $this->assertNoIndexUsesMissingMasterColumn();
        }

        $this->makeNewIdentityColumnsRequired();
        $this->ensureIndex(self::NEW_UNIQUE, self::NEW_IDENTITY, true);
        $this->ensureIndex(self::LOOKUP_INDEX, ['sub_kegiatan_kunci', 'kode_rekening'], false);
        $this->assertTargetSchema();
    }

    /**
     * Defensive rollback for development/test use only. A completed RAK with
     * rows cannot be mapped back to a Tagging-specific master_anggaran_id, so
     * down() refuses that lossy operation rather than inventing a mapping.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'master_anggaran_id') && DB::table(self::TABLE)->exists()) {
            throw new RuntimeException('Rollback RAK dihentikan: master_anggaran_id tidak dapat diturunkan secara aman dari data RAK yang sudah ada.');
        }

        $this->dropOwnedIndexIfPresent(self::NEW_UNIQUE, self::NEW_IDENTITY, true);
        $this->dropOwnedIndexIfPresent(self::LOOKUP_INDEX, ['sub_kegiatan_kunci', 'kode_rekening'], false);

        if (! Schema::hasColumn(self::TABLE, 'master_anggaran_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedBigInteger('master_anggaran_id')->nullable()->after('id');
            });
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedBigInteger('master_anggaran_id')->nullable(false)->change();
            });
        }

        $this->ensureOldForeignKeyAndIndexForDown();

        $columns = array_values(array_filter(
            ['sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening'],
            fn (string $column) => Schema::hasColumn(self::TABLE, $column)
        ));
        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function addColumnIfMissing(string $column, int $length, string $after): void
    {
        if (Schema::hasColumn(self::TABLE, $column)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($column, $length, $after) {
            $table->string($column, $length)->nullable()->after($after);
        });
    }

    private function backfillFromMasterAnggaran(): void
    {
        DB::table(self::TABLE)->orderBy('id')->chunkById(250, function ($rows) {
            $master = DB::table('master_anggaran')->whereIn('id', $rows->pluck('master_anggaran_id'))
                ->get(['id', 'sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening'])->keyBy('id');

            foreach ($rows as $row) {
                $anggaran = $master->get($row->master_anggaran_id);
                if (! $anggaran) {
                    throw new RuntimeException("RAK ID {$row->id} menunjuk master_anggaran yang tidak ditemukan.");
                }

                $updates = [];
                foreach (['sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening'] as $column) {
                    if ($row->{$column} !== null && (string) $row->{$column} !== (string) $anggaran->{$column}) {
                        throw new RuntimeException("Migrasi RAK dihentikan: nilai {$column} RAK ID {$row->id} bertentangan dengan master_anggaran.");
                    }
                    if ($row->{$column} === null) {
                        $updates[$column] = $anggaran->{$column};
                    }
                }

                if ($updates !== []) {
                    DB::table(self::TABLE)->where('id', $row->id)->update($updates);
                }
            }
        }, 'id');
    }

    private function assertNewIdentityValuesComplete(): void
    {
        $missing = DB::table(self::TABLE)
            ->whereNull('sub_kegiatan')->orWhereNull('sub_kegiatan_kunci')->orWhereNull('kode_rekening')
            ->first();

        if ($missing) {
            throw new RuntimeException("Migrasi RAK dihentikan: RAK ID {$missing->id} tidak memiliki identitas Sub Kegiatan + Kode Rekening yang lengkap.");
        }
    }

    private function assertNoDuplicateNewIdentity(): void
    {
        $duplicate = DB::table(self::TABLE)
            ->select('tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan', DB::raw('COUNT(*) jumlah'), DB::raw('COUNT(DISTINCT target) variasi'))
            ->groupBy(...self::NEW_IDENTITY)
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('tahun')->orderBy('sub_kegiatan_kunci')->orderBy('kode_rekening')->orderBy('bulan')
            ->first();

        if (! $duplicate) {
            return;
        }

        $jenis = (int) $duplicate->variasi === 1 ? 'duplikat identik' : 'konflik nilai';
        throw new RuntimeException(
            "Migrasi RAK dihentikan: ditemukan {$jenis} untuk tahun {$duplicate->tahun}, "
            ."sub kegiatan {$duplicate->sub_kegiatan_kunci}, kode rekening {$duplicate->kode_rekening}, bulan {$duplicate->bulan}. "
            .'Audit dan putuskan normalisasi secara manual; migration tidak memilih, menjumlahkan, atau menghapus data.'
        );
    }

    private function dropExpectedMasterForeignKey(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys(self::TABLE))
            ->filter(fn (array $foreignKey) => in_array('master_anggaran_id', $foreignKey['columns'], true))
            ->values();

        if ($foreignKeys->count() > 1) {
            throw new RuntimeException('Migrasi RAK dihentikan: ditemukan lebih dari satu foreign key yang melibatkan master_anggaran_id.');
        }

        $foreignKey = $foreignKeys->first();
        if (! $foreignKey) {
            return;
        }

        if ($foreignKey['columns'] !== ['master_anggaran_id']
            || $foreignKey['foreign_table'] !== 'master_anggaran'
            || $foreignKey['foreign_columns'] !== ['id']) {
            throw new RuntimeException('Migrasi RAK dihentikan: foreign key master_anggaran_id memiliki definisi yang tidak diharapkan.');
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($foreignKey) {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['master_anggaran_id']);
            } else {
                if (! $foreignKey['name']) {
                    throw new RuntimeException('Migrasi RAK dihentikan: nama foreign key master_anggaran_id tidak dapat diresolusi.');
                }
                $table->dropForeign($foreignKey['name']);
            }
        });
    }

    private function dropExpectedOldIdentityIndex(): void
    {
        $indexes = collect(Schema::getIndexes(self::TABLE));
        $involvingMaster = $indexes
            ->filter(fn (array $index) => in_array('master_anggaran_id', $index['columns'], true))
            ->values();

        foreach ($involvingMaster as $index) {
            if ($index['primary'] || ! $index['unique'] || $index['columns'] !== self::OLD_IDENTITY) {
                throw new RuntimeException("Migrasi RAK dihentikan: index {$index['name']} yang melibatkan master_anggaran_id tidak sesuai struktur lama yang dikenal.");
            }
        }

        if ($involvingMaster->count() > 1) {
            throw new RuntimeException('Migrasi RAK dihentikan: ditemukan lebih dari satu index identitas lama master_anggaran_id.');
        }

        if ($index = $involvingMaster->first()) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropUnique($index['name']));
        }
    }

    private function makeNewIdentityColumnsRequired(): void
    {
        $definitions = [
            'sub_kegiatan' => 255,
            'sub_kegiatan_kunci' => 255,
            'kode_rekening' => 50,
        ];

        foreach ($definitions as $column => $length) {
            $metadata = collect(Schema::getColumns(self::TABLE))->firstWhere('name', $column);
            if (! $metadata) {
                throw new RuntimeException("Migrasi RAK dihentikan: kolom {$column} hilang sebelum finalisasi.");
            }
            if ($metadata['nullable']) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->string($column, $length)->nullable(false)->change());
            }
        }
    }

    private function ensureIndex(string $name, array $columns, bool $unique): void
    {
        $indexes = collect(Schema::getIndexes(self::TABLE));
        $named = $indexes->firstWhere('name', $name);
        if ($named) {
            if ($named['primary'] || $named['unique'] !== $unique || $named['columns'] !== $columns) {
                throw new RuntimeException("Migrasi RAK dihentikan: index {$name} sudah ada dengan definisi yang bertentangan.");
            }

            return;
        }

        $equivalent = $indexes->first(fn (array $index) => ! $index['primary'] && $index['unique'] === $unique && $index['columns'] === $columns);
        if ($equivalent) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($name, $columns, $unique) {
            $unique ? $table->unique($columns, $name) : $table->index($columns, $name);
        });
    }

    private function assertNoForeignKeyUsesMissingMasterColumn(): void
    {
        $conflict = collect(Schema::getForeignKeys(self::TABLE))
            ->first(fn (array $foreignKey) => in_array('master_anggaran_id', $foreignKey['columns'], true));
        if ($conflict) {
            throw new RuntimeException('Migrasi RAK dihentikan: foreign key masih mengacu pada master_anggaran_id yang sudah tidak ada.');
        }
    }

    private function assertNoIndexUsesMissingMasterColumn(): void
    {
        $conflict = collect(Schema::getIndexes(self::TABLE))
            ->first(fn (array $index) => in_array('master_anggaran_id', $index['columns'], true));
        if ($conflict) {
            throw new RuntimeException("Migrasi RAK dihentikan: index {$conflict['name']} masih mengacu pada master_anggaran_id yang sudah tidak ada.");
        }
    }

    private function assertTargetSchema(): void
    {
        if (Schema::hasColumn(self::TABLE, 'master_anggaran_id')) {
            throw new RuntimeException('Migrasi RAK gagal finalisasi: master_anggaran_id masih ada.');
        }

        $target = collect(Schema::getIndexes(self::TABLE))
            ->filter(fn (array $index) => ! $index['primary'] && $index['unique'] && $index['columns'] === self::NEW_IDENTITY);
        if ($target->count() !== 1) {
            throw new RuntimeException('Migrasi RAK gagal finalisasi: unique identity Tahun + Sub Kegiatan + Kode Rekening + Bulan tidak tepat satu.');
        }
        if (in_array('tagging_id', $target->first()['columns'], true)) {
            throw new RuntimeException('Migrasi RAK gagal finalisasi: Tagging tidak boleh menjadi identitas RAK.');
        }
    }

    private function dropOwnedIndexIfPresent(string $name, array $columns, bool $unique): void
    {
        $index = collect(Schema::getIndexes(self::TABLE))->firstWhere('name', $name);
        if (! $index) {
            return;
        }
        if ($index['primary'] || $index['unique'] !== $unique || $index['columns'] !== $columns) {
            throw new RuntimeException("Rollback RAK dihentikan: index {$name} memiliki definisi yang tidak diharapkan.");
        }

        Schema::table(self::TABLE, fn (Blueprint $table) => $unique ? $table->dropUnique($name) : $table->dropIndex($name));
    }

    private function ensureOldForeignKeyAndIndexForDown(): void
    {
        $this->ensureIndex('rak_bulanan_master_anggaran_id_tahun_bulan_unique', self::OLD_IDENTITY, true);

        $foreignKeys = collect(Schema::getForeignKeys(self::TABLE));
        $masterForeignKeys = $foreignKeys->filter(fn (array $foreignKey) => in_array('master_anggaran_id', $foreignKey['columns'], true));
        if ($masterForeignKeys->count() > 1) {
            throw new RuntimeException('Rollback RAK dihentikan: lebih dari satu foreign key master_anggaran_id ditemukan.');
        }
        if ($foreignKey = $masterForeignKeys->first()) {
            if ($foreignKey['columns'] !== ['master_anggaran_id'] || $foreignKey['foreign_table'] !== 'master_anggaran' || $foreignKey['foreign_columns'] !== ['id']) {
                throw new RuntimeException('Rollback RAK dihentikan: foreign key master_anggaran_id bertentangan.');
            }

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreign('master_anggaran_id')->references('id')->on('master_anggaran')->restrictOnDelete();
        });
    }
};
