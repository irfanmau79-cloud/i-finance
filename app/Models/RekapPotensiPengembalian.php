<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris Rekap Potensi Pengembalian: berapa kelebihan pembayaran yang
 * ditaksir untuk seorang pegawai, berapa yang sudah disetor kembali, dan
 * karenanya berapa yang masih kurang.
 *
 * SISA tidak disimpan - ia selalu potensi dikurangi setoran. Lihat alasannya
 * di migrasi 2026_10_01_110000.
 */
#[Fillable(['nip', 'nama', 'jabatan', 'potensi', 'setoran', 'keterangan'])]
class RekapPotensiPengembalian extends Model
{
    protected $table = 'rekap_potensi_pengembalian';

    protected function casts(): array
    {
        return [
            'potensi' => 'decimal:2',
            'setoran' => 'decimal:2',
        ];
    }

    public function sisa(): float
    {
        return round((float) $this->potensi - (float) $this->setoran, 2);
    }

    /**
     * Urutan baku: sisa terbesar lebih dulu - itulah yang perlu ditagih -
     * lalu NIP menaik untuk yang sisanya sama (lazimnya sama-sama nol).
     *
     * NIP dibandingkan sebagai TEKS DIGIT, bukan angka: 18 digit melebihi
     * presisi bilangan bulat yang aman, sehingga dua NIP yang hanya beda di
     * digit terakhir bisa terbaca sama bila dibandingkan sebagai angka.
     * Panjang dibandingkan lebih dulu supaya "9" tetap di bawah "10".
     */
    public function scopeUrutanBaku(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(potensi - setoran) DESC')
            ->orderByRaw('LENGTH(nip) ASC')
            ->orderBy('nip');
    }

    /** Punya pengembalian yang masih harus ditagih? */
    public function adaPengembalian(): bool
    {
        return $this->sisa() > 0;
    }
}
