<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NpdNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_buat_npd_memuat_lima_jenis_dan_dijaga_backend(): void
    {
        $pptk = $this->user('pptk');
        $response = $this->actingAs($pptk)->get(route('npd.create'));

        $response->assertOk()
            ->assertSee('Barang/Jasa')
            ->assertSee('Perjalanan Dinas')
            ->assertSee('Transport')
            ->assertSee('Narasumber')
            ->assertSee('Kontribusi Diklat');

        foreach (['npd.bj.create', 'npd.pd.create', 'npd.tr.create', 'npd.ns.create', 'npd.kd.create'] as $route) {
            $response->assertSee(route($route), false);
        }

        $this->actingAs($this->user('bendahara_pengeluaran'))->get(route('npd.create'))->assertForbidden();
        $this->actingAs($this->user('bpp'))->get(route('npd.create'))->assertForbidden();
        $this->actingAs($this->user('verifikator'))->get(route('npd.create'))->assertForbidden();
    }

    public function test_daftar_npd_memakai_filter_query_dan_pagination_server_side(): void
    {
        $master = $this->master();
        foreach (range(1, 31) as $index) {
            $this->npd($master, 'bj', 'Draft NPD - PPTK', "DRAFT-{$index}");
        }
        $selesai = $this->npd($master, 'pd', 'Selesai', 'PD-SELESAI');
        $user = $this->user('pptk');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('npd.index', ['status' => 'Draft NPD - PPTK']))
            ->assertOk()
            ->assertViewHas('npds', fn ($npds) => $npds->total() === 31 && $npds->count() === 30 && $npds->perPage() === 30)
            ->assertDontSee($selesai->nomor_lengkap);
        $listingQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'npd') || str_contains($query['query'], 'master_anggaran'));
        $this->assertLessThanOrEqual(3, $listingQueries->count(), 'Daftar NPD melakukan query berlebihan atau N+1.');
        DB::disableQueryLog();

        $this->actingAs($user)->get(route('npd.index', ['jenis' => 'pd', 'status' => 'Selesai']))
            ->assertOk()
            ->assertSee($selesai->nomor_lengkap)
            ->assertViewHas('npds', fn ($npds) => $npds->total() === 1);

        $this->actingAs($user)->get(route('npd.index', ['jenis' => 'tidak-valid']))
            ->assertSessionHasErrors('jenis');
    }

    public function test_menu_npd_setiap_role_tidak_memuat_placeholder_atau_link_mati(): void
    {
        $obsolete = ['npd-selesai', 'persetujuan-selesai', 'verifikasi-selesai'];
        foreach (config('akses.menu') as $role => $menu) {
            $this->assertSame([], array_values(array_intersect($obsolete, $menu)), "Role {$role} masih memiliki menu NPD lama.");
            $this->actingAs($this->user($role));
            $html = view('layouts.app')->render();
            $this->assertStringNotContainsString('/menu/', $html, "Sidebar role {$role} masih memiliki link placeholder.");
            $this->assertStringNotContainsString('href="#"', $html, "Sidebar role {$role} masih memiliki link mati.");
        }

        $superadmin = $this->user('superadmin');
        $this->actingAs($superadmin)->get(route('npd.create'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.persetujuan'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.verifikasi'))->assertOk();

        $this->actingAs($this->user('bendahara_pengeluaran'))->get(route('npd.index'))->assertOk();
        $this->actingAs($this->user('bpp'))->get(route('npd.persetujuan'))->assertOk();
        $this->actingAs($this->user('verifikator'))->get(route('npd.verifikasi'))->assertOk();
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'nav-'.$role.'-'.User::count(),
            'nama' => 'Navigasi '.$role,
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function master(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Navigasi',
            'kegiatan' => 'Kegiatan Navigasi',
            'sub_kegiatan' => '6.01.01.2.01 Sub Navigasi',
            'kode_rekening' => '5.1.02.01.01.0099',
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    private function npd(MasterAnggaran $master, string $jenis, string $status, string $nomor): Npd
    {
        return Npd::create([
            'jenis' => $jenis,
            'master_anggaran_id' => $master->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_lengkap' => $nomor,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => $status,
        ]);
    }
}
