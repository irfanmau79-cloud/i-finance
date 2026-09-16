<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Spm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validasi SPM: penanda bahwa angkanya sudah dicocokkan dengan berkas SP2D
 * asli, dan sejak itu barisnya TIDAK BOLEH dihapus.
 *
 * Yang dijaga di sini bukan tombolnya, tapi rutenya: realisasi anggaran
 * dihitung dari baris-baris SPM, jadi baris tervalidasi yang hilang berarti
 * angka realisasi berubah tanpa jejak. Tombol yang disembunyikan tidak
 * menghalangi siapa pun memanggil DELETE-nya langsung.
 */
class SpmValidasiTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::create([
            'username' => 'bp-spm-validasi',
            'nama' => 'BP SPM',
            'role' => User::ROLE_BENDAHARA_PENGELUARAN,
            'password' => 'rahasia',
        ]);
    }

    public function test_spm_ls_bisa_divalidasi_dan_tercatat_siapa_serta_kapan(): void
    {
        $spm = $this->spmLs();

        $this->actingAs($this->bendahara)
            ->post(route('spm.validasi', $spm))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $spm->refresh();
        $this->assertTrue($spm->divalidasi());
        $this->assertSame($this->bendahara->id, $spm->divalidasi_oleh);
        $this->assertNotNull($spm->divalidasi_at);
    }

    public function test_spm_tervalidasi_tidak_bisa_dihapus_walau_rutenya_dipanggil_langsung(): void
    {
        $spm = $this->spmLs();
        $this->actingAs($this->bendahara)->post(route('spm.validasi', $spm));

        $this->actingAs($this->bendahara)
            ->delete(route('spm.destroy', $spm))
            ->assertRedirect()
            ->assertSessionHasErrors('spm');

        $this->assertNotNull(Spm::find($spm->id), 'SPM tervalidasi ikut terhapus.');
    }

    public function test_spm_belum_divalidasi_tetap_bisa_dihapus(): void
    {
        $spm = $this->spmLs();

        $this->actingAs($this->bendahara)
            ->delete(route('spm.destroy', $spm))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Spm::find($spm->id));
    }

    /** Validasi ganda tidak menimpa nama & waktu validasi yang pertama. */
    public function test_validasi_kedua_tidak_menimpa_pencatatan_yang_pertama(): void
    {
        $spm = $this->spmLs();
        $this->actingAs($this->bendahara)->post(route('spm.validasi', $spm));
        $pertama = $spm->fresh();

        $lain = User::create([
            'username' => 'superadmin-spm-validasi',
            'nama' => 'Superadmin SPM',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia',
        ]);

        $this->actingAs($lain)->post(route('spm.validasi', $spm))->assertRedirect();

        $this->assertSame($pertama->divalidasi_oleh, $spm->fresh()->divalidasi_oleh);
        $this->assertSame(
            $pertama->divalidasi_at->toDateTimeString(),
            $spm->fresh()->divalidasi_at->toDateTimeString(),
        );
    }

    /** Batal validasi adalah jalan keluar untuk klik keliru - hanya superadmin. */
    public function test_hanya_superadmin_boleh_membatalkan_validasi(): void
    {
        $spm = $this->spmLs();
        $this->actingAs($this->bendahara)->post(route('spm.validasi', $spm));

        $this->actingAs($this->bendahara)
            ->delete(route('spm.validasi.batal', $spm))
            ->assertForbidden();
        $this->assertTrue($spm->fresh()->divalidasi());

        $superadmin = User::create([
            'username' => 'superadmin-batal-validasi',
            'nama' => 'Superadmin',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia',
        ]);

        $this->actingAs($superadmin)
            ->delete(route('spm.validasi.batal', $spm))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($spm->fresh()->divalidasi());
        // Sesudah dibatalkan, barisnya boleh dihapus lagi.
        $this->actingAs($superadmin)->delete(route('spm.destroy', $spm))->assertSessionHasNoErrors();
        $this->assertNull(Spm::find($spm->id));
    }

    public function test_halaman_detail_menampilkan_seluruh_mata_anggaran_dan_status_validasi(): void
    {
        $anggaranB = $this->anggaran('5.1.02.02.001.00003', 'Honorarium Narasumber');
        $spm = $this->spmLs([
            ['master_anggaran_id' => $this->anggaran()->id, 'nominal' => 1_000_000],
            ['master_anggaran_id' => $anggaranB->id, 'nominal' => 500_000],
        ]);

        $this->actingAs($this->bendahara)->get(route('spm.ls.show', $spm))
            ->assertOk()
            ->assertSee('Belum divalidasi')
            ->assertSee('Mata Anggaran (2)')
            ->assertSee('Honorarium Narasumber')
            ->assertSee('1.500.000,00');

        $this->actingAs($this->bendahara)->post(route('spm.validasi', $spm));

        $this->actingAs($this->bendahara)->get(route('spm.ls.show', $spm))
            ->assertOk()
            ->assertSee('Tervalidasi')
            ->assertDontSee('Belum divalidasi');
    }

    /** SPM UP/GU tidak punya baris mata anggaran, jadi bukan urusan halaman rincian LS. */
    public function test_detail_ls_menolak_spm_up_gu(): void
    {
        $upGu = Spm::buatUpGu([
            'nomor_dokumen' => '001/SPM-UP/2026',
            'tanggal_dokumen' => '2026-07-10',
            'nominal' => 5_000_000,
        ]);

        $this->actingAs($this->bendahara)->get(route('spm.ls.show', $upGu))->assertNotFound();
    }

    /**
     * Rute rincian terdaftar SEBELUM /spm/ls/create, jadi tanpa batasan angka
     * pada parameternya, "create" akan tertangkap sebagai id SPM dan formulir
     * tambah SPM LS berubah jadi 404.
     */
    public function test_formulir_tambah_tidak_tertangkap_oleh_rute_rincian(): void
    {
        $this->actingAs($this->bendahara)->get(route('spm.ls.create'))->assertOk();
    }

    /** Daftar: baris tervalidasi kehilangan tombol hapus, yang belum tetap punya. */
    public function test_daftar_menyembunyikan_tombol_hapus_pada_baris_tervalidasi(): void
    {
        $spm = $this->spmLs();

        $this->actingAs($this->bendahara)->get(route('spm.ls.index'))
            ->assertOk()
            ->assertSee(route('spm.destroy', $spm), false)
            ->assertSee(route('spm.validasi', $spm), false);

        $this->actingAs($this->bendahara)->post(route('spm.validasi', $spm));

        $sesudah = $this->actingAs($this->bendahara)->get(route('spm.ls.index'))->assertOk();
        $sesudah->assertDontSee(route('spm.destroy', $spm), false);
        $sesudah->assertSee('Tervalidasi');
        // Lihat Detail tetap ada untuk baris tervalidasi.
        $sesudah->assertSee(route('spm.ls.show', $spm), false);
    }

    /**
     * Bug tampilan yang diperbaiki: karet pemicu rincian dulu disusun ulang
     * dari teks tiap klik dengan regex yang mencari aksara panah yang BERBEDA
     * dari yang dicetak, sehingga panahnya menumpuk tiap kali diklik.
     * Sekarang arahnya murni dari aria-expanded + CSS.
     */
    public function test_pemicu_rincian_tidak_lagi_menyusun_panah_sebagai_teks(): void
    {
        $anggaranB = $this->anggaran('5.1.02.02.001.00003', 'Honorarium Narasumber');
        $spm = $this->spmLs([
            ['master_anggaran_id' => $this->anggaran()->id, 'nominal' => 1_000_000],
            ['master_anggaran_id' => $anggaranB->id, 'nominal' => 500_000],
        ]);

        $isi = $this->actingAs($this->bendahara)->get(route('spm.ls.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-spm-toggle="spm-ls-'.$spm->id.'"', $isi);
        $this->assertStringContainsString('aria-expanded="false"', $isi);
        // Tidak ada lagi aksara panah yang dicetak sebagai teks, dan tidak ada
        // lagi penyusunan ulang innerHTML karet.
        $this->assertStringNotContainsString('&#9656;', $isi);
        $this->assertStringNotContainsString('&#9662;', $isi);
        $this->assertStringNotContainsString('caret.innerHTML', $isi);
    }

    /** UP/GU: aksi harus berupa ikon, bukan tombol berteks yang tidak muat di kolomnya. */
    public function test_aksi_up_gu_berupa_ikon(): void
    {
        Spm::buatUpGu([
            'nomor_dokumen' => '002/SPM-UP/2026',
            'tanggal_dokumen' => '2026-07-11',
            'nominal' => 3_000_000,
        ]);

        $isi = $this->actingAs($this->bendahara)->get(route('spm.up-gu.index'))->assertOk()->getContent();

        $kolomAksi = substr($isi, (int) strpos($isi, 'class="spm-aksi"'));
        $kolomAksi = substr($kolomAksi, 0, 1800);

        $this->assertStringContainsString('class="ic-btn"', $kolomAksi);
        $this->assertStringContainsString('class="ic-btn danger"', $kolomAksi);
        $this->assertStringContainsString('<svg', $kolomAksi);
        // Tidak ada lagi tombol berteks.
        $this->assertStringNotContainsString('class="btn">Edit', $kolomAksi);
        $this->assertStringNotContainsString('class="btn">Hapus', $kolomAksi);
    }

    // ----------------------------------------------------------------- bantu

    private function anggaran(string $kode = '5.1.02.01.001.00052', string $uraian = 'Belanja Pengujian'): MasterAnggaran
    {
        return MasterAnggaran::firstOrCreate(
            ['kode_rekening' => MasterAnggaran::gabungKodeUraian($kode, $uraian)],
            [
                'program' => 'Program SPM',
                'kegiatan' => 'Kegiatan SPM',
                'sub_kegiatan' => '6.01.01.1.01.0001 Sub Kegiatan SPM',
                'kode_sub_kegiatan' => '6.01.01.1.01.0001',
                'pagu' => 50_000_000,
                'aktif' => true,
            ],
        );
    }

    /** @param  array<int, array<string, mixed>>|null  $baris */
    private function spmLs(?array $baris = null): Spm
    {
        static $urut = 0;
        $urut++;

        return Spm::buatLs([
            'nomor_dokumen' => sprintf('%03d/SPM-LS/2026', $urut),
            'tanggal_dokumen' => '2026-07-15',
            'penerima' => 'PT Penyedia Pengujian',
            'baris' => $baris ?? [['master_anggaran_id' => $this->anggaran()->id, 'nominal' => 1_000_000]],
        ]);
    }
}
