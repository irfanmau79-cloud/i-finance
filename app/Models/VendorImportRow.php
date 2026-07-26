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
    'rekening',
    'npwp',
    'pkp',
    'jenis_usaha',
    'aktif',
    'vendor_id',
])]
class VendorImportRow extends Model
{
    protected $table = 'vendor_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'pkp' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(VendorImport::class, 'import_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
