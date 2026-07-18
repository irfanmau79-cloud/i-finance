<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nama',
    'aktif',
])]
class Vendor extends Model
{
    protected $table = 'vendor';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
