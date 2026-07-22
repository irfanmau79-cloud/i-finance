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

    /** Baris mata anggaran SPM LS (lihat Spm::buatLs()) - setiap baris di sini pasti milik SPM berjenis 'ls'. */
    public function spmDetail(): HasMany
    {
        return $this->hasMany(SpmDetail::class);
    }

    /** Baris mata anggaran Pengembalian (draft maupun disetujui - lihat Pengembalian::buatDraft()). */
    public function pengembalianDetail(): HasMany
    {
        return $this->hasMany(PengembalianDetail::class);
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

    /**
     * SUM(pengembalian_detail.nominal) untuk Pengembalian berstatus
     * 'disetujui' yang sumbernya NPD, pada mata anggaran ini. Draft tidak
     * dihitung — lihat Pengembalian::STATUS_DISETUJUI.
     */
    public function pengembalianDisetujuiNpd(): float
    {
        if (array_key_exists('pengembalian_disetujui_npd_total', $this->attributes)) {
            return (float) ($this->attributes['pengembalian_disetujui_npd_total'] ?? 0);
        }

        return (float) $this->pengembalianDetail()
            ->whereHas('pengembalian', fn ($q) => $q->where('status', 'disetujui')->where('dokumen_tipe', 'npd'))
            ->sum('nominal');
    }

    /** Sama seperti pengembalianDisetujuiNpd(), untuk pengembalian yang sumbernya SPM LS. */
    public function pengembalianDisetujuiLs(): float
    {
        if (array_key_exists('pengembalian_disetujui_ls_total', $this->attributes)) {
            return (float) ($this->attributes['pengembalian_disetujui_ls_total'] ?? 0);
        }

        return (float) $this->pengembalianDetail()
            ->whereHas('pengembalian', fn ($q) => $q->where('status', 'disetujui')->where('dokumen_tipe', 'spm_ls'))
            ->sum('nominal');
    }

    /**
     * Dana terikat seluruh NPD aktif/non-batal, termasuk draft dan proses,
     * dikurangi pengembalian disetujui dari NPD (uang yang sudah
     * dikembalikan tidak lagi mengikat pagu).
     */
    public function danaTerikatNpd(): float
    {
        $bruto = array_key_exists('dana_terikat_npd_total', $this->attributes)
            ? (float) ($this->attributes['dana_terikat_npd_total'] ?? 0)
            : (float) $this->npd()->where('status', 'not like', '%batal%')->sum('nominal');

        return $bruto - $this->pengembalianDisetujuiNpd();
    }

    /**
     * Realisasi aktual jalur NPD hanya berasal dari NPD berstatus Selesai,
     * dikurangi pengembalian disetujui dari NPD (realisasi_npd_net).
     */
    public function realisasiNpd(): float
    {
        $bruto = array_key_exists('realisasi_npd_total', $this->attributes)
            ? (float) ($this->attributes['realisasi_npd_total'] ?? 0)
            : (float) $this->npd()->where('status', 'Selesai')->sum('nominal');

        return $bruto - $this->pengembalianDisetujuiNpd();
    }

    /**
     * Realisasi dari jalur LS: dicairkan langsung di BPKAD ke pihak ketiga
     * tanpa NPD, langsung mengurangi pagu. Lihat Spm::buatLs(). PPN/PPh SPM
     * LS tidak dipecah per mata anggaran, jadi realisasi per mata anggaran
     * tetap dihitung dari nominal BRUTO tiap baris spm_detail (konsisten
     * dengan cara NPD menghitung realisasi dari nominal bruto), dikurangi
     * pengembalian disetujui dari SPM LS (realisasi_ls_net).
     */
    public function realisasiLs(): float
    {
        $bruto = array_key_exists('realisasi_ls_total', $this->attributes)
            ? (float) ($this->attributes['realisasi_ls_total'] ?? 0)
            : (float) $this->spmDetail()->sum('nominal');

        return $bruto - $this->pengembalianDisetujuiLs();
    }

    /** Nilai pagu DPA pada mata anggaran ini. */
    public function nilaiPagu(): float
    {
        return (float) $this->pagu;
    }

    /** Realisasi aktual = NPD selesai + SPM LS. */
    public function realisasiAktual(): float
    {
        return $this->realisasiNpd() + $this->realisasiLs();
    }

    /** Sisa tersedia = pagu - dana terikat NPD - SPM LS. */
    public function sisaTersedia(): float
    {
        return $this->nilaiPagu() - $this->danaTerikatNpd() - $this->realisasiLs();
    }

    public static function hitungPersentaseRealisasi(float $realisasi, float $pagu): float
    {
        return $pagu > 0 ? ($realisasi / $pagu) * 100 : 0.0;
    }

    /** Persentase realisasi aktual terhadap pagu; pagu nol selalu menghasilkan 0%. */
    public function persentaseRealisasiAktual(): float
    {
        return self::hitungPersentaseRealisasi($this->realisasiAktual(), $this->nilaiPagu());
    }

    /**
     * Snapshot angka terpusat untuk rincian realisasi dan pemanggil lain.
     * Semua nilai tetap diturunkan dari transaksi, tidak disimpan ulang.
     *
     * @return array{pagu: float, dana_terikat_npd: float, dana_terikat_belum_selesai: float, realisasi_npd: float, realisasi_ls: float, realisasi_aktual: float, sisa_tersedia: float, persentase_realisasi: float}
     */
    public function ringkasanRealisasi(): array
    {
        $realisasiNpd = $this->realisasiNpd();
        $realisasiLs = $this->realisasiLs();
        $danaTerikatNpd = $this->danaTerikatNpd();

        return [
            'pagu' => $this->nilaiPagu(),
            'dana_terikat_npd' => $danaTerikatNpd,
            'dana_terikat_belum_selesai' => $danaTerikatNpd - $realisasiNpd,
            'realisasi_npd' => $realisasiNpd,
            'realisasi_ls' => $realisasiLs,
            'realisasi_aktual' => $realisasiNpd + $realisasiLs,
            'sisa_tersedia' => $this->sisaTersedia(),
            'persentase_realisasi' => $this->persentaseRealisasiAktual(),
        ];
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

        $realisasiLsSebelum = (float) $this->spmDetail()
            ->whereHas('spm', fn ($query) => $query->whereDate('tanggal_dokumen', '<=', $npd->tanggal_npd))
            ->sum('nominal');

        $pengembalianNpdSebelum = (float) $this->pengembalianDetail()
            ->whereHas('pengembalian', fn ($query) => $query->where('status', 'disetujui')
                ->where('dokumen_tipe', 'npd')
                ->whereDate('tanggal_pengembalian', '<=', $npd->tanggal_npd))
            ->sum('nominal');

        $pengembalianLsSebelum = (float) $this->pengembalianDetail()
            ->whereHas('pengembalian', fn ($query) => $query->where('status', 'disetujui')
                ->where('dokumen_tipe', 'spm_ls')
                ->whereDate('tanggal_pengembalian', '<=', $npd->tanggal_npd))
            ->sum('nominal');

        return (float) $this->pagu
            - ($danaTerikatSebelum - $pengembalianNpdSebelum)
            - ($realisasiLsSebelum - $pengembalianLsSebelum);
    }

    /**
     * Target RAK bulan tertentu, atau NULL kalau belum diisi untuk
     * bulan/tahun itu. SENGAJA tidak pernah jatuh ke pagu/12 - pemanggil
     * (dashboard/grafik target bulanan) wajib menampilkan status "RAK belum
     * tersedia" saat hasilnya NULL, bukan menghitung perkiraan sendiri.
     *
     * Sejak Prompt 12A, RAK dikunci ke (tahun, sub_kegiatan_kunci,
     * kode_rekening) - BUKAN ke baris master_anggaran ini secara spesifik -
     * supaya baris master_anggaran lain dengan Tagging berbeda tapi Sub
     * Kegiatan + Kode Rekening yang sama membaca RAK yang SAMA persis.
     */
    public function targetRakBulan(int $bulan, int $tahun): ?float
    {
        $target = RakBulanan::where('sub_kegiatan_kunci', $this->sub_kegiatan_kunci)
            ->where('kode_rekening', $this->kode_rekening)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->value('target');

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
        $query = fn () => RakBulanan::where('sub_kegiatan_kunci', $this->sub_kegiatan_kunci)
            ->where('kode_rekening', $this->kode_rekening)
            ->where('tahun', $tahun);

        if (! $query()->exists()) {
            return null;
        }

        return (float) $query()->where('bulan', '<=', $bulan)->sum('target');
    }
}
