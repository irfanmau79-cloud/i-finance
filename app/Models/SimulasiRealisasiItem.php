<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu rencana belanja bernama pada sebuah mata anggaran, misalnya
 * "Perjalanan dinas ke Cirebon" Rp1.000.000. Satu mata anggaran boleh punya
 * banyak rencana; jumlahnya diringkas ke kolom proyeksi_total pada barisnya - itulah
 * kolom Proyeksi di layar.
 */
#[Fillable(['simulasi_realisasi_row_id', 'nama', 'nominal', 'urutan'])]
class SimulasiRealisasiItem extends Model
{
    protected $table = 'simulasi_realisasi_items';

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(SimulasiRealisasiRow::class, 'simulasi_realisasi_row_id');
    }
}
