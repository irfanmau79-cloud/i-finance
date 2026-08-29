<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Rangka aplikasi: bilah atas tetap, sakelar tema, menu profil, dan sidebar
 * yang mengecil jadi rel ikon (bukan hilang sama sekali).
 */
class RangkaAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $nama = 'Irfan Maulana'): User
    {
        return User::create([
            'username' => 'rangka-'.uniqid(),
            'nama' => $nama,
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_SUPERADMIN,
            'aktif' => true,
        ]);
    }

    private function halaman(?User $user = null): string
    {
        return $this->actingAs($user ?? $this->user())
            ->get(route('dashboard.index'))->assertOk()->getContent();
    }

    public function test_bilah_atas_memuat_tahun_anggaran_sakelar_tema_dan_menu_profil(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('Tahun Anggaran '.config('anggaran.tahun_aktif'), $isi);
        $this->assertStringContainsString('id="tb-tema"', $isi);
        $this->assertStringContainsString('id="tb-avatar"', $isi);
        $this->assertStringContainsString('id="tb-menu"', $isi);

        // Menu profil hanya berisi dua pilihan: Profil Saya dan Keluar.
        $mulai = strpos($isi, 'id="tb-menu"');
        $menu = substr($isi, $mulai, strpos($isi, '</div>', strpos($isi, '</form>', $mulai)) - $mulai);
        $this->assertStringContainsString('Profil Saya', $menu);
        $this->assertStringContainsString('Keluar', $menu);
        $this->assertStringContainsString(route('logout'), $menu);
    }

    /**
     * Bilah atas tetap terlihat saat digulir, dan dimulai TEPAT di tepi kanan
     * sidebar - bukan menutupinya. Tepi kirinya ikut menyesuaikan saat sidebar
     * menyusut jadi rel ikon.
     */
    public function test_bilah_atas_tetap_dan_dimulai_setelah_sidebar(): void
    {
        $isi = $this->halaman();

        $this->assertMatchesRegularExpression('/\.topbar\{position:fixed;top:0;left:255px;right:0;/', $isi);
        $this->assertStringContainsString('html.sidebar-collapsed .topbar{left:64px;}', $isi);

        // Sidebar tetap penuh dari puncak layar.
        $this->assertMatchesRegularExpression('/\.sidebar\{[^}]*position:sticky;top:0;[^}]*height:100vh;/', $isi);

        // Isi halaman diberi ruang setinggi bilah atas.
        $this->assertStringContainsString('.main{flex:1;min-width:0;padding:84px 14px 22px;}', $isi);
    }

    public function test_teks_berjalan_memuat_nama_pengguna(): void
    {
        $isi = $this->halaman($this->user('Budi Santoso'));

        $this->assertStringContainsString('i-Finance - Inspektorat Daerah Provinsi Jawa Barat', $isi);
        $this->assertStringContainsString('Selamat Datang, Pak\/Bu Budi Santoso!', $isi);
    }

    /**
     * Sidebar yang disembunyikan menyusut jadi rel ikon selebar 64px, bukan
     * hilang. Tombol mengambang berbentuk panah yang lama sudah tidak ada -
     * penggantinya logo di puncak rel.
     */
    /** Sapaan mengikuti jenis kelamin; kosong berarti tetap netral "Pak/Bu". */
    public function test_sapaan_mengikuti_jenis_kelamin(): void
    {
        $pria = $this->user('Budi Santoso');
        $pria->forceFill(['jenis_kelamin' => 'L'])->save();
        $this->assertStringContainsString('Selamat Datang, Pak Budi Santoso!', $this->halaman($pria));

        $wanita = $this->user('Siti Aminah');
        $wanita->forceFill(['jenis_kelamin' => 'P'])->save();
        $this->assertStringContainsString('Selamat Datang, Bu Siti Aminah!', $this->halaman($wanita));

        $belum = $this->user('Rahmat Hidayat');
        $this->assertStringContainsString('Selamat Datang, Pak\/Bu Rahmat Hidayat!', $this->halaman($belum));
    }

    /** Yang disapa adalah nama panggilan; kalau kosong, jatuh ke nama lengkap. */
    public function test_sapaan_memakai_nama_panggilan_bila_diisi(): void
    {
        $user = $this->user('Muhammad Irfan Maulana');
        $user->forceFill(['jenis_kelamin' => 'L', 'nama_panggilan' => 'Irfan'])->save();

        $isi = $this->halaman($user);
        $this->assertStringContainsString('Selamat Datang, Pak Irfan!', $isi);
        $this->assertStringNotContainsString('Selamat Datang, Pak Muhammad Irfan Maulana!', $isi);

        // Nama lengkap tetap dipakai sebagai identitas akun di menu profil.
        $this->assertStringContainsString('Muhammad Irfan Maulana', $isi);

        $user->forceFill(['nama_panggilan' => null])->save();
        $this->assertStringContainsString('Selamat Datang, Pak Muhammad Irfan Maulana!', $this->halaman($user));
    }

    public function test_nama_panggilan_dapat_disimpan_dari_profil(): void
    {
        $user = $this->user('Muhammad Irfan Maulana');

        $this->actingAs($user)->get(route('profil.show'))->assertOk()
            ->assertSee('Nama Lengkap')
            ->assertSee('Nama Panggilan');

        $this->actingAs($user)->put(route('profil.update'), [
            'nama' => 'Muhammad Irfan Maulana',
            'nama_panggilan' => 'Irfan',
        ])->assertRedirect();

        $this->assertSame('Irfan', $user->fresh()->nama_panggilan);
        $this->assertSame('Irfan', $user->fresh()->namaSapaan());

        // Dikosongkan lagi -> kembali memakai nama lengkap.
        $this->actingAs($user)->put(route('profil.update'), [
            'nama' => 'Muhammad Irfan Maulana',
            'nama_panggilan' => '   ',
        ])->assertRedirect();

        $this->assertNull($user->fresh()->nama_panggilan);
        $this->assertSame('Muhammad Irfan Maulana', $user->fresh()->namaSapaan());
    }

    public function test_jenis_kelamin_dapat_disimpan_dari_profil(): void
    {
        $user = $this->user('Budi Santoso');

        $this->actingAs($user)->get(route('profil.show'))->assertOk()
            ->assertSee('Jenis Kelamin')
            ->assertSee('Laki-laki')
            ->assertSee('Perempuan');

        $this->actingAs($user)->put(route('profil.update'), [
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
        ])->assertRedirect();

        $this->assertSame('L', $user->fresh()->jenis_kelamin);
        $this->assertSame('Pak', $user->fresh()->sapaan());
    }

    public function test_sidebar_mengecil_jadi_rel_ikon_bukan_hilang(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('html.sidebar-collapsed .sidebar{width:64px;}', $isi);
        $this->assertStringNotContainsString('html.sidebar-collapsed .sidebar{width:0', $isi);

        // Logo menjadi tombol pembentang, dan tombol panah lama dihapus.
        $this->assertStringContainsString('id="sb-logo"', $isi);
        $this->assertStringNotContainsString('id="sb-expand-btn"', $isi);

        // Judul teks di sebelah logo disembunyikan saat mengecil.
        $this->assertStringContainsString('html.sidebar-collapsed .sb-head .judul', $isi);
    }

    public function test_pilihan_tema_dipulihkan_sebelum_halaman_digambar(): void
    {
        $isi = $this->halaman();

        // Skrip pramuat harus berada sebelum sidebar supaya tidak ada kedipan terang.
        $posisiSkrip = strpos($isi, "localStorage.getItem('ifinance-tema')");
        $posisiSidebar = strpos($isi, '<aside class="sidebar">');

        $this->assertNotFalse($posisiSkrip);
        $this->assertLessThan($posisiSidebar, $posisiSkrip);
        $this->assertStringContainsString(':root[data-tema="gelap"]', $isi);
    }
}
