<?php

namespace App\Models;

use App\Support\GajiTunjanganKolom;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'nama_file', 'jenis', 'bulan', 'tahun', 'status',
    'total_baris', 'baris_valid', 'baris_invalid', 'baris_tertimpa', 'committed_at',
])]
class GajiImport extends Model
{
    protected $table = 'gaji_imports';

    protected function casts(): array
    {
        return ['committed_at' => 'datetime', 'bulan' => 'integer', 'tahun' => 'integer'];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(GajiImportRow::class, 'import_id');
    }

    public function labelJenis(): string
    {
        return GajiTunjanganKolom::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function labelPeriode(): string
    {
        return (GajiTunjanganKolom::NAMA_BULAN[$this->bulan] ?? '?').' '.$this->tahun;
    }
}
