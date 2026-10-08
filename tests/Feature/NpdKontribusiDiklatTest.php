<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
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

    /** Surat Perintah yang layak jadi Referensi SP, dengan dua anggota. */
    private function buatSp(array $override = []): SuratPerintah
    {
        $sp = SuratPerintah::create(array_replace([
            'nomor_sp' => '120/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-28',
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kota Bogor',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Koordinator',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '1 - 5 Agustus 2026',
            'keterangan' => 'Mengikuti Diklat Penjenjangan Auditor Ahli Muda',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'dipantau' => true,
            'sumber_npd' => true,
        ], $override));

        $sp->anggota()->create(['nama' => 'Andi Saputra', 'nip' => '198501012010011001', 'golongan' => 'III/a', 'pangkat' => 'Penata Muda', 'jabatan' => 'Auditor', 'rekening' => '1112223334', 'manual' => true, 'urutan' => 1]);
        $sp->anggota()->create(['nama' => 'Rina Marlina', 'nip' => '198602022011012002', 'golongan' => 'III/c', 'pangkat' => 'Penata', 'jabatan' => 'Auditor', 'rekening' => '5556667778', 'manual' => true, 'urutan' => 2]);

        return $sp;
    }

    private function payloadPerjalanan(MasterAnggaran $masterAnggaran, ?int $suratPerintahId = null): array
    {
        return [
            'mode' => 'perjalanan',
            'surat_perintah_id' => $suratPerintahId,
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
    public function test_mode_perjalanan_dengan_referensi_sp_menaut_surat_perintah_dan_formula_benar(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-pptk-perjalanan');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // NPD Kontribusinya tetap ada, tetapi BUKAN lagi yang dirujuk.
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $referensi = Npd::where('mode_kd', 'kontribusi')->firstOrFail();

        $sp = $this->buatSp();

        $masterAnggaranPd = $this->buatMasterAnggaran(100_000_000, '5.1.02.04.01.0002');
        $this->limpahkanSubKegiatan($pptk, $masterAnggaranPd);
        $response = $this->actingAs($pptk)->post(
            route('npd.kd.store'),
            $this->payloadPerjalanan($masterAnggaranPd, $sp->id)
        );

        $npdPerjalanan = Npd::with('peserta')->where('mode_kd', 'perjalanan')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npdPerjalanan));

        $this->assertSame('kd', $npdPerjalanan->jenis);
        $this->assertSame('perjalanan', $npdPerjalanan->mode_kd);
        $this->assertSame($sp->id, $npdPerjalanan->surat_perintah_id);
        $this->assertNull($npdPerjalanan->npd_referensi_id);

        // SP mengikuti status NPD yang menautnya, dan tidak lagi ditawarkan
        // sebagai sumber NPD lain.
        $this->assertSame('Draft NPD - PPTK', $sp->fresh()->status);
        $this->assertSame(0, SuratPerintah::sumberNpdPerjalanan()->count());

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
        $showResponse->assertSee('Referensi SP');
        $showResponse->assertSee('120/PW.02.01/Sekre');
        $showResponse->assertDontSee('Referensi NPD Kontribusi');
    }

    public function test_formulir_menawarkan_referensi_sp_beserta_anggotanya_bukan_npd_kontribusi(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-form-sp');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $layak = $this->buatSp();
        $terpakai = $this->buatSp(['nomor_sp' => '121/PW.02.01/Sekre', 'status' => 'Selesai']);
        $reimburse = $this->buatSp(['nomor_sp' => '122/PW.02.01/Sekre (Reimburse)', 'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE]);

        $isi = $this->actingAs($pptk)->get(route('npd.kd.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Referensi SP (opsional)', $isi);
        $this->assertStringContainsString('name="surat_perintah_id"', $isi);
        $this->assertStringNotContainsString('Referensi NPD Kontribusi', $isi);
        $this->assertStringNotContainsString('npd_referensi_id', $isi);

        // Dicocokkan lewat nomor SP-nya: id saja bisa kebetulan sama dengan
        // nilai pilihan lain di formulir (mis. bulan).
        $this->assertStringContainsString($layak->nomor_sp.' — Sekretariat (Kota Bogor)', $isi);
        $this->assertStringNotContainsString($terpakai->nomor_sp, $isi);
        $this->assertStringNotContainsString($reimburse->nomor_sp, $isi);

        // Anggota SP disediakan untuk disalin menjadi peserta.
        $this->assertStringContainsString('"nama":"Rina Marlina","pangkat":"Penata (III\/c)","nip":"198602022011012002","rekening":"5556667778"', $isi);
    }

    public function test_referensi_sp_yang_tidak_layak_jadi_sumber_npd_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-ref-invalid');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $tidakLayak = [
            'sudah dipakai NPD lain' => $this->buatSp(['nomor_sp' => '130/A', 'status' => 'Draft NPD - BPP']),
            'penanda Sumber NPD mati' => $this->buatSp(['nomor_sp' => '130/B', 'sumber_npd' => false]),
            'Reimburse Transportasi' => $this->buatSp(['nomor_sp' => '130/C (Reimburse)', 'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE]),
        ];

        foreach ($tidakLayak as $sebab => $sp) {
            $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaran, $sp->id))
                ->assertSessionHasErrors(['surat_perintah_id'], null, 'default');
            $this->assertSame(0, Npd::where('jenis', 'kd')->count(), "SP {$sebab} seharusnya ditolak.");
        }

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaran, 999_999))
            ->assertSessionHasErrors(['surat_perintah_id']);
    }

    public function test_referensi_sp_opsional_dan_diabaikan_pada_mode_kontribusi(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-ref-opsional');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);
        $sp = $this->buatSp();

        // Mode Perjalanan Dinas tanpa referensi: input manual tetap boleh.
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaran))
            ->assertSessionHasNoErrors();
        $this->assertNull(Npd::where('mode_kd', 'perjalanan')->sole()->surat_perintah_id);

        // Mode Kontribusi tidak punya Referensi SP - kiriman liar diabaikan.
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran) + ['surat_perintah_id' => $sp->id])
            ->assertSessionHasNoErrors();
        $this->assertNull(Npd::where('mode_kd', 'kontribusi')->sole()->surat_perintah_id);
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $sp->fresh()->status);
    }

    public function test_mengganti_atau_melepas_referensi_sp_saat_edit_mengembalikan_sp_lama(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-ref-ganti');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);
        $satu = $this->buatSp();
        $dua = $this->buatSp(['nomor_sp' => '121/PW.02.01/Sekre']);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaran, $satu->id));
        $npd = Npd::sole();

        // Menyimpan ulang dengan SP yang sama tetap diterima walau statusnya
        // kini sudah mengikuti NPD ini.
        $this->actingAs($pptk)->put(route('npd.kd.update', $npd), $this->payloadPerjalanan($masterAnggaran, $satu->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($satu->id, $npd->fresh()->surat_perintah_id);

        // Ganti ke SP lain: yang lama kembali Diterima PPTK.
        $this->actingAs($pptk)->put(route('npd.kd.update', $npd), $this->payloadPerjalanan($masterAnggaran, $dua->id))
            ->assertSessionHasNoErrors();
        $this->assertSame($dua->id, $npd->fresh()->surat_perintah_id);
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $satu->fresh()->status);
        $this->assertSame('Draft NPD - PPTK', $dua->fresh()->status);

        // Lepas referensi sama sekali.
        $this->actingAs($pptk)->put(route('npd.kd.update', $npd), $this->payloadPerjalanan($masterAnggaran))
            ->assertSessionHasNoErrors();
        $this->assertNull($npd->fresh()->surat_perintah_id);
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $dua->fresh()->status);
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
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaranPd));
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
