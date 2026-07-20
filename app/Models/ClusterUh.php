<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'tarif', 'jarak', 'aktif'])]
class ClusterUh extends Model
{
    protected $table = 'cluster_uh';

    /** Titik berangkat baku untuk seluruh NPD Perjalanan Dinas (lihat CodePerjalanan.gs). */
    public const ASAL_PERJALANAN = 'Kota Bandung';

    protected function casts(): array
    {
        return [
            'tarif' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function wilayah(): HasMany
    {
        return $this->hasMany(ClusterWilayah::class, 'cluster_id');
    }
}
