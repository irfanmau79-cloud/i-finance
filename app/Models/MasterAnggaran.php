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
     * Realisasi dari jalur NPD (UP/GU): BPP bayar transaksi lewat NPD, lalu
     * isi ulang kas lewat SPM UP/GU (SPM UP/GU sendiri BUKAN realisasi).
     * Semua NPD kecuali yang batal/dibatalkan (Draft tetap dihitung karena
     * dananya sudah dipesan).
     */
    public function realisasiNpd(): float
    {
        return (float) $this->npd()->where('status', 'not like', '%batal%')->sum('nominal');
    }

    /**
     * Realisasi dari jalur LS: dicairkan langsung di BPKAD ke pihak ketiga
     * tanpa NPD, langsung mengurangi pagu. Lihat Spm::buatLs().
     */
    public function realisasiLs(): float
    {
        return (float) $this->spm()->where('jenis_spm', 'ls')->sum('nominal');
    }

    /**
     * Total realisasi = jalur NPD (UP/GU) + jalur LS. Method terpusat ini
     * dipanggil semua modul (form NPD, Rincian, Analisis, Dashboard) — JANGAN
     * hitung realisasi terpisah di tempat lain, selalu lewat method ini.
     */
    public function totalRealisasi(): float
    {
        return $this->realisasiNpd() + $this->realisasiLs();
    }

    /** Sisa Anggaran = Pagu - total realisasi. Ini yang dipakai untuk validasi NPD/SPM LS, bukan pagu. */
    public function sisaAnggaran(): float
    {
        return (float) $this->pagu - $this->totalRealisasi();
    }

    /**
     * Sisa Anggaran SEBELUM $npd dibuat: pagu dikurangi seluruh NPD (non-batal)
     * pada rekening ini yang tercatat lebih dulu (id lebih kecil). Dipakai di
     * cetak NPD, kolom "Sisa Anggaran" di tabel rincian.
     */
    public function sisaAnggaranSebelum(Npd $npd): float
    {
        $terpakaiSebelum = (float) $this->npd()
            ->where('id', '<', $npd->id)
            ->where('status', 'not like', '%batal%')
            ->sum('nominal');

        return (float) $this->pagu - $terpakaiSebelum;
    }
}
