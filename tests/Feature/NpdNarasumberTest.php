<?php

namespace Tests\Feature;

use App\Helpers\Terbilang;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdNarasumberTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function buatMasterAnggaran(float $pagu = 100_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Narasumber',
            'kegiatan' => 'Kegiatan Uji Narasumber',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Narasumber',
            'kode_rekening' => '5.1.02.02.01.0001',
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function payload(MasterAnggaran $masterAnggaran): array
    {
        return [
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-23',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_kegiatan' => 'Rapat Koordinasi Pengawasan Internal',
            'tanggal_mulai' => '2026-07-20',
            'tanggal_selesai' => '2026-07-21',
            'narasumber' => [
                [
                    'nama' => 'Dr. Ahmad Fauzi',
                    'jabatan' => 'Inspektur Pembantu',
                    'rekening' => '1111111111',
                    'jumlah_jp' => 4,
                    'tarif_jp' => 500_000,
                    'transport' => 250_000,
                    'pph21' => 100_000,
                    'uraian' => '',
                ],
                [
                    'nama' => 'Siti Rahayu',
                    'jabatan' => 'Auditor Madya',
                    'rekening' => '2222222222',
                    'jumlah_jp' => 2,
                    'tarif_jp' => 300_000,
                    'transport' => 0,
                    'pph21' => 30_000,
                    'uraian' => 'Transfer khusus narasumber kedua',
                ],
            ],
        ];
    }

    /**
     * Formula harus persis GAS CodeNarasumber.gs:
     * - honor = jumlah_jp * tarif_jp
     * - bruto = honor + transport
     * - netto = bruto - pph21
     * - nominal NPD = TOTAL BRUTO seluruh narasumber (bukan netto)
     */
    public function test_pptk_dapat_membuat_npd_dengan_multi_narasumber_tarif_berbeda_transport_dan_pph21(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-pptk');
        $masterAnggaran = $this->buatMasterAnggaran();

        $response = $this->actingAs($pptk)->post(route('npd.ns.store'), $this->payload($masterAnggaran));

        $npd = Npd::with('narasumber')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));

        $this->assertSame('ns', $npd->jenis);
        $this->assertSame('1', $npd->keu);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame($pptk->id, $npd->dibuat_oleh);
        $this->assertSame('Rapat Koordinasi Pengawasan Internal', $npd->detail_json['uraian_kegiatan']);

        // Narasumber 1: honor 4*500.000=2.000.000, bruto 2.250.000, netto 2.150.000.
        // Narasumber 2: honor 2*300.000=600.000, bruto 600.000, netto 570.000.
        // Nominal = TOTAL BRUTO = 2.250.000 + 600.000 = 2.850.000 (bukan total netto).
        $this->assertEquals(2_850_000.0, (float) $npd->nominal);
        $this->assertSame(Terbilang::rupiah(2_850_000), $npd->terbilang);

        $this->assertCount(2, $npd->narasumber);

        $n1 = $npd->narasumber->firstWhere('nama', 'Dr. Ahmad Fauzi');
        $this->assertEquals(2_000_000.0, $n1->honor);
        $this->assertEquals(2_250_000.0, $n1->bruto);
        $this->assertEquals(2_150_000.0, $n1->netto);

        $n2 = $npd->narasumber->firstWhere('nama', 'Siti Rahayu');
        $this->assertEquals(600_000.0, $n2->honor);
        $this->assertEquals(600_000.0, $n2->bruto);
        $this->assertEquals(570_000.0, $n2->netto);
        $this->assertSame('Transfer khusus narasumber kedua', $n2->uraian);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npd));
        $showResponse->assertOk();
        $showResponse->assertSee('Dr. Ahmad Fauzi');
        $showResponse->assertSee('Siti Rahayu');
    }

    public function test_narasumber_pegawai_master_mengambil_nama_dari_master_bukan_input_manual(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-master');
        $masterAnggaran = $this->buatMasterAnggaran();

        $pegawai = Pegawai::create([
            'nama' => 'Bambang Wijaya',
            'nip' => '198001012000031099',
            'jabatan' => 'Kepala Bidang',
            'bidang' => 'Pengawasan',
            'rekening' => '999888777',
            'aktif' => true,
        ]);

        $payload = $this->payload($masterAnggaran);
        $payload['narasumber'][0]['pegawai_id'] = $pegawai->id;
        $payload['narasumber'][0]['nama'] = 'nama yang akan diabaikan';

        $this->actingAs($pptk)->post(route('npd.ns.store'), $payload);

        $npd = Npd::with('narasumber')->firstOrFail();
        $n1 = $npd->narasumber->firstWhere('pegawai_id', $pegawai->id);
        $this->assertSame('Bambang Wijaya', $n1->nama);
    }

    public function test_nominal_melebihi_sisa_anggaran_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-tolak');
        $masterAnggaran = $this->buatMasterAnggaran(1_000_000);

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

        $payload = $this->payload($masterAnggaran);

        $response = $this->actingAs($pptk)->post(route('npd.ns.store'), $payload);

        $response->assertSessionHasErrors(['narasumber']);
        $this->assertSame(0, Npd::where('jenis', 'ns')->count());
    }

    public function test_superadmin_dan_pptk_boleh_akses_tapi_role_lain_ditolak(): void
    {
        $verifikator = $this->buatUser('verifikator', 'ns-verif');
        $superadmin = $this->buatUser('superadmin', 'ns-superadmin');
        $pptk = $this->buatUser('pptk', 'ns-akses-pptk');

        $this->actingAs($pptk)->get(route('npd.ns.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.ns.create'))->assertOk();
        $this->actingAs($verifikator)->get(route('npd.ns.create'))->assertForbidden();
        $this->actingAs($verifikator)->post(route('npd.ns.store'), [])->assertForbidden();
    }

    public function test_validasi_gagal_tanpa_field_wajib(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-validasi');

        $response = $this->actingAs($pptk)->post(route('npd.ns.store'), [
            'tanggal_npd' => '2026-07-23',
            'bulan' => 7,
            'tahun' => 2026,
        ]);

        $response->assertSessionHasErrors(['master_anggaran_id', 'jenis_panjar', 'uraian_kegiatan', 'narasumber']);
        $this->assertSame(0, Npd::count());
    }

    public function test_ketiga_pdf_narasumber_berhasil_dirender(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-pdf');
        $masterAnggaran = $this->buatMasterAnggaran();

        $this->actingAs($pptk)->post(route('npd.ns.store'), $this->payload($masterAnggaran));
        $npd = Npd::firstOrFail();

        $cetakNpd = $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd));
        $cetakNpd->assertOk();
        $cetakNpd->assertHeader('Content-Type', 'application/pdf');

        $cetakLampiran = $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd));
        $cetakLampiran->assertOk();
        $cetakLampiran->assertHeader('Content-Type', 'application/pdf');

        $cetakDaftar = $this->actingAs($pptk)->get(route('npd.cetak-daftar-nara', $npd));
        $cetakDaftar->assertOk();
        $cetakDaftar->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cetak_daftar_nara_ditolak_untuk_jenis_lain(): void
    {
        $pptk = $this->buatUser('pptk', 'ns-jenis-lain');
        $masterAnggaran = $this->buatMasterAnggaran();

        $npdBj = Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 100_000,
            'terbilang' => 'seratus ribu rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);

        $this->actingAs($pptk)->get(route('npd.cetak-daftar-nara', $npdBj))->assertNotFound();
    }
}
