<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['npd_id', 'jenis_dokumen', 'lokasi', 'catatan', 'ditetapkan_oleh', 'ditetapkan_at', 'aktif'])]
class ArsipSpj extends Model
{
    protected $table = 'arsip_spj';

    protected function casts(): array
    {
        return ['ditetapkan_at' => 'datetime', 'aktif' => 'boolean'];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }
}
