<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use Database\Seeders\ClusterUhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

/**
 * Paginasi Daftar Pembayaran Perjalanan Dinas (adopsi GAS #73 & #75).
 *
 * Dua perbaikan yang dijaga di sini:
 *  - tinggi baris data MENYEMPIT mengikuti banyaknya penerima, supaya daftar
 *    sampai 20 orang muat satu halaman F4;
 *  - Terbilang dan blok tanda tangan tidak pernah terpisah antar halaman.
 *
 * Keduanya menyentuh dokumen yang ditandatangani, jadi diuji lewat PDF yang
 * benar-benar dirender - bukan sekadar memeriksa ada tidaknya kelas CSS.
 */
class DaftarPembayaranPaginasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClusterUhSeeder::class);
    }

    private function tinggi(int $nBaris): string
    {
        $metode = new ReflectionMethod(NpdController::class, 'tinggiBarisDaftar');
        $metode->setAccessible(true);

        return $metode->invoke(app(NpdController::class), $nBaris);
    }

    public function test_tinggi_baris_mengikuti_interpolasi_yang_sama_dengan_gas(): void
    {
        // Angka acuan diambil dari catatan verifikasi GAS #75:
        // 2/5/8 = 32,0 ; 12 = 30,4 ; 16 = 28,8 ; 20/25 = 27,2.
        $this->assertSame('32pt', $this->tinggi(2));
        $this->assertSame('32pt', $this->tinggi(5));
        $this->assertSame('32pt', $this->tinggi(8));
        $this->assertSame('30.4pt', $this->tinggi(12));
        $this->assertSame('28.8pt', $this->tinggi(16));
        $this->assertSame('27.2pt', $this->tinggi(20));

        // Lebih dari 20 penerima tetap dipatok di batas bawah - ruang tanda
        // tangan basah tidak boleh dikorbankan demi memaksa satu halaman.
        $this->assertSame('27.2pt', $this->tinggi(25));
        $this->assertSame('27.2pt', $this->tinggi(60));
    }

    public function test_daftar_dua_puluh_empat_penerima_muat_satu_halaman(): void
    {
        $pptk = User::create([
            'username' => 'daftar-pptk',
            'nama' => 'Daftar PPTK',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $anggaran = MasterAnggaran::create([
            'program' => 'Program Uji Daftar',
            'kegiatan' => 'Kegiatan Uji Daftar',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Daftar',
            'kode_rekening' => '5.1.02.04.001.00001',
            'tagging_id' => null,
            'pagu' => 500_000_000,
            'aktif' => true,
        ]);
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $sp = SuratPerintah::create([
            'nomor_sp' => '800/SP/DAFTAR/2026',
            'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Cirebon',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 - 22 Juli 2026',
            'keterangan' => 'Pemeriksaan rombongan besar',
            'file_url' => 'sp/daftar.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true,
            'dipantau' => true,
        ]);

        // Nama panjang bergelar seperti aslinya - nama pendek membuat tabelnya
        // lebih ringkas daripada kenyataan dan ujiannya jadi terlalu mudah.
        $tim = [];
        for ($i = 0; $i < 24; $i++) {
            $tim[] = [
                'nama' => 'Drs. Pemeriksa Nomor '.($i + 1).', M.M.',
                'jabatan' => $i === 0 ? 'Ketua Tim Pemeriksa' : 'Anggota Tim Pemeriksa',
                'nip' => '19800101200001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'rekening' => str_pad((string) (3000000000 + $i), 12, '0', STR_PAD_LEFT),
                'tol' => 50_000,
                'paket' => [[
                    'cluster' => 'D',
                    'wilayah' => 'Kota Cirebon',
                    'lama_hari' => 3,
                    'tarif_uh' => 430_000,
                    'malam' => 2,
                    'tarif_akom' => 500_000,
                ]],
            ];
        }

        $this->actingAs($pptk)->post(route('npd.pd.store'), [
            'master_anggaran_id' => $anggaran->id,
            'surat_perintah_id' => $sp->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_sp' => 'Pemeriksaan rombongan besar',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Cirebon',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-22',
            'penerima_index' => 0,
            'tim' => $tim,
        ])->assertSessionHasNoErrors();

        $npd = Npd::sole();
        $this->assertSame(24, $npd->tim()->count());

        $isi = $this->actingAs($pptk)->get(route('npd.cetak-daftar', $npd));
        $isi->assertOk();

        $halaman = (new PdfReader(new PdfParser(StreamReader::createByString($isi->getContent()))))->getPageCount();

        // Diukur langsung pada mPDF: dengan tinggi baris lama yang tetap
        // 32pt, 22 penerima ke atas SUDAH pecah jadi dua halaman; dengan
        // tinggi adaptif, satu halaman bertahan sampai 25 penerima. Angka 24
        // dipilih supaya test ini benar-benar gagal bila tinggi adaptifnya
        // hilang - pada 20 penerima keduanya sama-sama muat, jadi 20 tidak
        // membuktikan apa pun.
        //
        // Catatan GAS #75 menyebut ambangnya 20; mesin cetak Google memang
        // lebih boros ruang daripada mPDF. Yang diadopsi perilakunya, bukan
        // angka ambangnya.
        $this->assertSame(1, $halaman, 'Daftar 24 penerima seharusnya muat satu halaman F4 berkat tinggi baris adaptif.');
    }

    public function test_terbilang_dan_tanda_tangan_dibungkus_satu_blok_anti_pecah(): void
    {
        // Penjaga struktur: kalau pembungkusnya hilang, tanda tangan bisa
        // menggantung sendirian di halaman berikutnya pada daftar yang panjang
        // - persis bug yang diperbaiki GAS #73.
        $blade = file_get_contents(resource_path('views/npd/pdf/pd-daftar.blade.php'));

        $this->assertStringContainsString('.blok-penutup { page-break-inside:avoid; }', $blade);

        $mulai = strpos($blade, '<div class="blok-penutup">');
        $this->assertNotFalse($mulai);

        $sisa = substr($blade, $mulai);
        $this->assertStringContainsString('Terbilang', $sisa);
        $this->assertStringContainsString('class="ttd"', $sisa);
        $this->assertLessThan(
            strpos($sisa, 'class="ttd"'),
            strpos($sisa, 'Terbilang'),
            'Terbilang harus berada di dalam blok yang sama dan sebelum tanda tangan.'
        );
    }
}
