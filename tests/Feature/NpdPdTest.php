<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdPdTest extends TestCase
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
            'program' => 'Program Uji PD',
            'kegiatan' => 'Kegiatan Uji PD',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji PD',
            'kode_rekening' => '5.1.02.04.01.0001',
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatSuratPerintah(?string $nomor = null): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => $nomor ?? '001/SP/TEST/2026',
            'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 - 22 Juli 2026',
            'keterangan' => 'Perjalanan pengujian',
            'file_url' => 'sp/test.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true,
            'dipantau' => true,
        ]);
    }

    private function payload(MasterAnggaran $masterAnggaran, ?SuratPerintah $suratPerintah = null): array
    {
        // NPD Perjalanan Dinas wajib berangkat dari Surat Perintah, jadi
        // pemanggil yang tidak menyebutkan SP tetap dibuatkan satu.
        $suratPerintah ??= $this->buatSuratPerintah();

        return [
            'master_anggaran_id' => $masterAnggaran->id,
            'surat_perintah_id' => $suratPerintah->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_sp' => 'Perjalanan pengujian',
            'berangkat_dari' => 'Kabupaten Bekasi',
            'tujuan' => 'Bandung',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-22',
            'keterangan_lampiran' => 'Pengujian perjalanan dinas',
            'penerima_index' => 1,
            'tim' => [
                [
                    'nama' => 'Anggota Pertama',
                    'jabatan' => 'Auditor',
                    'nip' => '198001012000011001',
                    'rekening' => '111111',
                    'bbm_liter' => 10.5,
                    'bbm_tarif' => 10_000,
                    'tol' => 50_000,
                    'tiket' => 200_000,
                    'representatif' => 25_000,
                    'paket' => [
                        [
                            'cluster' => 'A',
                            'wilayah' => 'Bandung',
                            'lama_hari' => 2,
                            'tarif_uh' => 100_000,
                            'malam' => 1,
                            'tarif_akom' => 300_000,
                        ],
                    ],
                ],
                [
                    'nama' => 'Anggota Kedua',
                    'jabatan' => 'Pengawas',
                    'nip' => '198202022002021002',
                    'rekening' => '222222',
                    'paket' => [
                        [
                            'cluster' => 'B',
                            'wilayah' => 'Kota Bandung',
                            'lama_hari' => 3,
                            'tarif_uh' => 150_000,
                            'malam' => 2,
                            'tarif_akom' => 250_000,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_hanya_pptk_dan_superadmin_dapat_mengakses_pembuatan_npd_perjalanan(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-pptk');
        $superadmin = $this->buatUser('superadmin', 'pd-superadmin');
        $bpp = $this->buatUser('bpp', 'pd-bpp');

        $this->actingAs($pptk)->get(route('npd.pd.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.pd.create'))->assertOk();
        $this->actingAs($bpp)->get(route('npd.pd.create'))->assertForbidden();
        $this->actingAs($bpp)->post(route('npd.pd.store'), [])->assertForbidden();
    }

    /**
     * NPD Perjalanan Dinas WAJIB berangkat dari Surat Perintah. SP-nya pun
     * harus benar-benar layak: berjenis Uang Harian/Akomodasi, berstatus
     * Diterima PPTK, dan penanda Sumber NPD-nya menyala.
     */
    public function test_npd_perjalanan_dinas_wajib_berasal_dari_surat_perintah(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-wajib-sp');
        $master = $this->buatMasterAnggaran();

        $payload = $this->payload($master);
        unset($payload['surat_perintah_id']);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)
            ->assertSessionHasErrors(['surat_perintah_id']);

        $this->assertSame(0, Npd::where('jenis', 'pd')->count());
    }

    public function test_surat_perintah_reimburse_atau_tanpa_penanda_sumber_ditolak(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-sp-tidak-layak');
        $master = $this->buatMasterAnggaran();

        $reimburse = $this->buatSuratPerintah('900/SP/REIMBURSE/2026');
        $reimburse->update(['jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE]);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payload($master, $reimburse))
            ->assertSessionHasErrors(['surat_perintah_id']);

        $dimatikan = $this->buatSuratPerintah('901/SP/MATI/2026');
        $dimatikan->update(['sumber_npd' => false]);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payload($master, $dimatikan))
            ->assertSessionHasErrors(['surat_perintah_id']);

        $this->assertSame(0, Npd::where('jenis', 'pd')->count());
    }

    /** Nomor & tanggal SP tidak diambil dari formulir, tetapi dari SP-nya. */
    public function test_nomor_dan_tanggal_sp_selalu_mengikuti_surat_perintah(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-nomor-sp');
        $master = $this->buatMasterAnggaran();
        $sp = $this->buatSuratPerintah('777/SP/BENAR/2026');

        $payload = $this->payload($master, $sp);
        // Kiriman palsu dari formulir harus diabaikan.
        $payload['nomor_sp'] = '000/SP/PALSU/2026';
        $payload['tanggal_sp'] = '2020-01-01';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)->assertRedirect();

        $npd = Npd::where('jenis', 'pd')->latest('id')->firstOrFail();
        $this->assertSame('777/SP/BENAR/2026', $npd->detail_json['nomor_sp']);
        $this->assertSame($sp->tanggal_sp->format('Y-m-d'), $npd->detail_json['tanggal_sp']);
        $this->assertSame($sp->id, $npd->surat_perintah_id);
    }

    /**
     * Kewajiban Surat Perintah TIDAK mengunci data lama. Seluruh NPD
     * Perjalanan Dinas yang sudah ada berasal dari impor historis dan
     * berstatus Selesai, dan NPD berstatus Selesai memang tidak pernah bisa
     * disunting - jadi aturan baru ini tidak menghilangkan kemampuan apa pun
     * yang tadinya ada.
     */
    public function test_npd_lama_hasil_impor_tidak_terpengaruh_kewajiban_sp(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-lama-impor');
        $master = $this->buatMasterAnggaran();

        $lama = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $master->id,
            'surat_perintah_id' => null,
            'sumber_data' => 'import_historis',
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Selesai',
            'detail_json' => ['nomor_sp' => '999/SP/LAMA/2026'],
        ]);

        $this->assertFalse($lama->dapatDieditOleh($pptk));
        $this->actingAs($pptk)->get(route('npd.pd.edit', $lama))->assertForbidden();

        // Tetap dapat dilihat dan dicetak seperti biasa.
        $this->actingAs($pptk)->get(route('npd.show', $lama))->assertOk();
    }

    /**
     * Kalau toh ada NPD berstatus Draft yang belum tertaut SP, halaman
     * suntingnya harus menjelaskan sebabnya - bukan membiarkan pengguna
     * menabrak galat validasi tanpa tahu apa yang kurang.
     */
    public function test_npd_draft_tanpa_sp_diberi_penjelasan_saat_disunting(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-draft-tanpa-sp');
        $master = $this->buatMasterAnggaran();

        $draft = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $master->id,
            'surat_perintah_id' => null,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Draft NPD - PPTK',
            'detail_json' => ['nomor_sp' => '998/SP/DRAFT/2026'],
        ]);

        $this->actingAs($pptk)->get(route('npd.pd.edit', $draft))->assertOk()
            ->assertSee('dibuat sebelum Surat Perintah diwajibkan', false);
    }

    /**
     * Tahun tidak lagi dipilih saat membuat NPD - selalu tahun anggaran
     * berjalan. Isiannya dihapus dari formulir dan tahun di luar itu ditolak,
     * supaya penomoran dan perhitungan realisasi tidak jatuh ke tahun salah.
     */
    public function test_tahun_terkunci_ke_tahun_anggaran_berjalan(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-tahun');
        $master = $this->buatMasterAnggaran();

        // Tidak ada lagi isian Tahun yang bisa diketik pengguna.
        $this->actingAs($pptk)->get(route('npd.pd.create'))->assertOk()
            ->assertDontSee('<label class="fl" for="tahun">Tahun</label>', false)
            ->assertSee('<input type="hidden" name="tahun"', false);

        $payload = $this->payload($master);
        $payload['tahun'] = 2025;

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)
            ->assertSessionHasErrors(['tahun']);

        $payload['tahun'] = (int) config('anggaran.tahun_aktif');
        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)->assertRedirect();

        $this->assertSame((int) config('anggaran.tahun_aktif'), Npd::where('jenis', 'pd')->firstOrFail()->tahun);
    }

    public function test_input_wajib_dan_tanggal_pulang_divalidasi(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-validasi');

        $this->actingAs($pptk)->post(route('npd.pd.store'), [])
            ->assertSessionHasErrors([
                'master_anggaran_id',
                'jenis_panjar',
                'tanggal_npd',
                'surat_perintah_id',
                'tim',
            ]);

        $payload = $this->payload($this->buatMasterAnggaran());
        $payload['tanggal_pulang'] = '2026-07-19';

        $this->actingAs($pptk)->post(route('npd.pd.store'), $payload)
            ->assertSessionHasErrors(['tanggal_pulang']);

        $this->assertSame(0, Npd::count());
    }

    public function test_formula_total_dan_pagu_anggaran_divalidasi_di_backend(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-pagu');
        $masterAnggaran = $this->buatMasterAnggaran(1_000_000);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payload($masterAnggaran))
            ->assertSessionHasErrors(['tim']);

        $this->assertSame(0, Npd::count());
    }

    public function test_pptk_menyimpan_draft_tim_paket_formula_total_dan_relasi_sp(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-simpan');
        $masterAnggaran = $this->buatMasterAnggaran();
        $suratPerintah = $this->buatSuratPerintah();
        $pegawai = Pegawai::create([
            'nama' => 'Anggota Pertama', 'nip' => '198001012000011001', 'jabatan' => 'Auditor',
            'bidang' => 'Inspektur Pembantu I', 'rekening' => '111111', 'aktif' => true,
        ]);
        $payload = $this->payload($masterAnggaran, $suratPerintah);
        $payload['tim'][0]['pegawai_id'] = $pegawai->id;

        $response = $this->actingAs($pptk)
            ->post(route('npd.pd.store'), $payload);

        $npd = Npd::with('tim.paket')->sole();

        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame('pd', $npd->jenis);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame('Panjar', $npd->jenis_panjar);
        $this->assertSame($pptk->id, $npd->dibuat_oleh);
        $this->assertSame($suratPerintah->id, $npd->surat_perintah_id);
        $this->assertSame(1_830_000.0, (float) $npd->nominal);
        $this->assertSame('001/SP/TEST/2026', $npd->detail_json['nomor_sp']);
        $this->assertSame('Inspektur Pembantu I', $npd->tim->first()->bidang_snapshot);

        $this->assertCount(2, $npd->tim);
        $this->assertCount(1, $npd->tim[0]->paket);
        $this->assertCount(1, $npd->tim[1]->paket);
        $this->assertFalse($npd->tim[0]->is_penerima);
        $this->assertTrue($npd->tim[1]->is_penerima);
        $this->assertSame(880_000.0, $npd->tim[0]->hitung()['jumlah']);
        $this->assertSame(950_000.0, $npd->tim[1]->hitung()['jumlah']);

        $this->assertDatabaseHas('npd_tim_paket', [
            'npd_tim_id' => $npd->tim[0]->id,
            'cluster' => 'A',
            'wilayah' => 'Bandung',
            'lama_hari' => 2,
        ]);
        $this->assertSame('Draft NPD - PPTK', $suratPerintah->fresh()->status);
    }
}
