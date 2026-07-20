<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmTest extends TestCase
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
            'program' => 'Program Uji SPM',
            'kegiatan' => 'Kegiatan Uji SPM',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji SPM',
            'kode_rekening' => $kodeRekening,
            'uraian_rekening' => 'Belanja Pengujian SPM',
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    // ---------------- SPM UP/GU ----------------

    public function test_superadmin_dapat_membuat_mengedit_dan_menghapus_spm_up_gu(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-superadmin-upgu');

        $payload = [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '001/SPM-UP/2026',
            'tanggal_sp2d' => '2026-07-21',
            'nomor_sp2d' => 'SP2D-001',
            'nominal' => 5_000_000,
            'penerima' => 'BPP Uji',
            'uraian' => 'Pengisian UP tahap 1',
        ];

        $response = $this->actingAs($superadmin)->post(route('spm.up-gu.store'), $payload);
        $spm = Spm::firstOrFail();
        $response->assertRedirect(route('spm.up-gu.index'));

        $this->assertSame('up_gu', $spm->jenis_spm);
        $this->assertNull($spm->master_anggaran_id);
        $this->assertEquals(5_000_000.0, (float) $spm->nominal);

        $indexResponse = $this->actingAs($superadmin)->get(route('spm.up-gu.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('001/SPM-UP/2026');

        $updateResponse = $this->actingAs($superadmin)->put(route('spm.up-gu.update', $spm), array_merge($payload, [
            'nominal' => 6_000_000,
            'uraian' => 'Pengisian UP tahap 1 (revisi)',
        ]));
        $updateResponse->assertRedirect(route('spm.up-gu.index'));
        $this->assertEquals(6_000_000.0, (float) $spm->fresh()->nominal);
        $this->assertSame('Pengisian UP tahap 1 (revisi)', $spm->fresh()->uraian);

        $deleteResponse = $this->actingAs($superadmin)->delete(route('spm.destroy', $spm), ['_token' => 'x']);
        $deleteResponse->assertRedirect(route('spm.up-gu.index'));
        $this->assertSame(0, Spm::count());
    }

    public function test_up_gu_tidak_memilih_mata_anggaran_dan_tidak_mengurangi_pagu(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-upgu-pagu');
        $anggaran = $this->buatMasterAnggaran(1_000_000);

        $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '002/SPM-UP/2026',
            'nominal' => 50_000_000,
            'uraian' => 'UP besar, tidak boleh terpengaruh pagu',
        ])->assertRedirect(route('spm.up-gu.index'));

        // Nominal SPM UP/GU jauh melebihi pagu (1.000.000) tapi tetap diterima
        // dan tidak memengaruhi sisa tersedia mata anggaran manapun.
        $this->assertEquals(1_000_000.0, $anggaran->fresh()->sisaTersedia());
    }

    // ---------------- SPM LS ----------------

    public function test_bendahara_pengeluaran_dapat_membuat_mengedit_dan_menghapus_spm_ls(): void
    {
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'spm-bendahara-ls');
        $anggaran = $this->buatMasterAnggaran(10_000_000);

        $payload = [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '001/SPM-LS/2026',
            'master_anggaran_id' => $anggaran->id,
            'nominal' => 3_000_000,
            'ppn' => 100_000,
            'jenis_pph1' => 'PPh Pasal 22',
            'pph1' => 50_000,
            'penerima' => 'CV Uji Jaya',
            'uraian' => 'Pengadaan barang uji',
        ];

        $response = $this->actingAs($bendahara)->post(route('spm.ls.store'), $payload);
        $spm = Spm::firstOrFail();
        $response->assertRedirect(route('spm.ls.index'));

        $this->assertSame('ls', $spm->jenis_spm);
        $this->assertSame($anggaran->id, $spm->master_anggaran_id);
        $this->assertEquals(3_000_000.0, (float) $spm->nominal);
        $this->assertEquals(7_000_000.0, $anggaran->fresh()->sisaTersedia());

        $indexResponse = $this->actingAs($bendahara)->get(route('spm.ls.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('001/SPM-LS/2026');
        $indexResponse->assertSee('CV Uji Jaya');

        // Edit: menaikkan nominal masih dalam sisa (setelah nominal lama dikembalikan).
        $updateResponse = $this->actingAs($bendahara)->put(route('spm.ls.update', $spm), array_merge($payload, [
            'nominal' => 4_000_000,
        ]));
        $updateResponse->assertRedirect(route('spm.ls.index'));
        $this->assertEquals(4_000_000.0, (float) $spm->fresh()->nominal);
        $this->assertEquals(6_000_000.0, $anggaran->fresh()->sisaTersedia());

        // Hapus/edit LS otomatis memengaruhi agregasi karena realisasi dihitung dari transaksi.
        $this->actingAs($bendahara)->delete(route('spm.destroy', $spm), ['_token' => 'x'])
            ->assertRedirect(route('spm.ls.index'));
        $this->assertSame(0, Spm::count());
        $this->assertEquals(10_000_000.0, $anggaran->fresh()->sisaTersedia());
    }

    public function test_spm_ls_nominal_melebihi_sisa_tersedia_ditolak_saat_buat_dan_edit(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-ls-tolak');
        $anggaran = $this->buatMasterAnggaran(1_000_000);

        $response = $this->actingAs($superadmin)->post(route('spm.ls.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '002/SPM-LS/2026',
            'master_anggaran_id' => $anggaran->id,
            'nominal' => 1_500_000,
            'uraian' => 'Melebihi sisa tersedia',
        ]);
        $response->assertSessionHasErrors(['nominal']);
        $this->assertSame(0, Spm::count());

        // Simpan SPM LS yang sah dulu, lalu coba edit jadi melebihi sisa.
        $spm = Spm::buatLs([
            'nomor_dokumen' => '003/SPM-LS/2026',
            'tanggal_dokumen' => '2026-07-20',
            'master_anggaran_id' => $anggaran->id,
            'nominal' => 800_000,
        ]);

        $editResponse = $this->actingAs($superadmin)->put(route('spm.ls.update', $spm), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '003/SPM-LS/2026',
            'master_anggaran_id' => $anggaran->id,
            'nominal' => 1_200_000,
            'uraian' => 'Revisi melebihi sisa',
        ]);
        $editResponse->assertSessionHasErrors(['nominal']);
        $this->assertEquals(800_000.0, (float) $spm->fresh()->nominal);
    }

    public function test_form_ls_menampilkan_pagu_dana_terikat_realisasi_dan_sisa_tersedia(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-ls-form');
        $anggaran = $this->buatMasterAnggaran(10_000_000);

        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 2_000_000,
            'terbilang' => 'dua juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);

        $response = $this->actingAs($superadmin)->get(route('spm.ls.create'));
        $response->assertOk();
        $response->assertSee('"pagu":10000000', false);
        $response->assertSee('"dana_terikat":2000000', false);
        $response->assertSee('"sisa":8000000', false);
    }

    // ---------------- Duplikasi nomor ----------------

    public function test_nomor_spm_duplikat_pada_jenis_dan_tanggal_sama_ditolak_tetapi_beda_jenis_atau_tanggal_boleh(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-duplikat');
        $anggaran = $this->buatMasterAnggaran();

        $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '010/SPM/2026',
            'nominal' => 1_000_000,
            'uraian' => 'Pertama',
        ])->assertRedirect(route('spm.up-gu.index'));

        // Nomor + jenis + tanggal sama persis -> ditolak.
        $dupResponse = $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '010/SPM/2026',
            'nominal' => 2_000_000,
            'uraian' => 'Duplikat',
        ]);
        $dupResponse->assertSessionHasErrors(['nomor_dokumen']);
        $this->assertSame(1, Spm::where('nomor_dokumen', '010/SPM/2026')->count());

        // Nomor sama tapi tanggal beda -> boleh.
        $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [
            'tanggal_dokumen' => '2026-07-21',
            'nomor_dokumen' => '010/SPM/2026',
            'nominal' => 1_500_000,
            'uraian' => 'Tanggal beda',
        ])->assertRedirect(route('spm.up-gu.index'));

        // Nomor sama, tanggal sama, tapi jenis LS -> boleh (jenis berbeda).
        $this->actingAs($superadmin)->post(route('spm.ls.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '010/SPM/2026',
            'master_anggaran_id' => $anggaran->id,
            'nominal' => 500_000,
            'uraian' => 'Jenis beda',
        ])->assertRedirect(route('spm.ls.index'));

        $this->assertSame(3, Spm::where('nomor_dokumen', '010/SPM/2026')->count());
    }

    public function test_edit_spm_tidak_menganggap_dirinya_sendiri_sebagai_duplikat(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-edit-sendiri');

        $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '011/SPM/2026',
            'nominal' => 1_000_000,
            'uraian' => 'Awal',
        ]);
        $spm = Spm::firstOrFail();

        $response = $this->actingAs($superadmin)->put(route('spm.up-gu.update', $spm), [
            'tanggal_dokumen' => '2026-07-20',
            'nomor_dokumen' => '011/SPM/2026',
            'nominal' => 1_200_000,
            'uraian' => 'Diperbarui, nomor & tanggal tetap sama',
        ]);

        $response->assertRedirect(route('spm.up-gu.index'));
        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(1_200_000.0, (float) $spm->fresh()->nominal);
    }

    // ---------------- Akses role ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_boleh_akses_spm(): void
    {
        $pptk = $this->buatUser('pptk', 'spm-akses-pptk');
        $bpp = $this->buatUser('bpp', 'spm-akses-bpp');
        $superadmin = $this->buatUser('superadmin', 'spm-akses-superadmin');
        $bendahara = $this->buatUser('bendahara_pengeluaran', 'spm-akses-bendahara');

        $this->actingAs($superadmin)->get(route('spm.up-gu.index'))->assertOk();
        $this->actingAs($bendahara)->get(route('spm.up-gu.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('spm.ls.index'))->assertOk();
        $this->actingAs($bendahara)->get(route('spm.ls.index'))->assertOk();

        $this->actingAs($pptk)->get(route('spm.up-gu.index'))->assertForbidden();
        $this->actingAs($bpp)->get(route('spm.ls.index'))->assertForbidden();
        $this->actingAs($pptk)->post(route('spm.up-gu.store'), [])->assertForbidden();
        $this->actingAs($bpp)->post(route('spm.ls.store'), [])->assertForbidden();
    }

    // ---------------- Validasi ----------------

    public function test_validasi_gagal_tanpa_field_wajib(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-validasi');

        $this->actingAs($superadmin)->post(route('spm.up-gu.store'), [])
            ->assertSessionHasErrors(['tanggal_dokumen', 'nomor_dokumen', 'nominal']);

        $this->actingAs($superadmin)->post(route('spm.ls.store'), [])
            ->assertSessionHasErrors(['tanggal_dokumen', 'nomor_dokumen', 'master_anggaran_id', 'nominal']);

        $this->assertSame(0, Spm::count());
    }

    public function test_edit_route_menolak_jenis_yang_salah(): void
    {
        $superadmin = $this->buatUser('superadmin', 'spm-jenis-salah');

        $spm = Spm::buatUpGu([
            'nomor_dokumen' => '020/SPM/2026',
            'tanggal_dokumen' => '2026-07-20',
            'nominal' => 100_000,
        ]);

        $this->actingAs($superadmin)->get(route('spm.ls.edit', $spm))->assertNotFound();
    }
}
