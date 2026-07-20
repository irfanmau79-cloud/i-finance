<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'vendor_id',
    'nama',
    'jabatan',
    'rekening',
    'jumlah_jp',
    'tarif_jp',
    'transport',
    'pph21',
    'uraian',
])]
class NpdNarasumber extends Model
{
    protected $table = 'npd_narasumber';

    protected function casts(): array
    {
        return [
            'jumlah_jp' => 'integer',
            'tarif_jp' => 'decimal:2',
            'transport' => 'decimal:2',
            'pph21' => 'decimal:2',
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

    /** honor = jumlah_jp * tarif_jp. Port dari _hitungNara() di gas-lama/CodeNarasumber.gs. */
    protected function honor(): Attribute
    {
        return Attribute::get(fn () => (float) $this->jumlah_jp * (float) $this->tarif_jp);
    }

    /** bruto = honor + transport. Ini yang menjadi nominal NPD (bukan netto). */
    protected function bruto(): Attribute
    {
        return Attribute::get(fn () => $this->honor + (float) $this->transport);
    }

    /** netto = bruto - pph21 — jumlah yang benar-benar diterima narasumber. */
    protected function netto(): Attribute
    {
        return Attribute::get(fn () => $this->bruto - (float) $this->pph21);
    }
}
