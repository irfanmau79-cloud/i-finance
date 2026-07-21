<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pengajuan_id', 'disk', 'path', 'nama_asli', 'mime', 'ukuran'])]
class LampiranTunjangan extends Model
{
    protected $table = 'lampiran_tunjangan';

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPerubahanTunjangan::class, 'pengajuan_id');
    }
}
