<?php

namespace Tests\Feature;

use App\Http\Controllers\SegeraHadirController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Modul Gaji dan Tunjangan serta Cetak SPPD: rumahnya sudah ada, isinya
 * belum. Test ini menjaga agar menunya benar-benar bisa dibuka, hak
 * aksesnya sudah ditegakkan sejak sekarang, dan urutan modul di sidebar
 * tetap seperti yang disepakati.
 */
class MenuSegeraHadirTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'menu-'.$role.'-'.uniqid(),
            'nama' => 'Uji '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    public function test_semua_halaman_under_progress_dapat_dibuka_superadmin(): void
    {
        $user = $this->user(User::ROLE_SUPERADMIN);

        foreach (SegeraHadirController::HALAMAN as $menu => $meta) {
            $this->actingAs($user)->get(route('segera.'.$menu))->assertOk()
                ->assertSee('Under Progress')
                ->assertSee($meta['judul'])
                ->assertSee($meta['modul']);
        }
    }

    public function test_daftar_rincian_penghasilan_khusus_superadmin(): void
    {
        // Mengikuti pembagian di GAS: lima menu untuk semua role, "Daftar
        // Rincian Penghasilan" hanya untuk superadmin.
        $this->actingAs($this->user(User::ROLE_SUPERADMIN))->get(route('segera.gt-daftar'))->assertOk();
        $this->actingAs($this->user(User::ROLE_PPTK))->get(route('segera.gt-daftar'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_PPTK))->get(route('segera.gt-gaji'))->assertOk();
    }

    public function test_akses_mengikuti_config_akses_menu(): void
    {
        $user = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($user)->get(route('segera.gt-gaji'))->assertOk();

        config(['akses.menu.superadmin' => array_values(array_diff(config('akses.menu.superadmin'), ['gt-gaji']))]);
        $this->actingAs($user)->get(route('segera.gt-gaji'))->assertForbidden();
    }

    public function test_sidebar_memuat_modul_gaji_dan_cetak_sppd(): void
    {
        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('dashboard.index'))->assertOk();

        $halaman->assertSee('Gaji dan Tunjangan')
            ->assertSee('nav-gt-parent', false)
            ->assertSee('Gaji Induk')
            ->assertSee('TPP Beban Kerja')
            ->assertSee('TPP Kondisi Kerja')
            ->assertSee('Total Penghasilan')
            ->assertSee('Cetak Rincian Penghasilan')
            ->assertSee('Daftar Rincian Penghasilan')
            ->assertSee('Cetak SPPD');
    }

    /**
     * Urutan modul di sidebar, dari atas ke bawah. Perbandingan dibatasi
     * pada blok <nav class="sb-menu"> saja - "Dashboard" dan "Profil Saya"
     * juga muncul di bagian lain halaman, dan tanpa pembatasan ini yang
     * terbaca adalah kemunculan pertamanya, bukan posisi menunya.
     */
    public function test_urutan_modul_sidebar(): void
    {
        $isi = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('dashboard.index'))->assertOk()->getContent();

        $mulai = strpos($isi, '<nav class="sb-menu">');
        $this->assertNotFalse($mulai, 'Blok sidebar tidak ditemukan.');
        $nav = substr($isi, $mulai, strpos($isi, '</nav>', $mulai) - $mulai);

        $urutan = [
            'Dashboard', 'Rincian Realisasi', 'Analisis dan Tren', 'Nota Pencairan Dana (NPD)',
            'Pengembalian', 'Inventarisasi SPJ', 'Data Realisasi SP2D', 'Surat Perintah',
            'Data Kepegawaian', 'Gaji dan Tunjangan', 'Log Aktivitas', 'Setting', 'Profil Saya',
        ];

        $posisi = -1;

        foreach ($urutan as $modul) {
            $ditemukan = strpos($nav, $modul);
            $this->assertNotFalse($ditemukan, "Modul {$modul} tidak ditemukan di sidebar.");
            $this->assertGreaterThan($posisi, $ditemukan, "Modul {$modul} berada di luar urutan.");
            $posisi = $ditemukan;
        }
    }

    public function test_cetak_sppd_berada_paling_bawah_pada_grup_surat_perintah(): void
    {
        $isi = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertGreaterThan(
            strpos($isi, 'Cetak SPJ Perjalanan Dinas'),
            strpos($isi, 'Cetak SPPD'),
            'Cetak SPPD harus berada setelah Cetak SPJ Perjalanan Dinas.'
        );
    }
}
