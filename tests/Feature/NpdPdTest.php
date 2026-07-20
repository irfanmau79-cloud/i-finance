<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
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

    private function buatSuratPerintah(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '001/SP/TEST/2026',
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
        ]);
    }

    private function payload(MasterAnggaran $masterAnggaran, ?SuratPerintah $suratPerintah = null): array
    {
        return [
            'master_anggaran_id' => $masterAnggaran->id,
            'surat_perintah_id' => $suratPerintah?->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_sp' => $suratPerintah?->nomor_sp ?? '002/SP/TEST/2026',
            'tanggal_sp' => '2026-07-15',
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

    public function test_hanya_pptk_dan_bendahara_dapat_mengakses_pembuatan_npd_perjalanan(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-pptk');
        $bendahara = $this->buatUser('bendahara', 'pd-bendahara');
        $bpp = $this->buatUser('bpp', 'pd-bpp');

        $this->actingAs($pptk)->get(route('npd.pd.create'))->assertOk();
        $this->actingAs($bendahara)->get(route('npd.pd.create'))->assertOk();
        $this->actingAs($bpp)->get(route('npd.pd.create'))->assertForbidden();
        $this->actingAs($bpp)->post(route('npd.pd.store'), [])->assertForbidden();
    }

    public function test_input_wajib_dan_tanggal_pulang_divalidasi(): void
    {
        $pptk = $this->buatUser('pptk', 'pd-validasi');

        $this->actingAs($pptk)->post(route('npd.pd.store'), [])
            ->assertSessionHasErrors([
                'master_anggaran_id',
                'jenis_panjar',
                'tanggal_npd',
                'nomor_sp',
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

        $response = $this->actingAs($pptk)
            ->post(route('npd.pd.store'), $this->payload($masterAnggaran, $suratPerintah));

        $npd = Npd::with('tim.paket')->sole();

        $response->assertRedirect(route('npd.show', $npd));
        $this->assertSame('pd', $npd->jenis);
        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame('Panjar', $npd->jenis_panjar);
        $this->assertSame($pptk->id, $npd->dibuat_oleh);
        $this->assertSame($suratPerintah->id, $npd->surat_perintah_id);
        $this->assertSame(1_830_000.0, (float) $npd->nominal);
        $this->assertSame('001/SP/TEST/2026', $npd->detail_json['nomor_sp']);

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
