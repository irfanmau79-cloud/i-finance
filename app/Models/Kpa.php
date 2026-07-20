<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KPA (Kuasa Pengguna Anggaran) + BPP (Bendahara Pengeluaran Pembantu)
 * miliknya, 1:1. BEDA dari role login 'bpp' — ini jabatan tanda tangan.
 */
#[Fillable(['kpa_pegawai_id', 'bpp_pegawai_id', 'nama_jabatan', 'aktif'])]
class Kpa extends Model
{
    protected $table = 'kpa';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function kpaPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'kpa_pegawai_id');
    }

    public function bppPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'bpp_pegawai_id');
    }

    public function pelimpahan(): HasMany
    {
        return $this->hasMany(Pelimpahan::class);
    }

    /** True kalau $pegawaiId sudah jadi KPA aktif di baris lain — validasi "satu pegawai tidak jadi KPA ganda". */
    public static function sudahJadiKpaAktifLain(int $pegawaiId, ?int $kecualiId = null): bool
    {
        return self::where('kpa_pegawai_id', $pegawaiId)
            ->where('aktif', true)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }
}
