<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kali penyuntingan NPD oleh BPP/Verifikator - lihat App\Services\NpdRevisiService. */
#[Fillable([
    'npd_id',
    'user_id',
    'peran',
    'status_saat',
    'potret_sebelum',
    'perubahan',
])]
class NpdRevisi extends Model
{
    protected $table = 'npd_revisi';

    protected function casts(): array
    {
        return [
            'potret_sebelum' => 'array',
            'perubahan' => 'array',
        ];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
