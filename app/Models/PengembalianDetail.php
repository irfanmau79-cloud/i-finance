<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris mata anggaran pada satu Pengembalian. */
#[Fillable(['pengembalian_id', 'master_anggaran_id', 'nominal'])]
class PengembalianDetail extends Model
{
    protected $table = 'pengembalian_detail';

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function pengembalian(): BelongsTo
    {
        return $this->belongsTo(Pengembalian::class);
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
