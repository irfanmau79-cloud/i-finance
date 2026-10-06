<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdTransportTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
            // Pelimpahan menunjuk pegawai, bukan akun: akun PPTK harus sudah
            // tertaut sejak dibuat supaya objek yang dipegang test membawa
            // tautannya (lihat buatIndukSelesai).
            'pegawai_id' => $role === 'pptk'
                ? Pegawai::create(['nama' => 'Pegawai '.$username, 'nip' => sprintf('1975010119950%05d', Pegawai::count() + 1), 'jabatan' => 'PPTK', 'bidang' => 'Sekretariat', 'pangkat' => 'Penata', 'aktif' => true])->id
                : null,
        ]);
    }

    private function buatMasterAnggaran(float $pagu = 100_000_000, string $kodeRekening = '5.1.02.04.01.0001'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Transport',
            'kegiatan' => 'Kegiatan Uji Transport',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Transport',
            'kode_rekening' => $kodeRekening,
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatIndukSelesai(MasterAnggaran $masterAnggaran, int $jumlahAnggota = 2): Npd
    {
        // PPTK hanya boleh membuat Transport di atas Perjalanan Dinas pada
        // Sub Kegiatan limpahannya, jadi akun PPTK uji yang sudah dibuat
        // dilimpahi Sub Kegiatan induk ini. Pembatasannya sendiri diuji di
        // test_pptk_tidak_bisa_menumpang_pada_perjalanan_dinas_sub_kegiatan_orang_lain.
        User::where('role', 'pptk')->get()
            ->each(fn (User $pptk) => $this->limpahkanSubKegiatan($pptk, $masterAnggaran));

        $induk = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Panjar',
            'nominal' => 1_830_000,
            'terbilang' => 'satu juta delapan ratus tiga puluh ribu rupiah',
            'status' => 'Selesai',
            'detail_json' => [
                'nomor_sp' => '001/SP/TEST/2026',
                'tanggal_sp' => '2026-07-15',
                'uraian_sp' => 'Perjalanan pengujian',
                'berangkat_dari' => 'Kabupaten Bekasi',
                'tujuan' => 'Bandung',
                'tanggal_berangkat' => '2026-07-20',
                'tanggal_pulang' => '2026-07-22',
                'keterangan_lampiran' => null,
            ],
        ]);

        $nama = ['Anggota Pertama', 'Anggota Kedua', 'Anggota Ketiga'];
        for ($i = 0; $i < $jumlahAnggota; $i++) {
            $tim = $induk->tim()->create([
                'nama' => $nama[$i],
                'jabatan' => 'Auditor',
                'bidang_snapshot' => 'Sekretariat',
                'nip' => '19800101200001100'.$i,
                'rekening' => '11111'.$i,
                'bbm_liter' => 0,
                'bbm_tarif' => 0,
                'tol' => 0,
                'tiket' => 0,
                'representatif' => 0,
                'is_penerima' => $i === 0,
            ]);
            $tim->paket()->create([
                'cluster' => 'A',
                'wilayah' => 'Bandung',
                'lama_hari' => 2,
                'tarif_uh' => 100_000,
                'malam' => 1,
                'tarif_akom' => 300_000,
            ]);
        }

        return $induk;
    }

    private function payload(Npd $induk, int $jumlahAnggota = 2): array
    {
        $tim = [];
        for ($i = 0; $i < $jumlahAnggota; $i++) {
            $tim[] = [
                'bbm_liter' => 10,
                'bbm_tarif' => 10_000,
                'tol' => 20_000,
                'tiket' => 150_000,
                'representatif' => $i === 0 ? 50_000 : 0,
            ];
        }

        return [
            'npd_induk_id' => $induk->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-25',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima_index' => 0,
            'tim' => $tim,
        ];
    }

    /**
     * Formula harus persis NpdPerjalananHitung::hitungAnggota dengan paket kosong:
     * jumlah anggota = BBM(at-cost) + tol + tiket + representatif; uang harian &
     * akomodasi wajib nol walau induknya punya paket dengan tarif UH/akom.
     */
    public function test_pptk_dapat_membuat_transport_dari_induk_selesai_dengan_formula_benar(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-pptk');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk));

        $npd = Npd::with('tim')->where('jenis', 'tr')->firstOrFail();
        $response->assertRedirect(route('npd.show', $npd));

        $this->assertSame('tr', $npd->jenis);
        $this->assertSame($induk->id, $npd->npd_induk_id);
        $this->assertSame($induk->master_anggaran_id, $npd->master_anggaran_id);
        $this->assertSame($induk->keu, $npd->keu);
        $this->assertSame('001/SP/TEST/2026', $npd->detail_json['nomor_sp']);

        // Per anggota: BBM = 10*10.000=100.000, + tol 20.000 + tiket 150.000 = 270.000, + representatif.
        // Anggota 0 (penerima): 270.000 + 50.000 = 320.000. Anggota 1: 270.000 + 0 = 270.000.
        // Nominal = 320.000 + 270.000 = 590.000.
        $this->assertEquals(590_000.0, (float) $npd->nominal);
        $this->assertCount(2, $npd->tim);

        foreach ($npd->tim as $t) {
            $h = $t->hitung();
            $this->assertEquals(0.0, $h['jml_harian']);
            $this->assertEquals(0.0, $h['jml_akom']);
            $this->assertSame('Sekretariat', $t->bidang_snapshot);
        }

        $penerima = $npd->tim->firstWhere('is_penerima', true);
        $this->assertSame('Anggota Pertama', $penerima->nama);
        $this->assertEquals(320_000.0, $penerima->hitung()['jumlah']);

        $showResponse = $this->actingAs($pptk)->get(route('npd.show', $npd));
        $showResponse->assertOk();
        $showResponse->assertSee('Anggota Pertama');
        $showResponse->assertSee($induk->nomor_lengkap ?? '#'.$induk->id);
    }

    private function buatSuratPerintah(string $nomor, string $jenis = SuratPerintah::JENIS_UANG_HARIAN, ?SuratPerintah $induk = null): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => $nomor,
            'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 - 22 Juli 2026',
            'keterangan' => 'Perjalanan pengujian',
            'file_url' => 'sp/uji.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => $jenis,
            'sp_induk_id' => $induk?->id,
            'sumber_npd' => true,
            'dipantau' => true,
        ]);
    }

    /**
     * NPD Transport tidak memilih Surat Perintah sendiri - ia MEWARISI tautan
     * SP dari NPD Perjalanan Dinas induknya, sama seperti di GAS
     * (buatNPDTransport: tautanNoSP = _fromSP).
     */
    public function test_transport_mewarisi_tautan_surat_perintah_dari_induknya(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-warisi-sp');
        $masterAnggaran = $this->buatMasterAnggaran();
        $sp = $this->buatSuratPerintah('010/SP/INDUK/2026');

        $induk = $this->buatIndukSelesai($masterAnggaran);
        $induk->forceFill(['surat_perintah_id' => $sp->id])->save();

        $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk))->assertRedirect();

        $transport = Npd::where('jenis', 'tr')->firstOrFail();
        $this->assertSame($sp->id, $transport->surat_perintah_id);
        $this->assertSame($induk->id, $transport->npd_induk_id);
    }

    /**
     * Bila SP induk sudah punya entri "Reimburse Transportasi", NPD Transport
     * ditautkan ke entri itu - bukan ke SP Uang Harian/Akomodasi-nya. Entri
     * Reimburse memang dibuat khusus untuk pembayaran transportasi.
     */
    public function test_transport_menaut_ke_entri_reimburse_bila_ada(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-reimburse');
        $masterAnggaran = $this->buatMasterAnggaran();
        $sp = $this->buatSuratPerintah('011/SP/INDUK/2026');
        $reimburse = $this->buatSuratPerintah('011/SP/INDUK/2026 (Reimburse)', SuratPerintah::JENIS_REIMBURSE, $sp);

        $induk = $this->buatIndukSelesai($masterAnggaran);
        $induk->forceFill(['surat_perintah_id' => $sp->id])->save();

        $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk))->assertRedirect();

        $transport = Npd::where('jenis', 'tr')->firstOrFail();
        $this->assertSame($reimburse->id, $transport->surat_perintah_id);
        $this->assertNotSame($sp->id, $transport->surat_perintah_id);
    }

    public function test_pptk_tidak_bisa_menumpang_pada_perjalanan_dinas_sub_kegiatan_orang_lain(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-milik-saya');
        $milikSaya = $this->buatMasterAnggaran();
        $indukSaya = $this->buatIndukSelesai($milikSaya);

        // Perjalanan Dinas pada Sub Kegiatan yang TIDAK dilimpahkan ke akun ini.
        $milikOrangLain = MasterAnggaran::create([
            'program' => 'Program Orang Lain',
            'kegiatan' => 'Kegiatan Orang Lain',
            'sub_kegiatan' => '6.01.01.2.09 Sub Kegiatan Orang Lain',
            'kode_rekening' => '5.1.02.04.01.0009',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
        $indukOrangLain = Npd::create(array_merge(
            $indukSaya->only(['jenis', 'keu', 'bulan', 'tahun', 'jenis_panjar', 'nominal', 'terbilang', 'status', 'detail_json']),
            ['master_anggaran_id' => $milikOrangLain->id, 'tanggal_npd' => '2026-07-20'],
        ));
        foreach ($indukSaya->tim as $anggota) {
            $indukOrangLain->tim()->create($anggota->only(['nama', 'jabatan', 'nip', 'rekening', 'is_penerima']));
        }

        // Di formulir hanya induk miliknya yang ditawarkan.
        $formulir = $this->actingAs($pptk)->get(route('npd.tr.create'))->assertOk();
        $this->assertSame([$indukSaya->id], $formulir->viewData('indukList')->pluck('id')->all());

        // Mengirim id induk orang lain langsung pun ditolak.
        $this->actingAs($pptk)
            ->post(route('npd.tr.store'), $this->payload($indukOrangLain))
            ->assertSessionHasErrors('npd_induk_id');
        $this->assertSame(0, Npd::where('jenis', 'tr')->count());

        // Induk miliknya sendiri tetap bisa.
        $this->actingAs($pptk)
            ->post(route('npd.tr.store'), $this->payload($indukSaya))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Npd::where('jenis', 'tr')->count());

        // Superadmin tidak dibatasi pelimpahan.
        $superadmin = $this->buatUser('superadmin', 'tr-superadmin-bebas');
        $semua = $this->actingAs($superadmin)->get(route('npd.tr.create'))->viewData('indukList')->pluck('id')->all();
        $this->assertContains($indukOrangLain->id, $semua);
        $this->actingAs($superadmin)
            ->post(route('npd.tr.store'), $this->payload($indukOrangLain))
            ->assertSessionHasNoErrors();
    }

    public function test_transport_bbm_diisi_total_nominal_dan_liter_dihitung(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-bbm-nominal');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());

        $payload = $this->payload($induk);
        $payload['tim'] = [
            ['bbm_nominal' => 300_000, 'bbm_tarif' => 12_000, 'tol' => 50_000],
            ['bbm_nominal' => '', 'bbm_tarif' => 12_000, 'tiket' => 100_000],
        ];

        $this->actingAs($pptk)->post(route('npd.tr.store'), $payload)->assertSessionHasNoErrors();

        $npd = Npd::with('tim')->where('jenis', 'tr')->firstOrFail();
        $this->assertSame(450_000.0, (float) $npd->nominal);
        $this->assertSame(300_000.0, (float) $npd->tim[0]->bbm_nominal);
        $this->assertSame(25.0, (float) $npd->tim[0]->bbm_liter);
        // Anggota tanpa BBM: tarif standar yang ikut terkirim tidak membuat BBM.
        $this->assertNull($npd->tim[1]->bbm_nominal);
        $this->assertSame(0.0, (float) $npd->tim[1]->hitung()['bbm']);

        $this->actingAs($pptk)->get(route('npd.tr.edit', $npd))->assertOk()
            ->assertSee('data-bbm-nominal name="tim[0][bbm_nominal]" value="300000"', false)
            ->assertSee('Jumlah Liter (otomatis)')
            ->assertDontSee('data-bbm-liter name=', false);
        $this->actingAs($pptk)->get(route('npd.cetak-spd', $npd))->assertOk();
    }

    public function test_induk_harus_jenis_pd_dan_status_selesai(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-induk-salah');
        $masterAnggaran = $this->buatMasterAnggaran();

        $indukBelumSelesai = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Panjar',
            'nominal' => 100_000,
            'terbilang' => 'seratus ribu rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);
        $indukBelumSelesai->tim()->create(['nama' => 'X', 'is_penerima' => true]);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($indukBelumSelesai, 1));
        $response->assertSessionHasErrors(['npd_induk_id']);
        $this->assertSame(0, Npd::where('jenis', 'tr')->count());

        $indukBj = Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 100_000,
            'terbilang' => 'seratus ribu rupiah',
            'status' => 'Selesai',
        ]);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($indukBj, 1));
        $response->assertSessionHasErrors(['npd_induk_id']);
    }

    public function test_jumlah_anggota_harus_sama_dengan_induk(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-jumlah');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran, 2);

        $payload = $this->payload($induk, 2);
        unset($payload['tim'][1]);
        $payload['tim'] = array_values($payload['tim']);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $payload);

        $response->assertSessionHasErrors(['tim']);
        $this->assertSame(0, Npd::where('jenis', 'tr')->count());
    }

    public function test_satu_induk_hanya_boleh_satu_transport_aktif(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-duplikat');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk))->assertRedirect();
        $this->assertSame(1, Npd::where('jenis', 'tr')->count());

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk));
        $response->assertSessionHasErrors(['npd_induk_id']);
        $this->assertSame(1, Npd::where('jenis', 'tr')->count());

        // Setelah Transport pertama dibatalkan, induk boleh dipakai lagi.
        $transportPertama = Npd::where('jenis', 'tr')->firstOrFail();
        $this->actingAs($pptk)->delete(route('npd.destroy', $transportPertama), ['alasan' => 'Uji ulang']);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk));
        $response->assertRedirect();
        $this->assertSame(1, Npd::where('jenis', 'tr')->where('status', '!=', 'Dibatalkan')->count());
    }

    public function test_pembatalan_induk_diblokir_selama_ada_turunan_aktif(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-blokir-batal');
        $superadmin = $this->buatUser('superadmin', 'tr-blokir-superadmin');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk));

        // destroy() oleh superadmin harus ditolak selama turunan Transport masih aktif.
        $response = $this->actingAs($superadmin)->delete(route('npd.destroy', $induk), ['alasan' => 'Coba batalkan induk']);
        $response->assertSessionHasErrors(['alasan']);
        $this->assertSame('Selesai', $induk->fresh()->status);

        // Transisi batal_selesai (Selesai -> Draft NPD - BPP) juga harus ditolak.
        $response = $this->actingAs($superadmin)->post(route('npd.transisi', $induk), [
            'aksi' => 'batal_selesai',
            'catatan' => 'Coba batalkan status selesai',
        ]);
        $response->assertSessionHasErrors(['aksi']);
        $this->assertSame('Selesai', $induk->fresh()->status);
    }

    public function test_nominal_melebihi_sisa_anggaran_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-tolak');
        $masterAnggaran = $this->buatMasterAnggaran(500_000);
        $induk = $this->buatIndukSelesai($masterAnggaran, 1);

        $response = $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk, 1));

        // Anggota tunggal: BBM 100.000 + tol 20.000 + tiket 150.000 + representatif 50.000 = 320.000,
        // tapi induk sudah memakai 1.830.000 dari pagu 500.000 lewat nominalnya sendiri (Selesai),
        // sehingga sisa tersedia sumber dana ini sudah negatif/kurang dari 320.000.
        $response->assertSessionHasErrors(['tim']);
        $this->assertSame(0, Npd::where('jenis', 'tr')->count());
    }

    public function test_superadmin_dan_pptk_boleh_akses_tapi_role_lain_ditolak(): void
    {
        $verifikator = $this->buatUser('verifikator', 'tr-verif');
        $superadmin = $this->buatUser('superadmin', 'tr-superadmin');
        $pptk = $this->buatUser('pptk', 'tr-akses-pptk');

        $this->actingAs($pptk)->get(route('npd.tr.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.tr.create'))->assertOk();
        $this->actingAs($verifikator)->get(route('npd.tr.create'))->assertForbidden();
        $this->actingAs($verifikator)->post(route('npd.tr.store'), [])->assertForbidden();
    }

    public function test_keempat_pdf_transport_berhasil_dirender(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-pdf');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran);

        $this->actingAs($pptk)->post(route('npd.tr.store'), $this->payload($induk));
        $npd = Npd::where('jenis', 'tr')->firstOrFail();

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar', 'npd.cetak-spd'] as $route) {
            $resp = $this->actingAs($pptk)->get(route($route, $npd));
            $resp->assertOk();
            $resp->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
