<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'nama',
    'pangkat',
    'nip',
    'rekening',
    'volume_kontribusi',
    'tarif_kontribusi',
    'volume_mooc',
    'tarif_mooc',
    'hari_uh',
    'tarif_uh',
    'volume_akomodasi',
    'tarif_akomodasi',
    'hari_saku',
    'tarif_saku',
    'transport',
])]
class NpdPeserta extends Model
{
    protected $table = 'npd_peserta';

    protected function casts(): array
    {
        return [
            'volume_kontribusi' => 'integer',
            'tarif_kontribusi' => 'decimal:2',
            'volume_mooc' => 'integer',
            'tarif_mooc' => 'decimal:2',
            'hari_uh' => 'integer',
            'tarif_uh' => 'decimal:2',
            'volume_akomodasi' => 'integer',
            'tarif_akomodasi' => 'decimal:2',
            'hari_saku' => 'integer',
            'tarif_saku' => 'decimal:2',
            'transport' => 'decimal:2',
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

    /** jumlah_kontribusi = volume_kontribusi * tarif_kontribusi. Port dari _hitungPesertaKD() gas-lama/CodeKontribusiDiklat.gs. */
    protected function jumlahKontribusi(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_kontribusi * (float) $this->tarif_kontribusi);
    }

    /** jumlah_mooc = volume_mooc * tarif_mooc. */
    protected function jumlahMooc(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_mooc * (float) $this->tarif_mooc);
    }

    /** Subtotal mode kontribusi = jumlah_kontribusi + jumlah_mooc. Ini nominal NPD untuk mode 'kontribusi'. */
    protected function subKontribusi(): Attribute
    {
        return Attribute::get(fn () => $this->jumlah_kontribusi + $this->jumlah_mooc);
    }

    /** jumlah_harian = hari_uh * tarif_uh. */
    protected function jumlahHarian(): Attribute
    {
        return Attribute::get(fn () => (float) $this->hari_uh * (float) $this->tarif_uh);
    }

    /** jumlah_akomodasi = volume_akomodasi * tarif_akomodasi. */
    protected function jumlahAkomodasi(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_akomodasi * (float) $this->tarif_akomodasi);
    }

    /** jumlah_saku = hari_saku * tarif_saku. */
    protected function jumlahSaku(): Attribute
    {
        return Attribute::get(fn () => (float) $this->hari_saku * (float) $this->tarif_saku);
    }

    /** Subtotal mode perjalanan = uang harian + akomodasi + uang saku + transport at-cost. Nominal NPD untuk mode 'perjalanan'. */
    protected function subPerjalanan(): Attribute
    {
        return Attribute::get(fn () => $this->jumlah_harian + $this->jumlah_akomodasi + $this->jumlah_saku + (float) $this->transport);
    }
}
