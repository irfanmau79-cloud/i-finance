<?php

namespace Tests\Feature;

use App\Helpers\Terbilang;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdBjTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Logika ini harus persis sistem lama (GAS Code.gs buatNPD / _rowsLampiran):
     * - netto per penerima = bruto - ppn - total semua pph - biaya_ku_rtgs
     * - nominal NPD = TOTAL BRUTO seluruh penerima (bukan total netto)
     * - PPh boleh multi-jenis per penerima.
     */
    public function test_pptk_dapat_membuat_npd_dengan_dua_penerima_dan_pph_multi_jenis(): void
    {
        $pptk = User::create([
            'username' => 'test-pptk',
            'nama' => 'Test PPTK',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0001',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        $pegawai = Pegawai::create([
            'nama' => 'Budi Santoso',
            'nip' => '198001012000031001',
            'jabatan' => 'Staf',
            'bidang' => 'Sekretariat',
            'aktif' => true,
        ]);

        $vendor = Vendor::create([
            'nama' => 'CV Maju Jaya',
            'aktif' => true,
        ]);

        $payload = [
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-18',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => [
                [
                    'pegawai_id' => $pegawai->id,
                    'nama' => 'Budi Santoso',
                    'rekening' => '1234567890',
                    'bruto' => 1_000_000,
                    'ppn' => 50_000,
                    'biaya_ku_rtgs' => 15_000,
                    'pph_list' => [
                        ['jenis' => 'PPh Pasal 21', 'nilai' => 25_000],
                        ['jenis' => 'PPh Pasal 23', 'nilai' => 5_000],
                    ],
                    'keterangan' => 'Honor kegiatan',
                ],
                [
                    'vendor_id' => $vendor->id,
                    'nama' => 'CV Maju Jaya',
                    'rekening' => '0987654321',
                    'bruto' => 2_500_000,
                    'ppn' => 0,
                    'biaya_ku_rtgs' => 20_000,
                    'pph_list' => [
                        ['jenis' => 'PPh Pasal 22', 'nilai' => 50_000],
                        ['jenis' => 'PPh Pasal 4(2)', 'nilai' => 12_500],
                    ],
                    'keterangan' => 'Pengadaan barang',
                ],
            ],
        ];

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), $payload);

        $npd = Npd::firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));

        $this->assertSame('bj', $npd->jenis);
        $this->assertSame('1', $npd->keu);
        $this->assertSame('Tanpa Panjar', $npd->jenis_panjar);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame($pptk->id, $npd->dibuat_oleh);

        // Nominal = TOTAL BRUTO (1.000.000 + 2.500.000), bukan total netto.
        $this->assertEquals(3_500_000.0, (float) $npd->nominal);
        $this->assertSame(Terbilang::rupiah(3_500_000), $npd->terbilang);

        // Total bruto lampiran harus sama dengan nominal (toleransi 0, di sini pas).
        $totalBruto = $npd->penerima()->sum('bruto');
        $this->assertEqualsWithDelta((float) $npd->nominal, (float) $totalBruto, 1.0);

        $this->assertSame(2, $npd->penerima()->count());

        $penerimaPegawai = $npd->penerima()->with('pphList')->where('pegawai_id', $pegawai->id)->firstOrFail();
        $this->assertSame('Budi Santoso', $penerimaPegawai->nama);
        $this->assertEquals(1_000_000.0, (float) $penerimaPegawai->bruto);
        $this->assertEquals(50_000.0, (float) $penerimaPegawai->ppn);
        $this->assertEquals(15_000.0, (float) $penerimaPegawai->biaya_ku_rtgs);
        $this->assertSame(2, $penerimaPegawai->pphList->count());
        $this->assertEquals(30_000.0, $penerimaPegawai->total_pph);
        // netto = 1.000.000 - 50.000 - 30.000 - 15.000 = 905.000
        $this->assertEquals(905_000.0, $penerimaPegawai->netto);

        $penerimaVendor = $npd->penerima()->with('pphList')->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame('CV Maju Jaya', $penerimaVendor->nama);
        $this->assertEquals(2_500_000.0, (float) $penerimaVendor->bruto);
        $this->assertEquals(0.0, (float) $penerimaVendor->ppn);
        $this->assertSame(2, $penerimaVendor->pphList->count());
        $this->assertEquals(62_500.0, $penerimaVendor->total_pph);
        // netto = 2.500.000 - 0 - 62.500 - 20.000 = 2.417.500
        $this->assertEquals(2_417_500.0, $penerimaVendor->netto);

        $indexResponse = $this->actingAs($pptk)->get(route('npd.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($masterAnggaran->kode_rekening);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npd));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Budi Santoso');
        $showResponse->assertSee('CV Maju Jaya');
        $showResponse->assertSee('PPh Pasal 21');
        $showResponse->assertSee('PPh Pasal 4(2)');
    }

    public function test_form_create_menampilkan_sisa_anggaran_dan_rekening_pegawai_vendor(): void
    {
        $pptk = User::create([
            'username' => 'test-pptk-sisa',
            'nama' => 'Test PPTK Sisa',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0002',
            'uraian_rekening' => 'Belanja Makanan dan Minuman Rapat',
            'tagging_id' => null,
            'pagu' => 1_000_000,
            'aktif' => true,
        ]);

        // NPD lama berstatus Draft tetap mengurangi sisa (dananya sudah dipesan).
        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 300_000,
            'terbilang' => 'tiga ratus ribu rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);

        $pegawai = Pegawai::create([
            'nama' => 'Ani Wijaya',
            'nip' => '198501012000032002',
            'jabatan' => 'Staf',
            'bidang' => 'Sekretariat',
            'rekening' => '112233445566',
            'aktif' => true,
        ]);

        $vendor = Vendor::create([
            'nama' => 'CV Sumber Rejeki',
            'rekening' => '998877665544',
            'aktif' => true,
        ]);

        $this->assertEquals(700_000.0, $masterAnggaran->sisaTersedia());

        $response = $this->actingAs($pptk)->get(route('npd.bj.create'));

        $response->assertStatus(200);
        $response->assertSee('Sisa Anggaran');

        // Sisa (700.000), bukan pagu (1.000.000), yang dikirim ke JS untuk sumber dana ini.
        $response->assertSee('"id":'.$masterAnggaran->id.',"program"', false);
        $response->assertSee('"sisa":700000', false);

        // Kode Rekening + uraiannya (dari kolom D yang sudah dipisah saat import) ikut dikirim ke JS.
        $response->assertSee('"kode_rekening":"5.1.02.01.01.0002","uraian_rekening":"Belanja Makanan dan Minuman Rapat"', false);

        // Rekening pegawai & vendor ikut dikirim untuk auto-isi No. Rekening.
        $response->assertSee('"nama":"Ani Wijaya"', false);
        $response->assertSee('"rekening":"112233445566"', false);
        $response->assertSee('"nama":"CV Sumber Rejeki"', false);
        $response->assertSee('"rekening":"998877665544"', false);
    }

    public function test_nominal_melebihi_sisa_anggaran_ditolak(): void
    {
        $pptk = User::create([
            'username' => 'test-pptk-tolak',
            'nama' => 'Test PPTK Tolak',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0003',
            'tagging_id' => null,
            'pagu' => 1_000_000,
            'aktif' => true,
        ]);

        // Sudah terpakai 800.000, sisa hanya 200.000.
        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 800_000,
            'terbilang' => 'delapan ratus ribu rupiah',
            'status' => 'Selesai',
        ]);

        $payload = [
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-18',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => [
                [
                    'nama' => 'Penerima Uji',
                    'bruto' => 500_000,
                    'keterangan' => 'Melebihi sisa anggaran',
                ],
            ],
        ];

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), $payload);

        $response->assertSessionHasErrors(['penerima']);
        $this->assertSame(0, Npd::where('master_anggaran_id', $masterAnggaran->id)->where('nominal', 500_000)->count());
    }

    public function test_superadmin_dan_pptk_boleh_akses_tapi_role_lain_ditolak(): void
    {
        $verifikator = User::create([
            'username' => 'test-verif',
            'nama' => 'Test Verifikator',
            'role' => 'verifikator',
            'password' => 'rahasia',
        ]);

        $this->actingAs($verifikator)->get(route('npd.bj.create'))->assertForbidden();
    }

    public function test_validasi_gagal_tanpa_master_anggaran_jenis_panjar_dan_tanpa_penerima(): void
    {
        $pptk = User::create([
            'username' => 'test-pptk-2',
            'nama' => 'Test PPTK 2',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), [
            'tanggal_npd' => '2026-07-18',
            'bulan' => 7,
            'tahun' => 2026,
        ]);

        $response->assertSessionHasErrors(['master_anggaran_id', 'jenis_panjar', 'penerima']);
        $this->assertSame(0, Npd::count());
    }

    public function test_keterangan_penerima_wajib_diisi(): void
    {
        $pptk = User::create([
            'username' => 'test-pptk-3',
            'nama' => 'Test PPTK 3',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0001',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        $payload = [
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-18',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => [
                [
                    'nama' => 'Budi Santoso',
                    'bruto' => 1_000_000,
                    'keterangan' => '',
                ],
            ],
        ];

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), $payload);

        $response->assertSessionHasErrors(['penerima.0.keterangan']);
        $this->assertSame(0, Npd::count());
    }
}
