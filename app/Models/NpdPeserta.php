<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'npd_id',
    'pegawai_id',
    'nama',
    'pangkat',
    'nip',
    'rekening',
    'volume_kontribusi',
    'tarif_kontribusi',
    'volume_mooc',
    'tarif_mooc',
    'hari_uh',
    'tarif_uh',
    'volume_akomodasi',
    'tarif_akomodasi',
    'hari_saku',
    'tarif_saku',
    'transport',
])]
class NpdPeserta extends Model
{
    protected $table = 'npd_peserta';

    protected function casts(): array
    {
        return [
            'volume_kontribusi' => 'integer',
            'tarif_kontribusi' => 'decimal:2',
            'volume_mooc' => 'integer',
            'tarif_mooc' => 'decimal:2',
            'hari_uh' => 'integer',
            'tarif_uh' => 'decimal:2',
            'volume_akomodasi' => 'integer',
            'tarif_akomodasi' => 'decimal:2',
            'hari_saku' => 'integer',
            'tarif_saku' => 'decimal:2',
            'transport' => 'decimal:2',
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

    /**
     * Golongan SAJA untuk kolom GOL pada Daftar Pembayaran Perjalanan Dinas
     * Diklat - "III/a", "IV/b", "VII" - tanpa nama pangkatnya.
     *
     * Isian peserta hanya punya satu kolom "Pangkat/Golongan", jadi isinya
     * bisa "Penata Muda (III/a)", "Penata Muda Tk. I, III/b", "III/a", atau
     * "VII" (PPPK). Golongannya dipungut dari teks itu:
     *
     *   1. golongan PNS di mana pun letaknya: I-IV diikuti "/" dan huruf a-e;
     *   2. golongan PPPK (angka Romawi tanpa huruf) bila berdiri sendiri atau
     *      di dalam kurung. Sengaja TIDAK dicari di tengah kalimat: "Tk. I"
     *      pada "Penata Muda Tk. I" adalah tingkat pangkat, bukan golongan;
     *   3. bila tidak ada di teksnya, golongan pada master Pegawai;
     *   4. bila tetap tidak ketemu, teks aslinya dipertahankan - lebih baik
     *      kolomnya memuat apa yang diketik daripada kosong tanpa penjelasan.
     */
    public function golongan(): string
    {
        $teks = trim((string) $this->pangkat);

        return self::golonganDariTeks($teks)
            ?? self::golonganDariTeks(trim((string) $this->pegawai?->golongan))
            ?? $teks;
    }

    public static function golonganDariTeks(string $teks): ?string
    {
        if (preg_match('/\b(IV|III|II|I)\s*\/\s*([a-e])\b/i', $teks, $cocok)) {
            return strtoupper($cocok[1]).'/'.strtolower($cocok[2]);
        }

        $romawi = '(?:XVII|XVI|XV|XIV|XIII|XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)';

        if (preg_match('/^'.$romawi.'$/i', $teks, $cocok) || preg_match('/\(\s*('.$romawi.')\s*\)/i', $teks, $cocok)) {
            return strtoupper($cocok[1] ?? $cocok[0]);
        }

        return null;
    }

    /** jumlah_kontribusi = volume_kontribusi * tarif_kontribusi. Port dari _hitungPesertaKD() gas-lama/CodeKontribusiDiklat.gs. */
    protected function jumlahKontribusi(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_kontribusi * (float) $this->tarif_kontribusi);
    }

    /** jumlah_mooc = volume_mooc * tarif_mooc. */
    protected function jumlahMooc(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_mooc * (float) $this->tarif_mooc);
    }

    /** Subtotal mode kontribusi = jumlah_kontribusi + jumlah_mooc. Ini nominal NPD untuk mode 'kontribusi'. */
    protected function subKontribusi(): Attribute
    {
        return Attribute::get(fn () => $this->jumlah_kontribusi + $this->jumlah_mooc);
    }

    /** jumlah_harian = hari_uh * tarif_uh. */
    protected function jumlahHarian(): Attribute
    {
        return Attribute::get(fn () => (float) $this->hari_uh * (float) $this->tarif_uh);
    }

    /** jumlah_akomodasi = volume_akomodasi * tarif_akomodasi. */
    protected function jumlahAkomodasi(): Attribute
    {
        return Attribute::get(fn () => (float) $this->volume_akomodasi * (float) $this->tarif_akomodasi);
    }

    /** jumlah_saku = hari_saku * tarif_saku. */
    protected function jumlahSaku(): Attribute
    {
        return Attribute::get(fn () => (float) $this->hari_saku * (float) $this->tarif_saku);
    }

    /** Subtotal mode perjalanan = uang harian + akomodasi + uang saku + transport at-cost. Nominal NPD untuk mode 'perjalanan'. */
    protected function subPerjalanan(): Attribute
    {
        return Attribute::get(fn () => $this->jumlah_harian + $this->jumlah_akomodasi + $this->jumlah_saku + (float) $this->transport);
    }
}
