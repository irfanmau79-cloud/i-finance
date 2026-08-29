<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'nama',
    'nip',
    'jabatan',
    'bidang',
    'golongan',
    'pangkat',
    'periode_kgb',
    'status_kepegawaian',
    'rekening',
    'nomor_handphone',
    'aktif',
])]
class Pegawai extends Model
{
    protected $table = 'pegawai';

    public const STATUS_PNS = 'PNS';

    public const STATUS_PPPK_PENUH = 'PPPK Penuh Waktu';

    public const STATUS_PPPK_PARUH = 'PPPK Paruh Waktu';

    /** Pilihan Status Kepegawaian, urut sesuai tampilan di form. */
    public const STATUS_KEPEGAWAIAN = [
        self::STATUS_PNS,
        self::STATUS_PPPK_PENUH,
        self::STATUS_PPPK_PARUH,
    ];

    /**
     * Status yang berhak atas Tunjangan Keluarga. PPPK Paruh Waktu TIDAK
     * termasuk, sehingga tidak ikut muncul di Data Tunjangan Keluarga
     * maupun di berkas export/import-nya.
     */
    public const STATUS_BERHAK_TUNJANGAN = [self::STATUS_PNS, self::STATUS_PPPK_PENUH];

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

    /** Pegawai aktif yang berhak atas Tunjangan Keluarga (PNS & PPPK Penuh Waktu). */
    public function scopeBerhakTunjangan(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('aktif', true)->whereIn('status_kepegawaian', self::STATUS_BERHAK_TUNJANGAN);
    }

    /** "III/c / Penata" - bentuk gabungan untuk kolom Pangkat/Golongan. */
    public function pangkatGolongan(): string
    {
        return collect([$this->golongan, $this->pangkat])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->implode(' / ') ?: '-';
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
