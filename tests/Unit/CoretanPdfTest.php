<?php

namespace Tests\Unit;

use App\Support\CoretanPdf;
use PHPUnit\Framework\TestCase;

class CoretanPdfTest extends TestCase
{
    public function test_null_atau_kosong_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', CoretanPdf::overlayHtml(null, 215, 330));
        $this->assertSame('', CoretanPdf::overlayHtml('', 215, 330));
        $this->assertSame('', CoretanPdf::overlayHtml('{"strokes":[]}', 215, 330));
        $this->assertSame('', CoretanPdf::overlayHtml('bukan json', 215, 330));
    }

    public function test_menghasilkan_svg_dengan_koordinat_dalam_mm_relatif_halaman_penuh(): void
    {
        $json = json_encode([
            'strokes' => [
                ['page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0, 0], [1, 1]]],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        $this->assertStringContainsString('position:absolute', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('215.00mm', $html);
        $this->assertStringContainsString('330.00mm', $html);
        $this->assertStringContainsString('stroke="#e11d48"', $html);
        // (0,0) -> 0.00,0.00mm ; (1,1) -> ujung halaman 215x330mm.
        $this->assertStringContainsString('0.00,0.00 215.00,330.00', $html);
    }

    public function test_strokes_di_halaman_selain_1_diabaikan(): void
    {
        $json = json_encode([
            'strokes' => [
                ['page' => 2, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0, 0], [1, 1]]],
            ],
        ]);

        $this->assertSame('', CoretanPdf::overlayHtml($json, 215, 330));
    }

    public function test_stroke_dengan_kurang_dari_dua_titik_diabaikan(): void
    {
        $json = json_encode([
            'strokes' => [
                ['page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0.5, 0.5]]],
            ],
        ]);

        $this->assertSame('', CoretanPdf::overlayHtml($json, 215, 330));
    }

    public function test_warna_tidak_valid_diganti_default_merah(): void
    {
        $json = json_encode([
            'strokes' => [
                ['page' => 1, 'color' => 'javascript:alert(1)', 'width' => 0.01, 'points' => [[0, 0], [0.5, 0.5]]],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        $this->assertStringContainsString('stroke="#e11d48"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_lebar_garis_dibatasi_minimal_dan_maksimal(): void
    {
        $jsonTerlaluTipis = json_encode([
            'strokes' => [['page' => 1, 'color' => '#000000', 'width' => 0.0000001, 'points' => [[0, 0], [1, 1]]]],
        ]);
        $jsonTerlaluTebal = json_encode([
            'strokes' => [['page' => 1, 'color' => '#000000', 'width' => 999, 'points' => [[0, 0], [1, 1]]]],
        ]);

        $this->assertStringContainsString('stroke-width="0.15"', CoretanPdf::overlayHtml($jsonTerlaluTipis, 215, 330));
        $this->assertStringContainsString('stroke-width="5.00"', CoretanPdf::overlayHtml($jsonTerlaluTebal, 215, 330));
    }

    public function test_strokes_terpisah_per_dokumen(): void
    {
        $json = json_encode([
            'strokes' => [
                ['dokumen' => 'npd', 'page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0, 0], [0.1, 0.1]]],
                ['dokumen' => 'lampiran', 'page' => 1, 'color' => '#0000ff', 'width' => 0.01, 'points' => [[0.2, 0.2], [0.3, 0.3]]],
                ['dokumen' => 'daftar', 'page' => 1, 'color' => '#00aa00', 'width' => 0.01, 'points' => [[0.4, 0.4], [0.5, 0.5]]],
            ],
        ]);

        $npd = CoretanPdf::overlayHtml($json, 215, 330, 'npd');
        $lampiran = CoretanPdf::overlayHtml($json, 215, 330, 'lampiran');
        $daftar = CoretanPdf::overlayHtml($json, 215, 330, 'daftar');
        $spd = CoretanPdf::overlayHtml($json, 215, 330, 'spd');

        $this->assertStringContainsString('stroke="#e11d48"', $npd);
        $this->assertStringNotContainsString('stroke="#0000ff"', $npd);
        $this->assertStringNotContainsString('stroke="#00aa00"', $npd);

        $this->assertStringContainsString('stroke="#0000ff"', $lampiran);
        $this->assertStringNotContainsString('stroke="#e11d48"', $lampiran);

        $this->assertStringContainsString('stroke="#00aa00"', $daftar);
        $this->assertStringNotContainsString('stroke="#e11d48"', $daftar);

        // Tidak ada stroke bertanda dokumen 'spd' pada data ini.
        $this->assertSame('', $spd);
    }

    public function test_stroke_tanpa_key_dokumen_dianggap_npd_untuk_kompatibilitas_data_lama(): void
    {
        $json = json_encode([
            'strokes' => [
                ['page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0, 0], [0.1, 0.1]]],
            ],
        ]);

        $this->assertStringContainsString('polyline', CoretanPdf::overlayHtml($json, 215, 330, 'npd'));
        $this->assertSame('', CoretanPdf::overlayHtml($json, 215, 330, 'lampiran'));
    }

    /**
     * Stabilo bukan sekadar pena tebal: warnanya harus TEMBUS PANDANG supaya
     * tulisan di bawahnya tetap terbaca, dan ujungnya persegi seperti spidol.
     * Kalau stroke-opacity-nya hilang, stabilo berubah jadi penutup tinta
     * yang menghalangi tulisan yang justru ingin disorot.
     */
    public function test_stabilo_tembus_pandang_berujung_persegi_dan_boleh_jauh_lebih_tebal(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'stabilo', 'page' => 1, 'color' => '#f59e0b', 'width' => 0.03, 'points' => [[0.1, 0.2], [0.6, 0.2]]],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        $this->assertStringContainsString('stroke-opacity="0.35"', $html);
        $this->assertStringContainsString('stroke-linecap="butt"', $html);
        // 0,03 x 215mm = 6,45mm - jauh di atas batas 5mm milik pena.
        $this->assertStringContainsString('stroke-width="6.45"', $html);
    }

    public function test_pena_tetap_pekat_dan_berujung_bulat(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'pena', 'page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0, 0], [1, 1]]],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        $this->assertStringNotContainsString('stroke-opacity', $html);
        $this->assertStringContainsString('stroke-linecap="round"', $html);
    }

    public function test_teks_dirender_sebagai_div_absolut_pada_titik_relatifnya(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'teks', 'page' => 1, 'color' => '#1d4ed8', 'x' => 0.2, 'y' => 0.5,
                    'ukuran' => 0.02, 'teks' => "Nominal tidak sesuai\nkuitansi"],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        // 0,2 x 215 = 43mm dari kiri; 0,5 x 330 = 165mm dari atas.
        $this->assertStringContainsString('left:43.00mm', $html);
        $this->assertStringContainsString('top:165.00mm', $html);
        $this->assertStringContainsString('color:#1d4ed8', $html);
        $this->assertStringContainsString('Nominal tidak sesuai', $html);
        // Baris baru yang diketik Verifikator harus tetap terpisah di PDF.
        $this->assertStringContainsString('<br>', $html);
        // Teks lepas tidak berlatar - yang berlatar hanya sticky.
        $this->assertStringNotContainsString('#fef9c3', $html);
    }

    /**
     * Kertas tempel BERUKURAN TETAP: tinggi selalu 1,5 kali lebarnya
     * (perbandingan tinggi:lebar 3:2), tidak mengikuti panjang catatan.
     * Kalau suatu saat tingginya dibiarkan tumbuh lagi, test ini yang gagal.
     */
    public function test_sticky_berukuran_tetap_dengan_perbandingan_tiga_banding_dua(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'lebar' => 0.2,
                    'ukuran' => 0.014, 'baris' => ['Lampirkan kuitansi', 'asli sebelum diajukan']],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        // 0,2 x 215mm = 43mm lebar -> 64,50mm tinggi (43 x 1,5).
        $this->assertStringContainsString('width:43.00mm;height:64.50mm', $html);
        $this->assertStringContainsString('Lampirkan kuitansi<br>asli sebelum diajukan', $html);

        // Catatan panjang tidak melebarkan kertasnya - ukurannya sama saja.
        $panjang = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'lebar' => 0.2, 'ukuran' => 0.014,
                    'baris' => ['satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan']],
            ],
        ]);
        $this->assertStringContainsString('width:43.00mm;height:64.50mm', CoretanPdf::overlayHtml($panjang, 215, 330));
    }

    /**
     * Kesan tiga dimensi dibangun dari tiga lapis yang digambar berurutan:
     * bayangan di belakang, kertas bergradien, lalu lipatan sudut di atasnya.
     * mPDF tidak punya box-shadow, jadi ketiganya harus berupa elemen nyata -
     * dan urutannya menentukan mana yang menimpa mana.
     */
    public function test_sticky_bertumpuk_tiga_lapis_bayangan_gradien_dan_lipatan(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'lebar' => 0.2,
                    'ukuran' => 0.014, 'baris' => ['Periksa ulang']],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        $posisiBayangan = strpos($html, '#cbd5e1');
        $posisiKertas = strpos($html, 'linear-gradient(to bottom,#fefce8');
        $posisiLipatan = strpos($html, '<polygon');

        $this->assertNotFalse($posisiBayangan, 'Bayangan sticky tidak ada.');
        $this->assertNotFalse($posisiKertas, 'Gradien kertas sticky tidak ada.');
        $this->assertNotFalse($posisiLipatan, 'Lipatan sudut sticky tidak ada.');

        // Urutan gambar = urutan tumpuk.
        $this->assertLessThan($posisiKertas, $posisiBayangan);
        $this->assertLessThan($posisiLipatan, $posisiKertas);

        // Bayangan digeser ke kanan-bawah, bukan menutupi kertasnya.
        $this->assertStringContainsString('top:34.51mm;left:23.00mm', $html);
        $this->assertStringContainsString('top:33.00mm;left:21.50mm', $html);
    }

    /**
     * Pemecahan baris diambil dari yang sudah DIUKUR DI LAYAR ('baris'), bukan
     * dipecah ulang di sini: kalau PDF memecah di tempat lain, hasil cetak
     * tidak lagi sama dengan yang dilihat Verifikator saat menempel.
     */
    public function test_sticky_memakai_pemecahan_baris_dari_layar_dan_teks_utuh_sebagai_cadangan(): void
    {
        $dariLayar = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.1, 'y' => 0.1,
                    'baris' => ['Baris satu', 'Baris dua'], 'teks' => 'diabaikan karena baris ada'],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($dariLayar, 215, 330);
        $this->assertStringContainsString('Baris satu<br>Baris dua', $html);
        $this->assertStringNotContainsString('diabaikan', $html);

        // Tanpa 'baris' (data lama / JSON buatan tangan) teksnya tetap tercetak.
        $tanpaBaris = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'teks' => "Baris satu\nBaris dua"],
            ],
        ]);
        $this->assertStringContainsString('Baris satu<br>Baris dua', CoretanPdf::overlayHtml($tanpaBaris, 215, 330));
    }

    /** Kertas yang ditempel dekat tepi digeser masuk, bukan terpotong separuh. */
    public function test_sticky_di_tepi_digeser_masuk_halaman(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.98, 'y' => 0.98, 'lebar' => 0.2,
                    'ukuran' => 0.014, 'baris' => ['Di tepi']],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        // 215 - 43 = 172mm; 330 - 64,5 = 265,50mm.
        $this->assertStringContainsString('top:265.50mm;left:172.00mm', $html);
    }

    /**
     * Isi catatan diketik pemakai lalu ditempelkan ke HTML yang dirender
     * mPDF - jadi harus lolos htmlspecialchars, bukan masuk mentah.
     */
    public function test_isi_teks_dan_sticky_diloloskan_dari_html(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'teks', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'teks' => '<script>alert(1)</script>'],
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.2, 'y' => 0.2, 'teks' => '<img src=x onerror=alert(2)>'],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        // Yang berbahaya adalah TAG-nya, bukan kata "onerror" sebagai
        // tulisan biasa: sesudah diloloskan, isinya cuma teks di dalam div.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
    }

    public function test_teks_dan_sticky_tanpa_isi_diabaikan(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'teks', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'teks' => '   '],
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.2, 'y' => 0.2, 'teks' => null],
            ],
        ]);

        $this->assertSame('', CoretanPdf::overlayHtml($json, 215, 330));
    }

    /** Empat jenis sekaligus - urusan tiap jenis tidak saling makan. */
    public function test_empat_jenis_bisa_bercampur_dalam_satu_dokumen(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'pena', 'page' => 1, 'color' => '#e11d48', 'width' => 0.004, 'points' => [[0.1, 0.1], [0.2, 0.2]]],
                ['jenis' => 'stabilo', 'page' => 1, 'color' => '#f59e0b', 'width' => 0.03, 'points' => [[0.3, 0.3], [0.7, 0.3]]],
                ['jenis' => 'teks', 'page' => 1, 'color' => '#111827', 'x' => 0.1, 'y' => 0.6, 'teks' => 'Periksa ulang'],
                ['jenis' => 'sticky', 'page' => 1, 'x' => 0.5, 'y' => 0.7, 'teks' => 'Kurang tanda tangan PPTK'],
            ],
        ]);

        $html = CoretanPdf::overlayHtml($json, 215, 330);

        // Kedua garis masuk ke SATU svg berisi coretan. Ada satu svg lagi
        // milik lipatan sudut sticky - itu bagian bentuknya, bukan coretan.
        $this->assertSame(2, preg_match_all('/<polyline/', $html));
        $this->assertSame(1, preg_match_all('/viewBox="0 0 215.00 330.00"/', $html));
        $this->assertSame(2, preg_match_all('/<polygon/', $html));
        $this->assertStringContainsString('Periksa ulang', $html);
        $this->assertStringContainsString('Kurang tanda tangan PPTK', $html);
    }

    /** Jenis tak dikenal (data rusak) dianggap pena, tidak menggagalkan halaman. */
    public function test_jenis_asing_diperlakukan_sebagai_pena(): void
    {
        $json = json_encode([
            'strokes' => [
                ['jenis' => 'entah-apa', 'page' => 1, 'color' => '#000000', 'width' => 0.004, 'points' => [[0, 0], [0.5, 0.5]]],
            ],
        ]);

        $this->assertStringContainsString('<polyline', CoretanPdf::overlayHtml($json, 215, 330));
    }

    private function coret(string $dokumen, array $kotak, string $teks = 'Rp1.000.000,00', string $catatan = ''): array
    {
        return ['dokumen' => $dokumen, 'page' => 1, 'jenis' => 'coret', 'color' => '#1d4ed8',
            'kotak' => $kotak, 'teks' => $teks, 'catatan' => $catatan];
    }

    /**
     * Coret + Catatan: satu garis mendatar di TENGAH tinggi huruf per baris,
     * selebar teksnya, plus lencana nomor di ujung kanan baris terakhir.
     */
    public function test_coret_teks_bergaris_di_tengah_huruf_tiap_baris_dan_bernomor(): void
    {
        $json = json_encode(['strokes' => [
            $this->coret('npd', [[0.2, 0.1, 0.4, 0.01], [0.2, 0.2, 0.3, 0.01]]),
        ]]);

        $html = CoretanPdf::overlayHtml($json, 200, 300);

        // Baris 1: x 40..120mm, tengah = (0.1 + 0.005) * 300 = 31.5mm.
        $this->assertStringContainsString('<line x1="40.00" y1="31.50" x2="120.00" y2="31.50" stroke="#1d4ed8"', $html);
        // Baris 2: x 40..100mm, tengah = (0.2 + 0.005) * 300 = 61.5mm.
        $this->assertStringContainsString('<line x1="40.00" y1="61.50" x2="100.00" y2="61.50"', $html);
        $this->assertSame(1, preg_match_all('/<circle/', $html));
        $this->assertMatchesRegularExpression('#<text[^>]*>1</text>#', $html);
        // Lencana menempel di kanan baris TERAKHIR (100mm + 0,6mm), bukan baris pertama.
        $this->assertStringContainsString('left:100.60mm', $html);
    }

    /**
     * Nomor coret menerus di SEMUA dokumen dalam urutan simpan - nomor yang
     * sama tidak boleh menunjuk dua coretan, karena catatan pengembalian di
     * histori menyebut coretan lewat nomornya.
     */
    public function test_nomor_coret_menerus_lintas_dokumen_dan_mengabaikan_kotak_rusak(): void
    {
        $json = json_encode(['strokes' => [
            $this->coret('npd', [[0.1, 0.1, 0.2, 0.01]]),
            $this->coret('npd', []),                         // rusak: tidak bernomor
            ['jenis' => 'pena', 'dokumen' => 'lampiran', 'page' => 1, 'width' => 0.004, 'points' => [[0, 0], [0.1, 0.1]]],
            $this->coret('lampiran', [[0.1, 0.5, 0.2, 0.01]]),
        ]]);

        $npd = CoretanPdf::overlayHtml($json, 215, 330, 'npd');
        $lampiran = CoretanPdf::overlayHtml($json, 215, 330, 'lampiran');

        $this->assertSame(1, preg_match_all('/<circle/', $npd));
        $this->assertMatchesRegularExpression('#<text[^>]*>1</text>#', $npd);
        $this->assertSame(1, preg_match_all('/<circle/', $lampiran));
        $this->assertMatchesRegularExpression('#<text[^>]*>2</text>#', $lampiran);
    }

    public function test_halaman_catatan_hanya_untuk_dokumen_yang_punya_coret_teks(): void
    {
        $json = json_encode(['strokes' => [
            ['jenis' => 'pena', 'dokumen' => 'npd', 'page' => 1, 'width' => 0.004, 'points' => [[0, 0], [0.1, 0.1]]],
            $this->coret('lampiran', [[0.1, 0.5, 0.2, 0.01]], 'Rp1.000.000,00', 'Seharusnya <b>950.000</b>'),
            $this->coret('lampiran', [[0.1, 0.6, 0.2, 0.01]], 'Tangga-Alat'),
        ]]);

        $this->assertSame('', CoretanPdf::halamanCatatanHtml(null, 'npd'));
        $this->assertSame('', CoretanPdf::halamanCatatanHtml($json, 'npd'));

        $html = CoretanPdf::halamanCatatanHtml($json, 'lampiran');

        $this->assertStringStartsWith('<pagebreak />', $html);
        $this->assertStringContainsString('CATATAN VERIFIKASI', $html);
        $this->assertStringContainsString('Rp1.000.000,00', $html);
        // Isi catatan diketik pemakai: wajib diloloskan.
        $this->assertStringContainsString('Seharusnya &lt;b&gt;950.000&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>950.000</b>', $html);
        $this->assertStringContainsString('(tanpa catatan)', $html);
        $this->assertSame(2, preg_match_all('#<tr><td#', $html));
    }
}
