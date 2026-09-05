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
    'nomor',
    'unit_kerja',
    'area',
    'jenis_kegiatan',
    'tujuan',
    'ruang_lingkup',
    'jumlah_tim',
    'estimasi_anggaran',
    'realisasi',
    'pelaksanaan',
    'jumlah_laporan',
    'rencana_pelaksanaan',
    'terlaksana',
    'pkpt_id',
])]
class PkptImportRow extends Model
{
    protected $table = 'pkpt_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'estimasi_anggaran' => 'decimal:2',
            'realisasi' => 'decimal:2',
            'terlaksana' => 'boolean',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PkptImport::class, 'import_id');
    }

    public function pkpt(): BelongsTo
    {
        return $this->belongsTo(Pkpt::class);
    }
}
