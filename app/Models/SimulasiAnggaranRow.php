<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'simulasi_anggaran_id',
    'master_anggaran_id',
    'program',
    'kegiatan',
    'sub_kegiatan',
    'sub_kegiatan_kunci',
    'kode_rekening',
    'uraian_rekening',
    'tagging_nama',
    'pagu_eksisting',
    'pagu_simulasi',
    'selisih',
])]
class SimulasiAnggaranRow extends Model
{
    protected $table = 'simulasi_anggaran_rows';

    protected function casts(): array
    {
        return [
            'pagu_eksisting' => 'decimal:2',
            'pagu_simulasi' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    public function simulasiAnggaran(): BelongsTo
    {
        return $this->belongsTo(SimulasiAnggaran::class);
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
