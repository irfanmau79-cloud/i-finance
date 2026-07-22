<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris mata anggaran pada satu SPM LS. PPN/PPh/penerima/uraian tetap satu
 * angka per dokumen (di header Spm) - HANYA nominal bruto per mata anggaran
 * yang dipecah di sini. Hanya pernah dibuat oleh Spm::buatLs()/updateLs()
 * (satu-satunya penulis), jadi setiap baris spm_detail pasti milik SPM
 * berjenis 'ls' - lihat MasterAnggaran::realisasiLs().
 */
#[Fillable(['spm_id', 'master_anggaran_id', 'nominal'])]
class SpmDetail extends Model
{
    protected $table = 'spm_detail';

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function spm(): BelongsTo
    {
        return $this->belongsTo(Spm::class);
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
