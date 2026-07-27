<?php

namespace Tests\Feature;

use App\Models\Kpa;
use App\Models\KpaPptk;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

/**
 * Regression coverage for every on-demand PDF across all 6 NPD jenis, using
 * realistic multi-row data (8-12 rows, some multi-day/multi-paket) rather
 * than 1-2 row fixtures — that's what surfaces page-break/rowspan bugs that
 * small fixtures never hit (e.g. the SPD Rampung KPA signature page-break
 * bug found via this test during the Prompt 21B PDF visual audit).
 *
 * Set PDF_AUDIT_DUMP_DIR to an absolute path to also dump the rendered PDF
 * bytes to disk for manual visual inspection (page overflow, glyphs, table
 * layout) — not needed for routine `php artisan test` runs.
 */
class NpdPdfRenderTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function simpanPdf(string $namaFile, TestResponse $response, ?int $maksimalHalaman = null): void
    {
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);

        // Dump before asserting page count so a failing assertion still leaves the
        // rendered PDF on disk for inspection instead of only reporting a number.
        $dumpDir = env('PDF_AUDIT_DUMP_DIR');
        if ($dumpDir) {
            if (! is_dir($dumpDir)) {
                mkdir($dumpDir, 0777, true);
            }
            file_put_contents(rtrim($dumpDir, '/\\').'/'.$namaFile, $content);
        }

        if ($maksimalHalaman !== null) {
            $reader = new PdfReader(new PdfParser(StreamReader::createByString($content)));
            $this->assertLessThanOrEqual(
                $maksimalHalaman,
                $reader->getPageCount(),
                "$namaFile melebihi $maksimalHalaman halaman — cek page-break pada template (lihat bug SPD Rampung KPA yang terpisah ke halaman kosong, ditemukan saat audit visual PDF Prompt 21B)."
            );
        }
    }

    private function pegawai(string $nama): Pegawai
    {
        $this->seq++;

        return Pegawai::create([
            'nama' => $nama,
            'nip' => sprintf('19800101201001%04d', $this->seq),
            'jabatan' => 'Pejabat Audit',
            'bidang' => 'Sekretariat',
            'pangkat' => 'Pembina Tk. I (IV/b)',
            'rekening' => '99900'.str_pad((string) $this->seq, 6, '0', STR_PAD_LEFT),
            'aktif' => true,
        ]);
    }

    /** Bangun rantai pejabat lengkap (PA/Bendahara OPD + KPA/BPP/PPTK + Pelimpahan) untuk satu program/sub kegiatan, supaya tanda tangan PDF tidak kosong/placeholder. */
    private function pastikanPejabatLengkap(string $program, string $subKegiatan): void
    {
        if (PejabatOpd::aktif() === null) {
            PejabatOpd::simpan([
                'pa_pegawai_id' => $this->pegawai('Dr. H. Asep Kurnia, M.Si.')->id,
                'bendahara_pengeluaran_pegawai_id' => $this->pegawai('Hj. Rina Sundari, S.E.')->id,
            ]);
        }

        $kpa = Kpa::create([
            'kpa_pegawai_id' => $this->pegawai('Drs. H. Dadang Suryana, M.M. (KPA)')->id,
            'bpp_pegawai_id' => $this->pegawai('Yeni Marlina, S.E. (BPP)')->id,
            'aktif' => true,
        ]);
        $pptk = $this->pegawai('Agus Setiawan, S.H. (PPTK)');
        KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptk->id, 'aktif' => true]);
        Pelimpahan::tetapkan([['program' => $program, 'sub_kegiatan' => $subKegiatan]], $kpa->id, $kpa->bpp_pegawai_id, $pptk->id);
    }

    private function user(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia-audit',
        ]);
    }

    private function masterAnggaran(string $program, string $sub, string $kode, float $pagu = 900_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => $program,
            'kegiatan' => 'Kegiatan '.$program,
            'sub_kegiatan' => $sub,
            'kode_rekening' => MasterAnggaran::gabungKodeUraian($kode, 'Belanja Pengujian Audit PDF'),
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    // ============================================================ BJ ====

    public function test_render_semua_pdf_barang_jasa(): void
    {
        $program = 'Program Audit PDF BJ';
        $sub = '6.01.01.2.01 Sub Kegiatan Audit PDF Barang Jasa';
        $pptk = $this->user('pptk', 'audit-bj-pptk');
        $master = $this->masterAnggaran($program, $sub, '5.1.02.01.01.0900');
        $this->pastikanPejabatLengkap($program, $sub);

        $namaPenerima = [
            'Drs. Bambang Setiawan, M.Si.', 'Hj. Siti Nurhaliza, S.E.', 'CV Sumber Makmur Jaya',
            'Ahmad Fauzan Ridwan', 'PT Berkah Abadi Sentosa', 'Dewi Kartika Sari, S.Sos.',
            'Toko Alat Tulis Mandiri', 'Yusuf Maulana, S.T.', 'CV Cipta Karya Utama',
            'Rina Marlina, S.Pd.', 'PT Sumber Rezeki Bersama', 'Agus Salim, S.H.',
        ];

        $penerima = [];
        foreach ($namaPenerima as $i => $nama) {
            $bruto = 750_000 + ($i * 125_000);
            $jumlahPph = $i % 4; // pola 0,1,2,3 supaya kolom PPh adaptif teruji (0/1/2+ jenis)
            $pphList = [];
            $jenisPphSiklus = ['PPh Pasal 21', 'PPh Pasal 23', 'PPh Pasal 22', 'PPh Pasal 4(2)'];
            for ($j = 0; $j < $jumlahPph; $j++) {
                $pphList[] = ['jenis' => $jenisPphSiklus[$j], 'nilai' => 10_000 * ($j + 1)];
            }
            $penerima[] = [
                'nama' => $nama,
                'rekening' => str_pad((string) (1000000000 + $i), 10, '0', STR_PAD_LEFT),
                'bruto' => $bruto,
                'ppn' => $i % 3 === 0 ? round($bruto * 0.11) : 0,
                'biaya_ku_rtgs' => $i % 2 === 0 ? 15_000 : 0,
                'pph_list' => $pphList,
                'keterangan' => 'Belanja pengujian audit visual PDF baris ke-'.($i + 1),
            ];
        }

        $payload = [
            'master_anggaran_id' => $master->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-21',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => $penerima,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), $payload);
        $npd = Npd::where('jenis', 'bj')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(12, $npd->penerima()->count());

        $this->simpanPdf('bj-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('bj-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
    }

    // ============================================================ PD ====

    public function test_render_semua_pdf_perjalanan_dinas(): void
    {
        $program = 'Program Audit PDF PD';
        $sub = '6.01.01.2.02 Sub Kegiatan Audit PDF Perjalanan Dinas';
        $pptk = $this->user('pptk', 'audit-pd-pptk');
        $master = $this->masterAnggaran($program, $sub, '5.1.02.04.01.0900');
        $this->pastikanPejabatLengkap($program, $sub);

        $namaTim = [
            'Drs. Hendra Gunawan, M.M.', 'Nurul Fitriani, S.E.', 'Dedi Kurniawan, S.H.',
            'Lestari Wulandari, S.Sos.', 'Rudi Hartono, S.T.', 'Fitri Ramadhani, S.Pd.',
            'Bayu Aji Nugroho', 'Sri Wahyuni, S.E.', 'Doni Prasetyo, S.H.',
            'Yulia Anggraini, S.Sos.', 'Firman Syahputra', 'Wahyu Setiawan, S.T.',
        ];

        $tim = [];
        foreach ($namaTim as $i => $nama) {
            $paket = [[
                'cluster' => chr(65 + ($i % 3)),
                'wilayah' => ['Bandung', 'Kota Bandung', 'Cirebon'][$i % 3],
                'lama_hari' => 2 + ($i % 3),
                'tarif_uh' => 100_000 + ($i * 5_000),
                'malam' => 1 + ($i % 3),
                'tarif_akom' => 300_000 + ($i * 10_000),
            ]];
            // Sebagian anggota mendapat 2 paket (multi-wilayah) untuk menguji rowspan.
            if ($i % 4 === 0) {
                $paket[] = [
                    'cluster' => 'B',
                    'wilayah' => 'Sumedang',
                    'lama_hari' => 1,
                    'tarif_uh' => 90_000,
                    'malam' => 1,
                    'tarif_akom' => 250_000,
                ];
            }
            $tim[] = [
                'nama' => $nama,
                'jabatan' => $i === 0 ? 'Ketua Tim' : 'Anggota',
                'nip' => '19800101200001'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'rekening' => str_pad((string) (2000000000 + $i), 10, '0', STR_PAD_LEFT),
                'bbm_liter' => $i % 2 === 0 ? 10.5 : 0,
                'bbm_tarif' => $i % 2 === 0 ? 10_000 : 0,
                'tol' => $i % 3 === 0 ? 50_000 : 0,
                'tiket' => $i % 5 === 0 ? 350_000 : 0,
                'representatif' => $i === 0 ? 100_000 : 0,
                'paket' => $paket,
            ];
        }

        $payload = [
            'master_anggaran_id' => $master->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_sp' => '900/SP/AUDIT/2026',
            'tanggal_sp' => '2026-07-15',
            'uraian_sp' => 'Perjalanan dinas pengujian audit visual PDF multi-halaman',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Cirebon dan sekitarnya',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-24',
            'keterangan_lampiran' => 'Rombongan 12 orang untuk pengujian tabel multi-halaman',
            'penerima_index' => 0,
            'tim' => $tim,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.pd.store'), $payload);
        $npd = Npd::where('jenis', 'pd')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(12, $npd->tim()->count());

        $this->simpanPdf('pd-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('pd-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
        $this->simpanPdf('pd-03-daftar-bayar.pdf', $this->actingAs($pptk)->get(route('npd.cetak-daftar', $npd)));

        // maksimalHalaman:2 is a regression ceiling for the SPD Rampung KPA-signature
        // page-break bug found via this exact 12-anggota scenario during the Prompt
        // 21B PDF visual audit: the .kpa-box block used to SPLIT mid-block, stranding
        // just the KPA name/pangkat/NIP alone on page 2 while its own "Kuasa Pengguna
        // Anggaran" label stayed behind on page 1. After adding page-break-inside:avoid
        // (pd-spd.blade.php), the whole block now moves to page 2 together instead of
        // splitting — confirmed visually, page 1 no longer contains any fragment of the
        // label. Two pages for a 12-person rombongan is legitimate pagination, not a
        // bug; this ceiling exists to catch it regressing to 3+ pages, which is not
        // achievable by a single coherent block and would mean the split came back. A
        // naive assertOk()+%PDF- check wouldn't catch either failure mode — the
        // response is still a valid PDF either way.
        $this->simpanPdf('pd-04-spd-rampung.pdf', $this->actingAs($pptk)->get(route('npd.cetak-spd', $npd)), maksimalHalaman: 2);

        // Simpan induk untuk skenario Transport di bawah (butuh induk 'pd' berstatus Selesai).
        $npd->status = 'Selesai';
        $npd->save();
        self::$indukTransport = $npd;
    }

    private static ?Npd $indukTransport = null;

    // ============================================================ TR ====

    public function test_render_semua_pdf_transport(): void
    {
        // Jalankan skenario PD dulu untuk membuat induk yang valid dan realistis (bukan induk minimal).
        $this->test_render_semua_pdf_perjalanan_dinas();
        $induk = self::$indukTransport;
        $this->assertNotNull($induk);

        $pptk = $this->user('pptk', 'audit-tr-pptk');
        $indukTim = $induk->tim()->orderBy('id')->get();

        $tim = [];
        foreach ($indukTim as $i => $anggota) {
            $tim[] = [
                'nama' => $anggota->nama,
                'jabatan' => $anggota->jabatan,
                'nip' => $anggota->nip,
                'rekening' => $anggota->rekening,
                'bbm_liter' => 8 + $i,
                'bbm_tarif' => 10_000,
                'tol' => 40_000 + ($i * 5_000),
                'tiket' => $i % 3 === 0 ? 300_000 : 0,
                'representatif' => $i === 0 ? 75_000 : 0,
            ];
        }

        $payload = [
            'npd_induk_id' => $induk->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-25',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima_index' => 0,
            'tim' => $tim,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $payload);
        $npd = Npd::where('jenis', 'tr')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(count($tim), $npd->tim()->count());

        $this->simpanPdf('tr-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('tr-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
        $this->simpanPdf('tr-03-daftar-bayar.pdf', $this->actingAs($pptk)->get(route('npd.cetak-daftar', $npd)));
        $this->simpanPdf('tr-04-spd-rampung.pdf', $this->actingAs($pptk)->get(route('npd.cetak-spd', $npd)));
    }

    // ============================================================ NS ====

    public function test_render_semua_pdf_narasumber(): void
    {
        $program = 'Program Audit PDF Narasumber';
        $sub = '6.01.01.2.03 Sub Kegiatan Audit PDF Narasumber';
        $pptk = $this->user('pptk', 'audit-ns-pptk');
        $master = $this->masterAnggaran($program, $sub, '5.1.02.02.01.0900');
        $this->pastikanPejabatLengkap($program, $sub);

        $namaNara = [
            'Prof. Dr. H. Bambang Sutrisno, M.Si.', 'Dr. Hj. Ratna Kusuma, S.H., M.H.',
            'Ir. Wawan Setiawan, M.T.', 'Dra. Kartini Handayani, M.Pd.',
            'Dr. Agus Salim Nasution', 'Hj. Fatimah Az-Zahra, S.E., M.M.',
            'Drs. Suryadi Prawira, M.Si.', 'Dewi Anggraini Putri, S.Psi.',
            'Ir. Hendra Wijaya, M.Eng.', 'Dr. Rina Marlina, S.Sos., M.A.',
            'Ahmad Zainuddin, S.Ag., M.Pd.I.', 'Dra. Sinta Dewi Lestari',
        ];

        $narasumber = [];
        foreach ($namaNara as $i => $nama) {
            $narasumber[] = [
                'nama' => $nama,
                'jabatan' => $i % 2 === 0 ? 'Narasumber Ahli' : 'Narasumber Pendamping',
                'rekening' => str_pad((string) (3000000000 + $i), 10, '0', STR_PAD_LEFT),
                'jumlah_jp' => 2 + ($i % 4),
                'tarif_jp' => 400_000 + ($i * 25_000),
                'transport' => $i % 3 === 0 ? 250_000 : 0,
                'pph21' => $i % 2 === 0 ? 50_000 + ($i * 1_000) : 0,
                'uraian' => $i % 5 === 0 ? 'Transfer khusus rekening berbeda' : '',
            ];
        }

        $payload = [
            'master_anggaran_id' => $master->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-23',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_kegiatan' => 'Rapat Koordinasi Pengawasan Internal Multi-Narasumber (Audit Visual PDF)',
            'tanggal_mulai' => '2026-07-20',
            'tanggal_selesai' => '2026-07-21',
            'narasumber' => $narasumber,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.ns.store'), $payload);
        $npd = Npd::where('jenis', 'ns')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(12, $npd->narasumber()->count());

        $this->simpanPdf('ns-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('ns-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
        $this->simpanPdf('ns-03-daftar-honor.pdf', $this->actingAs($pptk)->get(route('npd.cetak-daftar-nara', $npd)));
    }

    // ================================================== KD (kontribusi) ====

    public function test_render_semua_pdf_kontribusi_diklat_mode_kontribusi(): void
    {
        $program = 'Program Audit PDF Kontribusi Diklat';
        $sub = '6.01.01.2.04 Sub Kegiatan Audit PDF Kontribusi Diklat';
        $pptk = $this->user('pptk', 'audit-kd-k-pptk');
        $master = $this->masterAnggaran($program, $sub, '5.1.02.03.01.0900');
        $this->pastikanPejabatLengkap($program, $sub);

        $namaPeserta = [
            'Andi Saputra, S.E.', 'Rina Marlina, S.H.', 'Budi Hartono, S.T.',
            'Sari Wulandari, S.Pd.', 'Fajar Nugroho, S.Sos.', 'Maya Kusuma, S.E.',
            'Dimas Prasetya, S.H.', 'Indah Permata, S.Psi.', 'Rizky Ramadhan, S.T.',
            'Putri Ayu Lestari, S.Sos.', 'Hendra Gunawan, S.E.', 'Nia Kurniasih, S.Pd.',
        ];

        $peserta = [];
        foreach ($namaPeserta as $i => $nama) {
            $peserta[] = [
                'nama' => $nama,
                'pangkat' => 'Penata Muda (III/'.chr(97 + ($i % 4)).')',
                'nip' => '19850101201001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'rekening' => str_pad((string) (4000000000 + $i), 10, '0', STR_PAD_LEFT),
                'volume_kontribusi' => 1,
                'tarif_kontribusi' => 2_500_000,
                'volume_mooc' => $i % 3 === 0 ? 1 : 0,
                'tarif_mooc' => $i % 3 === 0 ? 500_000 : 0,
            ];
        }

        $payload = [
            'mode' => 'kontribusi',
            'master_anggaran_id' => $master->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-24',
            'bulan' => 7,
            'tahun' => 2026,
            'nama_pelatihan' => 'Diklat Penjenjangan Auditor Ahli Muda Angkatan XII (Audit Visual PDF)',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-05',
            'penerima_index' => 0,
            'peserta' => $peserta,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $payload);
        $npd = Npd::where('jenis', 'kd')->where('mode_kd', 'kontribusi')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(12, $npd->peserta()->count());

        $this->simpanPdf('kd-kontribusi-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('kd-kontribusi-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
        $this->simpanPdf('kd-kontribusi-03-daftar-bayar.pdf', $this->actingAs($pptk)->get(route('npd.cetak-daftar-kd', $npd)));

        self::$referensiKdPerjalanan = $npd;
        self::$masterKdPerjalananProgram = $program;
        self::$masterKdPerjalananSub = $sub;
    }

    private static ?Npd $referensiKdPerjalanan = null;

    private static string $masterKdPerjalananProgram = '';

    private static string $masterKdPerjalananSub = '';

    // =================================================== KD (perjalanan) ====

    public function test_render_semua_pdf_kontribusi_diklat_mode_perjalanan(): void
    {
        $this->test_render_semua_pdf_kontribusi_diklat_mode_kontribusi();
        $referensi = self::$referensiKdPerjalanan;
        $this->assertNotNull($referensi);

        $pptk = $this->user('pptk', 'audit-kd-p-pptk');
        $master = $this->masterAnggaran(self::$masterKdPerjalananProgram, self::$masterKdPerjalananSub, '5.1.02.03.01.0901');

        $namaPeserta = [
            'Andi Saputra, S.E.', 'Rina Marlina, S.H.', 'Budi Hartono, S.T.',
            'Sari Wulandari, S.Pd.', 'Fajar Nugroho, S.Sos.', 'Maya Kusuma, S.E.',
            'Dimas Prasetya, S.H.', 'Indah Permata, S.Psi.', 'Rizky Ramadhan, S.T.',
            'Putri Ayu Lestari, S.Sos.',
        ];

        $peserta = [];
        foreach ($namaPeserta as $i => $nama) {
            $peserta[] = [
                'nama' => $nama,
                'pangkat' => 'Penata Muda (III/'.chr(97 + ($i % 4)).')',
                'nip' => '19850101201001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'rekening' => str_pad((string) (4100000000 + $i), 10, '0', STR_PAD_LEFT),
                'hari_uh' => 5,
                'tarif_uh' => 400_000,
                'volume_akomodasi' => 4,
                'tarif_akomodasi' => 600_000,
                'hari_saku' => 5,
                'tarif_saku' => 100_000,
                'transport' => $i % 2 === 0 ? 350_000 : 0,
            ];
        }

        $payload = [
            'mode' => 'perjalanan',
            'npd_referensi_id' => $referensi->id,
            'master_anggaran_id' => $master->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-08-06',
            'bulan' => 8,
            'tahun' => 2026,
            'nama_pelatihan' => 'Diklat Penjenjangan Auditor Ahli Muda Angkatan XII (Audit Visual PDF)',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-05',
            'penerima_index' => 0,
            'peserta' => $peserta,
        ];

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $payload);
        $npd = Npd::where('jenis', 'kd')->where('mode_kd', 'perjalanan')->latest('id')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame(10, $npd->peserta()->count());

        $this->simpanPdf('kd-perjalanan-01-npd.pdf', $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd)));
        $this->simpanPdf('kd-perjalanan-02-lampiran.pdf', $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd)));
        $this->simpanPdf('kd-perjalanan-03-daftar-bayar.pdf', $this->actingAs($pptk)->get(route('npd.cetak-daftar-kd', $npd)));
    }
}
