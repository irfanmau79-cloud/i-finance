<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Potret Status Tunjangan Keluarga seluruh pegawai pada satu periode gaji.
 * Dikunci superadmin; lihat migrasi 2026_08_31_120000 untuk alasannya.
 */
#[Fillable(['bulan', 'tahun', 'tanggal_penggajian', 'dikunci_oleh', 'dikunci_oleh_nama', 'dikunci_at'])]
class RekonsiliasiKunci extends Model
{
    protected $table = 'rekonsiliasi_kunci';

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'tanggal_penggajian' => 'date',
            'dikunci_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(RekonsiliasiKunciBaris::class, 'kunci_id');
    }

    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikunci_oleh');
    }

    public function labelPeriode(): string
    {
        return \App\Support\GajiTunjanganKolom::NAMA_BULAN[$this->bulan].' '.$this->tahun;
    }
}
