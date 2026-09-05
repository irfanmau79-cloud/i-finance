<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class NpdKontribusiDiklatTest extends TestCase
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

    private function buatMasterAnggaran(float $pagu = 100_000_000, string $kodeRekening = '5.1.02.03.01.0001'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Kontribusi Diklat',
            'kegiatan' => 'Kegiatan Uji Kontribusi Diklat',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Diklat',
            'kode_rekening' => $kodeRekening,
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function payloadKontribusi(MasterAnggaran $masterAnggaran): array
    {
        return [
            'mode' => 'kontribusi',
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-24',
            'bulan' => 7,
            'tahun' => 2026,
            'nama_pelatihan' => 'Diklat Penjenjangan Auditor Ahli Muda',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-05',
            'penerima_index' => 0,
            'peserta' => [
                [
                    'nama' => 'Andi Saputra',
                    'pangkat' => 'Penata Muda',
                    'nip' => '198501012010011001',
                    'rekening' => '1112223334',
                    'volume_kontribusi' => 1,
                    'tarif_kontribusi' => 2_500_000,
                    'volume_mooc' => 1,
                    'tarif_mooc' => 500_000,
                ],
                [
                    'nama' => 'Rina Marlina',
                    'pangkat' => 'Penata',
                    'nip' => '198602022011012002',
                    'rekening' => '5556667778',
                    'volume_kontribusi' => 1,
                    'tarif_kontribusi' => 2_500_000,
                    'volume_mooc' => 0,
                    'tarif_mooc' => 0,
                ],
            ],
        ];
    }

    private function payloadPerjalanan(MasterAnggaran $masterAnggaran, ?int $referensiId = null): array
    {
        return [
            'mode' => 'perjalanan',
            'npd_referensi_id' => $referensiId,
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-08-06',
            'bulan' => 8,
            'tahun' => 2026,
            'nama_pelatihan' => 'Diklat Penjenjangan Auditor Ahli Muda',
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-05',
            'penerima_index' => 0,
            'peserta' => [
                [
                    'nama' => 'Andi Saputra',
                    'pangkat' => 'Penata Muda',
                    'nip' => '198501012010011001',
                    'rekening' => '1112223334',
                    'hari_uh' => 5,
                    'tarif_uh' => 400_000,
                    'volume_akomodasi' => 4,
                    'tarif_akomodasi' => 600_000,
                    'hari_saku' => 5,
                    'tarif_saku' => 100_000,
                    'transport' => 350_000,
                ],
            ],
            // Mode Perjalanan Dinas wajib menyebut Tujuan Transfer, dan
            // jumlahnya harus menghabiskan Total Bruto.
            'penerima_transfer' => [
                ['nama' => 'Andi Saputra', 'rekening' => '1112223334', 'nominal' => 5_250_000],
            ],
        ];
    }

    /**
     * Formula kontribusi harus persis GAS CodeKontribusiDiklat.gs:
     * - jumlah_kontribusi = volume_kontribusi * tarif_kontribusi
     * - jumlah_mooc = volume_mooc * tarif_mooc
     * - subtotal = jumlah_kontribusi + jumlah_mooc
     * - nominal NPD = subtotal kontribusi saja.
     */
    public function test_mode_kontribusi_menyimpan_peserta_dan_formula_yang_benar(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-pptk-kontribusi');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));

        $npd = Npd::with('peserta')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));

        $this->assertSame('kd', $npd->jenis);
        $this->assertSame('kontribusi', $npd->mode_kd);
        $this->assertNull($npd->npd_referensi_id);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame('Diklat Penjenjangan Auditor Ahli Muda', $npd->detail_json['nama_pelatihan']);

        // Andi: 1*2.500.000 + 1*500.000 = 3.000.000. Rina: 1*2.500.000 + 0 = 2.500.000.
        // Nominal = TOTAL SUBTOTAL KONTRIBUSI = 5.500.000.
        $this->assertEquals(5_500_000.0, (float) $npd->nominal);
        $this->assertCount(2, $npd->peserta);

        $andi = $npd->peserta->firstWhere('nama', 'Andi Saputra');
        $this->assertEquals(2_500_000.0, $andi->jumlah_kontribusi);
        $this->assertEquals(500_000.0, $andi->jumlah_mooc);
        $this->assertEquals(3_000_000.0, $andi->sub_kontribusi);
        $this->assertEquals(0.0, $andi->sub_perjalanan);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npd));
        $showResponse->assertOk();
        $showResponse->assertSee('Andi Saputra');
        $showResponse->assertSee('Rina Marlina');
    }

    /**
     * Formula perjalanan: jumlah_harian = hari_uh*tarif_uh, jumlah_akomodasi =
     * volume_akomodasi*tarif_akomodasi, jumlah_saku = hari_saku*tarif_saku,
     * transport at-cost. Nominal = subtotal perjalanan saja.
     */
    public function test_mode_perjalanan_dengan_referensi_menyalin_snapshot_peserta_dan_formula_benar(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-pptk-perjalanan');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $referensi = Npd::where('mode_kd', 'kontribusi')->firstOrFail();

        $masterAnggaranPd = $this->buatMasterAnggaran(100_000_000, '5.1.02.04.01.0002');
        $this->limpahkanSubKegiatan($pptk, $masterAnggaranPd);
        $response = $this->actingAs($pptk)->post(
            route('npd.kd.store'),
            $this->payloadPerjalanan($masterAnggaranPd, $referensi->id)
        );

        $npdPerjalanan = Npd::with('peserta')->where('mode_kd', 'perjalanan')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npdPerjalanan));

        $this->assertSame('kd', $npdPerjalanan->jenis);
        $this->assertSame('perjalanan', $npdPerjalanan->mode_kd);
        $this->assertSame($referensi->id, $npdPerjalanan->npd_referensi_id);

        // 5*400.000 + 4*600.000 + 5*100.000 + 350.000 = 2.000.000+2.400.000+500.000+350.000 = 5.250.000.
        $this->assertEquals(5_250_000.0, (float) $npdPerjalanan->nominal);

        $andi = $npdPerjalanan->peserta->firstOrFail();
        $this->assertEquals(2_000_000.0, $andi->jumlah_harian);
        $this->assertEquals(2_400_000.0, $andi->jumlah_akomodasi);
        $this->assertEquals(500_000.0, $andi->jumlah_saku);
        $this->assertEquals(5_250_000.0, $andi->sub_perjalanan);
        $this->assertEquals(0.0, $andi->sub_kontribusi);

        // Referensi tetap independen — perubahan pada NPD perjalanan tidak memengaruhi nominal referensi.
        $this->assertEquals(5_500_000.0, (float) $referensi->fresh()->nominal);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npdPerjalanan));
        $showResponse->assertOk();
        $showResponse->assertSee($referensi->nomor_lengkap ?? '#'.$referensi->id);
    }

    public function test_referensi_ditolak_jika_bukan_npd_kontribusi_mode_kontribusi(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-ref-invalid');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Buat NPD BJ biasa sebagai referensi salah jenis.
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

        $payload = $this->payloadPerjalanan($masterAnggaran, $npdBj->id);

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $payload);

        $response->assertSessionHasErrors(['npd_referensi_id']);
        $this->assertSame(0, Npd::where('jenis', 'kd')->count());
    }

    public function test_referensi_ditolak_jika_npd_kontribusi_sudah_dibatalkan(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-ref-batal');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $referensi = Npd::where('mode_kd', 'kontribusi')->firstOrFail();
        $referensi->update(['status' => 'Dibatalkan']);

        $payload = $this->payloadPerjalanan($masterAnggaran, $referensi->id);

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $payload);

        $response->assertSessionHasErrors(['npd_referensi_id']);
    }

    public function test_nominal_melebihi_sisa_anggaran_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-tolak');
        $masterAnggaran = $this->buatMasterAnggaran(1_000_000);
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

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

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));

        $response->assertSessionHasErrors(['peserta']);
        $this->assertSame(0, Npd::where('jenis', 'kd')->count());
    }

    public function test_superadmin_dan_pptk_boleh_akses_tapi_role_lain_ditolak(): void
    {
        $verifikator = $this->buatUser('verifikator', 'kd-verif');
        $superadmin = $this->buatUser('superadmin', 'kd-superadmin');
        $pptk = $this->buatUser('pptk', 'kd-akses-pptk');

        $this->actingAs($pptk)->get(route('npd.kd.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.kd.create'))->assertOk();
        $this->actingAs($verifikator)->get(route('npd.kd.create'))->assertForbidden();
        $this->actingAs($verifikator)->post(route('npd.kd.store'), [])->assertForbidden();
    }

    public function test_validasi_gagal_tanpa_field_wajib(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-validasi');

        $response = $this->actingAs($pptk)->post(route('npd.kd.store'), [
            'tanggal_npd' => '2026-07-24',
            'bulan' => 7,
            'tahun' => 2026,
        ]);

        $response->assertSessionHasErrors(['mode', 'master_anggaran_id', 'jenis_panjar', 'nama_pelatihan', 'tanggal_mulai', 'tanggal_selesai', 'peserta']);
        $this->assertSame(0, Npd::count());
    }

    public function test_ketiga_pdf_kontribusi_dan_perjalanan_berhasil_dirender(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-pdf');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $npdKontribusi = Npd::where('mode_kd', 'kontribusi')->firstOrFail();

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar-kd'] as $route) {
            $resp = $this->actingAs($pptk)->get(route($route, $npdKontribusi));
            $resp->assertOk();
            $resp->assertHeader('Content-Type', 'application/pdf');
        }

        $masterAnggaranPd = $this->buatMasterAnggaran(100_000_000, '5.1.02.04.01.0003');
        $this->limpahkanSubKegiatan($pptk, $masterAnggaranPd);
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaranPd, $npdKontribusi->id));
        $npdPerjalanan = Npd::where('mode_kd', 'perjalanan')->firstOrFail();

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar-kd'] as $route) {
            $resp = $this->actingAs($pptk)->get(route($route, $npdPerjalanan));
            $resp->assertOk();
            $resp->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_cetak_daftar_kd_ditolak_untuk_jenis_lain(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-jenis-lain');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

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

        $this->actingAs($pptk)->get(route('npd.cetak-daftar-kd', $npdBj))->assertNotFound();
    }

    // ---------------- Tujuan Transfer (mode Perjalanan Dinas) ----------------

    public function test_tujuan_transfer_boleh_dibagi_ke_beberapa_penerima(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-bagi');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['penerima_transfer'] = [
            ['nama' => 'Andi Saputra', 'rekening' => '1112223334', 'nominal' => 3_000_000],
            ['nama' => 'Bendahara Tim', 'rekening' => '9998887776', 'nominal' => 2_250_000],
        ];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

        $npd = Npd::where('mode_kd', 'perjalanan')->sole();
        $penerima = $npd->detail_json['penerima_transfer'];

        $this->assertCount(2, $penerima);
        $this->assertSame('Bendahara Tim', $penerima[1]['nama']);
        // assertEquals, bukan assertSame: nilainya kembali dari kolom JSON,
        // dan bilangan bulat di sana terbaca sebagai int.
        $this->assertEquals(2_250_000.0, $penerima[1]['nominal']);
        // Nominal NPD tetap dari subtotal peserta, bukan dari daftar penerima.
        $this->assertEquals(5_250_000.0, (float) $npd->nominal);
    }

    public function test_total_tujuan_transfer_harus_sama_dengan_total_bruto(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-selisih');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['penerima_transfer'] = [['nama' => 'Andi Saputra', 'nominal' => 4_000_000]];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)
            ->assertSessionHasErrors('penerima_transfer');

        $this->assertSame(0, Npd::count());
    }

    public function test_penerima_tanpa_nama_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-tanpa-nama');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['penerima_transfer'] = [['nama' => '', 'nominal' => 5_250_000]];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)
            ->assertSessionHasErrors('penerima_transfer.0.nama');
    }

    public function test_mode_kontribusi_tidak_terpengaruh_tujuan_transfer(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-kontribusi');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Baris sisa dari mode Perjalanan Dinas ikut terkirim saat pengguna
        // berpindah mode; mode Kontribusi harus mengabaikannya, bukan gagal.
        $payload = $this->payloadKontribusi($masterAnggaran);
        $payload['penerima_transfer'] = [['nama' => '', 'nominal' => 0]];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

        $this->assertNull(Npd::sole()->detail_json['penerima_transfer']);
    }

    public function test_lampiran_multi_penerima_membebankan_pajak_di_baris_pertama(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-lampiran');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['ppn'] = 100_000;
        $payload['pph_jenis'] = 'PPh Pasal 21';
        $payload['pph_nilai'] = 50_000;
        $payload['biaya_lain'] = 10_000;
        $payload['penerima_transfer'] = [
            ['nama' => 'Andi Saputra', 'nominal' => 3_000_000],
            ['nama' => 'Bendahara Tim', 'nominal' => 2_250_000],
        ];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload);
        $npd = Npd::with('peserta')->sole();

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $lampiran = $metode->invoke(app(NpdController::class), $npd);

        $this->assertCount(2, $lampiran['rows']);

        // Potongan adalah beban tingkat dokumen: seluruhnya di baris pertama,
        // nol di baris berikutnya - kalau disebar, jumlahnya berlipat.
        $this->assertSame(100_000.0, $lampiran['rows'][0]['ppn']);
        $this->assertSame(0.0, $lampiran['rows'][1]['ppn']);
        $this->assertSame(50_000.0, $lampiran['rows'][0]['pph']['PPh Pasal 21']);
        $this->assertSame(0.0, $lampiran['rows'][1]['pph']['PPh Pasal 21']);
        $this->assertSame(10_000.0, $lampiran['rows'][0]['biaya']);

        $this->assertSame(2_840_000.0, $lampiran['rows'][0]['transfer']);
        $this->assertSame(2_250_000.0, $lampiran['rows'][1]['transfer']);
        $this->assertSame(5_250_000.0, $lampiran['totals']['bruto']);
        $this->assertSame(5_090_000.0, $lampiran['totals']['transfer']);

        // Keterangan otomatis menyebut seluruh penerimanya.
        $this->assertStringContainsString('an. Andi Saputra, Bendahara Tim', $lampiran['rows'][0]['keterangan']);
    }
}
