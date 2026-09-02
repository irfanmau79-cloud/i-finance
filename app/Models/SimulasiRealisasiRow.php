<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'simulasi_realisasi_id',
    'master_anggaran_id',
    'program',
    'kegiatan',
    'sub_kegiatan',
    'sub_kegiatan_kunci',
    'kode_rekening',
    'uraian_rekening',
    'tagging_nama',
    'pagu',
    'proyeksi_total',
])]
class SimulasiRealisasiRow extends Model
{
    protected $table = 'simulasi_realisasi_rows';

    protected function casts(): array
    {
        return [
            'pagu' => 'decimal:2',
            'proyeksi_total' => 'decimal:2',
        ];
    }

    public function simulasiRealisasi(): BelongsTo
    {
        return $this->belongsTo(SimulasiRealisasi::class);
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SimulasiRealisasiItem::class, 'simulasi_realisasi_row_id')
            ->orderBy('urutan')
            ->orderBy('id');
    }

    /**
     * Lampirkan realisasi BERJALAN yang terkini (NPD Selesai + SPM LS, neto
     * pengembalian) dari MasterAnggaran ke tiap baris, lalu turunkan angka
     * proyeksinya. Semua dibatch dalam satu query supaya tidak N+1 - pola yang
     * sama dengan SimulasiAnggaranRow::lampirkanRealisasi().
     *
     * Realisasi sengaja TIDAK disimpan di tabel: ia harus selalu mencerminkan
     * transaksi terbaru, sesuai aturan pokok aplikasi bahwa realisasi dihitung,
     * tidak pernah dicatat sebagai angka statis. Baris yang master aslinya
     * sudah dihapus mendapat 0.
     *
     * Atribut sementara yang ditempelkan:
     *   realisasi          - belanja yang SUDAH terjadi
     *   sisa_anggaran      - pagu - realisasi (sisa hari ini)
     *   realisasi_estimasi - realisasi + proyeksi_total (perkiraan akhir tahun)
     *   sisa_estimasi      - pagu - realisasi_estimasi (negatif berarti
     *                        diperkirakan melebihi pagu)
     *
     * @param  Collection<int, self>  $rows
     * @return Collection<int, self>
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
            $pagu = (float) $row->pagu;
            $realisasi = (float) ($realisasiById->get($row->master_anggaran_id) ?? 0.0);
            $estimasi = $realisasi + (float) $row->proyeksi_total;

            $row->realisasi = $realisasi;
            $row->sisa_anggaran = $pagu - $realisasi;
            $row->realisasi_estimasi = $estimasi;
            $row->sisa_estimasi = $pagu - $estimasi;
        });

        return $rows;
    }
}
