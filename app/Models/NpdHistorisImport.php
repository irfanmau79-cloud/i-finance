<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'nama_file', 'file_hash', 'format_sumber', 'status', 'total_baris',
    'jumlah_valid', 'jumlah_warning', 'jumlah_error', 'jumlah_duplikat',
    'jumlah_berhasil', 'jumlah_dilewati', 'total_nominal', 'total_ppn',
    'total_pph', 'expires_at', 'executed_at',
])]
class NpdHistorisImport extends Model
{
    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    public const MENIT_KEDALUWARSA = 60;

    protected $table = 'npd_historis_imports';

    protected function casts(): array
    {
        return [
            'total_nominal' => 'decimal:2', 'total_ppn' => 'decimal:2', 'total_pph' => 'decimal:2',
            'expires_at' => 'datetime', 'executed_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(NpdHistorisImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kedaluwarsa(): bool
    {
        return $this->status === self::STATUS_STAGED && $this->expires_at->isPast();
    }
}
