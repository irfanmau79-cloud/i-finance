<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nama',
    'rekening',
    'nomor_handphone',
    'npwp',
    'pkp',
    'jenis_usaha',
    'aktif',
])]
class Vendor extends Model
{
    protected $table = 'vendor';

    protected function casts(): array
    {
        return [
            'pkp' => 'boolean',
            'aktif' => 'boolean',
        ];
    }
}
