<?php

namespace App\Support;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Menyatukan beberapa PDF jadi satu berkas.
 *
 * Tiap dokumen tetap dirender sendiri-sendiri lebih dulu dengan konfigurasi
 * mPDF-nya masing-masing (margin NPD, Lampiran, Daftar Bayar, dan SPD Rampung
 * berbeda-beda), lalu di sini halaman jadinya DISALIN apa adanya lewat FPDI —
 * bukan dirender ulang dalam satu dokumen. Ini disengaja: dokumen cetak sudah
 * ditandatangani di kantor dan wajib identik dengan versi GAS, jadi hasil
 * gabungan harus sama persis dengan hasil cetak satu-satu.
 *
 * Ukuran dan orientasi tiap halaman diambil dari halaman sumbernya, jadi
 * dokumen yang kelak memakai kertas atau orientasi lain tetap ikut utuh.
 */
class PdfGabung
{
    /**
     * @param  array<int, string>  $dokumen  isi biner tiap PDF, sesuai urutan yang diinginkan
     * @return string isi biner PDF gabungan
     */
    public static function satukan(array $dokumen): string
    {
        $dokumen = array_values(array_filter($dokumen, static fn ($isi) => is_string($isi) && $isi !== ''));

        if ($dokumen === []) {
            throw new RuntimeException('Tidak ada dokumen yang bisa digabungkan.');
        }

        // Margin nol: tiap halaman ditempel sebagai satu kesatuan gambar
        // vektor pada koordinat 0,0, jadi marginnya sudah ikut di dalamnya.
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [215, 330],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        foreach ($dokumen as $isi) {
            $jumlah = $mpdf->setSourceFile(StreamReader::createByString($isi));

            for ($halaman = 1; $halaman <= $jumlah; $halaman++) {
                $cetakan = $mpdf->importPage($halaman);
                $ukuran = $mpdf->getTemplateSize($cetakan);

                $mpdf->AddPageByArray([
                    'orientation' => $ukuran['orientation'],
                    'sheet-size' => [$ukuran['width'], $ukuran['height']],
                ]);

                $mpdf->useTemplate($cetakan, 0, 0, $ukuran['width'], $ukuran['height']);
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
