<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_id',
    'nomor_baris',
    'bulan',
    'aksi',
    'alasan',
    'sub_kegiatan',
    'sub_kegiatan_kunci',
    'kode_rekening',
    'tagging_nama',
    'target',
    'target_asli',
    'rak_bulanan_id',
])]
class RakBulananImportRow extends Model
{
    protected $table = 'rak_bulanan_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'target_asli' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(RakBulananImport::class, 'import_id');
    }

    public function rakBulanan(): BelongsTo
    {
        return $this->belongsTo(RakBulanan::class);
    }
}
