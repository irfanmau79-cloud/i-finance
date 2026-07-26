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
}
