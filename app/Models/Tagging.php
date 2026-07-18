<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nama',
    'aktif',
])]
class Tagging extends Model
{
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function masterAnggaran(): HasMany
    {
        return $this->hasMany(MasterAnggaran::class);
    }
}
