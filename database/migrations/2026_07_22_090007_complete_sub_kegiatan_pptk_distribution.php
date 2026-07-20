<?php

use App\Models\MasterAnggaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $bppGanda = DB::table('kpa')
            ->select('bpp_pegawai_id', DB::raw('COUNT(*) jumlah'))
            ->where('aktif', true)
            ->groupBy('bpp_pegawai_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($bppGanda) {
            throw new RuntimeException("BPP pegawai ID {$bppGanda->bpp_pegawai_id} dipakai oleh lebih dari satu KPA aktif.");
        }

        $rantaiPptk = DB::table('pelimpahan')->get(['id', 'kode_sub_kegiatan', 'kpa_id', 'pptk_pegawai_id']);
        $pptkLintasKpa = $rantaiPptk->groupBy('pptk_pegawai_id')
            ->first(fn ($rows) => $rows->pluck('kpa_id')->unique()->count() > 1);
        if ($pptkLintasKpa) {
            throw new RuntimeException("PPTK pegawai ID {$pptkLintasKpa->first()->pptk_pegawai_id} berada di lebih dari satu rantai KPA.");
        }

        $rantaiTidakValid = DB::table('pelimpahan as p')
            ->join('kpa as k', 'k.id', '=', 'p.kpa_id')
            ->join('pegawai as pk', 'pk.id', '=', 'k.kpa_pegawai_id')
            ->join('pegawai as pb', 'pb.id', '=', 'k.bpp_pegawai_id')
            ->join('pegawai as pp', 'pp.id', '=', 'p.pptk_pegawai_id')
            ->where(function ($query) {
                $query->where('k.aktif', false)
                    ->orWhere('pk.aktif', false)
                    ->orWhere('pb.aktif', false)
                    ->orWhere('pp.aktif', false);
            })->value('p.id');
        if ($rantaiTidakValid) {
            throw new RuntimeException("Pelimpahan ID {$rantaiTidakValid} memiliki pejabat nonaktif dan harus diperbaiki sebelum migrasi.");
        }

        $scopeAmbigu = DB::table('master_anggaran')->where('aktif', true)->get(['program', 'sub_kegiatan'])
            ->groupBy(fn ($row) => MasterAnggaran::normalisasiKunci($row->sub_kegiatan))
            ->first(fn ($rows) => $rows->map(fn ($row) => MasterAnggaran::normalisasiKunci($row->program))->unique()->count() > 1);
        if ($scopeAmbigu) {
            throw new RuntimeException("Sub Kegiatan {$scopeAmbigu->first()->sub_kegiatan} ditemukan pada beberapa program; lingkup pelimpahan lama tidak dapat diinferensikan dengan aman.");
        }

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('program_normal')->nullable()->after('program');
            $table->string('kegiatan_normal')->nullable()->after('kegiatan');
            $table->string('sub_kegiatan_normal')->nullable()->after('sub_kegiatan');
            $table->string('program_kunci')->nullable()->after('program_normal');
            $table->string('sub_kegiatan_kunci')->nullable()->after('sub_kegiatan_normal');
            $table->index(['aktif', 'program_kunci', 'sub_kegiatan_kunci'], 'master_anggaran_scope_aktif_index');
        });

        DB::transaction(function () {
            DB::table('master_anggaran')->orderBy('id')->chunkById(250, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('master_anggaran')->where('id', $row->id)->update([
                        'program_normal' => MasterAnggaran::normalisasiTeks($row->program),
                        'kegiatan_normal' => MasterAnggaran::normalisasiTeks($row->kegiatan),
                        'sub_kegiatan_normal' => MasterAnggaran::normalisasiTeks($row->sub_kegiatan),
                        'program_kunci' => MasterAnggaran::normalisasiKunci($row->program),
                        'sub_kegiatan_kunci' => MasterAnggaran::normalisasiKunci($row->sub_kegiatan),
                    ]);
                }
            }, 'id');
        });

        Schema::create('kpa_pptk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpa_id')->constrained('kpa')->restrictOnDelete();
            $table->foreignId('pptk_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamp('dinonaktifkan_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('pptk_aktif_unik')->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN pptk_pegawai_id ELSE NULL END');
            $table->unique('pptk_aktif_unik', 'kpa_pptk_satu_rantai_aktif_unique');
            $table->index(['kpa_id', 'aktif']);
        });

        Schema::table('pelimpahan', function (Blueprint $table) {
            $table->string('program_normal')->nullable()->after('kode_sub_kegiatan');
            $table->string('program_kunci')->nullable()->after('program_normal');
            $table->string('sub_kegiatan_kunci')->nullable()->after('program_kunci');
            $table->foreignId('kpa_pptk_id')->nullable()->after('pptk_pegawai_id')->constrained('kpa_pptk')->restrictOnDelete();
            $table->boolean('aktif')->default(true)->after('kpa_pptk_id');
            $table->timestamp('dinonaktifkan_at')->nullable()->after('aktif');
            $table->foreignId('dibuat_oleh')->nullable()->after('dinonaktifkan_at')->constrained('users')->nullOnDelete();
        });

        DB::transaction(function () use ($rantaiPptk) {
            $masterScopes = DB::table('master_anggaran')
                ->get(['program_normal', 'program_kunci', 'sub_kegiatan_normal', 'sub_kegiatan_kunci'])
                ->groupBy('sub_kegiatan_kunci');
            $scopeAktif = [];

            foreach ($rantaiPptk->sortBy('id') as $row) {
                $subKunci = MasterAnggaran::normalisasiKunci($row->kode_sub_kegiatan);
                $scope = $masterScopes->get($subKunci)?->first();
                $programKunci = $scope?->program_kunci ?? '';
                $scopeKey = $programKunci.'|'.$subKunci;

                if (isset($scopeAktif[$scopeKey]) && $scopeAktif[$scopeKey] !== "{$row->kpa_id}|{$row->pptk_pegawai_id}") {
                    throw new RuntimeException("Sub Kegiatan {$row->kode_sub_kegiatan} memiliki rantai aktif yang bertentangan.");
                }

                $mappingId = DB::table('kpa_pptk')
                    ->where('kpa_id', $row->kpa_id)
                    ->where('pptk_pegawai_id', $row->pptk_pegawai_id)
                    ->where('aktif', true)
                    ->value('id');
                if (! $mappingId) {
                    $mappingId = DB::table('kpa_pptk')->insertGetId([
                        'kpa_id' => $row->kpa_id,
                        'pptk_pegawai_id' => $row->pptk_pegawai_id,
                        'aktif' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $tetapAktif = ! isset($scopeAktif[$scopeKey]);
                $scopeAktif[$scopeKey] = "{$row->kpa_id}|{$row->pptk_pegawai_id}";
                DB::table('pelimpahan')->where('id', $row->id)->update([
                    'kode_sub_kegiatan' => $scope?->sub_kegiatan_normal ?? MasterAnggaran::normalisasiTeks($row->kode_sub_kegiatan),
                    'program_normal' => $scope?->program_normal,
                    'program_kunci' => $programKunci,
                    'sub_kegiatan_kunci' => $subKunci,
                    'kpa_pptk_id' => $mappingId,
                    'aktif' => $tetapAktif,
                    'dinonaktifkan_at' => $tetapAktif ? null : now(),
                ]);
            }
        });

        Schema::table('pelimpahan', function (Blueprint $table) {
            $table->dropUnique('pelimpahan_kode_sub_kegiatan_unique');
            $table->unsignedTinyInteger('aktif_unik')->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN 1 ELSE NULL END');
            $table->unique(
                ['sub_kegiatan_kunci', 'aktif_unik'],
                'pelimpahan_satu_scope_aktif_unique'
            );
            $table->index(['kpa_id', 'pptk_pegawai_id', 'aktif'], 'pelimpahan_rantai_aktif_index');
        });

        Schema::table('kpa', function (Blueprint $table) {
            $table->unsignedBigInteger('bpp_pegawai_aktif_unik')->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN bpp_pegawai_id ELSE NULL END');
            $table->unique('bpp_pegawai_aktif_unik', 'kpa_bpp_satu_aktif_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('pelimpahan')->where('aktif', false)->exists()) {
            throw new RuntimeException('Rollback ditolak karena ada histori reassignment Pelimpahan yang harus dipertahankan.');
        }

        Schema::table('kpa', function (Blueprint $table) {
            $table->dropUnique('kpa_bpp_satu_aktif_unique');
            $table->dropColumn('bpp_pegawai_aktif_unik');
        });

        Schema::table('pelimpahan', function (Blueprint $table) {
            $table->dropUnique('pelimpahan_satu_scope_aktif_unique');
            $table->dropIndex('pelimpahan_rantai_aktif_index');
            $table->dropColumn('aktif_unik');
            $table->dropForeign(['dibuat_oleh']);
            $table->dropForeign(['kpa_pptk_id']);
            $table->dropColumn([
                'program_normal', 'program_kunci', 'sub_kegiatan_kunci', 'kpa_pptk_id',
                'aktif', 'dinonaktifkan_at', 'dibuat_oleh',
            ]);
            $table->unique('kode_sub_kegiatan');
        });

        Schema::dropIfExists('kpa_pptk');

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->dropIndex('master_anggaran_scope_aktif_index');
            $table->dropColumn([
                'program_normal', 'kegiatan_normal', 'sub_kegiatan_normal',
                'program_kunci', 'sub_kegiatan_kunci',
            ]);
        });
    }
};
