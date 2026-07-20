<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_id',
    'user_id',
    'aksi',
    'status_asal',
    'status_tujuan',
    'catatan',
    'nomor_urut',
])]
class NpdHistoriStatus extends Model
{
    protected $table = 'npd_histori_status';

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
