<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role): User
    {
        return User::create([
            'username' => 'matrix-'.$role,
            'nama' => 'Matrix '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatNpd(string $status = 'Draft NPD - PPTK'): Npd
    {
        $anggaran = MasterAnggaran::create([
            'program' => 'Program Matriks Role',
            'kegiatan' => 'Kegiatan Matriks Role',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Matriks Role',
            'kode_rekening' => '5.1.02.01.01.0999 Belanja Pengujian Role',
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => $status,
        ]);
    }

    public function test_superadmin_memiliki_akses_administratif_dan_override_workflow(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $npd = $this->buatNpd();

        $this->actingAs($superadmin)->get(route('users.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('pelimpahan.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.bj.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.persetujuan'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.verifikasi'))->assertOk();
        $this->actingAs($superadmin)->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Draft NPD - BPP', $npd->fresh()->status);
    }

    public function test_bendahara_pengeluaran_dapat_memantau_semua_npd_tanpa_hak_buat_atau_workflow(): void
    {
        $bendaharaPengeluaran = $this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN);
        $npd = $this->buatNpd();

        $this->actingAs($bendaharaPengeluaran)->get(route('npd.index'))
            ->assertOk()
            ->assertSee($npd->status)
            ->assertDontSee('+ NPD Barang/Jasa');
        $this->actingAs($bendaharaPengeluaran)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertDontSee('Ajukan ke BPP');

        $this->actingAs($bendaharaPengeluaran)->get(route('npd.bj.create'))->assertForbidden();
        $this->actingAs($bendaharaPengeluaran)->get(route('npd.persetujuan'))->assertForbidden();
        $this->actingAs($bendaharaPengeluaran)->get(route('npd.verifikasi'))->assertForbidden();
        $this->actingAs($bendaharaPengeluaran)->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])->assertForbidden();
        $this->actingAs($bendaharaPengeluaran)->get(route('users.index'))->assertForbidden();
        $this->actingAs($bendaharaPengeluaran)->get(route('pelimpahan.index'))->assertForbidden();

        $this->assertSame('Draft NPD - PPTK', $npd->fresh()->status);
        $this->assertContains('spm', config('akses.menu.bendahara_pengeluaran'));
        $this->assertContains('manajemen-data', config('akses.menu.bendahara_pengeluaran'));
    }

    public function test_pptk_bpp_dan_verifikator_hanya_mendapat_akses_workflow_masing_masing(): void
    {
        $pptk = $this->buatUser(User::ROLE_PPTK);
        $bpp = $this->buatUser(User::ROLE_BPP);
        $verifikator = $this->buatUser(User::ROLE_VERIFIKATOR);

        $this->actingAs($pptk)->get(route('npd.bj.create'))->assertOk();
        $this->actingAs($pptk)->get(route('npd.persetujuan'))->assertForbidden();
        $this->actingAs($pptk)->get(route('npd.verifikasi'))->assertForbidden();

        $this->actingAs($bpp)->get(route('npd.persetujuan'))->assertOk();
        $this->actingAs($bpp)->get(route('npd.bj.create'))->assertForbidden();
        $this->actingAs($bpp)->get(route('npd.verifikasi'))->assertForbidden();

        $this->actingAs($verifikator)->get(route('npd.verifikasi'))->assertOk();
        $this->actingAs($verifikator)->get(route('npd.bj.create'))->assertForbidden();
        $this->actingAs($verifikator)->get(route('npd.persetujuan'))->assertForbidden();

        foreach ([$pptk, $bpp, $verifikator] as $user) {
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        }
    }

    public function test_superadmin_aktif_terakhir_tidak_dapat_diturunkan_dinonaktifkan_atau_dihapus(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->put(route('users.update', $superadmin), [
            'nama' => $superadmin->nama,
            'role' => User::ROLE_BENDAHARA_PENGELUARAN,
        ])->assertSessionHasErrors('role');

        $this->actingAs($superadmin)->patch(route('users.toggle-aktif', $superadmin))
            ->assertSessionHasErrors('user');
        $this->actingAs($superadmin)->delete(route('users.destroy', $superadmin))
            ->assertSessionHasErrors('user');

        $superadmin->refresh();
        $this->assertTrue($superadmin->aktif);
        $this->assertSame(User::ROLE_SUPERADMIN, $superadmin->role);
        $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
    }

    // ---------------- Role Kepegawaian ----------------

    private function buatSuratPerintah(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '099/PW.02.01/Sekre',
            'tanggal_sp' => '2026-08-20',
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kota Bandung',
            'nama_pengirim' => 'Pengirim Uji',
            'tujuan_transfer' => 'Koordinator',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '3 - 4 Agustus 2026',
            'keterangan' => 'Uji akses Kepegawaian',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'pengajuan' => 'Uang Harian',
            'dipantau' => true,
            'sumber_npd' => false,
        ]);
    }

    /** Cakupan yang diminta: Dashboard, Surat Perintah, dan Data Kepegawaian. */
    public function test_kepegawaian_membuka_dashboard_surat_perintah_dan_seluruh_data_kepegawaian(): void
    {
        $kepegawaian = $this->buatUser(User::ROLE_KEPEGAWAIAN);

        foreach ([
            'dashboard.index',
            'surat-perintah.index',
            'surat-perintah.create',
            'surat-perintah.monitoring',
            'cetak-spj.index',
            'segera.sp-cetaksppd',
            'tunjangan.pegawai.index',
            'tunjangan.pegawai.create',
            'tunjangan.data.index',
            'tunjangan.monitoring',
            'tunjangan.form',
            'tunjangan.import.create',
            'profil.show',
        ] as $rute) {
            $this->actingAs($kepegawaian)->get(route($rute))
                ->assertOk("Kepegawaian seharusnya bisa membuka {$rute}.");
        }
    }

    /** "Full" pada Surat Perintah berhenti di lihat/input/cetak - ubah & hapus tetap milik PPTK. */
    public function test_kepegawaian_tidak_dapat_mengubah_atau_menghapus_surat_perintah(): void
    {
        $kepegawaian = $this->buatUser(User::ROLE_KEPEGAWAIAN);
        $sp = $this->buatSuratPerintah();

        $this->actingAs($kepegawaian)->get(route('surat-perintah.edit', $sp))->assertForbidden();
        $this->actingAs($kepegawaian)->delete(route('surat-perintah.destroy', $sp))->assertForbidden();
        $this->actingAs($kepegawaian)->patch(route('surat-perintah.toggle-pantau', $sp))->assertForbidden();
        $this->actingAs($kepegawaian)->patch(route('surat-perintah.pengajuan', $sp))->assertForbidden();
        $this->actingAs($kepegawaian)->patch(route('surat-perintah.toggle-sumber-npd', $sp))->assertForbidden();

        $sp->refresh();
        $this->assertTrue($sp->dipantau);
        $this->assertSame('Uang Harian', $sp->pengajuan);
    }

    /**
     * Memisahkan modul Data Kepegawaian dari grup superadmin TIDAK boleh
     * ikut membocorkan kewenangan superadmin yang lain.
     */
    public function test_kepegawaian_tertutup_dari_modul_di_luar_cakupannya(): void
    {
        $kepegawaian = $this->buatUser(User::ROLE_KEPEGAWAIAN);

        foreach ([
            'users.index',
            'pelimpahan.index',
            'audit-log.index',
            'manajemen-data.index',
            'manajemen-data.import.master-anggaran.create',
            'manajemen-data.import.npd-historis.create',
            'spm.up-gu.index',
            'spm.ls.index',
            'npd.index',
            'npd.data',
            'npd.persetujuan',
            'npd.verifikasi',
            'npd.bj.create',
            'rincian.index',
            'analisis.index',
            'inventarisasi-spj.index',
            'pengembalian.index',
            'dashboard.spj.index',
            'dashboard.perjalanan.index',
            'tunjangan.dashboard',
            'gaji-tunjangan.tabel.gaji',
            'gaji-tunjangan.rincian.index',
            'gaji-tunjangan.rekonsiliasi',
            'versi-pagu.index',
        ] as $rute) {
            $this->actingAs($kepegawaian)->get(route($rute))
                ->assertForbidden("Kepegawaian seharusnya DITOLAK di {$rute}.");
        }
    }

    public function test_kunci_menu_kepegawaian_persis_sesuai_cakupan_yang_disepakati(): void
    {
        $this->assertSame([
            'dashboard',
            'sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd',
            'tk-pegawai', 'tk-data', 'tk-form', 'tk-monitor',
            'profil',
        ], config('akses.menu.kepegawaian'));

        $this->assertSame('Kepegawaian', config('akses.role_label.kepegawaian'));
        $this->assertContains(User::ROLE_KEPEGAWAIAN, User::ROLE_OPTIONS);
    }

    /** Kolom users.role adalah ENUM - role baru harus ikut terdaftar di skema. */
    public function test_schema_role_menerima_kepegawaian(): void
    {
        $kepegawaian = $this->buatUser(User::ROLE_KEPEGAWAIAN);

        $this->assertDatabaseHas('users', [
            'id' => $kepegawaian->id,
            'role' => User::ROLE_KEPEGAWAIAN,
        ]);
    }

    /** Superadmin dapat membuat akun Kepegawaian lewat Manajemen Users. */
    public function test_superadmin_dapat_membuat_akun_kepegawaian(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->post(route('users.store'), [
            'username' => 'staf-kepegawaian',
            'nama' => 'Staf Kepegawaian',
            'role' => User::ROLE_KEPEGAWAIAN,
            'password' => 'kata-sandi-uji',
            'password_confirmation' => 'kata-sandi-uji',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'username' => 'staf-kepegawaian',
            'role' => User::ROLE_KEPEGAWAIAN,
        ]);
    }

    public function test_schema_role_menerima_role_baru_dan_menolak_role_bendahara_lama(): void
    {
        $this->buatUser(User::ROLE_SUPERADMIN);
        $this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN);

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'username' => 'legacy-bendahara',
            'nama' => 'Legacy Bendahara',
            'role' => 'bendahara',
            'password' => 'not-a-real-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
