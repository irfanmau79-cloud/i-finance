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
 * dokumen yang memakai kertas atau orientasi lain - mis. berkas SPJ hasil
 * pindaian mendatar - tetap ikut utuh.
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

                // orientation SELALU 'P', bukan $ukuran['orientation'].
                //
                // Ini bukan berarti halamannya dipaksa tegak - justru
                // sebaliknya. Mpdf::_setPageSize() MENUKAR sisi yang diberikan
                // begitu orientasinya 'L' (wPt = fhPt, hPt = fwPt), karena ia
                // mengharapkan ukuran kertas dalam susunan tegak lalu
                // memutarnya sendiri. Mengirim ukuran yang SUDAH mendatar
                // (330x215) BERSAMA orientation 'L' berarti penukaran itu
                // terjadi dua kali, dan halamannya berakhir tegak 215x330 -
                // sementara isinya tetap ditempel selebar 330mm, jadi bagian
                // kanannya terpotong. Itulah yang terjadi pada SPJ mendatar.
                //
                // Dengan 'P', ukuran dipakai apa adanya: halaman jadi persis
                // sebesar halaman sumbernya, mendatar maupun tegak.
                $mpdf->AddPageByArray([
                    'orientation' => 'P',
                    'sheet-size' => [$ukuran['width'], $ukuran['height']],
                ]);

                $mpdf->useTemplate($cetakan, 0, 0, $ukuran['width'], $ukuran['height']);
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
