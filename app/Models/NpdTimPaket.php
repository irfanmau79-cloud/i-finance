<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['npd_tim_id', 'cluster', 'wilayah', 'lama_hari', 'tarif_uh', 'malam', 'tarif_akom'])]
class NpdTimPaket extends Model
{
    protected $table = 'npd_tim_paket';

    protected function casts(): array
    {
        return [
            'tarif_uh' => 'decimal:2',
            'tarif_akom' => 'decimal:2',
        ];
    }

    public function npdTim(): BelongsTo
    {
        return $this->belongsTo(NpdTim::class);
    }

    /** Bentuk array datar dipakai App\Helpers\NpdPerjalananHitung. */
    public function toHitungArray(): array
    {
        return [
            'cluster' => $this->cluster,
            'wilayah' => $this->wilayah,
            'lama_hari' => (float) $this->lama_hari,
            'tarif_uh' => (float) $this->tarif_uh,
            'malam' => (float) $this->malam,
            'tarif_akom' => (float) $this->tarif_akom,
        ];
    }
}
