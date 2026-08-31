<?php

namespace Tests\Feature;

use App\Models\GajiImport;
use App\Models\GajiInduk;
use App\Models\Tpp;
use App\Models\User;
use App\Support\GajiTunjanganKolom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Import Data Gaji & Tunjangan: unggah -> preview/dry-run -> konfirmasi.
 *
 * Berkas ujinya dibangun dari GajiTunjanganKolom::header() supaya susunan
 * kolomnya selalu sama dengan yang dianggap benar oleh importer - kalau peta
 * kolomnya berubah, berkas uji ikut berubah dan yang gagal adalah anggapan
 * tentang isinya, bukan tentang susunannya. Berkas SIPD sungguhan diuji
 * terpisah di test_berkas_template_sipd_asli_diterima() bila tersedia.
 */
class GajiTunjanganImportTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::create([
            'username' => 'gt-import-'.uniqid(),
            'nama' => 'Uji Import',
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_SUPERADMIN,
            'aktif' => true,
        ]);
    }

    /**
     * Satu baris berisi nilai bawaan untuk seluruh kolom sebuah jenis, lalu
     * ditimpa oleh $ubah (kunci = nama kolom tabel).
     *
     * @param  array<string, mixed>  $ubah
     * @return array<int, mixed>
     */
    private function baris(string $jenis, array $ubah = []): array
    {
        $nilai = [];

        foreach (GajiTunjanganKolom::definisi($jenis) as [$kolom, $tipe]) {
            // array_key_exists, bukan ??: null adalah nilai uji yang sah di
            // sini (sel kosong), jadi tidak boleh jatuh ke nilai bawaan.
            if (array_key_exists($kolom, $ubah)) {
                $nilai[] = $ubah[$kolom];

                continue;
            }

            $nilai[] = match ($tipe) {
                'teks' => match ($kolom) {
                    'nama_pegawai' => 'PEGAWAI UJI',
                    'nip' => '196611041990032003',
                    'golongan' => 'IV/c',
                    'pppk_pns', 'pns_pppk' => 'PNS',
                    'nama_jabatan' => 'AUDITOR AHLI MADYA',
                    'nomor_rekening_bank_pegawai' => '0006235352100',
                    default => '',
                },
                'tanggal' => '04-11-1966',
                'persen' => 98.74,
                default => 0,
            };
        }

        return $nilai;
    }

    /**
     * @param  array<int, array<int, mixed>>  $baris
     * @param  array<int, string>|null  $header  ganti header untuk menguji penjaga susunan kolom
     */
    private function berkas(string $jenis, array $baris, ?array $header = null): UploadedFile
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([$header ?? GajiTunjanganKolom::header($jenis)], null, 'A1');

        if ($baris !== []) {
            $sheet->fromArray($baris, null, 'A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'gt').'.xlsx';
        (new XlsxWriter($sheet->getParent()))->save($path);

        return new UploadedFile($path, 'uji-'.$jenis.'.xlsx', null, null, true);
    }

    public function test_import_gaji_induk_preview_lalu_konfirmasi(): void
    {
        $user = $this->superadmin();

        $berkas = $this->berkas('gaji', [
            $this->baris('gaji', [
                'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
                'nip' => '196611041990032003',
                'belanja_gaji_pokok' => 5866400,
                'perhitungan_suami_istri' => 586640,
                'jumlah_gaji_tunjangan' => 7887943,
                'jumlah_potongan' => 636243,
                'jumlah_ditransfer' => 7251700,
            ]),
        ]);

        $respons = $this->actingAs($user)
            ->post(route('gaji-tunjangan.import.store'), [
                'jenis' => 'gaji', 'bulan' => 8, 'tahun' => 2026, 'file' => $berkas,
            ]);

        $import = GajiImport::firstOrFail();
        $respons->assertRedirect(route('gaji-tunjangan.import.preview', $import));

        // Preview belum menyentuh tabel tujuan sama sekali.
        $this->assertSame(0, GajiInduk::count());
        $this->assertSame(1, $import->baris_valid);
        $this->assertSame(0, $import->baris_invalid);

        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', $import))
            ->assertRedirect(route('gaji-tunjangan.tabel.gaji'));

        $tersimpan = GajiInduk::firstOrFail();
        $this->assertSame('196611041990032003', $tersimpan->nip);
        $this->assertSame(8, $tersimpan->bulan);
        $this->assertSame(2026, $tersimpan->tahun);
        $this->assertEqualsWithDelta(7887943, (float) $tersimpan->jumlah_gaji_tunjangan, 0.01);
        $this->assertSame('committed', $import->fresh()->status);
    }

    public function test_bulan_dan_tahun_diambil_dari_pilihan_bukan_dari_berkas(): void
    {
        // Berkas SIPD tidak punya kolom bulan/tahun. Berkas yang sama bisa
        // dipakai untuk dua periode berbeda hanya dengan mengganti pilihan.
        $user = $this->superadmin();

        foreach ([[7, 2026], [8, 2026]] as [$bulan, $tahun]) {
            $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
                'jenis' => 'gaji', 'bulan' => $bulan, 'tahun' => $tahun,
                'file' => $this->berkas('gaji', [$this->baris('gaji')]),
            ]);

            $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', GajiImport::latest('id')->first()));
        }

        $this->assertSame(2, GajiInduk::count());
        $this->assertSame([7, 8], GajiInduk::orderBy('bulan')->pluck('bulan')->all());
    }

    public function test_konfirmasi_menimpa_data_periode_yang_sama(): void
    {
        $user = $this->superadmin();

        $unggah = function (float $nominal) use ($user) {
            $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
                'jenis' => 'gaji', 'bulan' => 8, 'tahun' => 2026,
                'file' => $this->berkas('gaji', [$this->baris('gaji', ['jumlah_ditransfer' => $nominal])]),
            ]);

            return GajiImport::latest('id')->firstOrFail();
        };

        $pertama = $unggah(7251700);
        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', $pertama));

        $kedua = $unggah(7300000);
        // Peringatan jumlah baris yang akan tertimpa muncul di preview.
        $this->assertSame(1, $kedua->baris_tertimpa);
        $this->actingAs($user)->get(route('gaji-tunjangan.import.preview', $kedua))
            ->assertOk()->assertSee('MENGHAPUS', false);

        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', $kedua));

        // Menimpa, bukan menggandakan.
        $this->assertSame(1, GajiInduk::count());
        $this->assertEqualsWithDelta(7300000, (float) GajiInduk::first()->jumlah_ditransfer, 0.01);
    }

    public function test_nilai_kinerja_wajib_diisi_pada_berkas_tpp(): void
    {
        $user = $this->superadmin();

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'beban', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('beban', [$this->baris('beban', ['nilai_kinerja' => null])]),
        ]);

        $import = GajiImport::firstOrFail();
        $this->assertSame(1, $import->baris_invalid);
        $this->assertStringContainsString('nilai kinerja', implode(' ', $import->baris->first()->pesan));

        // Batch dengan baris bermasalah tidak boleh disimpan.
        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', $import))
            ->assertSessionHasErrors('konfirmasi');
        $this->assertSame(0, Tpp::count());
    }

    public function test_kolom_kosong_selain_nilai_kinerja_dianggap_nol(): void
    {
        $user = $this->superadmin();

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'beban', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('beban', [$this->baris('beban', [
                'koperasi_praja' => null,
                'zakat_praja' => null,
                'tpp_maksimum' => null,
            ])]),
        ]);

        $import = GajiImport::firstOrFail();
        $this->assertSame(0, $import->baris_invalid, implode(' | ', $import->baris->first()->pesan ?? []));

        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', $import));

        $tpp = Tpp::firstOrFail();
        $this->assertEqualsWithDelta(0, (float) $tpp->koperasi_praja, 0.01);
        $this->assertEqualsWithDelta(0, (float) $tpp->tpp_maksimum, 0.01);
    }

    public function test_koperasi_dan_zakat_diabaikan_pada_berkas_kondisi_kerja(): void
    {
        // Berkas TPP Kondisi Kerja memuat kedua kolom itu, tetapi di kantor
        // potongannya memang tidak pernah ada di TOL - mengikuti GAS yang
        // hanya membacanya dari TPP Beban Kerja.
        $user = $this->superadmin();

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'kondisi', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('kondisi', [$this->baris('kondisi', [
                'koperasi_praja' => 150000,
                'zakat_praja' => 25000,
            ])]),
        ]);

        $this->actingAs($user)->post(route('gaji-tunjangan.import.konfirmasi', GajiImport::firstOrFail()));

        $tpp = Tpp::firstOrFail();
        $this->assertSame('kondisi', $tpp->jenis);
        $this->assertNull($tpp->koperasi_praja);
        $this->assertNull($tpp->zakat_praja);
    }

    public function test_susunan_kolom_yang_bergeser_ditolak_dengan_menyebut_kolomnya(): void
    {
        // Pemetaan berbasis posisi hanya aman kalau pergeseran kolom
        // benar-benar tertangkap - bukan diam-diam menyimpan angka ke kolom
        // yang salah.
        $user = $this->superadmin();

        $header = GajiTunjanganKolom::header('gaji');
        [$header[21], $header[22]] = [$header[22], $header[21]];

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'gaji', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('gaji', [$this->baris('gaji')], $header),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, GajiImport::count());
        $this->assertSame(0, GajiInduk::count());
    }

    public function test_jumlah_kolom_yang_salah_ditolak(): void
    {
        $user = $this->superadmin();

        $header = array_slice(GajiTunjanganKolom::header('gaji'), 0, 40);

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'gaji', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('gaji', [array_slice($this->baris('gaji'), 0, 40)], $header),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, GajiInduk::count());
    }

    public function test_nip_ganda_dalam_satu_berkas_ditolak(): void
    {
        $user = $this->superadmin();

        $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
            'jenis' => 'gaji', 'bulan' => 8, 'tahun' => 2026,
            'file' => $this->berkas('gaji', [
                $this->baris('gaji', ['nip' => '196611041990032003']),
                $this->baris('gaji', ['nip' => '196611041990032003']),
            ]),
        ]);

        $import = GajiImport::firstOrFail();
        $this->assertSame(1, $import->baris_invalid);
        $this->assertStringContainsString('ganda', implode(' ', $import->baris->last()->pesan));
    }

    public function test_hanya_role_pengelola_yang_boleh_mengimpor(): void
    {
        $pptk = User::create([
            'username' => 'gt-pptk-'.uniqid(),
            'nama' => 'Uji PPTK',
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_PPTK,
            'aktif' => true,
        ]);

        $this->actingAs($pptk)->get(route('gaji-tunjangan.import.create'))->assertForbidden();
        $this->actingAs($this->superadmin())->get(route('gaji-tunjangan.import.create'))->assertOk();
    }

    /**
     * Berkas Template SIPD sungguhan, bila ada di mesin ini. Berkasnya memuat
     * data gaji asli sehingga sengaja TIDAK ikut git (storage/app diabaikan);
     * karena itu test-nya dilewati kalau berkasnya tidak ada, bukan gagal.
     */
    public function test_berkas_template_sipd_asli_diterima(): void
    {
        $dir = storage_path('app/template gaji tpp tol');

        if (! is_dir($dir)) {
            $this->markTestSkipped('Berkas Template SIPD tidak tersedia di mesin ini.');
        }

        $peta = [
            'gaji' => 'Gaji-Induk',
            'beban' => 'BEBAN KERJA',
            'kondisi' => 'Kondisi Kerja',
        ];

        $user = $this->superadmin();
        $diuji = 0;

        foreach ($peta as $jenis => $penanda) {
            $berkas = collect(scandir($dir))
                ->first(fn (string $f) => str_ends_with($f, '.xlsx') && str_contains($f, $penanda));

            if ($berkas === null) {
                continue;
            }

            $salinan = tempnam(sys_get_temp_dir(), 'sipd').'.xlsx';
            copy($dir.DIRECTORY_SEPARATOR.$berkas, $salinan);

            $this->actingAs($user)->post(route('gaji-tunjangan.import.store'), [
                'jenis' => $jenis, 'bulan' => 8, 'tahun' => 2026,
                'file' => new UploadedFile($salinan, $berkas, null, null, true),
            ])->assertSessionHasNoErrors();

            $import = GajiImport::latest('id')->firstOrFail();

            // Susunan kolomnya diterima apa adanya. Berkas TPP asli belum
            // memuat Nilai Kinerja, jadi barisnya memang ditandai bermasalah -
            // yang diuji di sini susunan kolomnya, bukan kelengkapan isinya.
            $this->assertGreaterThan(100, $import->total_baris);
            $diuji++;
        }

        $this->assertGreaterThan(0, $diuji, 'Tidak satu pun berkas Template SIPD ditemukan.');
    }
}
