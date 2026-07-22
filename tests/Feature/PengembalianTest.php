<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pengembalian;
use App\Models\Spm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengembalianTest extends TestCase
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

    private function buatMasterAnggaran(float $pagu = 10_000_000, string $kodeRekening = '5.1.02.05.01.0001'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Pengembalian',
            'kegiatan' => 'Kegiatan Uji Pengembalian',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Pengembalian',
            'kode_rekening' => $kodeRekening,
            'uraian_rekening' => 'Belanja Pengujian Pengembalian',
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatNpdSelesai(MasterAnggaran $anggaran, float $nominal, string $tanggal = '2026-07-10'): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => (int) substr($tanggal, 5, 2),
            'tahun' => (int) substr($tanggal, 0, 4),
            'tanggal_npd' => $tanggal,
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'nilai pengujian rupiah',
            'status' => 'Selesai',
        ]);
    }

    private function buatSpmLs(MasterAnggaran $anggaran, float $nominal, string $nomorDokumen, string $tanggal = '2026-07-10'): Spm
    {
        return Spm::buatLs([
            'nomor_dokumen' => $nomorDokumen,
            'tanggal_dokumen' => $tanggal,
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => $nominal]],
        ]);
    }

    private function fileValid(): UploadedFile
    {
        return UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');
    }

    // ---------------- UJI 1 & 2: breakdown draft dari NPD dan SPM LS ----------------

    public function test_draft_dari_npd_selesai_breakdown_menampilkan_mata_anggaran_dan_nominal_asli(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-1');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 3_000_000);

        Storage::fake('local');
        $response = $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 500_000]],
            'keterangan' => 'Sisa panjar dikembalikan',
        ]);

        $response->assertRedirect(route('pengembalian.index'));
        $pengembalian = Pengembalian::firstOrFail();
        $this->assertSame('npd', $pengembalian->dokumen_tipe);
        $this->assertSame($npd->id, $pengembalian->dokumen_id);
        $this->assertSame('draft', $pengembalian->status);
        $this->assertSame(1, $pengembalian->detail()->count());
        $this->assertSame($anggaran->id, $pengembalian->detail->first()->master_anggaran_id);
        $this->assertEquals(500_000.0, $pengembalian->totalNominal());

        // Breakdown dari halaman create menampilkan nominal ASLI dokumen (3jt), bukan yang dikembalikan.
        $createResponse = $this->actingAs($bpp)->get(route('pengembalian.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('"nominal_asli":3000000', false);
    }

    public function test_draft_dari_spm_ls_breakdown_benar(): void
    {
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-1');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $spm = $this->buatSpmLs($anggaran, 2_000_000, '001/SPM-LS/PENG');

        Storage::fake('local');
        $response = $this->actingAs($bendahara)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'spm_ls',
            'dokumen_id' => $spm->id,
            'tanggal_pengembalian' => '2026-07-16',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 400_000]],
        ]);

        $response->assertRedirect(route('pengembalian.index'));
        $pengembalian = Pengembalian::firstOrFail();
        $this->assertSame('spm_ls', $pengembalian->dokumen_tipe);
        $this->assertSame($spm->id, $pengembalian->dokumen_id);
        $this->assertEquals(400_000.0, $pengembalian->totalNominal());

        $createResponse = $this->actingAs($bendahara)->get(route('pengembalian.create'));
        $createResponse->assertOk()->assertSee('"nominal_asli":2000000', false);
    }

    // ---------------- UJI 3: melebihi nominal asli ----------------

    public function test_nominal_melebihi_nominal_asli_dokumen_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-2');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        $response = $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_500_000]],
        ]);

        $response->assertSessionHasErrors(['baris']);
        $this->assertSame(0, Pengembalian::count());
    }

    // ---------------- UJI 4: parsial berturut-turut, kumulatif melebihi asli ----------------

    public function test_dua_pengembalian_parsial_berturut_turut_total_melebihi_asli_pengembalian_kedua_ditolak(): void
    {
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-2');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        $pertama = Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-11',
            'dokumen_pendukung' => 'pengembalian/uji-bukti.pdf',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 600_000]],
        ], $bendahara->id);
        $pertama->setujui($bendahara);

        // Kedua: 600rb (sudah disetujui) + 500rb (baru) = 1.1jt > nominal asli 1jt -> ditolak.
        $response = $this->actingAs($bendahara)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-12',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 500_000]],
        ]);
        $response->assertSessionHasErrors(['baris']);
        $this->assertSame(1, Pengembalian::count());

        // Tapi 400rb (600rb + 400rb = 1jt, pas nominal asli) masih boleh.
        $response2 = $this->actingAs($bendahara)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-12',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 400_000]],
        ]);
        $response2->assertRedirect(route('pengembalian.index'));
        $this->assertSame(2, Pengembalian::count());
    }

    // ---------------- UJI 5: setujui menurunkan realisasi, menaikkan sisa (via Rincian Realisasi) ----------------

    public function test_bpp_draft_bendahara_setujui_realisasi_turun_sisa_naik_dicek_lewat_rincian_realisasi(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-3');
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-3');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 3_000_000);

        Storage::fake('local');
        $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
            'dokumen_pendukung' => $this->fileValid(),
        ]);
        $pengembalian = Pengembalian::firstOrFail();

        $this->assertEquals(3_000_000.0, $anggaran->fresh()->realisasiAktual());
        $this->assertEquals(7_000_000.0, $anggaran->fresh()->sisaTersedia());

        $setujuiResponse = $this->actingAs($bendahara)->post(route('pengembalian.setujui', $pengembalian));
        $setujuiResponse->assertRedirect();

        $this->assertEquals(2_000_000.0, $anggaran->fresh()->realisasiAktual());
        $this->assertEquals(8_000_000.0, $anggaran->fresh()->sisaTersedia());

        $response = $this->actingAs($bendahara)->get(route('rincian.index'));
        $response->assertOk();
        $response->assertViewHas('tree', function ($tree) {
            $angka = $tree->first()['angka'];

            return $angka['realisasi_aktual'] === 2_000_000.0
                && $angka['sisa_tersedia'] === 8_000_000.0;
        });
    }

    // ---------------- UJI 6: draft tidak mempengaruhi realisasi ----------------

    public function test_draft_belum_disetujui_tidak_mempengaruhi_realisasi_sama_sekali(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-4');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 3_000_000);

        Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 1_000_000]],
        ], $bpp->id);

        $this->assertEquals(3_000_000.0, $anggaran->fresh()->realisasiAktual());
        $this->assertEquals(7_000_000.0, $anggaran->fresh()->sisaTersedia());
    }

    // ---------------- UJI 7: setujui tanpa dokumen pendukung ----------------

    public function test_setujui_tanpa_dokumen_pendukung_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-5');
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-5');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        $pengembalian = Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 300_000]],
        ], $bpp->id);

        $this->assertNull($pengembalian->dokumen_pendukung);

        $response = $this->actingAs($bendahara)->post(route('pengembalian.setujui', $pengembalian));
        $response->assertSessionHasErrors(['pengembalian']);
        $this->assertSame('draft', $pengembalian->fresh()->status);
    }

    // ---------------- UJI 8: bpp mencoba menyetujui ----------------

    public function test_bpp_mencoba_menyetujui_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-6');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        $pengembalian = Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 300_000]],
        ], $bpp->id);

        $this->actingAs($bpp)->post(route('pengembalian.setujui', $pengembalian))->assertForbidden();
        $this->assertSame('draft', $pengembalian->fresh()->status);
    }

    // ---------------- UJI 9: role lain tidak bisa akses menu ----------------

    public function test_role_selain_bp_dan_bpp_tidak_bisa_akses_menu_pengembalian_sama_sekali(): void
    {
        $pptk = $this->buatUser('pptk', 'peng-pptk');
        $verifikator = $this->buatUser('verifikator', 'peng-verifikator');

        foreach ([$pptk, $verifikator] as $user) {
            $this->actingAs($user)->get(route('pengembalian.index'))->assertForbidden();
            $this->actingAs($user)->get(route('pengembalian.create'))->assertForbidden();
            $this->actingAs($user)->post(route('pengembalian.store'), [])->assertForbidden();
        }

        // superadmin, bendahara_pengeluaran, bpp semua boleh mengakses (bukan menyetujui) daftar.
        foreach ([
            $this->buatUser('superadmin', 'peng-superadmin'),
            $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-9'),
            $this->buatUser('bpp', 'peng-bpp-9'),
        ] as $user) {
            $this->actingAs($user)->get(route('pengembalian.index'))->assertOk();
        }
    }

    // ---------------- UJI 10: upload file tidak valid ----------------

    private function payloadDasarPengembalian(MasterAnggaran $anggaran, Npd $npd): array
    {
        return [
            'dokumen_tipe' => 'npd',
            'dokumen_id' => $npd->id,
            'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 300_000]],
        ];
    }

    public function test_upload_jenis_file_selain_jpg_png_pdf_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-7');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        Storage::fake('local');

        $this->actingAs($bpp)->post(route('pengembalian.store'), $this->payloadDasarPengembalian($anggaran, $npd) + [
            'dokumen_pendukung' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
        ])->assertSessionHasErrors(['dokumen_pendukung']);

        $this->assertSame(0, Pengembalian::count());
    }

    public function test_upload_file_melebihi_batas_ukuran_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-7b');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        Storage::fake('local');

        $this->actingAs($bpp)->post(route('pengembalian.store'), $this->payloadDasarPengembalian($anggaran, $npd) + [
            'dokumen_pendukung' => UploadedFile::fake()->create('terlalu-besar.pdf', 5200, 'application/pdf'),
        ])->assertSessionHasErrors(['dokumen_pendukung']);

        $this->assertSame(0, Pengembalian::count());
    }

    public function test_upload_file_sah_diterima_dan_tersimpan_private(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-7c');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        Storage::fake('local');

        $ok = $this->actingAs($bpp)->post(route('pengembalian.store'), $this->payloadDasarPengembalian($anggaran, $npd) + [
            'dokumen_pendukung' => $this->fileValid(),
        ]);
        $ok->assertRedirect(route('pengembalian.index'));
        $pengembalian = Pengembalian::firstOrFail();
        $this->assertNotNull($pengembalian->dokumen_pendukung);
        Storage::disk('local')->assertExists($pengembalian->dokumen_pendukung);
    }

    // ---------------- Tambahan: SPM UP/GU dan NPD belum Selesai tidak bisa jadi sumber ----------------

    public function test_spm_up_gu_dan_npd_belum_selesai_tidak_bisa_jadi_dokumen_sumber(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-8');
        $anggaran = $this->buatMasterAnggaran(10_000_000);

        $upGu = Spm::buatUpGu([
            'nomor_dokumen' => '900/SPM-UP/2026', 'tanggal_dokumen' => '2026-07-10', 'nominal' => 1_000_000,
        ]);
        $npdDraft = Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '1', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-10', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 500_000,
            'terbilang' => 'nilai pengujian rupiah', 'status' => 'Draft NPD - PPTK',
        ]);

        $responseUpGu = $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'spm_ls', 'dokumen_id' => $upGu->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 100_000]],
        ]);
        $responseUpGu->assertSessionHasErrors(['baris']);

        $responseDraft = $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npdDraft->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 100_000]],
        ]);
        $responseDraft->assertSessionHasErrors(['baris']);

        $this->assertSame(0, Pengembalian::count());
    }

    // ---------------- Tambahan: hapus draft ----------------

    public function test_hapus_draft_oleh_pembuat_atau_bendahara_pengeluaran_boleh_role_lain_tidak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-10');
        $bppLain = $this->buatUser('bpp', 'peng-bpp-11');
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'peng-bendahara-10');
        $anggaran = $this->buatMasterAnggaran(10_000_000);
        $npd = $this->buatNpdSelesai($anggaran, 1_000_000);

        $milikBpp = Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 100_000]],
        ], $bpp->id);

        // BPP lain (bukan pembuat, bukan bendahara) tidak boleh menghapus.
        $this->actingAs($bppLain)->delete(route('pengembalian.destroy', $milikBpp))->assertForbidden();
        $this->assertNotNull($milikBpp->fresh());

        // Pembuatnya sendiri boleh menghapus.
        $this->actingAs($bpp)->delete(route('pengembalian.destroy', $milikBpp))->assertRedirect();
        $this->assertNull(Pengembalian::find($milikBpp->id));

        // Bendahara Pengeluaran boleh menghapus draft siapa pun.
        $milikBpp2 = Pengembalian::buatDraft([
            'dokumen_tipe' => 'npd', 'dokumen_id' => $npd->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 100_000]],
        ], $bpp->id);
        $this->actingAs($bendahara)->delete(route('pengembalian.destroy', $milikBpp2))->assertRedirect();
        $this->assertNull(Pengembalian::find($milikBpp2->id));
    }

    // ---------------- Tambahan: mata anggaran duplikat dalam satu pengembalian ----------------

    public function test_mata_anggaran_duplikat_dalam_satu_pengembalian_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'peng-bpp-12');
        $a1 = $this->buatMasterAnggaran(10_000_000, '5.1.02.05.01.0021');
        $a2 = $this->buatMasterAnggaran(10_000_000, '5.1.02.05.01.0022');
        $spm = Spm::buatLs([
            'nomor_dokumen' => '910/SPM-LS/2026', 'tanggal_dokumen' => '2026-07-10',
            'baris' => [
                ['master_anggaran_id' => $a1->id, 'nominal' => 1_000_000],
                ['master_anggaran_id' => $a2->id, 'nominal' => 2_000_000],
            ],
        ]);

        $response = $this->actingAs($bpp)->post(route('pengembalian.store'), [
            'dokumen_tipe' => 'spm_ls', 'dokumen_id' => $spm->id, 'tanggal_pengembalian' => '2026-07-15',
            'baris' => [
                ['master_anggaran_id' => $a1->id, 'nominal' => 100_000],
                ['master_anggaran_id' => $a1->id, 'nominal' => 200_000],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, Pengembalian::count());
    }
}
