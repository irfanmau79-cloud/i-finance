<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nama',
    'nip',
    'jabatan',
    'bidang',
    'aktif',
])]
class Pegawai extends Model
{
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
