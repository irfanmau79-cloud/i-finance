<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu nominal pagu untuk satu mata anggaran pada satu versi DPA. */
#[Fillable(['versi_pagu_id', 'master_anggaran_id', 'pagu', 'aktif'])]
class VersiPaguDetail extends Model
{
    protected $table = 'versi_pagu_detail';

    protected function casts(): array
    {
        return [
            'pagu' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function versi(): BelongsTo
    {
        return $this->belongsTo(VersiPagu::class, 'versi_pagu_id');
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
