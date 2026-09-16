<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SpjBerkas;
use App\Models\User;
use App\Services\SpjBerkasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unggah SPJ: fitur PEMBANTU untuk menyimpan hasil pindaian SPJ pada satu
 * NPD, lalu ikut tercetak di "Cetak Semua (1 Berkas)".
 *
 * Yang paling penting dijaga di sini bukan unggahnya, melainkan sifat
 * OPSIONAL-nya: NPD tanpa berkas SPJ harus tetap tersimpan, tetap berjalan di
 * alur kerja, dan hasil "Cetak Semua"-nya harus tetap sama seperti sebelum
 * fitur ini ada. Kalau suatu saat ada yang membuatnya wajib, test di kelas
 * ini yang harus gagal lebih dulu.
 */
class SpjBerkasTest extends TestCase
{
    use RefreshDatabase;

    private User $pptk;

    /**
     * Formulir NPD disimpan sebagai superadmin, bukan PPTK: PPTK hanya boleh
     * memakai Sub Kegiatan limpahannya (App\Support\AnggaranNpd), dan yang
     * diuji di kelas ini berkas SPJ - bukan pelimpahan. Memakai PPTK di sini
     * berarti tiap test harus menyiapkan pelimpahan lebih dulu, dan kegagalan
     * pelimpahan akan terbaca seolah-olah unggah SPJ yang bermasalah.
     */
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->pptk = User::create([
            'username' => 'pptk-spj',
            'nama' => 'PPTK SPJ',
            'role' => User::ROLE_PPTK,
            'password' => 'rahasia',
        ]);

        $this->superadmin = User::create([
            'username' => 'superadmin-spj',
            'nama' => 'Superadmin SPJ',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia',
        ]);
    }

    public function test_npd_barang_jasa_bisa_dibuat_sekaligus_mengunggah_berkas_spj(): void
    {
        $anggaran = $this->anggaran(10_000_000);

        $response = $this->actingAs($this->superadmin)->post(route('npd.bj.store'), $this->payloadBj($anggaran) + [
            'spj' => [
                UploadedFile::fake()->create('spj-lembar-1.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->image('spj-lembar-2.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $npd = Npd::query()->latest('id')->firstOrFail();

        $berkas = $npd->spjBerkas;
        $this->assertCount(2, $berkas);
        $this->assertSame(['spj-lembar-1.pdf', 'spj-lembar-2.jpg'], $berkas->pluck('nama_asli')->all());
        $this->assertSame(['application/pdf', 'image/jpeg'], $berkas->pluck('mime')->all());
        // Urutan menentukan urutan halaman saat ikut dicetak.
        $this->assertSame([1, 2], $berkas->pluck('urutan')->all());

        foreach ($berkas as $item) {
            Storage::disk('local')->assertExists($item->path);
            // Nama berkas di disk TIDAK memakai nama kiriman - nama kiriman
            // adalah masukan pemakai dan tidak boleh jadi bagian path.
            $this->assertStringNotContainsString('spj-lembar', $item->path);
            $this->assertStringStartsWith('spj/'.$npd->id.'/', $item->path);
        }
    }

    /** Sifat opsional: tanpa berkas, NPD tetap tersimpan tanpa keluhan. */
    public function test_npd_tetap_tersimpan_tanpa_berkas_spj(): void
    {
        $anggaran = $this->anggaran(10_000_000);

        $this->actingAs($this->superadmin)->post(route('npd.bj.store'), $this->payloadBj($anggaran))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $npd = Npd::query()->latest('id')->firstOrFail();
        $this->assertCount(0, $npd->spjBerkas);
        $this->assertSame(0, SpjBerkas::count());
    }

    public function test_berkas_selain_pdf_dan_jpg_ditolak(): void
    {
        $anggaran = $this->anggaran(10_000_000);

        $this->actingAs($this->superadmin)->post(route('npd.bj.store'), $this->payloadBj($anggaran) + [
            'spj' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('spj.0');

        $this->assertSame(0, Npd::count());
        $this->assertSame(0, SpjBerkas::count());
    }

    public function test_unggah_menyusul_dari_halaman_detail_menambah_bukan_menimpa(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('lembar-1.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('lembar-2.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $this->assertSame(
            ['lembar-1.pdf', 'lembar-2.pdf'],
            $npd->fresh()->spjBerkas->pluck('nama_asli')->all(),
        );
        $this->assertSame([1, 2], $npd->fresh()->spjBerkas->pluck('urutan')->all());
    }

    public function test_berkas_bisa_dilihat_lewat_rute_dan_dihapus_beserta_isinya_di_disk(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('spj.pdf', 60, 'application/pdf')],
        ]);

        $berkas = SpjBerkas::query()->firstOrFail();

        // Dilihat: inline di tab peramban, bukan diunduh sebagai lampiran.
        $lihat = $this->actingAs($this->pptk)->get(route('npd.spj-berkas.show', [$npd, $berkas]));
        $lihat->assertOk();
        $this->assertSame('application/pdf', $lihat->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $lihat->headers->get('Content-Disposition'));

        $path = $berkas->path;
        $this->actingAs($this->pptk)->delete(route('npd.spj-berkas.destroy', [$npd, $berkas]))->assertRedirect();

        $this->assertSame(0, SpjBerkas::count());
        Storage::disk('local')->assertMissing($path);
    }

    /** Berkas milik NPD lain tidak bisa diambil lewat NPD yang sedang dibuka. */
    public function test_berkas_npd_lain_tidak_bisa_diakses_lewat_npd_ini(): void
    {
        $anggaran = $this->anggaran(20_000_000);
        $npdA = $this->npdSelesai($anggaran);
        $npdB = $this->npdSelesai($anggaran);

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npdA), [
            'spj' => [UploadedFile::fake()->create('milik-a.pdf', 40, 'application/pdf')],
        ]);

        $berkasA = SpjBerkas::query()->firstOrFail();

        $this->actingAs($this->pptk)->get(route('npd.spj-berkas.show', [$npdB, $berkasA]))->assertNotFound();
        $this->actingAs($this->pptk)->delete(route('npd.spj-berkas.destroy', [$npdB, $berkasA]))->assertNotFound();
        $this->assertSame(1, SpjBerkas::count());
    }

    /** Pengawas berperan baca-saja: boleh melihat berkasnya, tidak boleh mengubah. */
    public function test_pengawas_boleh_melihat_tapi_tidak_boleh_mengunggah_atau_menghapus(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('spj.pdf', 40, 'application/pdf')],
        ]);
        $berkas = SpjBerkas::query()->firstOrFail();

        $pengawas = User::create([
            'username' => 'pengawas-spj',
            'nama' => 'Pengawas SPJ',
            'role' => User::ROLE_PENGAWAS,
            'password' => 'rahasia',
        ]);

        $this->actingAs($pengawas)->get(route('npd.spj-berkas.show', [$npd, $berkas]))->assertOk();
        $this->actingAs($pengawas)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('lain.pdf', 40, 'application/pdf')],
        ])->assertForbidden();
        $this->actingAs($pengawas)->delete(route('npd.spj-berkas.destroy', [$npd, $berkas]))->assertForbidden();

        $this->assertSame(1, SpjBerkas::count());
    }

    /**
     * Inti permintaan: berkas SPJ ikut di "Cetak Semua (1 Berkas)".
     *
     * Yang diperiksa jumlah HALAMAN PDF-nya, bukan sekadar tanggapan 200 -
     * gabungan yang gagal menyisipkan SPJ tetap mengembalikan 200 dengan
     * dokumen NPD saja, dan itu justru kegagalan yang ingin ditangkap.
     */
    public function test_cetak_semua_menyertakan_berkas_spj_yang_diunggah(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        $tanpaSpj = $this->actingAs($this->pptk)->get(route('npd.cetak-gabungan', $npd));
        $tanpaSpj->assertOk();
        $halamanTanpaSpj = $this->jumlahHalaman($tanpaSpj->getContent());

        // Satu PDF SPJ satu halaman + satu JPG (dibungkus jadi satu halaman).
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [
                UploadedFile::fake()->createWithContent('spj-1.pdf', $this->pdfSatuHalaman()),
                UploadedFile::fake()->image('spj-2.jpg', 800, 1200),
            ],
        ])->assertRedirect();

        $denganSpj = $this->actingAs($this->pptk)->get(route('npd.cetak-gabungan', $npd));
        $denganSpj->assertOk();
        $halamanDenganSpj = $this->jumlahHalaman($denganSpj->getContent());

        $this->assertSame($halamanTanpaSpj + 2, $halamanDenganSpj);
    }

    /** Urutan bendel: dokumen NPD dulu, berkas SPJ paling belakang. */
    public function test_keterangan_urutan_gabungan_menyebut_spj_di_paling_belakang(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        $this->actingAs($this->pptk)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee('Berkas SPJ')
            ->assertSee('Belum ada berkas SPJ yang diunggah')
            ->assertViewHas('urutanGabungan', 'NPD → Lampiran NPD');

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('spj.pdf', 40, 'application/pdf')],
        ]);

        $this->actingAs($this->pptk)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee('spj.pdf')
            ->assertViewHas('urutanGabungan', 'NPD → Lampiran NPD → SPJ 1');
    }

    /** Inventarisasi SPJ ikut menampilkan berkasnya lewat JSON rincian. */
    public function test_rincian_inventarisasi_spj_memuat_daftar_berkas_dan_alamat_unggah(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('bukti-spj.pdf', 40, 'application/pdf')],
        ]);
        $berkas = SpjBerkas::query()->firstOrFail();

        $bp = User::create([
            'username' => 'bp-spj',
            'nama' => 'BP SPJ',
            'role' => User::ROLE_BENDAHARA_PENGELUARAN,
            'password' => 'rahasia',
        ]);

        $this->actingAs($bp)->get(route('inventarisasi-spj.rincian', $npd))
            ->assertOk()
            ->assertJsonPath('berkas_spj.0.nama', 'bukti-spj.pdf')
            ->assertJsonPath('berkas_spj.0.jenis', 'PDF')
            ->assertJsonPath('berkas_spj.0.url', route('npd.spj-berkas.show', [$npd, $berkas]))
            ->assertJsonPath('url_unggah_spj', route('npd.spj-berkas.store', $npd));

        // Halaman Inventarisasi SPJ menyediakan pintu unggahnya sendiri.
        $this->actingAs($bp)->get(route('inventarisasi-spj.index'))
            ->assertOk()
            ->assertSee('Upload SPJ')
            ->assertSee('inv-spj-input', false);
    }

    public function test_hapus_permanen_npd_ikut_menghapus_berkas_di_disk(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [UploadedFile::fake()->create('spj.pdf', 40, 'application/pdf')],
        ]);
        $path = SpjBerkas::query()->firstOrFail()->path;

        $this->actingAs($this->superadmin)
            ->delete(route('npd.destroy-permanent', $npd), ['alasan_permanen' => 'pengujian hapus permanen'])
            ->assertRedirect();

        $this->assertSame(0, SpjBerkas::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_jumlah_berkas_dibatasi_dan_kelebihannya_ditolak_validasi(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));
        $berlebih = array_map(
            fn (int $i) => UploadedFile::fake()->create("lembar-{$i}.pdf", 10, 'application/pdf'),
            range(1, SpjBerkasService::MAKS_BERKAS + 1),
        );

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), ['spj' => $berlebih])
            ->assertSessionHasErrors('spj');

        $this->assertSame(0, SpjBerkas::count());
    }

    /**
     * Kuota habis di tengah kiriman: yang masuk tetap disimpan, tapi pemakai
     * harus DIBERI TAHU bahwa tidak semuanya masuk - kalau diam, ia
     * menganggap lembar yang hilang sudah terunggah.
     */
    public function test_kuota_habis_di_tengah_kiriman_memberi_peringatan_bukan_diam(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        // Isi sampai satu slot sebelum penuh.
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => array_map(
                fn (int $i) => UploadedFile::fake()->create("awal-{$i}.pdf", 10, 'application/pdf'),
                range(1, SpjBerkasService::MAKS_BERKAS - 1),
            ),
        ])->assertSessionHasNoErrors();

        // Kirim 3 lagi: hanya 1 yang muat.
        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [
                UploadedFile::fake()->create('muat.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('tidak-muat-1.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('tidak-muat-2.pdf', 10, 'application/pdf'),
            ],
        ])->assertSessionHasErrors('spj');

        $this->assertSame(SpjBerkasService::MAKS_BERKAS, SpjBerkas::count());
    }

    /** Panel Inventarisasi SPJ mengunggah lewat fetch - galatnya harus 422 JSON, bukan redirect. */
    public function test_unggah_lewat_ajax_mengembalikan_json_bukan_redirect(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        // post() biasa dengan header Accept, BUKAN postJson(): berkas
        // dikirim sebagai multipart - postJson menyandikan badan permintaan
        // sebagai JSON dan objek UploadedFile tidak bisa ikut di dalamnya.
        $ajax = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($this->pptk)
            ->post(route('npd.spj-berkas.store', $npd), [
                'spj' => [UploadedFile::fake()->create('spj.pdf', 40, 'application/pdf')],
            ], $ajax)
            ->assertOk()
            ->assertJsonPath('jumlah', 1);

        // Berkas tidak sah: 422 dengan kunci galat di dalam 'errors' - bentuk
        // yang dibaca JS panel Inventarisasi. Diperiksa lewat json('errors')
        // dan bukan assertJsonValidationErrors(): kunci galatnya "spj.0",
        // dan titik di dalam nama kunci dibaca pembantu itu sebagai
        // pemisah jalur bersarang.
        $galat = $this->actingAs($this->pptk)
            ->post(route('npd.spj-berkas.store', $npd), [
                'spj' => [UploadedFile::fake()->create('salah.exe', 10, 'application/x-msdownload')],
            ], $ajax)
            ->assertStatus(422);

        $this->assertArrayHasKey('spj.0', $galat->json('errors'));
    }

    /**
     * Pindaian MENDATAR harus dapat kertas MENDATAR, di berkas SPJ-nya
     * sendiri maupun di bendel "Cetak Semua".
     *
     * Dulu kertasnya selalu tegak: pindaian mendatar dijejalkan ke dalamnya,
     * lebarnya mentok lebih dulu, dan gambarnya menyusut jadi sepertiga
     * tinggi halaman - tulisan di SPJ tidak lagi terbaca. Yang diperiksa di
     * sini ukuran MediaBox halamannya, bukan sekadar "PDF-nya jadi": bendel
     * yang salah arah kertas tetap menghasilkan PDF yang sah.
     */
    public function test_pindaian_mendatar_dapat_kertas_mendatar_tegak_tetap_tegak(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));
        $service = app(SpjBerkasService::class);

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            'spj' => [
                UploadedFile::fake()->image('mendatar.jpg', 3000, 2000),
                UploadedFile::fake()->image('tegak.jpg', 2000, 3000),
            ],
        ])->assertSessionHasNoErrors();

        [$mendatar, $tegak] = $npd->fresh()->spjBerkas->all();

        // F4: 215 x 330 mm. Mendatar berarti keduanya tertukar.
        $this->assertSame([[330, 215]], $this->ukuranHalaman($service->pdf($mendatar)));
        $this->assertSame([[215, 330]], $this->ukuranHalaman($service->pdf($tegak)));

        // Dan arah itu harus tetap terjaga sesudah digabung dengan dokumen
        // NPD yang semuanya tegak.
        $bendel = $this->actingAs($this->pptk)->get(route('npd.cetak-gabungan', $npd));
        $bendel->assertOk();
        $ukuran = $this->ukuranHalaman($bendel->getContent());

        $this->assertContains([330, 215], $ukuran, 'Halaman SPJ mendatar hilang arah kertasnya di bendel gabungan.');
        $this->assertContains([215, 330], $ukuran);
    }

    /** Gambar diperkecil agar MUAT, bukan dipotong - dan tetap satu halaman. */
    public function test_pindaian_lebar_muat_utuh_dalam_satu_halaman(): void
    {
        $npd = $this->npdSelesai($this->anggaran(10_000_000));

        $this->actingAs($this->pptk)->post(route('npd.spj-berkas.store', $npd), [
            // Sangat lebar (2,5:1), seperti nota panjang yang dipindai mendatar.
            'spj' => [UploadedFile::fake()->image('nota-panjang.jpg', 5000, 2000)],
        ]);

        $pdf = app(SpjBerkasService::class)->pdf(SpjBerkas::query()->firstOrFail());

        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'Gambar tumpah ke halaman kedua.');

        // Penempatan gambar dibaca dari matriks "lebar 0 0 tinggi x y cm"
        // sebelum operator Do - satu-satunya cara memastikan gambarnya utuh
        // di dalam area cetak, bukan sekadar ada di halaman.
        [$lebarMm, $tinggiMm] = $this->ukuranGambar($pdf);
        $this->assertLessThanOrEqual(330 - 16, $lebarMm + 0.5);
        $this->assertLessThanOrEqual(215 - 16, $tinggiMm + 0.5);
        // Benar-benar dipakai, bukan menyusut jadi sekeping kecil.
        $this->assertGreaterThan(300, $lebarMm);
        // Perbandingan sisi 2,5:1 dipertahankan.
        $this->assertEqualsWithDelta(2.5, $lebarMm / $tinggiMm, 0.02);
    }

    // ----------------------------------------------------------------- bantu

    /**
     * Ukuran tiap halaman PDF dalam mm, dibaca dari /MediaBox.
     *
     * Nilai yang sama persis muncul dua kali pada berkas mPDF (satu di simpul
     * /Pages sebagai bawaan, satu di halamannya) - karena itu dibuat unik.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function ukuranHalaman(string $pdf): array
    {
        preg_match_all('#/MediaBox\s*\[([^\]]+)\]#', $pdf, $cocok);

        $ukuran = array_map(function (string $kotak) {
            [, , $lebar, $tinggi] = array_map('floatval', preg_split('/\s+/', trim($kotak)));

            return [(int) round($lebar / 72 * 25.4), (int) round($tinggi / 72 * 25.4)];
        }, $cocok[1]);

        return array_values(array_unique($ukuran, SORT_REGULAR));
    }

    /**
     * Lebar & tinggi gambar TERPASANG (mm), dibaca dari matriks penempatan
     * di aliran isi halaman.
     *
     * @return array{0: float, 1: float}
     */
    private function ukuranGambar(string $pdf): array
    {
        // Aliran isi halaman dipadatkan mPDF, jadi harus dimekarkan dulu -
        // matriksnya tidak akan pernah terlihat pada berkas mentah.
        $isi = $pdf;
        if (preg_match_all('#stream?
(.*?)?
endstream#s', $pdf, $aliran)) {
            foreach ($aliran[1] as $blok) {
                $mekar = @gzuncompress($blok);
                if (is_string($mekar) && $mekar !== '') {
                    $isi .= "
".$mekar;
                }
            }
        }

        $ditemukan = preg_match('#([0-9.]+) 0 0 ([0-9.]+) [0-9.]+ [0-9.]+ cm\s*/I\d+ Do#', $isi, $m);
        $this->assertSame(1, $ditemukan, 'Matriks penempatan gambar tidak ditemukan di PDF.');

        return [(float) $m[1] / 72 * 25.4, (float) $m[2] / 72 * 25.4];
    }

    private function anggaran(float $pagu): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program SPJ',
            'kegiatan' => 'Kegiatan SPJ',
            'sub_kegiatan' => '6.01.01.1.01.0001 Sub Kegiatan SPJ',
            'kode_sub_kegiatan' => '6.01.01.1.01.0001',
            'kode_rekening' => MasterAnggaran::gabungKodeUraian('5.1.02.01.001.00052', 'Belanja Pengujian'),
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadBj(MasterAnggaran $anggaran): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-21',
            'bulan' => 7,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'penerima' => [[
                'nama' => 'Penerima Pengujian',
                'bruto' => 1_000_000,
                'keterangan' => 'keperluan pengujian',
            ]],
        ];
    }

    private function npdSelesai(MasterAnggaran $anggaran): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-21',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Selesai',
            'dibuat_oleh' => $this->pptk->id,
        ]);
    }

    /** PDF satu halaman paling sederhana yang masih sah, untuk diunggah sebagai SPJ. */
    private function pdfSatuHalaman(): string
    {
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => [215, 330]]);
        $mpdf->WriteHTML('<p>Lampiran SPJ pengujian</p>');

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /** Jumlah halaman PDF, dibaca dari objek /Type /Page di dalam berkasnya. */
    private function jumlahHalaman(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }
}
