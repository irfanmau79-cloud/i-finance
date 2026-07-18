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

    public function test_pptk_dapat_membuat_npd_barang_jasa_dengan_tiga_penerima(): void
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
            'tanggal_npd' => '2026-07-18',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => [
                [
                    'pegawai_id' => $pegawai->id,
                    'nama' => 'Budi Santoso',
                    'rekening' => '1234567890',
                    'bruto' => 1_000_000,
                    'pph' => 25_000,
                    'keterangan' => 'Honor kegiatan',
                ],
                [
                    'vendor_id' => $vendor->id,
                    'nama' => 'CV Maju Jaya',
                    'rekening' => '0987654321',
                    'bruto' => 2_500_000,
                    'pph' => 0,
                ],
                [
                    'nama' => 'Siti Aminah (manual, tidak ada di master)',
                    'rekening' => '5551122',
                    'bruto' => 750_000,
                    'pph' => 10_000,
                ],
            ],
        ];

        $response = $this->actingAs($pptk)->post(route('npd.bj.store'), $payload);

        $npd = Npd::firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));

        $this->assertSame('bj', $npd->jenis);
        $this->assertSame('1', $npd->keu);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame($pptk->id, $npd->dibuat_oleh);

        // 1_000_000 - 25_000 + 2_500_000 - 0 + 750_000 - 10_000 = 4_215_000
        $this->assertEquals(4_215_000.0, (float) $npd->nominal);
        $this->assertSame(Terbilang::rupiah(4_215_000), $npd->terbilang);

        $this->assertSame(3, $npd->penerima()->count());

        $penerimaPegawai = $npd->penerima()->where('pegawai_id', $pegawai->id)->firstOrFail();
        $this->assertSame('Budi Santoso', $penerimaPegawai->nama);
        $this->assertEquals(975_000.0, (float) $penerimaPegawai->biaya);

        $penerimaVendor = $npd->penerima()->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame('CV Maju Jaya', $penerimaVendor->nama);
        $this->assertEquals(2_500_000.0, (float) $penerimaVendor->biaya);

        $penerimaManual = $npd->penerima()->whereNull('pegawai_id')->whereNull('vendor_id')->firstOrFail();
        $this->assertSame('Siti Aminah (manual, tidak ada di master)', $penerimaManual->nama);
        $this->assertEquals(740_000.0, (float) $penerimaManual->biaya);

        $indexResponse = $this->actingAs($pptk)->get(route('npd.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($masterAnggaran->kode_rekening);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npd));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Budi Santoso');
        $showResponse->assertSee('CV Maju Jaya');
        $showResponse->assertSee('Siti Aminah (manual, tidak ada di master)');
    }

    public function test_bendahara_dan_pptk_boleh_akses_tapi_role_lain_ditolak(): void
    {
        $verifikator = User::create([
            'username' => 'test-verif',
            'nama' => 'Test Verifikator',
            'role' => 'verifikator',
            'password' => 'rahasia',
        ]);

        $this->actingAs($verifikator)->get(route('npd.bj.create'))->assertForbidden();
    }

    public function test_validasi_gagal_tanpa_master_anggaran_dan_tanpa_penerima(): void
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

        $response->assertSessionHasErrors(['master_anggaran_id', 'penerima']);
        $this->assertSame(0, Npd::count());
    }
}
