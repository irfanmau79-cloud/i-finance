<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu bantex/box fisik tempat berkas SPJ disimpan. Identitasnya adalah
 * Nomor Penyimpanan dua digit; label yang dipakai di seluruh tampilan dan
 * pada kolom Lokasi Penyimpanan adalah "{nomor} - {nama}", misalnya
 * "07 - PDTT Irban II".
 */
#[Fillable(['nomor', 'nama', 'keterangan', 'aktif', 'dibuat_oleh'])]
class BantexSpj extends Model
{
    protected $table = 'bantex_spj';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /**
     * Nomor selalu disimpan dua digit supaya berjajar rapi di layar dan
     * urutannya benar: pengguna boleh mengetik "9", tersimpan "09".
     */
    public static function normalNomor(mixed $nomor): string
    {
        $angka = preg_replace('/\D/', '', (string) $nomor) ?? '';

        return $angka === '' ? '' : str_pad(ltrim($angka, '0') ?: '0', 2, '0', STR_PAD_LEFT);
    }

    public function label(): string
    {
        return $this->nomor ? $this->nomor.' - '.$this->nama : (string) $this->nama;
    }
}
