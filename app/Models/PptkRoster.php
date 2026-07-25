<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daftar pegawai berstatus PPTK di OPD ini. Sengaja TIDAK terikat ke KPA
 * tertentu — lihat migration create_pptk_roster_table untuk alasannya.
 */
#[Fillable(['pegawai_id', 'aktif', 'dinonaktifkan_at'])]
class PptkRoster extends Model
{
    protected $table = 'pptk_roster';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'dinonaktifkan_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
