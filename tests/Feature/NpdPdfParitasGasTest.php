<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menjaga PDF NPD Laravel tetap sebangun dengan template GAS.
 *
 * Sumber kebenaran ada di folder GAS (read-only, di luar repo). Yang
 * dibandingkan adalah hal-hal yang menentukan RUPA cetakan dan tidak boleh
 * bergeser: ukuran & margin kertas F4, lebar kolom tabel, dan bunyi teks
 * header. Markup layout tidak dibandingkan mentah-mentah karena Laravel
 * sengaja memakai <table> asli menggantikan display:table - mPDF tidak
 * merender display:table dengan andal.
 *
 * Test di-skip bila folder GAS tidak ada (mis. dijalankan di mesin lain).
 */
class NpdPdfParitasGasTest extends TestCase
{
    private const FOLDER_GAS = 'C:/laragon/www/i-finance gas';

    /**
     * Daftar Bayar Perjalanan Dinas SENGAJA tidak dibandingkan ke
     * tpl_pd_daftar.html. Dokumen tertandatangani di storage/app/acuan-pdf
     * membuktikan template itu sudah tertinggal: judul kolomnya masih
     * "UANG HARIAN (Rp)" sedangkan dokumen resminya "UANG HARIAN DLM/LUAR
     * DAERAH (Rp)", dan lebar kolomnya pun berbeda. Yang berlaku adalah
     * dokumennya - lihat test_daftar_bayar_pd_mengikuti_dokumen_asli().
     */
    private const PASANGAN = [
        'tpl_npd.html' => 'npd.blade.php',
        'tpl_lampiran.html' => 'lampiran.blade.php',
        'tpl_pd_spd.html' => 'pd-spd.blade.php',
        'tpl_kd_daftar.html' => 'kd-daftar.blade.php',
        'tpl_kd_pd_daftar.html' => 'kd-pd-daftar.blade.php',
        'tpl_nara_daftar.html' => 'ns-daftar.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(self::FOLDER_GAS)) {
            $this->markTestSkipped('Folder referensi GAS tidak tersedia di mesin ini.');
        }
    }

    private function isiGas(string $berkas): string
    {
        return (string) file_get_contents(self::FOLDER_GAS.'/'.$berkas);
    }

    private function isiLaravel(string $berkas): string
    {
        return (string) file_get_contents(resource_path('views/npd/pdf/'.$berkas));
    }

    /** @return array<int, string> */
    private function lebarKolom(string $html): array
    {
        preg_match_all('/<col\s+style="width:([^";]+);?"/i', $html, $m);

        return $m[1];
    }

    /**
     * Teks header, spasi dinormalkan. Kolom PPh pada Lampiran jumlahnya
     * dinamis - GAS menyuntikkannya lewat placeholder {{PPH_HEADERS}},
     * Laravel lewat perulangan Blade - jadi keduanya tidak dibandingkan.
     *
     * @return array<int, string>
     */
    private function teksHeader(string $html): array
    {
        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/is', $html, $m);

        $teks = array_map(
            fn (string $isi) => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<br>', ' ', $isi)))),
            $m[1]
        );

        return array_values(array_filter(
            $teks,
            fn (string $t) => $t !== '' && ! str_contains($t, '$jenis')
        ));
    }

    public function test_semua_dokumen_memakai_kertas_f4_dan_margin_yang_sama(): void
    {
        foreach (self::PASANGAN as $gas => $laravel) {
            preg_match('/@page\s*\{([^}]*)\}/', $this->isiGas($gas), $mg);
            preg_match('/@page\s*\{([^}]*)\}/', $this->isiLaravel($laravel), $ml);

            $normal = fn (string $v) => trim(preg_replace('/\s+/', ' ', $v));

            $this->assertSame($normal($mg[1]), $normal($ml[1]), "@page berbeda pada {$laravel}.");
            $this->assertStringContainsString('215mm 330mm', $ml[1], "{$laravel} bukan kertas F4.");
        }
    }

    public function test_lebar_kolom_tabel_utama_sama_dengan_gas(): void
    {
        // Hanya dokumen bertabel lebar yang punya <col> eksplisit. pd-daftar
        // tidak ikut - lebarnya mengikuti dokumen tertandatangani, bukan
        // template GAS yang sudah tertinggal.
        foreach ([
            'tpl_kd_daftar.html' => 'kd-daftar.blade.php',
            'tpl_kd_pd_daftar.html' => 'kd-pd-daftar.blade.php',
            'tpl_pd_spd.html' => 'pd-spd.blade.php'] as $gas => $laravel) {
            $this->assertSame(
                $this->lebarKolom($this->isiGas($gas)),
                $this->lebarKolom($this->isiLaravel($laravel)),
                "Lebar kolom berbeda pada {$laravel}."
            );
        }
    }

    /**
     * Daftar Bayar Perjalanan Dinas mengikuti DOKUMEN yang sudah
     * ditandatangani, bukan template GAS. Angka-angka di bawah diukur
     * langsung dari berkas acuan (posisi garis vertikal tabelnya).
     */
    public function test_daftar_bayar_pd_mengikuti_dokumen_asli(): void
    {
        $isi = $this->isiLaravel('pd-daftar.blade.php');

        $this->assertSame(
            ['3%', '12%', '8%', '4%', '9.5%', '10%', '4%', '8%', '9%', '8%', '8.5%', '8%', '8%'],
            $this->lebarKolom($isi)
        );

        $this->assertStringContainsString('UANG HARIAN<br>DLM/LUAR<br>DAERAH (Rp)', $isi);
        $this->assertStringContainsString('JML UANG<br>HARIAN DLM/<br>LUAR DAERAH<br>(Rp)', $isi);
        $this->assertStringContainsString('TRANSPORT<br>/BBM/TIKET', $isi);

        // Label "Rp" tetap disembunyikan - dokumen aslinya hanya angka.
        $this->assertStringNotContainsString('rpwrap', $isi);
    }

    public function test_teks_header_tabel_sama_dengan_gas(): void
    {
        foreach (self::PASANGAN as $gas => $laravel) {
            $this->assertSame(
                $this->teksHeader($this->isiGas($gas)),
                $this->teksHeader($this->isiLaravel($laravel)),
                "Teks header berbeda pada {$laravel}."
            );
        }
    }

    /**
     * GAS menyembunyikan label "Rp" pada Daftar Bayar Perjalanan Dinas dan
     * bagian perjalanan Kontribusi Diklat (td.rp .rp-l { display:none }),
     * tetapi MENAMPILKANNYA pada Daftar Bayar Kontribusi Diklat.
     */
    public function test_label_rp_ditampilkan_sesuai_dokumennya(): void
    {
        foreach (['pd-daftar.blade.php', 'kd-pd-daftar.blade.php'] as $tanpaLabel) {
            $this->assertStringNotContainsString(
                'rpwrap',
                $this->isiLaravel($tanpaLabel),
                "{$tanpaLabel} seharusnya tanpa label Rp."
            );
        }

        $this->assertStringContainsString('rpwrap', $this->isiLaravel('kd-daftar.blade.php'));
    }

    /** Kop kecil Daftar Bayar & Daftar Narasumber harus berbunyi sama persis. */
    public function test_kop_kecil_berbunyi_sama_di_semua_daftar(): void
    {
        $baris = [
            'PEMERINTAH PROVINSI JAWA BARAT',
            'INSPEKTORAT DAERAH',
            'Jalan Surapati No. 4 Tlp. 4237174-4231567 Fax. 4231567',
            'BANDUNG 40115',
        ];

        foreach (['pd-daftar.blade.php', 'kd-daftar.blade.php', 'kd-pd-daftar.blade.php', 'ns-daftar.blade.php'] as $berkas) {
            $isi = $this->isiLaravel($berkas);

            foreach ($baris as $teks) {
                $this->assertStringContainsString($teks, $isi, "Kop {$berkas} tidak sama dengan GAS.");
            }
        }
    }

    /**
     * GAS mencetak Program & Kegiatan LENGKAP dengan kodenya (payload
     * programFull/kegiatan berasal dari dropdown gabungan kode+uraian).
     */
    public function test_program_dan_kegiatan_dicetak_lengkap_dengan_kodenya(): void
    {
        foreach (['npd.blade.php', 'lampiran.blade.php', 'pd-lampiran.blade.php'] as $berkas) {
            $isi = $this->isiLaravel($berkas);

            $this->assertStringContainsString('masterAnggaran->program_lengkap', $isi, $berkas);
            $this->assertStringContainsString('masterAnggaran->kegiatan_lengkap', $isi, $berkas);
            $this->assertStringContainsString('masterAnggaran->sub_kegiatan_lengkap', $isi, $berkas);
        }
    }

    /**
     * Lampiran Perjalanan Dinas mencetak payload.kodeRek milik GAS, yaitu
     * GABUNGAN kode + uraian rekening - bukan kodenya saja. Dokumen
     * tertandatangani memperlihatkan baris tebal "5.1.02.04.001.00001 Belanja
     * Perjalanan Dinas Biasa" di atas kalimat keterangan.
     */
    public function test_lampiran_perjalanan_mencetak_kode_dan_uraian_rekening(): void
    {
        $isi = $this->isiLaravel('pd-lampiran.blade.php');

        $this->assertStringContainsString('masterAnggaran->rekening_lengkap', $isi);
        $this->assertStringNotContainsString('kode_rekening_bersih', $isi);
    }

    /**
     * Tiga jalan pintas mPDF yang WAJIB dipertahankan. Semuanya ditemukan
     * lewat pembandingan piksel dengan dokumen tertandatangani, bukan dari
     * membaca kode - kalau dikembalikan ke bentuk "bersih" ala CSS biasa,
     * cetakan langsung meleset tanpa satu pun test lain gagal:
     *
     * 1. Jarak tanda tangan memakai TABEL bersarang. Div kosong bertinggi di
     *    dalam sel tabel diabaikan mPDF - jaraknya ambruk dari 41pt jadi 8pt.
     * 2. Garis tepi kolom Keterangan dipasang pada kelas selnya langsung.
     *    Pemilih berjenjang "table.wrap > tbody > tr > td" diabaikan, kolomnya
     *    kehilangan kotak dan isinya melayang di tengah tabel.
     * 3. Tanda centang memakai DejaVu; Arial tidak punya U+2713.
     */
    public function test_penyesuaian_mpdf_tidak_dikembalikan(): void
    {
        $spd = $this->isiLaravel('pd-spd.blade.php');

        $this->assertStringContainsString('table.ttd-jarak td { height:32pt;', $spd);
        $this->assertStringContainsString('<table class="ttd-jarak"><tr><td></td></tr></table>', $spd);
        $this->assertStringNotContainsString('.ttd .sp', $spd);
        $this->assertStringContainsString('.wrap-kiri, .wrap-kanan { padding:0; vertical-align:top; border:1pt solid #000; }', $spd);

        $npd = $this->isiLaravel('npd.blade.php');

        $this->assertStringContainsString('.centang { font-family: dejavusanscondensed', $npd);
        $this->assertSame(2, substr_count($npd, '<span class="centang">&#10003;</span>'));
    }

    /**
     * Header vertikal (VOL / JML HARI / VOLUME). GAS memakai
     * writing-mode+rotate yang tidak dikenal mPDF; padanannya text-rotate.
     */
    public function test_header_vertikal_dipertahankan(): void
    {
        foreach (['kd-daftar.blade.php' => 2, 'kd-pd-daftar.blade.php' => 3] as $berkas => $jumlah) {
            $isi = $this->isiLaravel($berkas);

            $this->assertStringContainsString('text-rotate:90', $isi, "{$berkas} kehilangan header vertikal.");
            $this->assertSame($jumlah, substr_count($isi, 'class="rot"'), "Jumlah header vertikal {$berkas} berubah.");
        }
    }
}
