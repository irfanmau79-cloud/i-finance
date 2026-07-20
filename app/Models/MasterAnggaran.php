<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program',
    'kegiatan',
    'sub_kegiatan',
    'kode_rekening',
    'uraian_rekening',
    'tagging_id',
    'pagu',
    'aktif',
    'program_normal',
    'kegiatan_normal',
    'sub_kegiatan_normal',
    'program_kunci',
    'sub_kegiatan_kunci',
])]
class MasterAnggaran extends Model
{
    protected $table = 'master_anggaran';

    protected function casts(): array
    {
        return [
            'pagu' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $anggaran) {
            $anggaran->program_normal = self::normalisasiTeks($anggaran->program);
            $anggaran->kegiatan_normal = self::normalisasiTeks($anggaran->kegiatan);
            $anggaran->sub_kegiatan_normal = self::normalisasiTeks($anggaran->sub_kegiatan);
            $anggaran->program_kunci = self::normalisasiKunci($anggaran->program);
            $anggaran->sub_kegiatan_kunci = self::normalisasiKunci($anggaran->sub_kegiatan);
        });
    }

    public function tagging(): BelongsTo
    {
        return $this->belongsTo(Tagging::class);
    }

    public function npd(): HasMany
    {
        return $this->hasMany(Npd::class);
    }

    public function spm(): HasMany
    {
        return $this->hasMany(Spm::class);
    }

    public function rakBulanan(): HasMany
    {
        return $this->hasMany(RakBulanan::class);
    }

    /**
     * Normalisasi whitespace (baris baru / spasi ganda dari hasil impor
     * data — lihat juga DataTambahan::normalisasiSpasi()). Dipakai sebagai
     * kunci pencocokan program/sub_kegiatan di Pelimpahan, supaya varian
     * whitespace yang berbeda pada baris master_anggaran yang berbeda tetap
     * dianggap sub kegiatan yang sama. Tanpa ini, satu "sub kegiatan" yang
     * sama bisa muncul sebagai puluhan varian string berbeda (terverifikasi:
     * 639 sub_kegiatan mentah, cuma 38 yang benar-benar unik).
     */
    public static function normalisasiTeks(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    public static function normalisasiKunci(?string $s): string
    {
        return mb_strtolower(self::normalisasiTeks($s));
    }

    public function subKegiatanNormal(): string
    {
        return self::normalisasiTeks($this->sub_kegiatan);
    }

    public function programNormal(): string
    {
        return self::normalisasiTeks($this->program);
    }

    public function kegiatanNormal(): string
    {
        return self::normalisasiTeks($this->kegiatan);
    }

    /**
     * KEU ditentukan dari prefix sub_kegiatan: 6.01.01 -> KEU 1,
     * 6.01.02/6.01.03 -> KEU 2. Null kalau tidak dikenali.
     */
    public function tentukanKeu(): ?string
    {
        return match (true) {
            str_starts_with($this->sub_kegiatan, '6.01.01') => '1',
            str_starts_with($this->sub_kegiatan, '6.01.02'),
            str_starts_with($this->sub_kegiatan, '6.01.03') => '2',
            default => null,
        };
    }

    /** Dana terikat seluruh NPD aktif/non-batal, termasuk draft dan proses. */
    public function danaTerikatNpd(): float
    {
        return (float) $this->npd()->where('status', 'not like', '%batal%')->sum('nominal');
    }

    /** Realisasi aktual jalur NPD hanya berasal dari NPD berstatus Selesai. */
    public function realisasiNpd(): float
    {
        return (float) $this->npd()->where('status', 'Selesai')->sum('nominal');
    }

    /**
     * Realisasi dari jalur LS: dicairkan langsung di BPKAD ke pihak ketiga
     * tanpa NPD, langsung mengurangi pagu. Lihat Spm::buatLs().
     */
    public function realisasiLs(): float
    {
        return (float) $this->spm()->where('jenis_spm', 'ls')->sum('nominal');
    }

    /** Realisasi aktual = NPD selesai + SPM LS. */
    public function realisasiAktual(): float
    {
        return $this->realisasiNpd() + $this->realisasiLs();
    }

    /** Sisa tersedia = pagu - dana terikat NPD - SPM LS. */
    public function sisaTersedia(): float
    {
        return (float) $this->pagu - $this->danaTerikatNpd() - $this->realisasiLs();
    }

    /** Compatibility wrapper untuk pemanggil lama. */
    public function sisaAnggaran(): float
    {
        return $this->sisaTersedia();
    }

    /**
     * Sisa sebelum NPD menurut tanggal transaksi. NPD pada tanggal sama
     * diurutkan dengan id; SPM LS sampai tanggal NPD ikut mengurangi.
     */
    public function sisaAnggaranSebelum(Npd $npd): float
    {
        $danaTerikatSebelum = (float) $this->npd()
            ->where(function ($query) use ($npd) {
                $query->whereDate('tanggal_npd', '<', $npd->tanggal_npd)
                    ->orWhere(function ($query) use ($npd) {
                        $query->whereDate('tanggal_npd', $npd->tanggal_npd)
                            ->where('id', '<', $npd->id);
                    });
            })
            ->where('status', 'not like', '%batal%')
            ->sum('nominal');

        $realisasiLsSebelum = (float) $this->spm()
            ->where('jenis_spm', 'ls')
            ->whereDate('tanggal_dokumen', '<=', $npd->tanggal_npd)
            ->sum('nominal');

        return (float) $this->pagu - $danaTerikatSebelum - $realisasiLsSebelum;
    }

    /**
     * Target RAK bulan tertentu, atau NULL kalau belum diisi untuk
     * bulan/tahun itu. SENGAJA tidak pernah jatuh ke pagu/12 - pemanggil
     * (dashboard/grafik target bulanan) wajib menampilkan status "RAK belum
     * tersedia" saat hasilnya NULL, bukan menghitung perkiraan sendiri.
     */
    public function targetRakBulan(int $bulan, int $tahun): ?float
    {
        $target = $this->rakBulanan()->where('tahun', $tahun)->where('bulan', $bulan)->value('target');

        return $target !== null ? (float) $target : null;
    }

    /**
     * Target RAK kumulatif dari bulan 1 s.d. $bulan pada $tahun tsb, atau
     * NULL kalau sama sekali belum ada data RAK untuk tahun itu (beda dari
     * 0 - 0 berarti RAK ADA tapi memang bernilai nol). Bulan yang belum
     * diisi di antaranya dihitung 0 dalam penjumlahan, selama setidaknya
     * satu bulan pada tahun itu sudah ada.
     */
    public function targetRakKumulatifSampai(int $bulan, int $tahun): ?float
    {
        if (! $this->rakBulanan()->where('tahun', $tahun)->exists()) {
            return null;
        }

        return (float) $this->rakBulanan()->where('tahun', $tahun)->where('bulan', '<=', $bulan)->sum('target');
    }
}
