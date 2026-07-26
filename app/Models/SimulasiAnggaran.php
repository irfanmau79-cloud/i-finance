<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'keterangan', 'user_id', 'total_pagu_eksisting', 'total_pagu_simulasi', 'total_selisih'])]
class SimulasiAnggaran extends Model
{
    protected $table = 'simulasi_anggaran';

    protected function casts(): array
    {
        return [
            'total_pagu_eksisting' => 'decimal:2',
            'total_pagu_simulasi' => 'decimal:2',
            'total_selisih' => 'decimal:2',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SimulasiAnggaranRow::class)->orderBy('sub_kegiatan')->orderBy('kode_rekening');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
