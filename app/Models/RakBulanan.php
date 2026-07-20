<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = target Rencana Anggaran Kas (RAK) untuk satu mata anggaran
 * pada satu bulan tertentu (nilai BULANAN, bukan kumulatif - lihat
 * dokumentasi lengkap di migration create_rak_bulanan_table).
 */
#[Fillable(['master_anggaran_id', 'tahun', 'bulan', 'target'])]
class RakBulanan extends Model
{
    protected $table = 'rak_bulanan';

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
        ];
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
