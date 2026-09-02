<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga mode gelap.
 *
 * Warna terang yang dipatok langsung di CSS atau di atribut style adalah
 * penyebab data "hilang" saat mode gelap: latarnya ikut menggelap lewat
 * token, tetapi tulisan atau latar yang nilainya dipatok tetap terang -
 * atau sebaliknya, tulisan gelap tetap gelap di atas latar yang sudah gelap.
 *
 * Test ini menyisir seluruh berkas tampilan LAYAR (blade cetak/PDF sengaja
 * dikecualikan karena memang selalu dicetak di atas kertas putih) dan gagal
 * begitu ada warna baru yang dipatok langsung.
 */
class ModeGelapKontrasTest extends TestCase
{
    /** Ambang luminansi: di atas ini dianggap warna terang. */
    private const TERANG = 200;

    /** Di bawah ini dianggap warna gelap. */
    private const GELAP = 150;

    /**
     * Nilai yang memang harus tetap seperti apa adanya di semua mode, dengan
     * alasannya masing-masing.
     *
     * @var array<string, string>
     */
    private const DIKECUALIKAN = [
        '#dc2626' => 'Tombol .btn.danger: merah pekat dengan tulisan putih, benar di kedua mode.',
        '#1f9d55' => 'Hijau merek WhatsApp; dicerahkan lewat override khusus mode gelap.',
        '#1b1408' => 'Tulisan di atas tombol emas.',
        '#3ddc84' => 'Hijau WhatsApp versi mode gelap.',
        '#3a4a5e' => 'Rel sakelar pada mode gelap.',
    ];

    /**
     * Putih HANYA boleh untuk tulisan/ikon di atas latar yang memang selalu
     * gelap (tombol utama, bilah atas). Sebagai LATAR putih tetap dilarang -
     * itu justru kartu yang tidak ikut menggelap.
     *
     * @var array<string, string>
     */
    private const PUTIH_TEKS = [
        '#fff' => 'Tulisan/ikon/spinner di atas tombol utama.',
        '#ffffff' => 'Sama seperti di atas.',
    ];

    /** @return array<int, string> */
    private function berkasLayar(): array
    {
        $semua = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $berkas) {
            $jalur = str_replace('\\', '/', $berkas->getPathname());

            if (! str_ends_with($jalur, '.blade.php')) {
                continue;
            }

            // Cetakan PDF selalu di atas kertas putih, dan welcome.blade.php
            // bawaan Laravel tidak dipakai route mana pun.
            if (str_contains($jalur, '/pdf/') || str_contains($jalur, 'pdf.blade.php')
                || str_contains($jalur, '_baris-pdf') || str_contains($jalur, 'welcome.blade.php')) {
                continue;
            }

            $semua[] = $jalur;
        }

        return $semua;
    }

    /**
     * True kalau selektor pada baris ini juga punya aturan penimpa di bawah
     * :root[data-tema="gelap"], sehingga nilai terangnya memang disengaja
     * dan sudah ditangani secara terpisah.
     */
    private function punyaPenimpaGelap(string $selektor): bool
    {
        $selektor = trim($selektor);
        if ($selektor === '') {
            return false;
        }

        static $stylesheet = null;
        $stylesheet ??= file_get_contents(resource_path('views/layouts/partials/styles.blade.php'));

        return str_contains($stylesheet, '[data-tema="gelap"] '.$selektor.'{')
            || str_contains($stylesheet, '[data-tema="gelap"] '.$selektor.',');
    }

    /**
     * True kalau aturannya SENDIRI sudah khusus mode gelap. Nilai di dalamnya
     * memang ditulis untuk mode itu - misalnya tulisan gelap di atas batang
     * yang sengaja dicerahkan - jadi tidak perlu diperiksa ulang.
     */
    private function aturanKhususGelap(string $selektor): bool
    {
        return str_contains($selektor, '[data-tema="gelap"]');
    }

    private function luminansi(string $hex): ?float
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (strlen($h) !== 6 || ! ctype_xdigit($h)) {
            return null;
        }

        return 0.2126 * hexdec(substr($h, 0, 2))
            + 0.7152 * hexdec(substr($h, 2, 2))
            + 0.0722 * hexdec(substr($h, 4, 2));
    }

    /** Latar dan garis tidak boleh dipatok terang: mode gelap tidak akan bisa membalikkannya. */
    public function test_tidak_ada_latar_atau_garis_terang_yang_dipatok_langsung(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasLayar() as $jalur) {
            $isi = file_get_contents($jalur);

            // Definisi token di :root memang berisi nilai mentah - itu memang
            // tempatnya. Yang dibuang HANYA blok definisinya; pola yang lebih
            // longgar akan ikut menelan aturan ':root[data-tema=..] .selektor{}'
            // beserta ratusan baris sesudahnya, sehingga pemeriksaannya bocor.
            if (str_contains($jalur, 'partials/styles.blade.php')) {
                $isi = preg_replace('/^  :root(?:\[[^\]]*\])?\{.*?\n  \}/ms', '', $isi);
            }

            // Sebagian aturan menyebar di beberapa baris, jadi selektornya
            // diingat sampai kurung penutupnya - kalau tidak, baris lanjutan
            // kehilangan pemiliknya dan penimpa mode gelapnya tidak terdeteksi.
            $selektorBerjalan = '';

            foreach (explode("\n", $isi) as $baris) {
                if (($kurung = strpos($baris, '{')) !== false) {
                    $selektorBerjalan = trim(substr($baris, 0, $kurung));
                }

                preg_match_all(
                    '/(?<![-\w])(background|background-color|border[a-z-]*)\s*:\s*([^;}{"\'>\n]+)/',
                    $baris,
                    $cocok,
                    PREG_SET_ORDER
                );

                if (str_contains($baris, '}')) {
                    $sisa = $selektorBerjalan;
                    $selektorBerjalan = '';
                } else {
                    $sisa = $selektorBerjalan;
                }

                if ($cocok === []) {
                    continue;
                }

                foreach ($cocok as [$penuh, $properti, $nilai]) {
                    preg_match_all('/#[0-9a-fA-F]{3,6}\b/', $nilai, $warna);

                    foreach ($warna[0] as $w) {
                        $lum = $this->luminansi($w);

                        if ($lum === null || $lum <= self::TERANG || isset(self::DIKECUALIKAN[strtolower($w)])) {
                            continue;
                        }

                        // Spinner tombol berputar di atas tombol utama yang
                        // latarnya selalu gelap di semua mode.
                        if ($sisa === '.spin') {
                            continue;
                        }

                        // Boleh tetap terang KALAU selektornya punya penimpa
                        // khusus mode gelap - sebagian sorotan baris memang
                        // lebih tepat memakai lapisan putih transparan
                        // daripada token permukaan.
                        if ($this->aturanKhususGelap($sisa) || $this->punyaPenimpaGelap($sisa)) {
                            continue;
                        }

                        $pelanggaran[] = basename($jalur).': '.$properti.':'.trim($nilai);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($pelanggaran)),
            'Latar/garis terang dipatok langsung - pakai token (var(--surface), var(--line), var(--ok-bg), ...) '
            .'supaya mode gelap ikut berlaku.');
    }

    /**
     * Tulisan gelap di atas latar bernada status akan hilang saat latarnya
     * ikut menggelap. Pasangannya harus sama-sama token.
     */
    public function test_tidak_ada_tulisan_gelap_di_atas_latar_bernada(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasLayar() as $jalur) {
            $isi = file_get_contents($jalur);

            preg_match_all(
                '/background(?:-color)?\s*:\s*var\(--(surface|surface-2|surface-3|ok-bg|warn-bg|err-bg|info-bg)\)'
                .'[^;}"\'>]*[;"]?[^;}]{0,140}?color\s*:\s*(#[0-9a-fA-F]{3,6})\b/',
                $isi,
                $cocok,
                PREG_SET_ORDER
            );

            foreach ($cocok as [$penuh, $latar, $teks]) {
                $lum = $this->luminansi($teks);

                if ($lum !== null && $lum < self::GELAP && ! isset(self::DIKECUALIKAN[strtolower($teks)])) {
                    $pelanggaran[] = basename($jalur).': latar var(--'.$latar.') dengan teks '.$teks;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($pelanggaran)),
            'Tulisan gelap dipasangkan dengan latar bertoken - pakai var(--ink), var(--ok-teks), '
            .'var(--warn-teks), var(--err-teks), atau var(--info).');
    }

    /**
     * Penjaga terpenting: SETIAP warna tulisan harus terang setelah token-nya
     * diselesaikan di mode gelap.
     *
     * Ini menutup jebakan --navy yang sempat lolos: token itu punya dua tugas
     * sekaligus - warna merek untuk LATAR (sidebar, tombol) dan warna TEKS -
     * padahal di mode gelap latarnya harus tetap gelap sementara tulisannya
     * wajib terang. Tulisan yang ditekankan sekarang memakai --tegas.
     */
    public function test_semua_warna_tulisan_terang_di_mode_gelap(): void
    {
        $kamus = $this->tokenModeGelap();
        $pelanggaran = [];

        foreach ($this->berkasLayar() as $jalur) {
            $isi = file_get_contents($jalur);

            if (str_contains($jalur, 'partials/styles.blade.php')) {
                $isi = preg_replace('/^  :root(?:\[[^\]]*\])?\{.*?\n  \}/ms', '', $isi);
            }

            // Per baris supaya selektor pemiliknya diketahui - sebagian warna
            // gelap memang disengaja dan sudah punya penimpa mode gelap.
            // Selektornya diingat sampai kurung penutup karena ada aturan
            // yang isinya menyebar ke beberapa baris.
            $selektorBerjalan = '';

            foreach (explode("\n", $isi) as $baris) {
                if (($kurung = strpos($baris, '{')) !== false) {
                    $selektorBerjalan = trim(substr($baris, 0, $kurung));
                }
                $sisa = $selektorBerjalan;
                if (str_contains($baris, '}')) {
                    $selektorBerjalan = '';
                }

                // Bentuk CSS (color:...) maupun bentuk array PHP ('color' => '...').
                preg_match_all(
                    '/(?:(?<![-\w])(color|stroke|fill)\s*:\s*|["\'](color|stroke|fill)["\']\s*=>\s*["\'])'
                    .'([^;}{"\'>\n]+)/',
                    $baris,
                    $cocok,
                    PREG_SET_ORDER
                );

                foreach ($cocok as $m) {
                    $properti = $m[1] !== '' ? $m[1] : $m[2];
                    $akhir = $this->selesaikan($m[3], $kamus);

                    if ($akhir === null) {
                        continue;
                    }

                    $lum = $this->luminansi($akhir);

                    if ($lum === null || $lum >= 110 || isset(self::DIKECUALIKAN[strtolower($akhir)])
                        || isset(self::PUTIH_TEKS[strtolower($akhir)])) {
                        continue;
                    }

                    if ($this->aturanKhususGelap($sisa) || $this->punyaPenimpaGelap($sisa)) {
                        continue;
                    }

                    $pelanggaran[] = basename($jalur).': '.$properti.':'.trim($m[3]).' -> '.$akhir;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($pelanggaran)),
            'Warna tulisan ini tetap gelap saat mode gelap aktif, jadi teksnya hilang. '
            .'Pakai var(--tegas) untuk penekanan, var(--ink) untuk teks biasa, var(--mut) untuk teks redup, '
            .'atau token nada status.');
    }

    /** @return array<string, string> */
    private function tokenModeGelap(): array
    {
        $s = file_get_contents(resource_path('views/layouts/partials/styles.blade.php'));

        $ambil = function (string $selektor) use ($s): array {
            preg_match('/'.preg_quote($selektor, '/').'\{(.*?)\n  \}/s', $s, $m);
            preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $m[1] ?? '', $t, PREG_SET_ORDER);

            return array_column($t, 2, 1);
        };

        return array_merge($ambil(':root'), $ambil(':root[data-tema="gelap"]'));
    }

    /** @param  array<string, string>  $kamus */
    private function selesaikan(string $nilai, array $kamus, int $kedalaman = 0): ?string
    {
        $nilai = trim($nilai);

        while (str_starts_with($nilai, 'var(') && $kedalaman < 8) {
            if (! preg_match('/^var\((--[a-z0-9-]+)\s*(?:,\s*(.+))?\)$/', $nilai, $m)) {
                return null;
            }
            $nilai = trim($kamus[$m[1]] ?? ($m[2] ?? ''));
            $kedalaman++;
        }

        return $nilai !== '' ? $nilai : null;
    }

    /** Setiap token yang dipakai harus punya nilai bawaan di :root. */
    public function test_setiap_token_yang_dipakai_terdefinisi(): void
    {
        $stylesheet = file_get_contents(resource_path('views/layouts/partials/styles.blade.php'));

        preg_match('/:root\{(.*?)\n  \}/s', $stylesheet, $akar);
        preg_match_all('/(--[a-z0-9-]+)\s*:/', $akar[1] ?? '', $terdefinisi);
        $tersedia = $terdefinisi[1] ?? [];

        $dipakai = [];
        foreach ($this->berkasLayar() as $jalur) {
            preg_match_all('/var\((--[a-z0-9-]+)\)/', file_get_contents($jalur), $cocok);
            $dipakai = array_merge($dipakai, $cocok[1]);
        }

        $hilang = array_values(array_unique(array_diff($dipakai, $tersedia)));

        $this->assertSame([], $hilang,
            'Token dipakai tanpa nilai bawaan di :root - nilainya akan kosong dan elemennya jadi transparan.');
    }
}
