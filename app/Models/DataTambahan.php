<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'program',
    'no_dpa',
    'kpa',
    'pptk',
    'bpp',
])]
class DataTambahan extends Model
{
    protected $table = 'data_tambahan';
}
