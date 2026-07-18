<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program',
    'kegiatan',
    'sub_kegiatan',
    'kode_rekening',
    'uraian_rekening',
    'tagging_id',
    'pagu',
    'aktif',
])]
class MasterAnggaran extends Model
{
    protected $table = 'master_anggaran';

    protected function casts(): array
    {
        return [
            'pagu' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function tagging(): BelongsTo
    {
        return $this->belongsTo(Tagging::class);
    }

    public function npd(): HasMany
    {
        return $this->hasMany(Npd::class);
    }

    /**
     * KEU ditentukan dari prefix sub_kegiatan: 6.01.01 -> KEU 1,
     * 6.01.02/6.01.03 -> KEU 2. Null kalau tidak dikenali.
     */
    public function tentukanKeu(): ?string
    {
        return match (true) {
            str_starts_with($this->sub_kegiatan, '6.01.01') => '1',
            str_starts_with($this->sub_kegiatan, '6.01.02'),
            str_starts_with($this->sub_kegiatan, '6.01.03') => '2',
            default => null,
        };
    }

    /**
     * Total nominal seluruh NPD terkait, kecuali yang batal/dibatalkan
     * (Draft tetap ikut dihitung karena dananya sudah dipesan).
     */
    public function totalRealisasi(): float
    {
        return (float) $this->npd()->where('status', 'not like', '%batal%')->sum('nominal');
    }

    /** Sisa Anggaran = Pagu - total realisasi. Ini yang dipakai untuk validasi NPD, bukan pagu. */
    public function sisaAnggaran(): float
    {
        return (float) $this->pagu - $this->totalRealisasi();
    }

    /**
     * Sisa Anggaran SEBELUM $npd dibuat: pagu dikurangi seluruh NPD (non-batal)
     * pada rekening ini yang tercatat lebih dulu (id lebih kecil). Dipakai di
     * cetak NPD, kolom "Sisa Anggaran" di tabel rincian.
     */
    public function sisaAnggaranSebelum(Npd $npd): float
    {
        $terpakaiSebelum = (float) $this->npd()
            ->where('id', '<', $npd->id)
            ->where('status', 'not like', '%batal%')
            ->sum('nominal');

        return (float) $this->pagu - $terpakaiSebelum;
    }
}
