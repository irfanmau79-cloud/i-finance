<?php

namespace App\Support;

use App\Helpers\NpdPerjalananHitung;
use App\Models\Npd;

/**
 * "Pilih Penerima" pada NPD Perjalanan Dinas (keputusan Irfan, Oktober 2026).
 *
 * Menggantikan kotak centang "PPTK Sebagai Penerima" dan radio "Penerima
 * Dana" per anggota. Tiga pilihannya hanya menentukan TUJUAN TRANSFER pada
 * Lampiran NPD; Daftar Pembayaran dan SPD Rampung tetap memerinci seluruh
 * anggota tim apa pun pilihannya.
 *
 *  - koordinator : satu anggota tim (dipilih di formulir) menerima totalnya.
 *  - semua       : tiap anggota menerima bagiannya sendiri - satu baris
 *                  Lampiran per anggota yang DIBAYAR.
 *  - pptk        : seluruhnya ke PPTK sub kegiatan (App\Support\PptkPenerima).
 *
 * Penanda lama `pptk_penerima` tetap ikut disimpan (true hanya pada mode
 * pptk), supaya NPD lama dan seluruh kode yang membacanya tidak berubah arti.
 */
class PenerimaPd
{
    public const KOORDINATOR = 'koordinator';

    public const SEMUA = 'semua';

    public const PPTK = 'pptk';

    /** Urutan dan bunyi pilihan di formulir. */
    public const PILIHAN = [
        self::KOORDINATOR => 'Koordinator Anggota Tim',
        self::SEMUA => 'Semua Anggota Tim menjadi Penerima',
        self::PPTK => 'PPTK',
    ];

    /**
     * Mode dari isian formulir. Permintaan cara lama (tanpa `mode_penerima`)
     * dibaca dari centang `pptk_penerima`-nya.
     *
     * @param  array<string, mixed>  $data
     */
    public static function modeDariIsian(array $data): string
    {
        $mode = $data['mode_penerima'] ?? null;

        if (is_string($mode) && isset(self::PILIHAN[$mode])) {
            return $mode;
        }

        return ($data['pptk_penerima'] ?? false) ? self::PPTK : self::KOORDINATOR;
    }

    /**
     * Mode sebuah NPD tersimpan. "Semua anggota" hanya dikenal NPD Perjalanan
     * Dinas: NPD Transport menyalin detail induknya, tetapi penerimanya tetap
     * satu orang seperti sebelumnya.
     */
    public static function mode(Npd $npd): string
    {
        $mode = self::modeDariIsian($npd->detail_json ?? []);

        return $mode === self::SEMUA && $npd->jenis !== 'pd' ? self::KOORDINATOR : $mode;
    }

    /**
     * Apakah anggota ini dibayar? Anggota yang ikut ditugaskan dalam Surat
     * Perintah tetapi tidak dibayar tetap tercatat di tim dengan jumlah 0.
     *
     * @param  array<string, mixed>  $anggota
     */
    public static function dibayar(array $anggota): bool
    {
        return NpdPerjalananHitung::hitungAnggota($anggota)['jumlah'] > 0;
    }
}
