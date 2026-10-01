<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian perjalanan dinas seorang pegawai pada satu bulan, diinput manual.
 *
 * Menambal periode sebelum migrasi: NPD-nya sudah masuk lewat Import NPD
 * Historis dan nilainya real, tetapi tanpa rincian anggota tim - padahal
 * itulah yang dibaca Dashboard Perjalanan Dinas.
 *
 * TIDAK pernah ikut perhitungan anggaran. Realisasi tetap murni dari tabel
 * npd; baris ini hanya dibaca dashboard.
 */
#[Fillable([
    'pegawai_id',
    'bulan',
    'tahun',
    'hari',
    'uang_harian',
    'akomodasi',
    'transport',
    'representatif',
    'keterangan',
])]
class PerjalananDinasManual extends Model
{
    protected $table = 'perjalanan_dinas_manual';

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'hari' => 'decimal:2',
            'uang_harian' => 'decimal:2',
            'akomodasi' => 'decimal:2',
            'transport' => 'decimal:2',
            'representatif' => 'decimal:2',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /**
     * Jumlah Diterima tidak disimpan - rumusnya sama persis dengan
     * NpdPerjalananHitung::hitungAnggota() supaya baris manual dan baris
     * dari NPD dijumlahkan dengan cara yang sama di dashboard.
     */
    public function jumlahDiterima(): float
    {
        return round(
            (float) $this->uang_harian
            + (float) $this->akomodasi
            + (float) $this->transport
            + (float) $this->representatif,
            2
        );
    }
}
