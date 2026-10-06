<?php

namespace App\Models;

use App\Helpers\NpdPerjalananHitung;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'nama',
    'jabatan',
    'bidang_snapshot',
    'nip',
    'rekening',
    'bbm_nominal',
    'bbm_liter',
    'bbm_tarif',
    'tol',
    'tiket',
    'representatif',
    'is_penerima',
])]
class NpdTim extends Model
{
    protected $table = 'npd_tim';

    protected function casts(): array
    {
        return [
            // Liter tidak dibatasi dua desimal: diketik panjang supaya
            // liter x tarif tepat membulat ke nominal BBM. 'float', bukan
            // 'decimal:N', supaya formulir sunting tidak menampilkan
            // deretan nol di belakang koma.
            'bbm_nominal' => 'decimal:2',
            'bbm_liter' => 'float',
            'bbm_tarif' => 'decimal:2',
            'tol' => 'decimal:2',
            'tiket' => 'decimal:2',
            'representatif' => 'decimal:2',
            'is_penerima' => 'boolean',
        ];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function paket(): HasMany
    {
        return $this->hasMany(NpdTimPaket::class);
    }

    /**
     * Liter untuk dicetak: koma desimal, seluruh angka di belakang koma
     * dipertahankan (tanpa nol di ujung). 10,5 tetap "10,5"; 10,123456
     * tetap "10,123456" - tidak dibulatkan ke dua desimal.
     */
    public static function formatLiter(float $liter): string
    {
        $teks = rtrim(rtrim(number_format(round($liter, 10), 10, ',', ''), '0'), ',');

        return $teks === '' ? '0' : $teks;
    }

    /** Bentuk array datar dipakai App\Helpers\NpdPerjalananHitung — paket relation harus sudah di-load. */
    public function toHitungArray(): array
    {
        return [
            'paket' => $this->paket->map(fn (NpdTimPaket $p) => $p->toHitungArray())->all(),
            // null = NPD lama yang BBM-nya masih liter x tarif.
            'bbm_nominal' => $this->bbm_nominal === null ? null : (float) $this->bbm_nominal,
            'bbm_liter' => (float) $this->bbm_liter,
            'bbm_tarif' => (float) $this->bbm_tarif,
            'tol' => (float) $this->tol,
            'tiket' => (float) $this->tiket,
            'representatif' => (float) $this->representatif,
        ];
    }

    /** Hasil hitung lengkap (jml_harian, jml_akom, bbm, jumlah, dst) — port _hitungAnggota. */
    public function hitung(): array
    {
        return NpdPerjalananHitung::hitungAnggota($this->toHitungArray());
    }
}
