<?php

namespace Tests\Feature;

use App\Helpers\PejabatResolver;
use App\Models\DataTambahan;
use App\Models\Kpa;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelimpahanPejabatTest extends TestCase
{
    use RefreshDatabase;

    private int $urutanPegawai = 0;

    private function buatPegawai(string $nama, bool $aktif = true): Pegawai
    {
        $this->urutanPegawai++;

        return Pegawai::create([
            'nama' => $nama,
            'nip' => sprintf('19900101202001%04d', $this->urutanPegawai),
            'jabatan' => 'Pejabat Pengujian',
            'bidang' => 'Sekretariat',
            'pangkat' => 'Pembina',
            'aktif' => $aktif,
        ]);
    }

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function buatAnggaran(string $program, string $subKegiatan, string $kode): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => $program,
            'kegiatan' => 'Kegiatan '.$program,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kode,
            'uraian_rekening' => 'Belanja Pengujian',
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    private function buatNpd(MasterAnggaran $anggaran, string $jenis): Npd
    {
        return Npd::create([
            'jenis' => $jenis,
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);
    }

    public function test_hanya_superadmin_dapat_mengelola_pelimpahan(): void
    {
        $superadmin = $this->buatUser('superadmin', 'admin-pelimpahan');
        $pptk = $this->buatUser('pptk', 'non-admin-pelimpahan');
        $pa = $this->buatPegawai('PA Administrator');
        $bendaharaPengeluaran = $this->buatPegawai('Bendahara Pengeluaran Administrator');

        $this->actingAs($superadmin)->get(route('pelimpahan.index'))->assertOk();
        $this->actingAs($pptk)->get(route('pelimpahan.index'))->assertForbidden();
        $this->actingAs($pptk)->post(route('pelimpahan.opd.update'), [
            'pa_pegawai_id' => $pa->id,
            'bendahara_pengeluaran_pegawai_id' => $bendaharaPengeluaran->id,
        ])->assertForbidden();

        $this->actingAs($superadmin)->post(route('pelimpahan.opd.update'), [
            'pa_pegawai_id' => $pa->id,
            'bendahara_pengeluaran_pegawai_id' => $bendaharaPengeluaran->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PejabatOpd::where('aktif', true)->count());
    }

    public function test_penyimpanan_pejabat_opd_menyisakan_tepat_satu_konfigurasi_aktif(): void
    {
        $paLama = $this->buatPegawai('PA Lama');
        $bpLama = $this->buatPegawai('BP Lama');
        $paLain = $this->buatPegawai('PA Lain');
        $bpLain = $this->buatPegawai('BP Lain');
        $paBaru = $this->buatPegawai('PA Baru');
        $bpBaru = $this->buatPegawai('BP Baru');

        PejabatOpd::create(['pa_pegawai_id' => $paLama->id, 'bendahara_pengeluaran_pegawai_id' => $bpLama->id, 'aktif' => true]);
        PejabatOpd::create(['pa_pegawai_id' => $paLain->id, 'bendahara_pengeluaran_pegawai_id' => $bpLain->id, 'aktif' => false]);

        $hasil = PejabatOpd::simpan([
            'pa_pegawai_id' => $paBaru->id,
            'bendahara_pengeluaran_pegawai_id' => $bpBaru->id,
        ]);

        $this->assertSame(1, PejabatOpd::where('aktif', true)->count());
        $this->assertSame($hasil->id, PejabatOpd::aktif()?->id);
        $this->assertSame($paBaru->id, PejabatOpd::aktif()?->pa_pegawai_id);
        $this->assertSame($bpBaru->id, PejabatOpd::aktif()?->bendahara_pengeluaran_pegawai_id);
    }

    public function test_kpa_aktif_ganda_ditolak_dan_setiap_kpa_wajib_memiliki_bpp_aktif(): void
    {
        $admin = $this->buatUser('superadmin', 'admin-kpa');
        $pegawaiKpa = $this->buatPegawai('KPA Tunggal');
        $bppSatu = $this->buatPegawai('BPP Aktif Satu');
        $bppDua = $this->buatPegawai('BPP Aktif Dua');
        $bppNonaktif = $this->buatPegawai('BPP Nonaktif', false);

        $this->actingAs($admin)->post(route('pelimpahan.kpa.store'), [
            'kpa_pegawai_id' => $pegawaiKpa->id,
            'bpp_pegawai_id' => $bppSatu->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('pelimpahan.kpa.store'), [
            'kpa_pegawai_id' => $pegawaiKpa->id,
            'bpp_pegawai_id' => $bppDua->id,
        ])->assertSessionHasErrors('kpa_pegawai_id');

        $this->actingAs($admin)->post(route('pelimpahan.kpa.store'), [
            'kpa_pegawai_id' => $bppDua->id,
            'bpp_pegawai_id' => $bppNonaktif->id,
        ])->assertSessionHasErrors('bpp_pegawai_id');

        $this->assertSame(1, Kpa::where('aktif', true)->count());
        $this->assertNotNull(Kpa::where('aktif', true)->firstOrFail()->bpp_pegawai_id);
    }

    public function test_dua_kpa_dua_bpp_dan_pelimpahan_lintas_program_menentukan_tanda_tangan_semua_jenis_npd(): void
    {
        $kpaPegawaiSatu = $this->buatPegawai('KPA Program Satu');
        $bppPegawaiSatu = $this->buatPegawai('BPP Program Satu');
        $pptkSatu = $this->buatPegawai('PPTK Program Satu');
        $kpaPegawaiDua = $this->buatPegawai('KPA Program Dua');
        $bppPegawaiDua = $this->buatPegawai('BPP Program Dua');
        $pptkDua = $this->buatPegawai('PPTK Program Dua');

        $kpaSatu = Kpa::create(['kpa_pegawai_id' => $kpaPegawaiSatu->id, 'bpp_pegawai_id' => $bppPegawaiSatu->id, 'aktif' => true]);
        $kpaDua = Kpa::create(['kpa_pegawai_id' => $kpaPegawaiDua->id, 'bpp_pegawai_id' => $bppPegawaiDua->id, 'aktif' => true]);

        $subSatu = '6.01.01.2.01 Sub Kegiatan Program Satu';
        $subDua = '6.01.02.2.02 Sub Kegiatan Program Dua';
        $anggaranSatu = $this->buatAnggaran('Program Satu', "6.01.01.2.01  Sub\nKegiatan Program Satu", '5.1.01.01');
        $anggaranDua = $this->buatAnggaran('Program Dua', $subDua, '5.1.01.02');

        Pelimpahan::setBorongan([$subSatu, "  6.01.01.2.01 Sub\nKegiatan   Program Satu  "], $kpaSatu->id, $pptkSatu->id);
        Pelimpahan::setBorongan([$subDua], $kpaDua->id, $pptkDua->id);

        $this->assertSame(2, Pelimpahan::count(), 'Varian whitespace tidak boleh membuat pelimpahan duplikat.');

        foreach (['bj', 'tr', 'ns', 'kd'] as $jenis) {
            $pejabat = PejabatResolver::untukNpd($this->buatNpd($anggaranSatu, $jenis));
            $this->assertSame('KPA Program Satu', $pejabat['kpa']->nama);
            $this->assertSame('BPP Program Satu', $pejabat['bpp']->nama);
            $this->assertSame('PPTK Program Satu', $pejabat['pptk']->nama);
            $this->assertNull($pejabat['peringatan']);
        }

        $npdPd = $this->buatNpd($anggaranDua, 'pd');
        $pejabatPd = PejabatResolver::untukNpd($npdPd);
        $this->assertSame('KPA Program Dua', $pejabatPd['kpa']->nama);
        $this->assertSame('BPP Program Dua', $pejabatPd['bpp']->nama);
        $this->assertSame('PPTK Program Dua', $pejabatPd['pptk']->nama);
        $this->assertNull($pejabatPd['peringatan']);

        $htmlBj = $this->renderNpdUtama($this->buatNpd($anggaranSatu, 'bj'));
        $htmlPd = $this->renderNpdUtama($npdPd);
        $this->assertStringContainsString('KPA Program Satu', $htmlBj);
        $this->assertStringContainsString('PPTK Program Satu', $htmlBj);
        $this->assertStringContainsString('KPA Program Dua', $htmlPd);
        $this->assertStringContainsString('PPTK Program Dua', $htmlPd);
    }

    public function test_fallback_data_tambahan_dipertahankan_dengan_peringatan_yang_jelas(): void
    {
        $pptkLogin = $this->buatUser('pptk', 'pptk-fallback');
        $kpaFallback = $this->buatPegawai('KPA Fallback');
        $bppFallback = $this->buatPegawai('BPP Fallback');
        $pptkFallback = $this->buatPegawai('PPTK Fallback');
        $anggaran = $this->buatAnggaran('Program Fallback', '6.01.03.2.03 Sub Kegiatan Tanpa Pelimpahan', '5.1.01.03');

        DataTambahan::create([
            'program' => 'Program Fallback',
            'no_dpa' => 'DPA-FALLBACK',
            'kpa' => $kpaFallback->nama,
            'bpp' => $bppFallback->nama,
            'pptk' => $pptkFallback->nama,
        ]);

        $npd = $this->buatNpd($anggaran, 'bj');
        $pejabat = PejabatResolver::untukNpd($npd);

        $this->assertSame('KPA Fallback', $pejabat['kpa']->nama);
        $this->assertSame('BPP Fallback', $pejabat['bpp']->nama);
        $this->assertSame('PPTK Fallback', $pejabat['pptk']->nama);
        $this->assertStringContainsString('Data Tambahan', $pejabat['peringatan']);

        $this->actingAs($pptkLogin)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee('Pelimpahan belum diset untuk sub kegiatan ini')
            ->assertSee('Data Tambahan');

        $html = $this->renderNpdUtama($npd);
        $this->assertStringContainsString('KPA Fallback', $html);
        $this->assertStringContainsString('PPTK Fallback', $html);
    }

    private function renderNpdUtama(Npd $npd): string
    {
        $npd->load('masterAnggaran.tagging');
        $pejabat = PejabatResolver::untukNpd($npd);

        return view('npd.pdf.npd', [
            'npd' => $npd,
            'kpa' => $pejabat['kpa'],
            'pptk' => $pejabat['pptk'],
            'noDpa' => '',
            'sisaSebelum' => (float) $npd->masterAnggaran->pagu,
            'logoPath' => null,
        ])->render();
    }
}
