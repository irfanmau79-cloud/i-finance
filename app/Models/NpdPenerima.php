<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'vendor_id',
    'nama',
    'rekening',
    'bruto',
    'ppn',
    'biaya_ku_rtgs',
    'keterangan',
])]
class NpdPenerima extends Model
{
    protected $table = 'npd_penerima';

    protected function casts(): array
    {
        return [
            'bruto' => 'decimal:2',
            'ppn' => 'decimal:2',
            'biaya_ku_rtgs' => 'decimal:2',
        ];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function pphList(): HasMany
    {
        return $this->hasMany(NpdPenerimaPph::class);
    }

    /** Total seluruh baris PPh (bisa multi-jenis). */
    protected function totalPph(): Attribute
    {
        return Attribute::get(fn () => (float) $this->pphList->sum('nilai'));
    }

    /** Netto/transfer = bruto − ppn − total pph − biaya KU/RTGS. Sama seperti GAS: dihitung, tidak disimpan. */
    protected function netto(): Attribute
    {
        return Attribute::get(fn () => (float) $this->bruto - (float) $this->ppn - $this->total_pph - (float) $this->biaya_ku_rtgs);
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
