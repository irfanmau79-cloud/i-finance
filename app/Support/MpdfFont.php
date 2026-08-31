<?php

namespace App\Support;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Konfigurasi font untuk mPDF.
 *
 * mPDF hanya membawa DejaVu. Menulis 'default_font' => 'arial' TIDAK membuat
 * dokumen memakai Arial - mPDF diam-diam jatuh ke DejaVuSansCondensed, yang
 * bentuk dan lebar hurufnya berbeda. Akibatnya seluruh PDF tercetak dengan
 * huruf yang tidak sama dengan dokumen yang sudah ditandatangani di kantor.
 *
 * Kelas ini mendaftarkan berkas Arial yang sesungguhnya bila tersedia:
 * pertama dari storage/fonts (bisa disalin saat penyiapan server), lalu dari
 * direktori font Windows. Bila tidak ada satu pun, dokumen tetap tercetak
 * dengan DejaVu - hurufnya beda, tetapi tidak gagal.
 */
class MpdfFont
{
    /** Berkas yang dibutuhkan: tegak, tebal, miring, tebal-miring. */
    private const BERKAS = [
        'R' => 'arial.ttf',
        'B' => 'arialbd.ttf',
        'I' => 'ariali.ttf',
        'BI' => 'arialbi.ttf',
    ];

    /**
     * Times New Roman, dibutuhkan Surat Keterangan Penghasilan: isi suratnya
     * berhuruf Times sementara kop dan kotak TTE-nya Arial. Tanpa berkas ini
     * bagian isi jatuh ke DejaVu dan lebar barisnya berubah, sehingga tata
     * letak kolom nominal tidak lagi sama dengan dokumen aslinya.
     */
    private const BERKAS_TIMES = [
        'R' => 'times.ttf',
        'B' => 'timesbd.ttf',
        'I' => 'timesi.ttf',
        'BI' => 'timesbi.ttf',
    ];

    /** @return array<int, string> */
    private static function kandidatDirektori(): array
    {
        return [
            storage_path('fonts'),
            'C:\\Windows\\Fonts',
            '/usr/share/fonts/truetype/msttcorefonts',
        ];
    }

    /** Direktori pertama yang memuat keempat berkas Arial, atau null. */
    public static function direktoriArial(): ?string
    {
        return self::direktoriLengkap(self::BERKAS);
    }

    /** Direktori pertama yang memuat keempat berkas Times New Roman, atau null. */
    public static function direktoriTimes(): ?string
    {
        return self::direktoriLengkap(self::BERKAS_TIMES);
    }

    /**
     * @param  array<string, string>  $berkasWajib
     */
    private static function direktoriLengkap(array $berkasWajib): ?string
    {
        foreach (self::kandidatDirektori() as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $lengkap = true;

            foreach ($berkasWajib as $berkas) {
                if (! is_file($dir.DIRECTORY_SEPARATOR.$berkas)) {
                    $lengkap = false;
                    break;
                }
            }

            if ($lengkap) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Konfigurasi mPDF lengkap: kertas F4, margin yang diminta, dan Arial
     * bila tersedia.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $margin  kiri, kanan, atas, bawah (mm)
     * @return array<string, mixed>
     */
    public static function konfigF4(array $margin): array
    {
        return self::konfig([215, 330], $margin);
    }

    /**
     * Konfigurasi mPDF untuk kertas A4. Dipakai Surat Keterangan Penghasilan,
     * satu-satunya dokumen yang di GAS memang dicetak A4 (@page{size:A4})
     * dan bukan F4 seperti dokumen NPD.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $margin  kiri, kanan, atas, bawah (mm)
     * @return array<string, mixed>
     */
    public static function konfigA4(array $margin): array
    {
        return self::konfig([210, 297], $margin);
    }

    /**
     * @param  array{0: float, 1: float}  $format  lebar, tinggi (mm)
     * @param  array{0: float, 1: float, 2: float, 3: float}  $margin  kiri, kanan, atas, bawah (mm)
     * @return array<string, mixed>
     */
    private static function konfig(array $format, array $margin): array
    {
        [$kiri, $kanan, $atas, $bawah] = $margin;

        $konfig = [
            'format' => $format,
            'margin_left' => $kiri,
            'margin_right' => $kanan,
            'margin_top' => $atas,
            'margin_bottom' => $bawah,
        ];

        $dirArial = self::direktoriArial();
        $dirTimes = self::direktoriTimes();

        if ($dirArial === null && $dirTimes === null) {
            return $konfig + ['default_font' => 'dejavusanscondensed'];
        }

        $bawaan = (new ConfigVariables)->getDefaults();
        $fontBawaan = (new FontVariables)->getDefaults();

        $tambahan = [];

        if ($dirArial !== null) {
            $tambahan['arial'] = self::BERKAS;
        }

        if ($dirTimes !== null) {
            $tambahan['times'] = self::BERKAS_TIMES;
            // Alias agar font-family:"Times New Roman" pada CSS dokumen
            // langsung kena, tanpa perlu menulis nama font khusus mPDF.
            $tambahan['times new roman'] = self::BERKAS_TIMES;
        }

        $direktori = array_values(array_unique(array_filter([$dirArial, $dirTimes])));

        return $konfig + [
            'fontDir' => array_merge($bawaan['fontDir'], $direktori),
            'fontdata' => $fontBawaan['fontdata'] + $tambahan,
            'default_font' => $dirArial !== null ? 'arial' : 'dejavusanscondensed',
        ];
    }
}
