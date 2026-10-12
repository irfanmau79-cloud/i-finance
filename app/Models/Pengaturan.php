<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi yang diubah dari dalam aplikasi: satu baris per kunci.
 */
#[Fillable(['kunci', 'nilai'])]
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    public static function ambil(string $kunci): ?self
    {
        return self::query()->where('kunci', $kunci)->first();
    }

    public static function simpan(string $kunci, ?string $nilai): self
    {
        return self::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }
}
