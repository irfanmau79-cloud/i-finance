<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Pejabat level OPD: Pengguna Anggaran (PA) & Bendahara Pengeluaran.
 * Selalu satu baris aktif — lihat aktif()/simpan().
 */
#[Fillable(['pa_pegawai_id', 'bendahara_pengeluaran_pegawai_id', 'aktif'])]
class PejabatOpd extends Model
{
    protected $table = 'pejabat_opd';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function paPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pa_pegawai_id');
    }

    public function bendaharaPengeluaranPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'bendahara_pengeluaran_pegawai_id');
    }

    public static function aktif(): ?self
    {
        return self::where('aktif', true)->first();
    }

    /** Set/ubah pejabat OPD. Selalu satu baris aktif — pakai yang sudah ada kalau ada, buat baru kalau belum. */
    public static function simpan(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $rows = self::query()->lockForUpdate()->orderBy('id')->get();
            $row = $rows->firstWhere('aktif', true) ?? $rows->first() ?? new self;

            self::query()
                ->when($row->exists, fn ($query) => $query->whereKeyNot($row->getKey()))
                ->where('aktif', true)
                ->update(['aktif' => false]);

            $row->fill($data);
            $row->aktif = true;
            $row->save();

            return $row;
        });
    }
}
