<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Coretan OTOMATIS pada "Cetak Draft NPD": membandingkan HTML dokumen draft
 * awal buatan PPTK dengan HTML dokumen yang sama setelah disunting BPP/
 * Verifikator, lalu menandai bedanya langsung di dokumen draft - nilai lama
 * dicoret, nilai penggantinya ditulis merah di sebelahnya.
 *
 * Bekerja di tingkat HTML hasil render, bukan di tiap templat. Dua alasannya:
 * templat npd.pdf.* dan dokumen terverifikasi tidak disentuh sama sekali
 * (dokumen yang ditandatangani tetap persis seperti semula), dan kedelapan
 * templat langsung ikut tanpa harus diberi penanda satu per satu.
 *
 * Berbeda dari App\Support\CoretanPdf, yang menggambar coretan TANGAN
 * Verifikator di atas halaman. Keduanya bisa muncul bersamaan.
 *
 * Cara membandingkannya, dari yang paling rapi ke yang paling kasar:
 *
 *   1. Deret baris tabel diselaraskan dulu lewat isi hurufnya (nama,
 *      jabatan, uraian), supaya baris yang dihapus atau disisipkan tidak
 *      membuat seluruh baris di bawahnya ikut tercoret.
 *   2. Dua elemen yang susunan anaknya sama dibandingkan anak demi anak;
 *      teks yang berbeda diganti "lama dicoret + baru".
 *   3. Selain itu: seluruh isi lama dicoret, isi baru ditambahkan di bawahnya.
 */
class CoretanOtomatis
{
    private const GAYA_CORET = 'text-decoration:line-through;color:#c00000;';

    private const GAYA_BARU = 'color:#c00000;';

    /** Elemen yang isinya bukan tulisan dokumen. */
    private const LEWATI = ['style', 'script', 'head', 'title', 'meta', 'colgroup', 'col'];

    public static function gabung(string $htmlLama, string $htmlBaru): string
    {
        if ($htmlLama === $htmlBaru) {
            return $htmlLama;
        }

        $lama = self::muat($htmlLama);
        $baru = self::muat($htmlBaru);

        $badanLama = $lama->getElementsByTagName('body')->item(0);
        $badanBaru = $baru->getElementsByTagName('body')->item(0);

        if (! $badanLama instanceof DOMElement || ! $badanBaru instanceof DOMElement) {
            return $htmlLama;
        }

        self::banding($badanLama, $badanBaru);

        return str_replace('<?xml encoding="UTF-8">', '', (string) $lama->saveHTML());
    }

    private static function muat(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $galatLama = libxml_use_internal_errors(true);
        // Awalan ini memaksa libxml membaca dokumen sebagai UTF-8; tanpa itu
        // huruf beraksen dan tanda centang rusak saat ditulis ulang.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($galatLama);

        return $dom;
    }

    private static function banding(DOMElement $lama, DOMElement $baru): void
    {
        $anakLama = self::anakBerarti($lama);
        $anakBaru = self::anakBerarti($baru);

        // Deret baris SELALU diselaraskan lewat isinya, juga saat jumlahnya
        // sama: satu baris dihapus dan satu ditambah menghasilkan jumlah
        // yang sama, padahal isinya sudah bergeser.
        if ($anakLama !== [] && $anakBaru !== [] && self::semuaBaris($anakLama) && self::semuaBaris($anakBaru)) {
            self::selaraskanBaris($lama, $anakLama, $anakBaru);

            return;
        }

        if (self::susunan($anakLama) === self::susunan($anakBaru)) {
            foreach ($anakLama as $i => $anak) {
                $pasangan = $anakBaru[$i];

                if ($anak instanceof DOMText) {
                    if (self::rapikan($anak->nodeValue) !== self::rapikan($pasangan->nodeValue)) {
                        self::gantiTeks($anak, self::rapikan($pasangan->nodeValue));
                    }
                } elseif ($anak instanceof DOMElement && $pasangan instanceof DOMElement) {
                    self::banding($anak, $pasangan);
                }
            }

            return;
        }

        if (self::rapikan($lama->textContent) === self::rapikan($baru->textContent)) {
            return;
        }

        self::coretSemua($lama);
        self::tempelkan($lama, $baru);
    }

    /**
     * Selaraskan dua deret <tr>. Jangkar-nya baris yang isi hurufnya sama
     * (lihat kunciBaris); baris di antara dua jangkar dipasangkan menurut
     * urutan, dan yang tidak mendapat pasangan dicoret (baris lama) atau
     * disisipkan merah (baris baru).
     *
     * @param  array<int, DOMElement>  $barisLama
     * @param  array<int, DOMElement>  $barisBaru
     */
    private static function selaraskanBaris(DOMElement $induk, array $barisLama, array $barisBaru): void
    {
        // Menyisipkan <tr> ke tabel yang selnya membentang beberapa baris
        // (rowspan) merusak susunan kolomnya. Di tabel seperti itu baris
        // baru tidak disisipkan; jumlah dan totalnya tetap tercoret-terganti.
        $bolehSisip = ! self::adaRentang($barisLama) && ! self::adaRentang($barisBaru);

        $kunciLama = array_map(self::kunciBaris(...), $barisLama);
        $kunciBaru = array_map(self::kunciBaris(...), $barisBaru);
        $jangkar = self::deretSama($kunciLama, $kunciBaru);
        $jangkar[] = [count($barisLama), count($barisBaru)];

        $iLama = 0;
        $iBaru = 0;

        foreach ($jangkar as [$jLama, $jBaru]) {
            while ($iLama < $jLama && $iBaru < $jBaru) {
                self::bandingBaris($induk, $barisLama[$iLama], $barisBaru[$iBaru], $bolehSisip);
                $iLama++;
                $iBaru++;
            }

            for (; $iLama < $jLama; $iLama++) {
                self::coretSemua($barisLama[$iLama]);
            }

            for (; $iBaru < $jBaru; $iBaru++) {
                if ($bolehSisip) {
                    self::sisipkanBaris($induk, $barisBaru[$iBaru], $barisLama[$jLama] ?? null);
                }
            }

            if ($jLama < count($barisLama)) {
                self::bandingBaris($induk, $barisLama[$jLama], $barisBaru[$jBaru], $bolehSisip);
            }

            $iLama = $jLama + 1;
            $iBaru = $jBaru + 1;
        }
    }

    private static function bandingBaris(DOMElement $induk, DOMElement $lama, DOMElement $baru, bool $bolehSisip): void
    {
        if (count(self::anakBerarti($lama)) === count(self::anakBerarti($baru))) {
            self::banding($lama, $baru);

            return;
        }

        self::coretSemua($lama);

        if ($bolehSisip) {
            self::sisipkanBaris($induk, $baru, $lama->nextSibling);
        }
    }

    private static function sisipkanBaris(DOMElement $induk, DOMElement $baru, ?DOMNode $sebelum): void
    {
        $salinan = $induk->ownerDocument->importNode($baru, true);
        self::warnai($salinan);
        $induk->insertBefore($salinan, $sebelum);
    }

    /**
     * Pasangan indeks deret terpanjang yang urutannya sama di kedua daftar
     * (longest common subsequence).
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: int, 1: int}>
     */
    private static function deretSama(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $panjang = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $panjang[$i][$j] = $a[$i] === $b[$j]
                    ? $panjang[$i + 1][$j + 1] + 1
                    : max($panjang[$i + 1][$j], $panjang[$i][$j + 1]);
            }
        }

        $hasil = [];
        $i = $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $hasil[] = [$i++, $j++];
            } elseif ($panjang[$i + 1][$j] >= $panjang[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $hasil;
    }

    /**
     * Isi huruf satu baris, tanpa angka, "Rp", tanda baca, dan spasi. Nomor
     * urut dan nominal sengaja dibuang: keduanya justru yang paling sering
     * berubah, sedangkan baris dikenali dari nama dan uraiannya.
     */
    private static function kunciBaris(DOMElement $baris): string
    {
        return (string) preg_replace('/Rp|[\d\s.,\-\x{00A0}]+/u', '', $baris->textContent);
    }

    /** @param  array<int, DOMElement>  $baris */
    private static function adaRentang(array $baris): bool
    {
        foreach ($baris as $satu) {
            foreach ($satu->getElementsByTagName('*') as $sel) {
                if ($sel instanceof DOMElement && (int) $sel->getAttribute('rowspan') > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Anak yang ikut dibandingkan: elemen dan teks yang benar-benar berisi.
     * Spasi pemisah antar-tag bukan isi dokumen.
     *
     * @return array<int, DOMNode>
     */
    private static function anakBerarti(DOMElement $elemen): array
    {
        $hasil = [];

        foreach ($elemen->childNodes as $anak) {
            if ($anak instanceof DOMText) {
                if (self::rapikan($anak->nodeValue) !== '') {
                    $hasil[] = $anak;
                }
            } elseif ($anak instanceof DOMElement && ! in_array(strtolower($anak->nodeName), self::LEWATI, true)) {
                $hasil[] = $anak;
            }
        }

        return $hasil;
    }

    /** @param  array<int, DOMNode>  $anak */
    private static function susunan(array $anak): string
    {
        return implode(',', array_map(
            fn (DOMNode $satu) => $satu instanceof DOMText ? '#' : strtolower($satu->nodeName),
            $anak
        ));
    }

    /** @param  array<int, DOMNode>  $anak */
    private static function semuaBaris(array $anak): bool
    {
        foreach ($anak as $satu) {
            if (! $satu instanceof DOMElement || strtolower($satu->nodeName) !== 'tr') {
                return false;
            }
        }

        return true;
    }

    private static function rapikan(?string $teks): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $teks));
    }

    /** Ganti satu teks dengan "lama dicoret" + "baru merah". */
    private static function gantiTeks(DOMText $teks, string $baru): void
    {
        $dok = $teks->ownerDocument;
        $induk = $teks->parentNode;
        $lama = self::rapikan($teks->nodeValue);

        $coret = $dok->createElement('span');
        $coret->setAttribute('style', self::GAYA_CORET);
        $coret->appendChild($dok->createTextNode($lama));
        $induk->replaceChild($coret, $teks);

        if ($baru === '') {
            return;
        }

        $pengganti = $dok->createElement('span');
        $pengganti->setAttribute('style', self::GAYA_BARU);
        $pengganti->appendChild($dok->createTextNode($baru));

        // Di dalam sel tabel nilai baru turun ke baris berikutnya: kolom
        // angka dicetak tanpa pembungkusan baris, jadi dua angka berjajar
        // akan menabrak kolom sebelahnya.
        $pemisah = self::dalamSel($induk) ? $dok->createElement('br') : $dok->createTextNode(' ');

        $induk->insertBefore($pengganti, $coret->nextSibling);
        $induk->insertBefore($pemisah, $pengganti);
    }

    private static function dalamSel(?DOMNode $simpul): bool
    {
        for (; $simpul instanceof DOMElement; $simpul = $simpul->parentNode) {
            if (in_array(strtolower($simpul->nodeName), ['td', 'th'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Coret seluruh tulisan di bawah satu elemen. */
    private static function coretSemua(DOMElement $elemen): void
    {
        self::bungkusTeks($elemen, self::GAYA_CORET);
    }

    /** Warnai merah seluruh tulisan di bawah satu elemen. */
    private static function warnai(DOMNode $simpul): void
    {
        if ($simpul instanceof DOMElement) {
            self::bungkusTeks($simpul, self::GAYA_BARU);
        }
    }

    private static function bungkusTeks(DOMElement $elemen, string $gaya): void
    {
        $teksSemua = [];
        self::kumpulkanTeks($elemen, $teksSemua);

        foreach ($teksSemua as $teks) {
            $bungkus = $elemen->ownerDocument->createElement('span');
            $bungkus->setAttribute('style', $gaya);
            $teks->parentNode->replaceChild($bungkus, $teks);
            $bungkus->appendChild($teks);
        }
    }

    /** @param  array<int, DOMText>  $hasil */
    private static function kumpulkanTeks(DOMNode $simpul, array &$hasil): void
    {
        foreach ($simpul->childNodes as $anak) {
            if ($anak instanceof DOMText) {
                if (self::rapikan($anak->nodeValue) !== '') {
                    $hasil[] = $anak;
                }
            } elseif ($anak instanceof DOMElement && ! in_array(strtolower($anak->nodeName), self::LEWATI, true)) {
                self::kumpulkanTeks($anak, $hasil);
            }
        }
    }

    /** Tambahkan isi elemen baru (merah) di belakang isi elemen lama. */
    private static function tempelkan(DOMElement $lama, DOMElement $baru): void
    {
        if (self::rapikan($baru->textContent) === '') {
            return;
        }

        $dok = $lama->ownerDocument;

        if (self::rapikan($lama->textContent) !== '') {
            $lama->appendChild(self::dalamSel($lama) ? $dok->createElement('br') : $dok->createTextNode(' '));
        }

        foreach ($baru->childNodes as $anak) {
            $salinan = $dok->importNode($anak, true);

            if ($salinan instanceof DOMText) {
                if (self::rapikan($salinan->nodeValue) === '') {
                    continue;
                }

                $bungkus = $dok->createElement('span');
                $bungkus->setAttribute('style', self::GAYA_BARU);
                $bungkus->appendChild($salinan);
                $salinan = $bungkus;
            } else {
                self::warnai($salinan);
            }

            $lama->appendChild($salinan);
        }
    }
}
