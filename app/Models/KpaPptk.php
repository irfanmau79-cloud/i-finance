<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kpa_id', 'pptk_pegawai_id', 'aktif', 'dinonaktifkan_at'])]
class KpaPptk extends Model
{
    protected $table = 'kpa_pptk';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'dinonaktifkan_at' => 'datetime',
        ];
    }

    public function kpa(): BelongsTo
    {
        return $this->belongsTo(Kpa::class);
    }

    public function pptkPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pptk_pegawai_id');
    }

    public function pelimpahan(): HasMany
    {
        return $this->hasMany(Pelimpahan::class);
    }
}
