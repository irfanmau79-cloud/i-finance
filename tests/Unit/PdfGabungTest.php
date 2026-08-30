<?php

namespace Tests\Unit;

use App\Support\PdfGabung;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Penggabung PDF di balik tombol "Cetak Semua (1 Berkas)" pada detail NPD.
 *
 * Tiap dokumen NPD dicetak dengan margin dan jumlah halaman sendiri, lalu
 * halamannya disalin ke satu berkas. Yang dijaga di sini: urutan masukan
 * dipertahankan, tidak ada halaman yang hilang atau berganda, dan tiap
 * halaman mempertahankan ukuran kertas aslinya.
 *
 * Urutannya diperiksa lewat ukuran kertas: tiap dokumen uji dibuat dengan
 * lebar berbeda, jadi deretan lebar halaman pada berkas hasil menunjukkan
 * dokumen mana yang mendarat di urutan ke berapa.
 */
class PdfGabungTest extends TestCase
{
    /** PDF uji dengan lebar kertas & jumlah halaman tertentu (tinggi tetap 330mm). */
    private function dokumen(int $lebarMm, int $halaman): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [$lebarMm, 330],
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);

        $isi = '';
        for ($i = 1; $i <= $halaman; $i++) {
            $isi .= ($i > 1 ? '<pagebreak>' : '')."<h1>Dokumen {$lebarMm}mm halaman {$i}</h1>";
        }
        $mpdf->WriteHTML($isi);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Lebar tiap halaman dalam milimeter, dibulatkan.
     *
     * @return array<int, int>
     */
    private function lebarHalaman(string $pdf): array
    {
        $pembaca = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
        $lebar = [];

        for ($i = 1; $i <= $pembaca->getPageCount(); $i++) {
            $kotak = $pembaca->getPage($i)->getWidthAndHeight();
            // getWidthAndHeight() memakai satuan titik (1/72 inci).
            $lebar[] = (int) round($kotak[0] / 72 * 25.4);
        }

        return $lebar;
    }

    public function test_semua_halaman_ikut_dan_urutannya_sesuai_masukan(): void
    {
        $npd = $this->dokumen(215, 1);
        $lampiran = $this->dokumen(200, 2);
        $daftar = $this->dokumen(180, 3);

        $gabungan = PdfGabung::satukan([$npd, $lampiran, $daftar]);

        $this->assertStringStartsWith('%PDF-', $gabungan);
        // 1 + 2 + 3 halaman, dan tiap halaman membawa lebar dokumen asalnya —
        // jadi deretan ini sekaligus membuktikan urutannya tidak tertukar.
        $this->assertSame([215, 200, 200, 180, 180, 180], $this->lebarHalaman($gabungan));
    }

    public function test_urutan_lain_menghasilkan_berkas_dengan_urutan_lain(): void
    {
        // Penjaga agar test di atas tidak lolos hanya karena kebetulan urutannya
        // sama dengan urutan pengurutan internal apa pun.
        $gabungan = PdfGabung::satukan([$this->dokumen(180, 1), $this->dokumen(215, 1)]);

        $this->assertSame([180, 215], $this->lebarHalaman($gabungan));
    }

    public function test_satu_dokumen_saja_tetap_utuh(): void
    {
        // NPD Barang/Jasa hanya 2 dokumen, dan jenis mendatang bisa saja 1.
        $gabungan = PdfGabung::satukan([$this->dokumen(215, 2)]);

        $this->assertSame([215, 215], $this->lebarHalaman($gabungan));
    }

    public function test_dokumen_kosong_diabaikan_bukan_jadi_halaman_hampa(): void
    {
        $gabungan = PdfGabung::satukan(['', $this->dokumen(215, 1), '']);

        $this->assertSame([215], $this->lebarHalaman($gabungan));
    }

    public function test_tanpa_dokumen_sama_sekali_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        PdfGabung::satukan(['', '']);
    }
}
