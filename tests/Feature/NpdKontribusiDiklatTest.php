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
            // Kedua mode kini wajib menyebut Tujuan Transfer, dan jumlahnya
            // harus menghabiskan Total Bruto (3.000.000 + 2.500.000).
            'penerima_transfer' => [
                ['nama' => 'Andi Saputra', 'rekening' => '1112223334', 'nominal' => 5_500_000],
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
        $this->assertStringContainsString('"nama":"Rina Marlina","pangkat":"III\/c","nip":"198602022011012002","rekening":"5556667778"', $isi);
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

    public function test_mode_kontribusi_memakai_tujuan_transfer_seperti_mode_perjalanan(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-kontribusi');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Dana kontribusi dibagi ke dua rekening; baris kosong sisa formulir dibuang.
        $payload = $this->payloadKontribusi($masterAnggaran);
        unset($payload['penerima_index']);
        $payload['penerima_transfer'] = [
            ['nama' => 'Andi Saputra', 'rekening' => '1112223334', 'nominal' => 3_000_000],
            ['nama' => 'Lembaga Diklat', 'rekening' => '9998887776', 'nominal' => 2_500_000],
        ];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

        $npd = Npd::with('peserta')->sole();
        $this->assertSame('kontribusi', $npd->mode_kd);
        $this->assertSame(['Andi Saputra', 'Lembaga Diklat'], array_column($npd->detail_json['penerima_transfer'], 'nama'));
        $this->assertFalse($npd->detail_json['pptk_penerima']);

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $rows = $metode->invoke(app(NpdController::class), $npd)['rows'];

        $this->assertSame(['Andi Saputra', 'Lembaga Diklat'], array_column($rows, 'nama'));
        $this->assertSame([3_000_000.0, 2_500_000.0], array_column($rows, 'bruto'));
        $this->assertStringStartsWith('Transfer Pembayaran Belanja Kontribusi Diklat', $rows[1]['keterangan']);
        $this->assertStringEndsWith(' an. Lembaga Diklat', $rows[1]['keterangan']);

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar-kd'] as $route) {
            $this->actingAs($pptk)->get(route($route, $npd))->assertOk();
        }
    }

    public function test_mode_kontribusi_wajib_tujuan_transfer_yang_menghabiskan_total_bruto(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-kontribusi-wajib');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadKontribusi($masterAnggaran);

        $tanpa = $payload;
        unset($tanpa['penerima_transfer']);
        $this->actingAs($pptk)->post(route('npd.kd.store'), $tanpa)->assertSessionHasErrors('penerima_transfer');

        $kurang = $payload;
        $kurang['penerima_transfer'] = [['nama' => 'Andi Saputra', 'nominal' => 5_000_000]];
        $this->actingAs($pptk)->post(route('npd.kd.store'), $kurang)->assertSessionHasErrors('penerima_transfer');

        $this->assertSame(0, Npd::count());
    }

    public function test_pptk_sebagai_penerima_transfer_mengalihkan_seluruh_dana_ke_pptk(): void
    {
        $pegawaiPptk = \App\Models\Pegawai::create([
            'nama' => 'Dra. PPTK Diklat', 'nip' => '197501011995031001', 'jabatan' => 'Kepala Subbagian',
            'bidang' => 'Sekretariat', 'golongan' => 'IV/a', 'pangkat' => 'Pembina', 'rekening' => '7770001112', 'aktif' => true,
        ]);
        $pptk = User::create([
            'username' => 'kd-pptk-penerima', 'nama' => 'Dra. PPTK Diklat', 'role' => 'pptk',
            'password' => 'rahasia', 'pegawai_id' => $pegawaiPptk->id,
        ]);
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $namaPptk = \App\Support\PptkPenerima::nama($masterAnggaran, 2026);
        $this->assertNotSame('', $namaPptk, 'Pelimpahan uji seharusnya menetapkan PPTK sub kegiatan ini.');

        foreach (['kontribusi' => 5_500_000.0, 'perjalanan' => 5_250_000.0] as $mode => $nominal) {
            $payload = $mode === 'kontribusi' ? $this->payloadKontribusi($masterAnggaran) : $this->payloadPerjalanan($masterAnggaran);
            // Daftar penerima yang tersisa di formulir harus diabaikan, walau totalnya tidak cocok.
            $payload['penerima_transfer'] = [['nama' => 'Andi Saputra', 'nominal' => 1]];
            $payload['pptk_penerima'] = '1';
            unset($payload['penerima_index']);

            $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

            $npd = Npd::with('peserta')->where('mode_kd', $mode)->sole();
            $this->assertTrue($npd->detail_json['pptk_penerima']);
            $this->assertNull($npd->detail_json['penerima_transfer']);

            $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
            $metode->setAccessible(true);
            $rows = $metode->invoke(app(NpdController::class), $npd)['rows'];

            $this->assertCount(1, $rows, "Mode {$mode}: PPTK sebagai penerima harus satu baris.");
            $this->assertSame($namaPptk, $rows[0]['nama']);
            $this->assertSame($nominal, $rows[0]['bruto']);
            $this->assertStringEndsWith(' an. '.$namaPptk, $rows[0]['keterangan']);
            $this->assertStringNotContainsString('Andi Saputra', $rows[0]['keterangan']);

            $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd))->assertOk();
        }
    }

    public function test_pptk_sebagai_penerima_ditolak_bila_pptk_belum_punya_rekening(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-pptk-tanpa-rekening');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadKontribusi($masterAnggaran);
        $payload['pptk_penerima'] = '1';

        $namaPptk = \App\Support\PptkPenerima::nama($masterAnggaran, 2026);
        $punyaRekening = \App\Support\PptkPenerima::rekening($namaPptk) !== '';

        if (! $punyaRekening) {
            $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasErrors('pptk_rekening');
            $this->assertSame(0, Npd::count());
        }

        // Rekening diisi manual di formulir -> diterima dan dipakai di Lampiran.
        $payload['pptk_rekening'] = '5550009998';
        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

        $npd = Npd::with('peserta')->sole();
        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $baris = $metode->invoke(app(NpdController::class), $npd)['rows'][0];

        $this->assertSame($namaPptk, $baris['nama']);
        if (! $punyaRekening) {
            $this->assertSame('5550009998', $baris['rekening']);
        }
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

        // Uraian tiap baris hanya menyebut penerima baris itu sendiri -
        // bukan seluruh penerima digabung.
        $this->assertStringEndsWith(' an. Andi Saputra', $lampiran['rows'][0]['keterangan']);
        $this->assertStringEndsWith(' an. Bendahara Tim', $lampiran['rows'][1]['keterangan']);
        $this->assertStringNotContainsString('Bendahara Tim', $lampiran['rows'][0]['keterangan']);
        $this->assertStringNotContainsString('Andi Saputra', $lampiran['rows'][1]['keterangan']);
        // Selain nama penerimanya, kalimatnya sama.
        $this->assertSame(
            str_replace('Andi Saputra', 'Bendahara Tim', $lampiran['rows'][0]['keterangan']),
            $lampiran['rows'][1]['keterangan']
        );
    }

    public function test_transfer_ke_setiap_peserta_uraian_tiap_baris_menyebut_namanya_sendiri(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-setiap-peserta');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Tiga peserta, masing-masing ditransfer sendiri - hasil tombol
        // "Transfer ke Setiap Peserta".
        $satu = $this->payloadPerjalanan($masterAnggaran)['peserta'][0];
        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['peserta'] = [
            array_replace($satu, ['nama' => 'AGUS SURYANA']),
            array_replace($satu, ['nama' => 'FAJAR LAZUARDI']),
            array_replace($satu, ['nama' => 'SITI AMINAH']),
        ];
        $payload['penerima_transfer'] = [
            ['nama' => 'AGUS SURYANA', 'nominal' => 5_250_000],
            ['nama' => 'FAJAR LAZUARDI', 'nominal' => 5_250_000],
            ['nama' => 'SITI AMINAH', 'nominal' => 5_250_000],
        ];
        $payload['keterangan_mode'] = 'otomatis';

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();
        $npd = Npd::with('peserta')->sole();

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $rows = $metode->invoke(app(NpdController::class), $npd)['rows'];

        $this->assertSame(['AGUS SURYANA', 'FAJAR LAZUARDI', 'SITI AMINAH'], array_column($rows, 'nama'));

        foreach ($rows as $baris) {
            $this->assertStringEndsWith(' an. '.$baris['nama'], $baris['keterangan']);

            foreach (array_diff(['AGUS SURYANA', 'FAJAR LAZUARDI', 'SITI AMINAH'], [$baris['nama']]) as $lain) {
                $this->assertStringNotContainsString($lain, $baris['keterangan'], "Uraian baris {$baris['nama']} ikut menyebut {$lain}.");
            }
        }

        $this->actingAs($pptk)->get(route('npd.cetak-lampiran', $npd))->assertOk();
    }

    public function test_uraian_lampiran_yang_diketik_manual_dipakai_sama_di_semua_baris(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-trf-manual');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['penerima_transfer'] = [
            ['nama' => 'Andi Saputra', 'nominal' => 3_000_000],
            ['nama' => 'Bendahara Tim', 'nominal' => 2_250_000],
        ];
        $payload['keterangan_mode'] = 'manual';
        $payload['keterangan_lampiran'] = 'Uraian khusus yang diketik petugas';

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $rows = $metode->invoke(app(NpdController::class), Npd::with('peserta')->sole())['rows'];

        $this->assertSame(
            ['Uraian khusus yang diketik petugas', 'Uraian khusus yang diketik petugas'],
            array_column($rows, 'keterangan')
        );
    }

    // ---------------- Penerima Dana vs penomoran baris peserta ----------------

    /**
     * Kasus nyata: peserta diisi lewat Referensi SP, sehingga baris-barisnya
     * dibuat ulang dengan nomor internal yang tidak lagi mulai dari 0 dan
     * tidak ada pilihan "Penerima Dana" yang terkirim. Di mode Perjalanan
     * Dinas penerimanya ditentukan Tujuan Transfer, jadi penyimpanan tidak
     * boleh ditolak "Penerima Dana wajib diisi".
     */
    public function test_mode_perjalanan_tersimpan_walau_pilihan_penerima_dana_tidak_terkirim(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-tanpa-penerima-index');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $payload = $this->payloadPerjalanan($masterAnggaran, $this->buatSp()->id);
        unset($payload['penerima_index']);
        // Baris peserta bernomor internal 3, bukan 0 - seperti setelah daftar dibuat ulang.
        $payload['peserta'] = [3 => $payload['peserta'][0]];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)
            ->assertSessionHasNoErrors();

        $npd = Npd::with('peserta')->sole();
        $this->assertSame('perjalanan', $npd->mode_kd);
        $this->assertSame(5_250_000.0, (float) $npd->nominal);
        $this->assertCount(1, $npd->peserta);
        $this->assertSame(0, $npd->detail_json['penerima_index']);
        // Penerima dananya tetap Tujuan Transfer yang diisi.
        $this->assertSame('Andi Saputra', $npd->detail_json['penerima_transfer'][0]['nama']);

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar-kd'] as $route) {
            $this->actingAs($pptk)->get(route($route, $npd))->assertOk();
        }
    }

    public function test_penerima_dana_dibaca_sebagai_urutan_peserta_bukan_nomor_baris(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-urutan-penerima');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Dua peserta bernomor internal 4 dan 7; penerimanya peserta KEDUA (urutan 1).
        $payload = $this->payloadKontribusi($masterAnggaran);
        $payload['peserta'] = [4 => $payload['peserta'][0], 7 => $payload['peserta'][1]];
        $payload['penerima_index'] = 1;

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)
            ->assertSessionHasNoErrors();

        $npd = Npd::with('peserta')->sole();
        $this->assertSame(1, $npd->detail_json['penerima_index']);
        $this->assertSame('Rina Marlina', $npd->peserta->values()->get($npd->detail_json['penerima_index'])->nama);
    }

    public function test_penerima_dana_per_peserta_sudah_dihapus_dari_formulir(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-form-penerima');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $isi = $this->actingAs($pptk)->get(route('npd.kd.create'))->assertOk()->getContent();

        // Radio "Penerima Dana / Jadikan penerima transfer" tidak ada lagi,
        // di baris peserta bawaan maupun di skrip pembuat baris baru.
        $this->assertStringNotContainsString('name="penerima_index"', $isi);
        $this->assertStringNotContainsString('data-penerima-radio', $isi);
        $this->assertStringNotContainsString('Jadikan penerima transfer', $isi);

        // Gantinya: bagian Tujuan Transfer untuk kedua mode, dengan pilihan PPTK.
        $this->assertStringContainsString('<h3 style="margin-top:22px;">Tujuan Transfer</h3>', $isi);
        $this->assertStringContainsString('id="trf-semua">Transfer ke Setiap Peserta</button>', $isi);
        $this->assertStringContainsString('name="pptk_penerima"', $isi);
        $this->assertStringContainsString('PPTK Sebagai Penerima Transfer', $isi);
        // Subtotal peserta untuk Tujuan Transfer mengikuti mode yang dipilih.
        $this->assertStringContainsString("if (currentMode() === 'kontribusi') {", $isi);

        // Tanpa penerima_index sama sekali, mode Kontribusi tetap tersimpan.
        $payload = $this->payloadKontribusi($masterAnggaran);
        unset($payload['penerima_index']);
        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, Npd::sole()->detail_json['penerima_index']);
    }

    public function test_npd_kontribusi_lama_tanpa_tujuan_transfer_tetap_tercetak_dan_terbuka_saat_disunting(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-kontribusi-lama');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran));
        $npd = Npd::with('peserta')->sole();

        // Bentuk yang tersimpan skema lama: tanpa daftar, penerimanya peserta kedua.
        $detail = $npd->detail_json;
        unset($detail['pptk_penerima'], $detail['pptk_rekening']);
        $detail['penerima_transfer'] = null;
        $detail['penerima_index'] = 1;
        $npd->forceFill(['detail_json' => $detail])->save();

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranKontribusiDiklat');
        $metode->setAccessible(true);
        $rows = $metode->invoke(app(NpdController::class), $npd->fresh('peserta'))['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('Rina Marlina', $rows[0]['nama']);
        $this->assertSame(5_500_000.0, $rows[0]['bruto']);

        // Saat disunting, penerima lamanya sudah menjadi satu baris Tujuan Transfer.
        $isi = $this->actingAs($pptk)->get(route('npd.kd.edit', $npd))->assertOk()->getContent();
        $this->assertStringContainsString('let TRF = [{"nama":"Rina Marlina","rekening":"5556667778","nominal":5500000}]', $isi);
    }

    // ---------------- Kolom GOL pada Daftar Pembayaran Perjalanan Dinas ----------------

    public function test_golongan_dipungut_dari_isian_pangkat_golongan(): void
    {
        $harapan = [
            'Penata Muda (III/a)' => 'III/a',
            'Penata Muda Tk. I (III/b)' => 'III/b',
            'Pembina Utama Muda, IV/c' => 'IV/c',
            'Pengatur II/c' => 'II/c',
            'III/a' => 'III/a',
            'iv / A' => 'IV/a',
            // PPPK: angka Romawi tanpa huruf, berdiri sendiri atau dalam kurung.
            'VII' => 'VII',
            'ix' => 'IX',
            'Ahli Pertama (IX)' => 'IX',
            // Bentuk rusak dari Input SP lama (dipecah di garis miring pertama).
            'a / Penata Muda (III)' => 'III/a',
            'c / Pembina Utama Muda (IV)' => 'IV/c',
            // "Tk. I" adalah tingkat pangkat, BUKAN golongan I.
            'Penata Muda Tk. I' => null,
            'Penata Muda' => null,
            '' => null,
        ];

        foreach ($harapan as $teks => $golongan) {
            $this->assertSame($golongan, \App\Models\NpdPeserta::golonganDariTeks((string) $teks), "Golongan dari \"{$teks}\" keliru.");
        }
    }

    public function test_kolom_gol_daftar_pembayaran_perjalanan_dinas_hanya_memuat_golongan(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-kolom-gol');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        // Pegawai master dipakai bila isian pangkat tidak memuat golongan.
        $pegawai = \App\Models\Pegawai::create([
            'nama' => 'Citra Lestari', 'nip' => '199001012015012001', 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat',
            'golongan' => 'III/d', 'pangkat' => 'Penata Tk. I', 'aktif' => true,
        ]);

        $baris = fn (array $ubah) => array_replace($this->payloadPerjalanan($masterAnggaran)['peserta'][0], $ubah);
        $payload = $this->payloadPerjalanan($masterAnggaran);
        $payload['peserta'] = [
            $baris(['nama' => 'Andi Saputra', 'pangkat' => 'Penata Muda (III/a)']),
            $baris(['nama' => 'Bayu Pratama', 'pangkat' => 'VII']),
            $baris(['nama' => 'Citra Lestari', 'pegawai_id' => $pegawai->id, 'pangkat' => 'Penata Tk. I']),
            $baris(['nama' => 'Dedi Kurnia', 'pangkat' => 'Tenaga Ahli']),
        ];
        $payload['penerima_transfer'] = [['nama' => 'Andi Saputra', 'rekening' => '1112223334', 'nominal' => 21_000_000]];

        $this->actingAs($pptk)->post(route('npd.kd.store'), $payload)->assertSessionHasNoErrors();
        $npd = Npd::sole();

        $html = (new ReflectionMethod(NpdController::class, 'htmlDaftarKontribusiDiklat'))
            ->invoke(app(NpdController::class), $npd);

        $this->assertStringContainsString('<td>Andi Saputra</td><td class="center">III/a</td>', $html);
        $this->assertStringContainsString('<td>Bayu Pratama</td><td class="center">VII</td>', $html);
        // Tidak ada golongan di isiannya -> dari master Pegawai.
        $this->assertStringContainsString('<td>Citra Lestari</td><td class="center">III/d</td>', $html);
        // Tidak ketemu di mana pun -> teks aslinya dipertahankan, tidak dikosongkan.
        $this->assertStringContainsString('<td>Dedi Kurnia</td><td class="center">Tenaga Ahli</td>', $html);
        $this->assertStringNotContainsString('Penata Muda', $html);
        $this->assertStringNotContainsString('Penata Tk. I', $html);

        $this->actingAs($pptk)->get(route('npd.cetak-daftar-kd', $npd))->assertOk();
    }

    // ---------------- Isian "Golongan" di Daftar Peserta ----------------

    /**
     * Isian peserta dulu berjudul "Pangkat/Golongan" dan diisi JABATAN saat
     * nama pegawai dipilih. Kini berjudul "Golongan" dan diisi golongan dari
     * Data Pegawai - untuk kedua mode.
     */
    public function test_isian_peserta_berjudul_golongan_dan_ditarik_dari_data_pegawai(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-isian-golongan');
        $this->limpahkanSubKegiatan($pptk, $this->buatMasterAnggaran());

        \App\Models\Pegawai::create([
            'nama' => 'AGUS SURYANA', 'nip' => '199001012015011001', 'jabatan' => 'Auditor Ahli Pertama',
            'bidang' => 'Sekretariat', 'golongan' => 'III/a', 'pangkat' => 'Penata Muda', 'rekening' => '123', 'aktif' => true,
        ]);

        $isi = $this->actingAs($pptk)->get(route('npd.kd.create'))->assertOk()->getContent();

        $this->assertStringContainsString('<label class="fl">Golongan</label>', $isi);
        $this->assertStringNotContainsString('Pangkat/Golongan', $isi);

        // Yang disediakan untuk ditarik adalah golongannya - bukan jabatan, bukan pangkat.
        $this->assertStringContainsString('"nama":"AGUS SURYANA"', $isi);
        $this->assertStringContainsString('"golongan":"III\/a"', $isi);
        $this->assertStringNotContainsString('"pangkat":"Auditor Ahli Pertama"', $isi);
        $this->assertStringNotContainsString('Penata Muda', $isi);
        $this->assertStringContainsString("pangkatInput.value = n.golongan || '';", $isi);
    }

    public function test_referensi_sp_menarik_golongan_dari_data_pegawai_bukan_snapshot_yang_rusak(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-sp-golongan');
        $this->limpahkanSubKegiatan($pptk, $this->buatMasterAnggaran());

        $pegawai = \App\Models\Pegawai::create([
            'nama' => 'AGUS SURYANA', 'nip' => '199001012015011001', 'jabatan' => 'Auditor',
            'bidang' => 'Sekretariat', 'golongan' => 'III/a', 'pangkat' => 'Penata Muda', 'aktif' => true,
        ]);

        // Snapshot anggota SP yang tersimpan rusak oleh formulir lama.
        $sp = $this->buatSp();
        $sp->anggota()->delete();
        $sp->anggota()->create([
            'pegawai_id' => $pegawai->id, 'nama' => 'AGUS SURYANA', 'nip' => '199001012015011001',
            'golongan' => 'III', 'pangkat' => 'a / Penata Muda', 'jabatan' => 'Auditor', 'rekening' => '123', 'manual' => false, 'urutan' => 1,
        ]);

        $isi = $this->actingAs($pptk)->get(route('npd.kd.create'))->assertOk()->getContent();

        $this->assertStringContainsString('"nama":"AGUS SURYANA","pangkat":"III\/a","nip":"199001012015011001"', $isi);
        $this->assertStringNotContainsString('a \/ Penata Muda', $isi);
    }

    public function test_golongan_kosong_dilengkapi_dari_data_pegawai_dan_tercetak_di_kedua_daftar_pembayaran(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-golongan-cetak');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $pegawai = \App\Models\Pegawai::create([
            'nama' => 'Andi Saputra', 'nip' => '198501012010011001', 'jabatan' => 'Auditor Ahli Muda',
            'bidang' => 'Sekretariat', 'golongan' => 'III/c', 'pangkat' => 'Penata', 'rekening' => '1112223334', 'aktif' => true,
        ]);

        $html = fn (Npd $npd) => (new ReflectionMethod(NpdController::class, 'htmlDaftarKontribusiDiklat'))
            ->invoke(app(NpdController::class), $npd);

        // Mode Kontribusi: isian Golongan dikosongkan, pegawainya dipilih dari master.
        $kontribusi = $this->payloadKontribusi($masterAnggaran);
        $kontribusi['peserta'][0] = array_replace($kontribusi['peserta'][0], ['pegawai_id' => $pegawai->id, 'pangkat' => '']);
        // Peserta kedua: NPD lama yang isiannya masih jabatan/pangkat campur.
        $kontribusi['peserta'][1]['pangkat'] = 'Penata (III/c)';
        $this->actingAs($pptk)->post(route('npd.kd.store'), $kontribusi)->assertSessionHasNoErrors();

        $npd = Npd::with('peserta')->where('mode_kd', 'kontribusi')->sole();
        $this->assertSame('III/c', $npd->peserta[0]->pangkat, 'Golongan kosong seharusnya dilengkapi dari Data Pegawai, bukan pangkatnya.');

        $daftar = $html($npd);
        $this->assertStringContainsString('<td>Andi Saputra</td><td class="center">III/c</td>', $daftar);
        $this->assertStringContainsString('<td>Rina Marlina</td><td class="center">III/c</td>', $daftar);
        $this->assertStringNotContainsString('Penata', $daftar);

        // Mode Perjalanan Dinas: sama.
        $perjalanan = $this->payloadPerjalanan($masterAnggaran);
        $perjalanan['peserta'][0] = array_replace($perjalanan['peserta'][0], ['pegawai_id' => $pegawai->id, 'pangkat' => '']);
        $this->actingAs($pptk)->post(route('npd.kd.store'), $perjalanan)->assertSessionHasNoErrors();

        $npdPd = Npd::with('peserta')->where('mode_kd', 'perjalanan')->sole();
        $this->assertSame('III/c', $npdPd->peserta[0]->pangkat);
        $this->assertStringContainsString('<td>Andi Saputra</td><td class="center">III/c</td>', $html($npdPd));

        // Halaman detail menyebut kolomnya "Golongan".
        $this->actingAs($pptk)->get(route('npd.show', $npdPd))
            ->assertOk()
            ->assertSee('<th>Golongan</th>', false)
            ->assertDontSee('<th>Pangkat</th>', false);
    }

    // ---------------- Baris J U M L A H Daftar Pembayaran ----------------

    /**
     * Baris jumlah harus tercetak TEBAL. Kelas "bold" pada <tr> saja tidak
     * cukup - mPDF tidak meneruskannya ke sel - jadi tebalnya dipasang di
     * isi tiap sel lewat <b>.
     */
    public function test_baris_jumlah_daftar_pembayaran_tebal_di_kedua_mode(): void
    {
        $pptk = $this->buatUser('pptk', 'kd-jumlah-tebal');
        $masterAnggaran = $this->buatMasterAnggaran();
        $this->limpahkanSubKegiatan($pptk, $masterAnggaran);

        $html = fn (Npd $npd) => (new ReflectionMethod(NpdController::class, 'htmlDaftarKontribusiDiklat'))
            ->invoke(app(NpdController::class), $npd);

        // Mode Kontribusi: angka berlabel "Rp" dalam tabel kecil di tiap sel.
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadKontribusi($masterAnggaran))->assertSessionHasNoErrors();
        $kontribusi = $html(Npd::where('mode_kd', 'kontribusi')->sole());

        $this->assertStringContainsString('<td class="center bold" colspan="5"><b>J U M L A H</b></td>', $kontribusi);
        // Kontribusi 5.000.000, MOOC 500.000, jumlah 5.500.000.
        foreach (['5.000.000', '500.000', '5.500.000'] as $angka) {
            $this->assertStringContainsString('<td class="rp-l"><b>Rp</b></td><td class="rp-a"><b>'.$angka.'</b></td>', $kontribusi);
        }

        // Mode Perjalanan Dinas: angka tanpa label.
        $this->actingAs($pptk)->post(route('npd.kd.store'), $this->payloadPerjalanan($masterAnggaran))->assertSessionHasNoErrors();
        $perjalanan = $html(Npd::where('mode_kd', 'perjalanan')->sole());

        $this->assertStringContainsString('<td class="center bold" colspan="5"><b>J U M L A H</b></td>', $perjalanan);
        // Harian 2.000.000, akomodasi 2.400.000, saku 500.000, transport 350.000, jumlah 5.250.000.
        foreach (['2.000.000', '2.400.000', '500.000', '350.000', '5.250.000'] as $angka) {
            $this->assertStringContainsString('<td class="rp bold"><b>'.$angka.'</b></td>', $perjalanan);
        }

        // Baris peserta tidak ikut dibungkus <b>: hanya baris jumlah yang berubah.
        $this->assertSame(5, substr_count($perjalanan, '<td class="rp bold"><b>'));
    }
}