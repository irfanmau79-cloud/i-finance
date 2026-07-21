<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_file', 'user_id', 'status', 'total_baris', 'baris_valid', 'baris_invalid', 'committed_at'])]
class TunjanganKeluargaImport extends Model
{
    protected $table = 'tunjangan_keluarga_imports';

    protected function casts(): array
    {
        return ['committed_at' => 'datetime'];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(TunjanganKeluargaImportRow::class, 'import_id');
    }
}
