<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use App\Models\VersiPagu;
use App\Models\VersiPaguDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aktivasi versi pagu: satu-satunya titik yang boleh mengubah pagu yang
 * berlaku (master_anggaran.pagu). Import hanya menghasilkan versi draft.
 */
class VersiPaguTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role): User
    {
        return User::create([
            'username' => 'penguji-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatMaster(string $kodeRekening, float $pagu = 0, bool $aktif = false): MasterAnggaran
    {
        return MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Uji Versi',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji Versi',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Uji Versi',
            'kode_rekening' => $kodeRekening,
            'rekening' => 'Belanja Uji '.$kodeRekening,
            'pagu' => $pagu,
            'aktif' => $aktif,
        ]);
    }

    /** @param  array<int, array{0: MasterAnggaran, 1: float}>  $baris */
    private function buatVersi(string $nama, array $baris, string $status = VersiPagu::STATUS_DRAFT): VersiPagu
    {
        $versi = VersiPagu::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nama' => $nama,
            'status' => $status,
        ]);

        foreach ($baris as [$master, $pagu]) {
            VersiPaguDetail::create([
                'versi_pagu_id' => $versi->id,
                'master_anggaran_id' => $master->id,
                'pagu' => $pagu,
                'aktif' => true,
            ]);
        }

        $versi->segarkanRingkasan();

        return $versi->fresh();
    }

    public function test_aktivasi_menulis_pagu_berlaku_dan_mencatat_audit(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');
        $versi = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);

        $this->assertSame(0.0, (float) $master->fresh()->pagu);

        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $versi))
            ->assertRedirect(route('versi-pagu.index'));

        $versi->refresh();
        $this->assertSame(VersiPagu::STATUS_AKTIF, $versi->status);
        $this->assertNotNull($versi->diaktifkan_at);
        $this->assertSame($superadmin->id, $versi->diaktifkan_oleh_id);

        $master->refresh();
        $this->assertSame(15_000_000.0, (float) $master->pagu);
        $this->assertTrue($master->aktif);

        $log = AuditLog::where('aktivitas', 'Aktivasi Versi Pagu')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('DPA Murni', $log->keterangan);
    }

    public function test_aktivasi_versi_baru_mengarsipkan_versi_sebelumnya(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');

        $murni = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $murni));

        $pergeseran = $this->buatVersi('DPA Pergeseran 1', [[$master, 18_500_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $pergeseran));

        $this->assertSame(VersiPagu::STATUS_ARSIP, $murni->fresh()->status);
        $this->assertSame(VersiPagu::STATUS_AKTIF, $pergeseran->fresh()->status);
        $this->assertSame(18_500_000.0, (float) $master->fresh()->pagu);

        // Tepat satu versi aktif per tahun.
        $this->assertSame(1, VersiPagu::where('tahun', $murni->tahun)->where('status', VersiPagu::STATUS_AKTIF)->count());
    }

    public function test_mata_anggaran_di_luar_versi_dinolkan_dan_dinonaktifkan(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $dipakai = $this->buatMaster('5.1.02.05.01.7001');
        $dilepas = $this->buatMaster('5.1.02.05.01.7002');

        $murni = $this->buatVersi('DPA Murni', [[$dipakai, 10_000_000], [$dilepas, 4_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $murni));

        $this->assertSame(4_000_000.0, (float) $dilepas->fresh()->pagu);
        $this->assertTrue($dilepas->fresh()->aktif);

        // Versi berikutnya tidak memuat baris kedua sama sekali.
        $pergeseran = $this->buatVersi('DPA Pergeseran 1', [[$dipakai, 14_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $pergeseran));

        $this->assertSame(14_000_000.0, (float) $dipakai->fresh()->pagu);
        $this->assertSame(0.0, (float) $dilepas->fresh()->pagu);
        $this->assertFalse($dilepas->fresh()->aktif);
    }

    public function test_versi_arsip_dapat_diaktifkan_kembali_untuk_mengembalikan_pagu(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');

        $murni = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $murni));

        $pergeseran = $this->buatVersi('DPA Pergeseran 1', [[$master, 18_500_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $pergeseran));
        $this->assertSame(18_500_000.0, (float) $master->fresh()->pagu);

        // Kembalikan ke DPA Murni.
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $murni));

        $this->assertSame(15_000_000.0, (float) $master->fresh()->pagu);
        $this->assertSame(VersiPagu::STATUS_AKTIF, $murni->fresh()->status);
        $this->assertSame(VersiPagu::STATUS_ARSIP, $pergeseran->fresh()->status);
    }

    public function test_aktivasi_diblokir_bila_pagu_lebih_kecil_dari_dana_terikat(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001', 20_000_000, true);

        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $master->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 12_000_000,
            'terbilang' => 'dua belas juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);

        $versi = $this->buatVersi('DPA Pergeseran 1', [[$master, 9_000_000]]);

        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $versi))
            ->assertSessionHasErrors('aktivasi');

        $this->assertSame(VersiPagu::STATUS_DRAFT, $versi->fresh()->status);
        $this->assertSame(20_000_000.0, (float) $master->fresh()->pagu);
    }

    public function test_versi_yang_sudah_aktif_tidak_bisa_diaktifkan_lagi(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');
        $versi = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);

        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $versi));
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $versi->fresh()))
            ->assertSessionHasErrors('aktivasi');
    }

    public function test_hanya_versi_draft_yang_bisa_dihapus(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');

        $draft = $this->buatVersi('DPA Pergeseran 9', [[$master, 1_000_000]]);
        $this->actingAs($superadmin)->delete(route('versi-pagu.destroy', $draft))
            ->assertRedirect(route('versi-pagu.index'));
        $this->assertNull(VersiPagu::find($draft->id));

        $aktif = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $aktif));

        $this->actingAs($superadmin)->delete(route('versi-pagu.destroy', $aktif))->assertForbidden();
        $this->assertNotNull(VersiPagu::find($aktif->id));
    }

    public function test_akses_versi_pagu_terbatas_pada_superadmin_dan_bendahara_pengeluaran(): void
    {
        $master = $this->buatMaster('5.1.02.05.01.7001');
        $versi = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);

        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))->get(route('versi-pagu.index'))->assertOk();
        $this->actingAs($this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN))->get(route('versi-pagu.index'))->assertOk();

        $pptk = $this->buatUser(User::ROLE_PPTK);
        $this->actingAs($pptk)->get(route('versi-pagu.index'))->assertForbidden();
        $this->actingAs($pptk)->get(route('versi-pagu.show', $versi))->assertForbidden();
        $this->actingAs($pptk)->post(route('versi-pagu.aktifkan', $versi))->assertForbidden();
    }

    /**
     * Judul kartu "Data Pagu Anggaran" adalah SATU-SATUNYA pintu masuk ke
     * halaman Versi Pagu dari Manajemen Data (tombolnya sengaja tidak ada
     * supaya deretan tombol seragam antar kartu) - jangan sampai hilang.
     */
    public function test_kartu_data_pagu_anggaran_menautkan_ke_halaman_versi_pagu(): void
    {
        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->get(route('manajemen-data.index'))
            ->assertOk()
            ->assertSee('href="'.route('versi-pagu.index').'"', false)
            ->assertSee('Data Pagu Anggaran')
            ->assertSee('Klik judul untuk melihat rincian dan histori pagu');
    }

    public function test_halaman_rincian_menampilkan_selisih_terhadap_versi_berlaku(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $master = $this->buatMaster('5.1.02.05.01.7001');

        $murni = $this->buatVersi('DPA Murni', [[$master, 15_000_000]]);
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $murni));

        $pergeseran = $this->buatVersi('DPA Pergeseran 1', [[$master, 18_500_000]]);

        $this->actingAs($superadmin)->get(route('versi-pagu.show', $pergeseran))
            ->assertOk()
            ->assertSee('DPA Pergeseran 1')
            ->assertSee('DPA Murni')
            ->assertSee('3.500.000');
    }
}
