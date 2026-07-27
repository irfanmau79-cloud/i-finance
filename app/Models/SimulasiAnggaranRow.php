<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'simulasi_anggaran_id',
    'master_anggaran_id',
    'program',
    'kegiatan',
    'sub_kegiatan',
    'sub_kegiatan_kunci',
    'kode_rekening',
    'uraian_rekening',
    'tagging_nama',
    'pagu_eksisting',
    'pagu_simulasi',
    'selisih',
])]
class SimulasiAnggaranRow extends Model
{
    protected $table = 'simulasi_anggaran_rows';

    protected function casts(): array
    {
        return [
            'pagu_eksisting' => 'decimal:2',
            'pagu_simulasi' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    public function simulasiAnggaran(): BelongsTo
    {
        return $this->belongsTo(SimulasiAnggaran::class);
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    /**
     * Lampirkan realisasi aktual TERKINI (NPD Selesai + SPM LS, bukan
     * snapshot) dari MasterAnggaran ke tiap baris sebagai atribut sementara
     * 'realisasi', dibatch dalam satu query per angka (pola sama dengan
     * AnggaranRealisasiService::rincian()) supaya tidak N+1. Baris yang
     * master_anggaran_id-nya sudah NULL (master asli sudah dihapus) mendapat 0.
     */
    public static function lampirkanRealisasi(Collection $rows): Collection
    {
        $ids = $rows->pluck('master_anggaran_id')->filter()->unique();

        $realisasiById = MasterAnggaran::query()
            ->whereIn('id', $ids)
            ->withSum(['npd as realisasi_npd_total' => fn (Builder $q) => $q->where('status', 'Selesai')], 'nominal')
            ->withSum('spmDetail as realisasi_ls_total', 'nominal')
            ->withSum(['pengembalianDetail as pengembalian_disetujui_npd_total' => fn (Builder $q) => $q
                ->whereHas('pengembalian', fn (Builder $q2) => $q2->where('status', 'disetujui')->where('dokumen_tipe', 'npd'))], 'nominal')
            ->withSum(['pengembalianDetail as pengembalian_disetujui_ls_total' => fn (Builder $q) => $q
                ->whereHas('pengembalian', fn (Builder $q2) => $q2->where('status', 'disetujui')->where('dokumen_tipe', 'spm_ls'))], 'nominal')
            ->get(['id'])
            ->mapWithKeys(fn (MasterAnggaran $m) => [$m->id => $m->realisasiAktual()]);

        $rows->each(function (self $row) use ($realisasiById) {
            $row->realisasi = (float) ($realisasiById->get($row->master_anggaran_id) ?? 0.0);
        });

        return $rows;
    }
}
