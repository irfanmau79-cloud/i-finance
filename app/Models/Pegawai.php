<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'nama',
    'nip',
    'jabatan',
    'bidang',
    'golongan',
    'pangkat',
    'rekening',
    'aktif',
])]
class Pegawai extends Model
{
    protected $table = 'pegawai';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function tunjanganKeluarga(): HasOne
    {
        return $this->hasOne(TunjanganKeluarga::class);
    }

    /**
     * Cari pegawai berdasarkan nama bebas (mis. dari data_tambahan: "Drs. Budi, M.Si").
     * Port 1:1 dari _cariPegawai/_normNama di gas-lama/Code.gs: normalisasi
     * (buang gelar umum, tanda baca, spasi ganda), lalu coba cocok persis,
     * lalu salah satu nama memuat yang lain, lalu 2 kata pertama saja.
     */
    public static function cariByNama(?string $nama): ?self
    {
        $nama = trim((string) $nama);

        if ($nama === '') {
            return null;
        }

        $target = self::normalisasiNama($nama);

        if ($target === '') {
            return null;
        }

        $list = self::all();

        $hit = $list->first(fn (self $p) => self::normalisasiNama($p->nama) === $target);

        if (! $hit) {
            $hit = $list->first(function (self $p) use ($target) {
                $n = self::normalisasiNama($p->nama);

                return $n !== '' && (str_starts_with($n, $target) || str_starts_with($target, $n));
            });
        }

        if (! $hit) {
            $depan = implode(' ', array_slice(explode(' ', $target), 0, 2));

            if ($depan !== '') {
                $hit = $list->first(fn (self $p) => str_starts_with(self::normalisasiNama($p->nama), $depan));
            }
        }

        return $hit;
    }

    private static function normalisasiNama(?string $nama): string
    {
        $s = mb_strtolower(trim((string) $nama));
        $s = preg_replace('/\b(s\.?stp|s\.?sos|s\.?h|s\.?e|s\.?t|s\.?ip|s\.?kom|m\.?ap|m\.?si|m\.?m|m\.?sp|a\.?md|se|sh|amd)\b/u', '', $s);
        $s = preg_replace('/[.,()]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
