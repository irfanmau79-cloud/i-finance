<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_id',
    'nomor_baris',
    'aksi',
    'alasan',
    'program',
    'kegiatan',
    'sub_kegiatan',
    'kode_rekening',
    'uraian_rekening',
    'tagging_nama',
    'aktif',
    'pagu_baru',
    'pagu_lama',
    'master_anggaran_id',
])]
class MasterAnggaranImportRow extends Model
{
    protected $table = 'master_anggaran_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'pagu_baru' => 'decimal:2',
            'pagu_lama' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaranImport::class, 'import_id');
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }
}
