<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman pertama sesudah login adalah Dashboard Realisasi Anggaran - menu
 * yang dibuka SEMUA role, jadi tidak ada akun yang mendarat di 403.
 */
class HalamanAwalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_setiap_role_mendarat_di_dashboard_realisasi_anggaran_dan_boleh_membukanya(): void
    {
        foreach (User::ROLE_OPTIONS as $role) {
            $user = User::create([
                'username' => 'awal-'.$role,
                'nama' => 'Awal '.$role,
                'role' => $role,
                'password' => 'rahasia-awal',
                'aktif' => true,
            ]);

            $this->post('/login', ['username' => $user->username, 'password' => 'rahasia-awal'])
                ->assertRedirect(route('dashboard.index'));
            $this->assertAuthenticatedAs($user);

            $this->get(route('dashboard.index'))->assertOk();

            $this->post(route('logout'));
        }
    }
}
