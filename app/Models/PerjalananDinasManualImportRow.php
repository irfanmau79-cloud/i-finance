<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_id', 'nomor_baris', 'aksi', 'alasan', 'isi'])]
class PerjalananDinasManualImportRow extends Model
{
    protected $table = 'perjalanan_dinas_manual_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return ['isi' => 'array'];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PerjalananDinasManualImport::class, 'import_id');
    }
}
