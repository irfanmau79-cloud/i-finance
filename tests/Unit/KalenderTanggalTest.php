<?php

namespace Tests\Unit;

use Symfony\Component\Process\Process;
use PHPUnit\Framework\TestCase;

/**
 * Ringkas & urai tanggal pada pemilih kalender.
 *
 * Kedua fungsi ini hidup di JavaScript (layouts/partials/kalender-tanggal)
 * karena rangkumannya harus tampil hidup saat pengguna mengklik tanggal.
 * Menyalinnya ke PHP hanya demi test berarti dua sumber kebenaran, jadi test
 * ini menjalankan berkas aslinya dengan Node — yang diuji benar-benar kode
 * yang dikirim ke peramban.
 *
 * Yang dijaga: string hasil ringkasan SAMA PERSIS dengan GAS, sebab string
 * itulah yang tersimpan di kolom rincian_tgl_bayar dan tercetak di SPJ.
 */
class KalenderTanggalTest extends TestCase
{
    /** Jalankan satu ekspresi terhadap window.KalenderTanggal, hasilnya JSON. */
    private function jalankan(string $ekspresi): mixed
    {
        $partial = dirname(__DIR__, 2).'/resources/views/layouts/partials/kalender-tanggal.blade.php';
        $isi = file_get_contents($partial);

        $this->assertTrue(
            (bool) preg_match('#<script>(.*)</script>#s', $isi, $cocok),
            'Partial kalender tidak lagi berisi satu blok <script>.'
        );

        // Komponennya memasang dirinya sendiri saat dimuat, jadi cukup sediakan
        // dokumen tiruan sekadar agar pemasangan itu tidak menemui apa pun.
        $skrip = <<<JS
        globalThis.MutationObserver = class { observe() {} };
        globalThis.document = {
            readyState: 'complete',
            documentElement: {},
            matches: () => false,
            querySelectorAll: () => [],
            addEventListener: () => {},
        };
        globalThis.window = globalThis;

        {$cocok[1]}

        const K = window.KalenderTanggal;
        process.stdout.write(JSON.stringify({$ekspresi}));
        JS;

        $berkas = tempnam(sys_get_temp_dir(), 'kal').'.mjs';
        file_put_contents($berkas, $skrip);

        $proses = new Process(['node', $berkas]);

        try {
            $proses->run();
        } finally {
            @unlink($berkas);
        }

        if (! $proses->isSuccessful()) {
            $this->markTestSkipped('Node tidak tersedia atau gagal: '.trim($proses->getErrorOutput()));
        }

        return json_decode($proses->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_ringkasan_tanggal_sama_persis_dengan_gas(): void
    {
        $kasus = [
            // Kosong, sehari, dan sederet hari dalam satu bulan.
            [[], ''],
            [['2026-07-13'], '13 Juli 2026'],
            [['2026-07-01', '2026-07-02'], '1-2 Juli 2026'],
            // Beberapa rentang terpisah dalam satu bulan: bulan & tahun cukup
            // sekali di ujung.
            [
                ['2026-07-01', '2026-07-02', '2026-07-04', '2026-07-05', '2026-07-06', '2026-07-07', '2026-07-13'],
                '1-2, 4-7, 13 Juli 2026',
            ],
            // Lintas bulan: tiap bagian membawa nama bulannya, tahun di ujung.
            [
                ['2026-06-30', '2026-07-01', '2026-07-02', '2026-07-05'],
                '30 Juni - 2 Juli, 5 Juli 2026',
            ],
            // Lintas tahun: tiap ujung membawa tahunnya sendiri.
            [
                ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02'],
                '30 Desember 2026 - 2 Januari 2027',
            ],
        ];

        $masukan = array_column($kasus, 0);
        $hasil = $this->jalankan(json_encode($masukan).'.map(K.rangkum)');

        foreach ($kasus as $i => [$tanggal, $harapan]) {
            $this->assertSame($harapan, $hasil[$i], 'Ringkasan salah untuk '.json_encode($tanggal));
        }
    }

    public function test_urutan_masukan_tidak_mempengaruhi_ringkasan(): void
    {
        // Kalender mengumpulkan tanggal dari objek, urutannya tidak dijamin.
        $acak = ['2026-07-13', '2026-07-02', '2026-07-01'];

        $this->assertSame(
            ['1-2, 13 Juli 2026'],
            $this->jalankan('['.json_encode($acak).'].map(K.rangkum)')
        );
    }

    public function test_string_lama_diurai_kembali_menjadi_tanggal(): void
    {
        $kasus = [
            ['1-2, 4-7, 13 Juli 2026', [
                '2026-07-01', '2026-07-02', '2026-07-04', '2026-07-05',
                '2026-07-06', '2026-07-07', '2026-07-13',
            ]],
            ['30 Juni - 2 Juli, 5 Juli 2026', ['2026-06-30', '2026-07-01', '2026-07-02', '2026-07-05']],
            // Perbaikan atas GAS: di sana kedua ujung rentang jatuh ke 2026.
            ['30 Desember 2026 - 2 Januari 2027', ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02']],
            ['13 Juli 2026', ['2026-07-13']],
        ];

        $hasil = $this->jalankan(json_encode(array_column($kasus, 0)).'.map(K.urai)');

        foreach ($kasus as $i => [$teks, $harapan]) {
            $this->assertSame($harapan, $hasil[$i], "Uraian salah untuk \"{$teks}\".");
        }
    }

    public function test_string_tak_beraturan_tidak_ditebak(): void
    {
        // Tanpa tahun tidak ada yang bisa dipastikan; kalender lebih baik mulai
        // kosong daripada memilih tanggal yang salah diam-diam.
        $kasus = ['', 'tanggal menyusul', 'sesuai jadwal Irban', '1-2 Juli'];

        $hasil = $this->jalankan(json_encode($kasus).'.map(K.urai)');

        foreach ($kasus as $i => $teks) {
            $this->assertSame([], $hasil[$i], "\"{$teks}\" seharusnya tidak menghasilkan tanggal.");
        }
    }

    public function test_ringkas_lalu_urai_kembali_ke_tanggal_semula(): void
    {
        $kasus = [
            ['2026-07-01', '2026-07-02', '2026-07-04', '2026-07-05', '2026-07-06', '2026-07-07', '2026-07-13'],
            ['2026-06-30', '2026-07-01', '2026-07-02', '2026-07-05'],
            ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02'],
            ['2026-05-18'],
        ];

        $hasil = $this->jalankan(json_encode($kasus).'.map(t => K.urai(K.rangkum(t)))');

        foreach ($kasus as $i => $tanggal) {
            $this->assertSame($tanggal, $hasil[$i], 'Bolak-balik gagal untuk '.json_encode($tanggal));
        }
    }
}
