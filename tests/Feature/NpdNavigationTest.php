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

    public function test_halaman_pembuatan_npd_memuat_pemilih_lima_jenis_untuk_yang_boleh_membuat(): void
    {
        $pptk = $this->user('pptk');
        $response = $this->actingAs($pptk)->get(route('npd.index'));

        $response->assertOk()
            ->assertSee('Barang/Jasa')
            ->assertSee('Perjalanan Dinas')
            ->assertSee('Transport')
            ->assertSee('Narasumber')
            ->assertSee('Kontribusi Diklat');

        foreach (['npd.bj.create', 'npd.pd.create', 'npd.tr.create', 'npd.ns.create', 'npd.kd.create'] as $route) {
            $response->assertSee(route($route), false);
        }

        // Bendahara Pengeluaran boleh memantau daftarnya tapi tidak melihat pemilih jenis (tidak boleh membuat NPD).
        $this->actingAs($this->user('bendahara_pengeluaran'))->get(route('npd.index'))
            ->assertOk()
            ->assertDontSee(route('npd.bj.create'), false);

        $this->actingAs($this->user('bpp'))->get(route('npd.index'))->assertForbidden();
        $this->actingAs($this->user('verifikator'))->get(route('npd.index'))->assertForbidden();
    }

    public function test_daftar_npd_memakai_filter_status_dan_pagination_server_side(): void
    {
        $master = $this->master();
        foreach (range(1, 31) as $index) {
            $this->npd($master, 'bj', 'Draft NPD - PPTK', "DRAFT-{$index}");
        }
        $this->npd($master, 'pd', 'Selesai', 'PD-SELESAI');
        $user = $this->user('pptk');

        DB::flushQueryLog();
        DB::enableQueryLog();
        // Kolom "Nomor" tidak lagi ditampilkan di tabel Pembuatan NPD (port gas-lama tidak
        // punya kolom itu) — kebenaran filter status dibuktikan lewat total()/count() paginator,
        // bukan lewat teks nomor_lengkap yang sudah tidak dirender.
        $this->actingAs($user)->get(route('npd.index', ['status' => 'Draft NPD - PPTK']))
            ->assertOk()
            ->assertViewHas('npds', fn ($npds) => $npds->total() === 31 && $npds->count() === 30 && $npds->perPage() === 30);
        $listingQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'npd') || str_contains($query['query'], 'master_anggaran'));
        // count + select + masterAnggaran + tagging + (penerima/tim/narasumber/peserta eager loads
        // untuk kolom Tagging/Penerima gas-lama-style) = 8 query flat, bukan N+1 per baris.
        $this->assertLessThanOrEqual(8, $listingQueries->count(), 'Daftar NPD melakukan query berlebihan atau N+1.');
        DB::disableQueryLog();
    }

    public function test_daftar_npd_memakai_filter_jenis_dan_status_khusus(): void
    {
        $master = $this->master();
        $this->npd($master, 'bj', 'Draft NPD - PPTK', 'DRAFT-LAIN');
        $this->npd($master, 'pd', 'Selesai', 'PD-SELESAI');
        $user = $this->user('pptk');

        $this->actingAs($user)->get(route('npd.index', ['jenis' => 'pd', 'status' => 'Selesai']))
            ->assertOk()
            ->assertSee($master->sub_kegiatan)
            ->assertViewHas('npds', fn ($npds) => $npds->total() === 1);
    }

    public function test_daftar_npd_menolak_jenis_tidak_valid(): void
    {
        $user = $this->user('pptk');

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
