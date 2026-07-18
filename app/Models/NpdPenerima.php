<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'vendor_id',
    'nama',
    'rekening',
    'bruto',
    'pph',
    'biaya',
    'keterangan',
])]
class NpdPenerima extends Model
{
    protected $table = 'npd_penerima';

    protected function casts(): array
    {
        return [
            'bruto' => 'decimal:2',
            'pph' => 'decimal:2',
            'biaya' => 'decimal:2',
        ];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
