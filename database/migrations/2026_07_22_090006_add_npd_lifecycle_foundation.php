<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_NPD = [
        'Draft NPD - PPTK',
        'Draft NPD - BPP',
        'Verifikasi - Verifikator',
        'NPD Disetujui - BPP',
        'Selesai',
        'Dibatalkan',
    ];

    private const STATUS_SP = [
        'Diterima PPTK',
        'Draft NPD - PPTK',
        'Draft NPD - BPP',
        'Verifikasi - Verifikator',
        'NPD Disetujui - BPP',
        'Selesai',
        'Dibatalkan',
    ];

    public function up(): void
    {
        $duplikat = DB::table('npd')
            ->select('keu', 'tahun', 'nomor_urut', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('nomor_urut')
            ->groupBy('keu', 'tahun', 'nomor_urut')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplikat) {
            throw new RuntimeException(
                "Nomor NPD duplikat ditemukan pada KEU {$duplikat->keu}, tahun {$duplikat->tahun}, nomor {$duplikat->nomor_urut}. Rapikan data sebelum migrasi."
            );
        }

        Schema::create('npd_histori_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_id')->constrained('npd')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 50);
            $table->string('status_asal', 50)->nullable();
            $table->string('status_tujuan', 50);
            $table->text('catatan')->nullable();
            $table->unsignedInteger('nomor_urut');
            $table->timestamps();
            $table->unique(['npd_id', 'nomor_urut']);
        });

        Schema::table('npd', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::transaction(function () {
            DB::table('npd')->where('status', 'Dikembalikan')->update(['status' => 'Draft NPD - PPTK']);
            DB::table('surat_perintah')->where('status', 'Dikembalikan')->update(['status' => 'Draft NPD - PPTK']);

            $waktu = now();
            DB::table('npd')->orderBy('id')->chunkById(500, function ($npds) use ($waktu) {
                DB::table('npd_histori_status')->insert($npds->map(fn ($npd) => [
                    'npd_id' => $npd->id,
                    'user_id' => $npd->dibuat_oleh,
                    'aksi' => 'migrasi_status_awal',
                    'status_asal' => null,
                    'status_tujuan' => $npd->status,
                    'catatan' => 'Snapshot status saat fondasi histori diterapkan.',
                    'nomor_urut' => 1,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ])->all());
            }, 'id');
        });

        $this->ubahEnum('npd', self::STATUS_NPD, false);
        $this->ubahEnum('surat_perintah', self::STATUS_SP, true);

        Schema::table('npd', function (Blueprint $table) {
            $table->dropUnique('npd_keu_bulan_tahun_nomor_urut_unique');
            $table->unique(['keu', 'tahun', 'nomor_urut'], 'npd_keu_tahun_nomor_urut_unique');
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropUnique('npd_keu_tahun_nomor_urut_unique');
            $table->unique(['keu', 'bulan', 'tahun', 'nomor_urut']);
        });

        DB::table('npd')->where('status', 'Dibatalkan')->update(['status' => 'Draft NPD - PPTK']);
        DB::table('surat_perintah')->where('status', 'Dibatalkan')->update(['status' => 'Diterima PPTK']);

        $legacyNpd = [...array_values(array_diff(self::STATUS_NPD, ['Dibatalkan'])), 'Dikembalikan'];
        $legacySp = [...array_values(array_diff(self::STATUS_SP, ['Dibatalkan'])), 'Dikembalikan'];
        $this->ubahEnum('npd', $legacyNpd, false);
        $this->ubahEnum('surat_perintah', $legacySp, true);

        Schema::table('npd', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('npd_histori_status');
    }

    private function ubahEnum(string $table, array $nilai, bool $nullable): void
    {
        if (DB::getDriverName() === 'mysql') {
            $enum = collect($nilai)->map(fn (string $status) => DB::getPdo()->quote($status))->implode(',');
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM({$enum}) {$null}");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($nilai, $nullable) {
            $column = $blueprint->enum('status', $nilai);
            if ($nullable) {
                $column->nullable();
            }
            $column->change();
        });
    }
};
