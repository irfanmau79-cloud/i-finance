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
}
