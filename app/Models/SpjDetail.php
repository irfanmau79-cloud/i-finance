<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override manual satu-baris-per-NPD untuk "Tabel Detail SPJ" (Inventarisasi
 * SPJ) - lihat migration create_spj_detail_table untuk aturan Restore per
 * kolom. Baris ini TIDAK dibuat otomatis saat NPD dibuat; hanya ada begitu
 * Bendahara Pengeluaran/BPP pertama kali mengedit atau memberi catatan.
 */
#[Fillable([
    'npd_id',
    'bulan',
    'nomor_sp',
    'nominal',
    'koordinator',
    'bidang',
    'uraian',
    'lokasi',
    'status',
    'catatan',
    'diedit_oleh',
    'diedit_at',
])]
class SpjDetail extends Model
{
    protected $table = 'spj_detail';

    public const STATUS_LENGKAP = 'lengkap';

    public const STATUS_BELUM_LENGKAP = 'belum_lengkap';

    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    public const STATUS_TIDAK_DITEMUKAN = 'tidak_ditemukan';

    /** Nilai status yang sah beserta labelnya. Urutannya = urutan dropdown. */
    public const STATUS = [
        self::STATUS_LENGKAP => 'Lengkap',
        self::STATUS_BELUM_LENGKAP => 'Belum Lengkap',
        self::STATUS_DIKEMBALIKAN => 'Dikembalikan',
        self::STATUS_TIDAK_DITEMUKAN => 'Tidak Ditemukan',
    ];

    public static function labelStatus(?string $status): string
    {
        return self::STATUS[$status] ?? self::STATUS[self::STATUS_BELUM_LENGKAP];
    }

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'diedit_at' => 'datetime',
        ];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function dieditOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diedit_oleh');
    }

    /** Kosongkan kolom yang punya nilai default hasil hitung - status/catatan sengaja tidak disentuh. */
    public function restoreKeDefault(): void
    {
        $this->update([
            'bulan' => null,
            'nomor_sp' => null,
            'nominal' => null,
            'koordinator' => null,
            'bidang' => null,
            'uraian' => null,
            'lokasi' => null,
        ]);
    }
}
