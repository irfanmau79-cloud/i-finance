<?php

namespace App\Support;

/**
 * Mengubah coretan_json Verifikator menjadi markup yang bisa disisipkan ke
 * HTML sebelum WriteHTML() mPDF, supaya coretannya ikut ter-render langsung
 * ke file PDF (bukan overlay terpisah).
 *
 * Satu coretan_json menyimpan coretan untuk BEBERAPA dokumen sekaligus (NPD,
 * Lampiran, Daftar Bayar, SPD Rampung, dst) dibedakan lewat key 'dokumen'
 * per butir - overlayHtml() hanya merender milik $dokumen yang diminta.
 * Hanya halaman 1 tiap dokumen yang didukung karena dokumen-dokumen ini
 * selalu satu halaman dalam praktiknya.
 *
 * LIMA JENIS CORETAN, dan dua cara merendernya:
 *
 *   pena, stabilo  -> <polyline> di dalam satu <svg>
 *   teks, sticky   -> <div position:absolute> masing-masing
 *   coret          -> <line> di <svg> yang sama + <div> nomor bulat; catatannya
 *                     dicetak di halaman tambahan (halamanCatatanHtml)
 *
 * Teks TIDAK ditulis sebagai <text> SVG walau mPDF sanggup merendernya:
 * pembungkusan baris di dalam SVG harus dihitung sendiri (tidak ada yang
 * memecah baris untuk kita), sementara <div> berlebar tetap memecah
 * barisnya sendiri - dan catatan sticky justru selalu berupa beberapa baris.
 *
 * Koordinat seluruh butir RELATIF (0..1) terhadap halaman fisik PDF termasuk
 * margin, jadi tidak ada satu pun angka milimeter yang tersimpan di basis
 * data: ukuran kertas dokumen boleh berubah tanpa membuat coretan lama
 * bergeser.
 */
class CoretanPdf
{
    /** Dipakai untuk butir lama (sebelum fitur multi-dokumen) yang belum punya key 'dokumen'. */
    public const DOKUMEN_DEFAULT = 'npd';

    /**
     * Butir lama (sebelum ada stabilo/teks/sticky) tidak punya key 'jenis'.
     * Semuanya coretan pena, dan HARUS tetap dirender sama seperti dulu -
     * NPD yang sudah dikembalikan ke BPP tidak boleh berubah tampilannya.
     */
    public const JENIS_DEFAULT = 'pena';

    private const WARNA_CADANGAN = '#e11d48';

    /**
     * Ukuran kertas tempel: lebarnya sekian bagian dari lebar halaman, dan
     * tingginya 1,5 kali lebarnya (perbandingan tinggi:lebar 3:2). Keduanya
     * TETAP - tidak mengikuti panjang catatan - dan nilainya harus sama
     * dengan yang dipakai layar (lihat resources/views/npd/coret.blade.php).
     */
    public const STICKY_LEBAR = 0.2;
    public const STICKY_RASIO = 1.5;

    public static function overlayHtml(?string $coretanJson, float $pageWidthMm, float $pageHeightMm, string $dokumen = self::DOKUMEN_DEFAULT): string
    {
        if (! $coretanJson) {
            return '';
        }

        $decoded = json_decode($coretanJson, true);
        $butirSemua = is_array($decoded) ? ($decoded['strokes'] ?? []) : [];

        if (! is_array($butirSemua) || $butirSemua === []) {
            return '';
        }

        $gambar = '';   // isi <svg>: pena, stabilo, garis coret teks
        $lapisan = '';  // <div> absolut: teks, sticky, nomor coret teks

        $nomorCoret = self::nomorCoret($butirSemua);

        foreach ($butirSemua as $indeks => $butir) {
            if (! is_array($butir)) {
                continue;
            }

            $butirDokumen = is_string($butir['dokumen'] ?? null) ? $butir['dokumen'] : self::DOKUMEN_DEFAULT;
            if ($butirDokumen !== $dokumen) {
                continue;
            }

            if ((int) ($butir['page'] ?? 1) !== 1) {
                continue;
            }

            $jenis = is_string($butir['jenis'] ?? null) ? $butir['jenis'] : self::JENIS_DEFAULT;

            $gambar .= match ($jenis) {
                'stabilo' => self::polyline($butir, $pageWidthMm, $pageHeightMm, true),
                'teks', 'sticky' => '',
                'coret' => self::garisCoret($butir, $pageWidthMm, $pageHeightMm),
                default => self::polyline($butir, $pageWidthMm, $pageHeightMm, false),
            };

            $lapisan .= match ($jenis) {
                'teks' => self::teks($butir, $pageWidthMm, $pageHeightMm),
                'sticky' => self::sticky($butir, $pageWidthMm, $pageHeightMm),
                'coret' => isset($nomorCoret[$indeks])
                    ? self::lencanaCoret($butir, $nomorCoret[$indeks], $pageWidthMm, $pageHeightMm)
                    : '',
                default => '',
            };
        }

        if ($gambar === '' && $lapisan === '') {
            return '';
        }

        $svg = $gambar === '' ? '' : sprintf(
            '<div style="position:absolute;top:0mm;left:0mm;width:%1$.2Fmm;height:%2$.2Fmm;">'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="%1$.2Fmm" height="%2$.2Fmm" viewBox="0 0 %1$.2F %2$.2F">%3$s</svg>'
            .'</div>',
            $pageWidthMm,
            $pageHeightMm,
            $gambar
        );

        return $svg.$lapisan;
    }

    /**
     * Satu garis pena atau sapuan stabilo.
     *
     * Stabilo bukan sekadar pena yang lebih tebal: warnanya TEMBUS PANDANG
     * (stroke-opacity) supaya tulisan di bawahnya tetap terbaca - itu
     * seluruh gunanya - dan ujungnya persegi ('butt') seperti spidol asli,
     * bukan membulat seperti pena.
     */
    private static function polyline(array $butir, float $lebarMm, float $tinggiMm, bool $stabilo): string
    {
        $titik = $butir['points'] ?? [];
        if (! is_array($titik) || count($titik) < 2) {
            return '';
        }

        $koordinat = [];
        foreach ($titik as $satu) {
            if (! is_array($satu) || count($satu) < 2) {
                continue;
            }
            $koordinat[] = sprintf(
                '%.2F,%.2F',
                self::pecahan($satu[0]) * $lebarMm,
                self::pecahan($satu[1]) * $tinggiMm
            );
        }

        if (count($koordinat) < 2) {
            return '';
        }

        $tebalRel = is_numeric($butir['width'] ?? null) ? (float) $butir['width'] : ($stabilo ? 0.03 : 0.003);
        $tebalMm = max(0.15, min($stabilo ? 20 : 5, $tebalRel * $lebarMm));

        return sprintf(
            '<polyline points="%s" fill="none" stroke="%s" stroke-width="%.2F" stroke-linecap="%s" stroke-linejoin="round"%s />',
            implode(' ', $koordinat),
            self::warna($butir['color'] ?? null),
            $tebalMm,
            $stabilo ? 'butt' : 'round',
            $stabilo ? ' stroke-opacity="0.35"' : ''
        );
    }

    /** Teks lepas: hanya tulisan, tanpa kotak maupun latar. */
    private static function teks(array $butir, float $lebarMm, float $tinggiMm): string
    {
        $isi = self::isiTeks($butir['teks'] ?? null);
        if ($isi === '') {
            return '';
        }

        // Titik simpannya SUDUT KIRI-ATAS teks, sama seperti di layar.
        // Lebar kotaknya dibatasi sisa lebar halaman supaya teks panjang
        // membungkus ke bawah alih-alih keluar dari kertas.
        $x = self::pecahan($butir['x'] ?? 0) * $lebarMm;
        $y = self::pecahan($butir['y'] ?? 0) * $tinggiMm;
        $lebarKotak = max(20, $lebarMm - $x - 4);

        return sprintf(
            '<div style="position:absolute;top:%.2Fmm;left:%.2Fmm;width:%.2Fmm;font-family:Arial,sans-serif;'
            .'font-size:%.1Fpt;font-weight:bold;line-height:1.25;color:%s;">%s</div>',
            $y,
            $x,
            $lebarKotak,
            self::ukuranPt($butir['ukuran'] ?? null, $lebarMm),
            self::warna($butir['color'] ?? null),
            $isi
        );
    }

    /**
     * Catatan sticky: kertas tempel BERUKURAN TETAP dengan perbandingan
     * tinggi:lebar = 3:2, bukan kotak yang tumbuh mengikuti panjang teksnya.
     *
     * Ukuran tetap dipilih supaya catatan terlihat seperti benda yang
     * ditempelkan - tebalnya sama di mana pun ia ditaruh - dan supaya
     * tata letak dokumen bisa diperkirakan sebelum dicetak. Konsekuensinya:
     * teks yang terlalu panjang DIPOTONG, bukan melebarkan kertasnya. Batas
     * itu sudah dihitung di layar (lihat 'baris'), jadi apa yang tercetak
     * sama dengan apa yang dilihat Verifikator saat menempel.
     *
     * Kesan tiga dimensinya dibangun dari tiga lapis yang digambar berurutan
     * - bayangan, kertas, lalu lipatan sudut - karena mPDF tidak punya
     * box-shadow: yang ada gradien (didukung, jadi shading PDF sungguhan),
     * div bertumpuk, dan poligon SVG.
     */
    private static function sticky(array $butir, float $lebarMm, float $tinggiMm): string
    {
        $baris = self::barisTeks($butir);

        if ($baris === []) {
            return '';
        }

        $lebarRel = is_numeric($butir['lebar'] ?? null) ? (float) $butir['lebar'] : self::STICKY_LEBAR;
        $lebarKotak = max(15, min($lebarMm * 0.6, $lebarRel * $lebarMm));
        $tinggiKotak = $lebarKotak * self::STICKY_RASIO;

        // Titik tempelnya sudut kiri-atas. Kalau kertasnya akan keluar dari
        // halaman, ia digeser masuk - bukan dipotong: catatan yang separuhnya
        // hilang di tepi kertas tidak ada gunanya bagi yang membacanya.
        $x = max(0, min($lebarMm - $lebarKotak, self::pecahan($butir['x'] ?? 0) * $lebarMm));
        $y = max(0, min($tinggiMm - $tinggiKotak, self::pecahan($butir['y'] ?? 0) * $tinggiMm));

        $pt = self::ukuranPt($butir['ukuran'] ?? null, $lebarMm);
        $lipat = $lebarKotak * 0.2;
        $geserBayang = $lebarKotak * 0.035;

        // 1. Bayangan: kertas yang sama, digeser ke kanan-bawah. mPDF tidak
        //    bisa mengaburkan tepi, jadi yang dipakai abu muda - bayangan
        //    tajam tapi tipis masih terbaca sebagai bayangan, sementara abu
        //    tua akan terlihat seperti kotak kedua.
        $html = sprintf(
            '<div style="position:absolute;top:%.2Fmm;left:%.2Fmm;width:%.2Fmm;height:%.2Fmm;background-color:#cbd5e1;"></div>',
            $y + $geserBayang,
            $x + $geserBayang,
            $lebarKotak,
            $tinggiKotak
        );

        // 2. Kertasnya: gradien dari kuning muda di atas ke kuning tua di
        //    bawah, meniru cahaya yang datang dari atas.
        $html .= sprintf(
            '<div style="position:absolute;top:%.2Fmm;left:%.2Fmm;width:%.2Fmm;height:%.2Fmm;'
            .'background-image:linear-gradient(to bottom,#fefce8 0%%,#fef3b0 55%%,#fde68a 100%%);'
            .'border:0.25mm solid #e6c34a;padding:%.2Fmm;box-sizing:border-box;'
            .'font-family:Arial,sans-serif;font-size:%.1Fpt;line-height:1.3;color:#6b4e12;">%s</div>',
            $y,
            $x,
            $lebarKotak,
            $tinggiKotak,
            $lebarKotak * 0.08,
            $pt,
            implode('<br>', $baris)
        );

        // 3. Lipatan sudut kanan-bawah: dua poligon - sisi kertas yang
        //    terangkat (lebih tua) dan bidang di baliknya (lebih muda).
        $html .= sprintf(
            '<div style="position:absolute;top:%.2Fmm;left:%.2Fmm;width:%.2Fmm;height:%.2Fmm;">'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="%3$.2Fmm" height="%4$.2Fmm" viewBox="0 0 10 10">'
            .'<polygon points="0,10 10,10 10,0" fill="#e6c34a" />'
            .'<polygon points="0,10 10,0 0,0" fill="#fbe89a" />'
            .'</svg></div>',
            $y + $tinggiKotak - $lipat,
            $x + $lebarKotak - $lipat,
            $lipat,
            $lipat
        );

        return $html;
    }

    /**
     * Baris teks catatan yang siap dicetak.
     *
     * Didahulukan 'baris' - hasil pemecahan baris DI LAYAR, tempat lebar tiap
     * huruf benar-benar terukur dan jumlah barisnya sudah dipangkas sesuai
     * tinggi kertas. Memecah ulang di sini berarti PDF bisa memecah di tempat
     * berbeda dari yang dilihat Verifikator saat menempel.
     *
     * 'teks' utuh tetap dipakai sebagai cadangan untuk data yang dibuat di
     * luar layar (mis. coretan lama atau JSON buatan tangan).
     *
     * @return array<int, string> sudah diloloskan untuk HTML
     */
    private static function barisTeks(array $butir): array
    {
        $baris = $butir['baris'] ?? null;

        if (is_array($baris) && $baris !== []) {
            $hasil = [];
            foreach (array_slice($baris, 0, 20) as $satu) {
                if (! is_string($satu)) {
                    continue;
                }
                $hasil[] = htmlspecialchars(mb_substr($satu, 0, 120), ENT_QUOTES, 'UTF-8');
            }

            // Baris kosong di ujung tidak menambah apa pun selain ruang.
            while ($hasil !== [] && trim(end($hasil)) === '') {
                array_pop($hasil);
            }

            if ($hasil !== []) {
                return $hasil;
            }
        }

        // Cadangan: pecah teks utuh pada baris barunya sendiri. TIDAK lewat
        // isiTeks() lalu dipecah di tag <br> - nl2br menyisipkan "<br>" plus
        // satu baris baru, sehingga tiap baris membawa baris baru liar yang
        // di PDF terbaca sebagai spasi menggantung.
        $mentah = $butir['teks'] ?? null;

        if (! is_string($mentah)) {
            return [];
        }

        $baris = preg_split("/\r\n|\r|\n/", trim(mb_substr($mentah, 0, 600))) ?: [];
        $hasil = [];

        foreach (array_slice($baris, 0, 20) as $satu) {
            $hasil[] = htmlspecialchars(mb_substr($satu, 0, 120), ENT_QUOTES, 'UTF-8');
        }

        while ($hasil !== [] && trim(end($hasil)) === '') {
            array_pop($hasil);
        }

        return $hasil;
    }

    /**
     * Nomor tiap butir coret teks, dengan indeks butir sebagai kunci.
     *
     * Penomorannya menerus di SELURUH dokumen (NPD, Lampiran, Daftar Bayar,
     * dst) mengikuti urutan simpan, bukan diulang per dokumen: catatan
     * pengembalian di histori menyebut coretan lewat nomornya, dan nomor
     * yang sama tidak boleh menunjuk dua coretan berbeda. Harus sama dengan
     * penomoran di layar (resources/views/npd/coret.blade.php).
     *
     * @return array<int, int>
     */
    private static function nomorCoret(array $butirSemua): array
    {
        $nomor = [];
        $urut = 0;

        foreach ($butirSemua as $indeks => $butir) {
            if (is_array($butir) && ($butir['jenis'] ?? null) === 'coret' && self::kotakCoret($butir) !== []) {
                $nomor[$indeks] = ++$urut;
            }
        }

        return $nomor;
    }

    /**
     * Kotak-kotak teks yang dicoret, satu per baris tulisan: [x, y, lebar,
     * tinggi] relatif halaman, y = batas atas huruf. Kotak rusak atau
     * berukuran nol dibuang.
     *
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private static function kotakCoret(array $butir): array
    {
        $kotak = $butir['kotak'] ?? null;

        if (! is_array($kotak)) {
            return [];
        }

        $hasil = [];
        foreach (array_slice($kotak, 0, 40) as $satu) {
            if (! is_array($satu) || count($satu) < 4) {
                continue;
            }

            [$x, $y, $w, $h] = array_map(self::pecahan(...), array_values(array_slice($satu, 0, 4)));
            if ($w <= 0 || $h <= 0) {
                continue;
            }

            $hasil[] = [$x, $y, $w, $h];
        }

        return $hasil;
    }

    /**
     * Coret teks: satu garis mendatar lurus di tengah tinggi huruf pada tiap
     * baris yang dicoret. Tebalnya mengikuti tinggi huruf supaya coretan pada
     * judul besar dan pada isi tabel kecil sama-sama terbaca wajar.
     */
    private static function garisCoret(array $butir, float $lebarMm, float $tinggiMm): string
    {
        $warna = self::warna($butir['color'] ?? null);
        $html = '';

        foreach (self::kotakCoret($butir) as [$x, $y, $w, $h]) {
            $tinggiHuruf = $h * $tinggiMm;
            $tengah = ($y + $h * 0.5) * $tinggiMm;

            $html .= sprintf(
                '<line x1="%.2F" y1="%.2F" x2="%.2F" y2="%.2F" stroke="%s" stroke-width="%.2F" stroke-linecap="butt" />',
                $x * $lebarMm,
                $tengah,
                ($x + $w) * $lebarMm,
                $tengah,
                $warna,
                max(0.2, min(1.2, $tinggiHuruf * 0.09))
            );
        }

        return $html;
    }

    /**
     * Nomor coret teks: lingkaran kecil berwarna sama dengan coretannya,
     * tepat di sebelah kanan baris terakhir yang dicoret, sejajar tengahnya
     * - bukan di atasnya, supaya tidak menimpa baris tulisan di atas. Isinya
     * dibaca di halaman Catatan Verifikasi (halamanCatatanHtml). Posisi ini
     * harus sama dengan gambarLencana() di layar.
     */
    private static function lencanaCoret(array $butir, int $nomor, float $lebarMm, float $tinggiMm): string
    {
        $kotak = self::kotakCoret($butir);
        [$x, $y, $w, $h] = end($kotak);

        $garisTengah = max(3.2, min(6, $h * $tinggiMm * 1.15));
        $kiri = min($lebarMm - $garisTengah, ($x + $w) * $lebarMm + 0.6);
        $atas = max(0, ($y + $h * 0.5) * $tinggiMm - $garisTengah * 0.5);

        return sprintf(
            '<div style="position:absolute;top:%.2Fmm;left:%.2Fmm;width:%.2Fmm;height:%.2Fmm;">'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="%3$.2Fmm" height="%4$.2Fmm" viewBox="0 0 10 10">'
            .'<circle cx="5" cy="5" r="5" fill="%5$s" />'
            .'<text x="5" y="7.3" font-family="Arial" font-size="%6$.1F" font-weight="bold" fill="#ffffff" text-anchor="middle">%7$d</text>'
            .'</svg></div>',
            $atas,
            $kiri,
            $garisTengah,
            $garisTengah,
            self::warna($butir['color'] ?? null),
            $nomor > 9 ? 5.2 : 6.6,
            $nomor
        );
    }

    /**
     * Halaman tambahan "Catatan Verifikasi" berisi daftar coret teks milik
     * $dokumen: nomor, teks yang dicoret, dan catatannya. Kosong bila dokumen
     * itu tidak punya coret teks - dokumen tanpa coretan jenis ini tetap
     * persis satu halaman seperti aslinya.
     *
     * Disisipkan setelah overlayHtml(), jadi diawali <pagebreak />: coretan
     * absolut di atas tetap menempel pada halaman dokumen, daftar ini di
     * halaman berikutnya.
     */
    public static function halamanCatatanHtml(?string $coretanJson, string $dokumen = self::DOKUMEN_DEFAULT): string
    {
        $decoded = $coretanJson ? json_decode($coretanJson, true) : null;
        $butirSemua = is_array($decoded) && is_array($decoded['strokes'] ?? null) ? $decoded['strokes'] : [];

        $baris = '';
        foreach (self::nomorCoret($butirSemua) as $indeks => $nomor) {
            $butir = $butirSemua[$indeks];
            $butirDokumen = is_string($butir['dokumen'] ?? null) ? $butir['dokumen'] : self::DOKUMEN_DEFAULT;

            if ($butirDokumen !== $dokumen || (int) ($butir['page'] ?? 1) !== 1) {
                continue;
            }

            $dicoret = self::isiTeks(is_string($butir['teks'] ?? null) ? mb_substr($butir['teks'], 0, 300) : null);
            $catatan = self::isiTeks($butir['catatan'] ?? null);

            $baris .= sprintf(
                '<tr><td style="border:0.2mm solid #555;padding:1.5mm;text-align:center;vertical-align:top;font-weight:bold;color:%s;">%d</td>'
                .'<td style="border:0.2mm solid #555;padding:1.5mm;vertical-align:top;text-decoration:line-through;">%s</td>'
                .'<td style="border:0.2mm solid #555;padding:1.5mm;vertical-align:top;">%s</td></tr>',
                self::warna($butir['color'] ?? null),
                $nomor,
                $dicoret === '' ? '-' : $dicoret,
                $catatan === '' ? '<i style="color:#777;">(tanpa catatan)</i>' : $catatan
            );
        }

        if ($baris === '') {
            return '';
        }

        return '<pagebreak />'
            .'<div style="font-family:Arial,sans-serif;font-size:10pt;">'
            .'<div style="font-size:12pt;font-weight:bold;margin-bottom:1mm;">CATATAN VERIFIKASI</div>'
            .'<div style="font-size:9pt;color:#555;margin-bottom:4mm;">Keterangan atas coretan bernomor pada halaman sebelumnya.</div>'
            .'<table style="width:100%;border-collapse:collapse;font-size:9.5pt;">'
            .'<tr><th style="border:0.2mm solid #555;padding:1.5mm;width:10mm;background-color:#eeeeee;">No</th>'
            .'<th style="border:0.2mm solid #555;padding:1.5mm;width:38%;background-color:#eeeeee;text-align:left;">Teks yang Dicoret</th>'
            .'<th style="border:0.2mm solid #555;padding:1.5mm;background-color:#eeeeee;text-align:left;">Catatan</th></tr>'
            .$baris
            .'</table></div>';
    }

    /** 0..1, angka sampah jadi 0. */
    private static function pecahan(mixed $nilai): float
    {
        return is_numeric($nilai) ? max(0, min(1, (float) $nilai)) : 0.0;
    }

    private static function warna(mixed $nilai): string
    {
        return (is_string($nilai) && preg_match('/^#[0-9a-fA-F]{6}$/', $nilai))
            ? $nilai
            : self::WARNA_CADANGAN;
    }

    /**
     * Ukuran huruf disimpan RELATIF terhadap lebar halaman (mis. 0,018),
     * lalu diubah ke pt di sini: 1mm = 2,8346pt.
     */
    private static function ukuranPt(mixed $nilai, float $lebarMm): float
    {
        $rel = is_numeric($nilai) ? (float) $nilai : 0.018;
        $mm = max(1.5, min(15, $rel * $lebarMm));

        return $mm * 2.8346;
    }

    /**
     * Teks catatan yang aman dipasang ke HTML dokumen.
     *
     * Dilewatkan htmlspecialchars karena isinya diketik pemakai dan akan
     * ditempelkan ke HTML yang dirender mPDF; baris barunya diubah jadi <br>
     * supaya pemisahan baris yang diketik Verifikator tetap terbaca di PDF.
     * Panjangnya dipotong agar satu catatan tidak bisa membanjiri halaman.
     */
    private static function isiTeks(mixed $nilai): string
    {
        if (! is_string($nilai)) {
            return '';
        }

        $bersih = trim(mb_substr($nilai, 0, 600));

        if ($bersih === '') {
            return '';
        }

        return nl2br(htmlspecialchars($bersih, ENT_QUOTES, 'UTF-8'), false);
    }
}
