<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Penandatangan Surat Keterangan Penghasilan.
 *
 * Dikelola superadmin (diambil dari Data Pegawai); role lain hanya memilih
 * dari daftar ini. Identitasnya dibekukan ke rincian_penghasilan saat
 * dokumen dibuat, jadi baris di sini boleh berubah tanpa memengaruhi
 * dokumen yang sudah dicetak.
 */
#[Fillable(['pegawai_id', 'kunci', 'nama', 'jabatan', 'pangkat', 'aktif'])]
class PenandatanganRincian extends Model
{
    protected $table = 'penandatangan_rincian';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /** @param  EloquentBuilder<PenandatanganRincian>  $query */
    public function scopeAktif(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('aktif', true);
    }

    /** Teks pada dropdown, formatnya sama dengan GAS: nama — jabatan — pangkat. */
    public function label(): string
    {
        return sprintf('%s — %s — %s', $this->nama, $this->jabatan, $this->pangkat);
    }

    /**
     * Kunci unik dari nama, dipotong 40 karakter mengikuti lebar kolom
     * rincian_penghasilan.penandatangan_kunci. Bila sudah terpakai,
     * ditambahi angka urut supaya dua pejabat bernama mirip tidak bentrok.
     */
    public static function kunciUnik(string $nama): string
    {
        $dasar = Str::limit(Str::slug($nama) ?: 'penandatangan', 36, '');
        $kunci = $dasar;

        for ($i = 2; static::query()->where('kunci', $kunci)->exists(); $i++) {
            $kunci = $dasar.'-'.$i;
        }

        return $kunci;
    }
}
