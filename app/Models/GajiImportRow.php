<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['import_id', 'nomor_baris', 'valid', 'nama_pegawai', 'nip', 'pesan', 'payload'])]
class GajiImportRow extends Model
{
    protected $table = 'gaji_import_rows';

    protected function casts(): array
    {
        return ['valid' => 'boolean', 'pesan' => 'array', 'payload' => 'array'];
    }
}
