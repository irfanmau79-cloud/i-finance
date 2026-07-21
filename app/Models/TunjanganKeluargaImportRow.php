<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['import_id', 'nomor_baris', 'valid', 'pesan', 'payload'])]
class TunjanganKeluargaImportRow extends Model
{
    protected $table = 'tunjangan_keluarga_import_rows';

    protected function casts(): array
    {
        return ['valid' => 'boolean', 'pesan' => 'array', 'payload' => 'array'];
    }
}
