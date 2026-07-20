<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsernameManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username, string $role = User::ROLE_PPTK, string $password = 'current-password'): User
    {
        return User::create([
            'username' => $username,
            'nama' => 'User '.$username,
            'role' => $role,
            'password' => $password,
            'aktif' => true,
        ]);
    }

    private function ubahUsername(User $actor, User $target, string $username, string $password = 'current-password')
    {
        return $this->actingAs($actor)->patch(route('users.username.update', $target), [
            'username' => $username,
            'current_password' => $password,
        ]);
    }

    public function test_superadmin_dapat_mengubah_username_user_lain_dengan_normalisasi(): void
    {
        $admin = $this->user('admin-utama', User::ROLE_SUPERADMIN);
        $target = $this->user('username-lama');

        $this->actingAs($admin)->get(route('users.edit', $target))
            ->assertOk()
            ->assertSee(route('users.username.update', $target))
            ->assertSee('current_password', false);

        $this->ubahUsername($admin, $target, '  SUPERADMIN-IF  ')
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('superadmin-if', $target->fresh()->username);
    }

    public function test_non_superadmin_dan_perubahan_username_diri_sendiri_ditolak_backend(): void
    {
        $target = $this->user('target-otorisasi');

        foreach (array_diff(User::ROLE_OPTIONS, [User::ROLE_SUPERADMIN]) as $role) {
            $actor = $this->user('actor-'.str_replace('_', '-', $role), $role);
            $this->ubahUsername($actor, $target, 'tidak-boleh')->assertForbidden();
        }

        $admin = $this->user('admin-sendiri', User::ROLE_SUPERADMIN);
        $this->ubahUsername($admin, $admin, 'admin-baru')->assertForbidden();
        $this->assertSame('target-otorisasi', $target->fresh()->username);
        $this->assertSame('admin-sendiri', $admin->fresh()->username);
    }

    public function test_user_tanpa_autentikasi_diarahkan_ke_login(): void
    {
        $target = $this->user('target-guest');

        $this->patch(route('users.username.update', $target), [
            'username' => 'guest-tidak-boleh',
            'current_password' => 'current-password',
        ])->assertRedirect(route('login'));

        $this->assertSame('target-guest', $target->fresh()->username);
    }

    public function test_username_duplikat_dan_duplikat_setara_case_ditolak(): void
    {
        $admin = $this->user('admin-duplikat', User::ROLE_SUPERADMIN);
        $target = $this->user('target-duplikat');
        $this->user('sudah-dipakai');

        $this->ubahUsername($admin, $target, 'sudah-dipakai')->assertSessionHasErrors('username');

        DB::table('users')->insert([
            'username' => 'Legacy.Case',
            'nama' => 'Legacy Case',
            'role' => User::ROLE_PPTK,
            'password' => 'test-hash-only',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->ubahUsername($admin, $target, 'legacy.case')->assertSessionHasErrors('username');

        $this->assertSame('target-duplikat', $target->fresh()->username);
    }

    public function test_spasi_dan_karakter_tidak_aman_ditolak(): void
    {
        $admin = $this->user('admin-format', User::ROLE_SUPERADMIN);
        $target = $this->user('target-format');

        foreach (['nama user', 'nama@user', 'nama/user', 'nama<script>', 'nama+user'] as $username) {
            $this->ubahUsername($admin, $target, $username)->assertSessionHasErrors('username');
        }

        $this->assertSame('target-format', $target->fresh()->username);
    }

    public function test_password_superadmin_yang_salah_ditolak(): void
    {
        $admin = $this->user('admin-password', User::ROLE_SUPERADMIN);
        $target = $this->user('target-password');

        $this->ubahUsername($admin, $target, 'target-baru', 'password-salah')
            ->assertSessionHasErrors('current_password');

        $this->assertSame('target-password', $target->fresh()->username);
    }

    public function test_perubahan_hanya_menyentuh_username_dan_mempertahankan_sesi_berdasarkan_user_id(): void
    {
        $admin = $this->user('admin-preservasi', User::ROLE_SUPERADMIN);
        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Target', 'nip' => '198001012010011234', 'jabatan' => 'PPTK',
            'bidang' => 'Pengujian', 'pangkat' => 'Pembina', 'aktif' => true,
        ]);
        $target = $this->user('target-preservasi', User::ROLE_BPP);
        $target->forceFill([
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'remember_token' => 'token-test-tidak-berubah',
            'last_login_at' => '2026-07-19 10:00:00',
        ])->save();
        DB::table('sessions')->insert([
            'id' => 'session-target', 'user_id' => $target->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit', 'payload' => 'payload-test', 'last_activity' => now()->timestamp,
        ]);
        $sebelum = $target->fresh();

        $this->ubahUsername($admin, $target, 'target-preservasi-baru')->assertSessionHasNoErrors();
        $sesudah = $target->fresh();

        $this->assertSame($sebelum->password, $sesudah->password);
        $this->assertSame($sebelum->role, $sesudah->role);
        $this->assertSame($sebelum->pegawai_id, $sesudah->pegawai_id);
        $this->assertSame($sebelum->aktif, $sesudah->aktif);
        $this->assertSame($sebelum->remember_token, $sesudah->remember_token);
        $this->assertSame($sebelum->nama, $sesudah->nama);
        $this->assertSame($sebelum->nip, $sesudah->nip);
        $this->assertTrue($sebelum->last_login_at->equalTo($sesudah->last_login_at));
        $this->assertDatabaseHas('sessions', ['id' => 'session-target', 'user_id' => $target->id]);
    }

    public function test_audit_log_mencatat_aktor_target_username_lama_baru_dan_waktu_tanpa_rahasia(): void
    {
        $password = 'password-rahasia-test';
        $admin = $this->user('admin-audit', User::ROLE_SUPERADMIN, $password);
        $target = $this->user('target-audit');

        $this->ubahUsername($admin, $target, 'target-audit-baru', $password)->assertSessionHasNoErrors();

        $log = AuditLog::where('aktivitas', 'Ubah Username User')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('admin-audit', $log->username);
        $this->assertSame(User::ROLE_SUPERADMIN, $log->role);
        $this->assertStringContainsString("target_user_id: {$target->id}", $log->keterangan);
        $this->assertStringContainsString('username_lama: target-audit', $log->keterangan);
        $this->assertStringContainsString('username_baru: target-audit-baru', $log->keterangan);
        $this->assertStringContainsString('waktu:', $log->keterangan);
        $this->assertStringNotContainsString($password, $log->keterangan);
        $this->assertStringNotContainsString($admin->password, $log->keterangan);
        $this->assertNotNull($log->created_at);
    }

    public function test_pembuatan_user_tetap_berfungsi_dengan_kebijakan_username_yang_sama(): void
    {
        $admin = $this->user('admin-create', User::ROLE_SUPERADMIN);

        $this->actingAs($admin)->post(route('users.store'), [
            'username' => '  USER.BARU-1  ',
            'nama' => 'User Baru',
            'role' => User::ROLE_VERIFIKATOR,
            'password' => 'password-development',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'user.baru-1', 'role' => User::ROLE_VERIFIKATOR]);
    }
}
