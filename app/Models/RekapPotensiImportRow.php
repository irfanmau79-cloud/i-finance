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
    'nip',
    'nama',
    'jabatan',
    'potensi',
    'setoran',
    'keterangan',
    'rekap_id',
])]
class RekapPotensiImportRow extends Model
{
    protected $table = 'rekap_potensi_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'potensi' => 'decimal:2',
            'setoran' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(RekapPotensiImport::class, 'import_id');
    }

    public function rekap(): BelongsTo
    {
        return $this->belongsTo(RekapPotensiPengembalian::class, 'rekap_id');
    }

    /** Sisa baris ini bila jadi disimpan - sama rumusnya dengan rekapnya. */
    public function sisa(): float
    {
        return round((float) $this->potensi - (float) $this->setoran, 2);
    }
}
