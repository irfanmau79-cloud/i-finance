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
    'nama',
    'nip',
    'jabatan',
    'golongan',
    'pangkat',
    'bidang',
    'rekening',
    'aktif',
    'pegawai_id',
])]
class PegawaiImportRow extends Model
{
    protected $table = 'pegawai_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PegawaiImport::class, 'import_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
