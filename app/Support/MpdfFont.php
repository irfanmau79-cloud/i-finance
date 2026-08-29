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
        foreach (self::kandidatDirektori() as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $lengkap = true;

            foreach (self::BERKAS as $berkas) {
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
        [$kiri, $kanan, $atas, $bawah] = $margin;

        $konfig = [
            'format' => [215, 330],
            'margin_left' => $kiri,
            'margin_right' => $kanan,
            'margin_top' => $atas,
            'margin_bottom' => $bawah,
        ];

        $dir = self::direktoriArial();

        if ($dir === null) {
            return $konfig + ['default_font' => 'dejavusanscondensed'];
        }

        $bawaan = (new ConfigVariables)->getDefaults();
        $fontBawaan = (new FontVariables)->getDefaults();

        return $konfig + [
            'fontDir' => array_merge($bawaan['fontDir'], [$dir]),
            'fontdata' => $fontBawaan['fontdata'] + [
                'arial' => [
                    'R' => self::BERKAS['R'],
                    'B' => self::BERKAS['B'],
                    'I' => self::BERKAS['I'],
                    'BI' => self::BERKAS['BI'],
                ],
            ],
            'default_font' => 'arial',
        ];
    }
}
