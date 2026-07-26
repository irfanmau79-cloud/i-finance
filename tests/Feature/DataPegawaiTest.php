<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private const SEMUA_ROLE = [
        'superadmin', 'bendahara_pengeluaran', 'pptk', 'bpp', 'verifikator',
        'perencanaan', 'inspektur', 'sekretaris', 'kasubbag', 'inspektur_pembantu', 'layanan',
    ];

    private function buatUser(string $role, string $username): User
    {
        return User::create(['username' => $username, 'nama' => ucfirst($username), 'role' => $role, 'password' => 'rahasia']);
    }

    public function test_semua_role_bisa_melihat_daftar_pegawai(): void
    {
        Pegawai::create(['nama' => 'Budi Santoso', 'nip' => '111', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'nomor_handphone' => '0812111', 'aktif' => true]);

        foreach (self::SEMUA_ROLE as $role) {
            $user = $this->buatUser($role, 'dp-'.$role);
            $this->actingAs($user)->get(route('data-pegawai.index'))
                ->assertOk()
                ->assertSee('Budi Santoso')
                ->assertSee('0812111');
        }
    }

    public function test_hanya_superadmin_yang_bisa_edit_pegawai(): void
    {
        $pegawai = Pegawai::create(['nama' => 'Citra Uji', 'nip' => '222', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);

        foreach (self::SEMUA_ROLE as $role) {
            $user = $this->buatUser($role, 'dpedit-'.$role);
            $response = $this->actingAs($user)->put(route('data-pegawai.update', $pegawai), [
                'nama' => 'Citra Diedit', 'nip' => '222', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat',
                'nomor_handphone' => '0899999', 'aktif' => '1',
            ]);

            if ($role === 'superadmin') {
                $response->assertSessionHasNoErrors();
            } else {
                $response->assertForbidden();
            }
        }

        $this->assertSame('Citra Diedit', $pegawai->fresh()->nama);
    }

    public function test_superadmin_dapat_mengubah_nomor_handphone_dan_menonaktifkan_serta_tercatat_di_audit(): void
    {
        $superadmin = $this->buatUser('superadmin', 'dp-superadmin-edit');
        $pegawai = Pegawai::create(['nama' => 'Dedi Uji', 'nip' => '333', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);

        $response = $this->actingAs($superadmin)->put(route('data-pegawai.update', $pegawai), [
            'nama' => 'Dedi Uji', 'nip' => '333', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat',
            'golongan' => 'III/a', 'pangkat' => 'Penata Muda', 'rekening' => '001-2233',
            'nomor_handphone' => '081234567890', 'aktif' => '0',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pegawai->refresh();
        $this->assertSame('081234567890', $pegawai->nomor_handphone);
        $this->assertFalse($pegawai->aktif);
        $this->assertDatabaseHas('audit_log', ['user_id' => $superadmin->id, 'aktivitas' => 'Edit Data Pegawai']);
    }

    public function test_nip_duplikat_ditolak_tapi_nip_milik_sendiri_boleh(): void
    {
        $superadmin = $this->buatUser('superadmin', 'dp-superadmin-nip');
        Pegawai::create(['nama' => 'Sudah Ada', 'nip' => '444', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);
        $pegawai = Pegawai::create(['nama' => 'Eka Uji', 'nip' => '555', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);

        $this->actingAs($superadmin)->put(route('data-pegawai.update', $pegawai), [
            'nama' => 'Eka Uji', 'nip' => '444', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => '1',
        ])->assertSessionHasErrors('nip');

        // NIP tetap sama seperti sebelumnya (555) - boleh disimpan ulang tanpa dianggap duplikat diri sendiri.
        $this->actingAs($superadmin)->put(route('data-pegawai.update', $pegawai), [
            'nama' => 'Eka Uji Revisi', 'nip' => '555', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Eka Uji Revisi', $pegawai->fresh()->nama);
    }

    public function test_pencarian_menyaring_daftar_pegawai(): void
    {
        $superadmin = $this->buatUser('superadmin', 'dp-superadmin-cari');
        Pegawai::create(['nama' => 'Farhan Cari', 'nip' => '666', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);
        Pegawai::create(['nama' => 'Lainnya', 'nip' => '777', 'jabatan' => 'Staf', 'bidang' => 'Sekretariat', 'aktif' => true]);

        $this->actingAs($superadmin)->get(route('data-pegawai.index', ['cari' => 'Farhan']))
            ->assertOk()
            ->assertSee('Farhan Cari')
            ->assertDontSee('Lainnya');
    }
}
