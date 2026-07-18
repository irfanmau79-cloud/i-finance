<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_penerima_id',
    'jenis',
    'nilai',
])]
class NpdPenerimaPph extends Model
{
    protected $table = 'npd_penerima_pph';

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function npdPenerima(): BelongsTo
    {
        return $this->belongsTo(NpdPenerima::class);
    }
}
