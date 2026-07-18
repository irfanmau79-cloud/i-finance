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

    /**
     * Cari data tambahan (No. DPA, KPA, PPTK, BPP) berdasarkan teks program.
     * Dibandingkan setelah normalisasi whitespace (spasi/baris-baru berturutan
     * jadi satu spasi) sebab master_anggaran.program hasil impor kadang
     * menyimpan baris baru di antara kode & nama program, sedangkan
     * data_tambahan.program selalu satu spasi.
     */
    public static function untukProgram(?string $program): ?self
    {
        $target = self::normalisasiSpasi($program);

        if ($target === '') {
            return null;
        }

        return self::all()->first(fn (self $d) => self::normalisasiSpasi($d->program) === $target);
    }

    private static function normalisasiSpasi(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }
}
