<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'program',
    'kegiatan',
    'sub_kegiatan',
    'kode_rekening',
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
}
