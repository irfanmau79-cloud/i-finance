<?php

namespace App\Support;

/**
 * Mengubah coretan_json (strokes freehand, koordinat relatif 0..1 terhadap
 * seluruh halaman fisik PDF termasuk margin) menjadi markup SVG yang bisa
 * disisipkan ke HTML sebelum WriteHTML() mPDF, supaya coretan Verifikator
 * ikut ter-render langsung ke file PDF (bukan overlay terpisah). Satu
 * coretan_json menyimpan strokes untuk BEBERAPA dokumen sekaligus (NPD,
 * Lampiran, Daftar Bayar, SPD Rampung, dst) dibedakan lewat key 'dokumen'
 * per stroke - overlayHtml() hanya merender strokes milik $dokumen yang
 * diminta. Hanya halaman 1 tiap dokumen yang didukung untuk saat ini
 * karena dokumen-dokumen ini selalu satu halaman dalam praktiknya.
 */
class CoretanPdf
{
    /** Dipakai untuk strokes lama (sebelum fitur multi-dokumen) yang belum punya key 'dokumen'. */
    public const DOKUMEN_DEFAULT = 'npd';

    public static function overlayHtml(?string $coretanJson, float $pageWidthMm, float $pageHeightMm, string $dokumen = self::DOKUMEN_DEFAULT): string
    {
        if (! $coretanJson) {
            return '';
        }

        $decoded = json_decode($coretanJson, true);
        $strokes = is_array($decoded) ? ($decoded['strokes'] ?? []) : [];

        if (! is_array($strokes) || $strokes === []) {
            return '';
        }

        $polylines = '';

        foreach ($strokes as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }

            $strokeDokumen = is_string($stroke['dokumen'] ?? null) ? $stroke['dokumen'] : self::DOKUMEN_DEFAULT;
            if ($strokeDokumen !== $dokumen) {
                continue;
            }

            $page = (int) ($stroke['page'] ?? 1);
            if ($page !== 1) {
                continue;
            }

            $points = $stroke['points'] ?? [];
            if (! is_array($points) || count($points) < 2) {
                continue;
            }

            $color = (is_string($stroke['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $stroke['color']))
                ? $stroke['color']
                : '#e11d48';

            $widthRel = is_numeric($stroke['width'] ?? null) ? (float) $stroke['width'] : 0.003;
            $widthMm = max(0.15, min(5, $widthRel * $pageWidthMm));

            $coords = [];
            foreach ($points as $point) {
                if (! is_array($point) || count($point) < 2) {
                    continue;
                }
                $x = max(0, min(1, (float) $point[0])) * $pageWidthMm;
                $y = max(0, min(1, (float) $point[1])) * $pageHeightMm;
                $coords[] = sprintf('%.2F,%.2F', $x, $y);
            }

            if (count($coords) < 2) {
                continue;
            }

            $polylines .= sprintf(
                '<polyline points="%s" fill="none" stroke="%s" stroke-width="%.2F" stroke-linecap="round" stroke-linejoin="round" />',
                implode(' ', $coords),
                $color,
                $widthMm
            );
        }

        if ($polylines === '') {
            return '';
        }

        return sprintf(
            '<div style="position:absolute;top:0mm;left:0mm;width:%1$.2Fmm;height:%2$.2Fmm;">'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="%1$.2Fmm" height="%2$.2Fmm" viewBox="0 0 %1$.2F %2$.2F">%3$s</svg>'
            .'</div>',
            $pageWidthMm,
            $pageHeightMm,
            $polylines
        );
    }
}
