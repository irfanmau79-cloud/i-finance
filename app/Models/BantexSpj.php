<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'keterangan', 'aktif', 'dibuat_oleh'])]
class BantexSpj extends Model
{
    protected $table = 'bantex_spj';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
