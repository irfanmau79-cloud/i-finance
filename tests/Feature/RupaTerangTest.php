<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Penjaga rupa Default sesudah penyetelan mengikuti acuan UI.
 *
 * Inti keputusannya satu: yang gelap HANYA sidebar. Bilah atas dan halaman
 * sama-sama terang, sehingga hal paling terang di layar adalah angka dan
 * tabelnya - bukan bingkainya. Sebelum ini bilah atas ikut navy, dan seluruh
 * layar dibaca sebagai "aplikasi berwarna biru tua" lebih dulu, isinya nomor
 * dua.
 *
 * Test ini menjaga keputusan itu beserta bagian-bagian yang lahir bersamanya
 * (remah jejak di bilah, kartu identitas di kaki sidebar, kaki halaman), dan
 * memastikan dua hal yang TIDAK boleh ikut berubah tetap utuh: logo dan
 * susunan menu.
 */
class RupaTerangTest extends TestCase
{
    use RefreshDatabase;

    private function halaman(): string
    {
        $user = User::create([
            'username' => 'rupa-'.uniqid(),
            'nama' => 'Irfan Maulana',
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_SUPERADMIN,
            'aktif' => true,
        ]);

        return $this->actingAs($user)->get(route('dashboard.index'))->assertOk()->getContent();
    }

    /** Bilah atas Default berwarna PUTIH, bukan balok navy kedua. */
    public function test_bilah_atas_bawaan_terang(): void
    {
        $isi = $this->halaman();

        // Kedua ujung gradien bilah sama-sama putih pada mode Default.
        $this->assertStringContainsString('--tb-kiri:#ffffff; --tb-kanan:#ffffff;', $isi);
        // Tulisannya jadi tinta, bukan putih. Dipatok ke TOKEN (--tegas),
        // bukan nilai heksadesimalnya: yang dijaga test ini keputusan "bilah
        // atas bertulisan tinta", bukan nada tinta yang dipakai tahun ini.
        $this->assertStringContainsString('--tb-teks:var(--tegas);', $isi);
    }

    /** Sidebar tetap satu-satunya bidang gelap, dan warnanya lewat token. */
    public function test_sidebar_bawaan_tetap_gelap_lewat_token(): void
    {
        $isi = $this->halaman();

        $this->assertMatchesRegularExpression(
            '/--sb-bg:linear-gradient\(180deg,var\(--navy-d\)/', $isi,
            'Sidebar Default tidak lagi memakai latar gelap bertoken.');
        // Nilai navy sengaja dipatok: palet rangka adalah keputusan rupa,
        // jadi menggantinya harus jadi suntingan sadar di test ini - bukan
        // efek samping yang lolos tanpa disadari. Diperbarui ke navy
        // sungguhan (hue ~220) sesudah sempat memakai slate-900 yang
        // terbaca hampir hitam.
        $this->assertStringContainsString('--navy:#17294d;', $isi);
    }

    /**
     * Remah jejak di bilah atas memakai @yield('title') yang sudah diisi tiap
     * halaman - kalau ia diganti section baru, seluruh halaman lama akan
     * kehilangan remahnya tanpa satu pun error.
     */
    public function test_remah_jejak_bilah_atas_mengikuti_judul_halaman(): void
    {
        $isi = $this->halaman();

        $this->assertMatchesRegularExpression(
            '/<div class="tb-crumb"[^>]*>\s*<span>Beranda<\/span>.*?<b>Dashboard Realisasi Anggaran<\/b>/s',
            $isi);
    }

    /**
     * Kaki sidebar: kartu identitas (avatar, nama, lencana peran) dengan
     * tombol keluar sebagai ikon. Label "Keluar" tetap ada di DOM - ia hanya
     * disembunyikan secara visual, jadi pembaca layar masih membacanya.
     */
    public function test_kaki_sidebar_berupa_kartu_identitas(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('class="sb-kaki"', $isi);
        $this->assertStringContainsString('class="sb-av"', $isi);
        $this->assertStringContainsString('id="sb-userinfo"', $isi);
        $this->assertStringContainsString('Irfan Maulana', $isi);

        $kaki = substr($isi, (int) strpos($isi, 'class="sb-kaki"'));
        $kaki = substr($kaki, 0, (int) strpos($kaki, '</aside>'));
        $this->assertStringContainsString(route('logout'), $kaki);
        $this->assertStringContainsString('title="Keluar"', $kaki);
        $this->assertStringContainsString('<span>Keluar</span>', $kaki);

        // Disembunyikan lewat clip, bukan display:none - display:none akan
        // ikut menghapusnya dari pembaca layar.
        $this->assertStringContainsString('.sb-logout span{position:absolute;', $isi);
    }

    /** Kaki halaman menyebut pemilik sistem. */
    public function test_kaki_halaman_ada(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('class="app-foot"', $isi);
        $this->assertStringContainsString('Inspektorat Daerah Provinsi Jawa Barat.', $isi);
    }

    /**
     * DUA HAL YANG TIDAK BOLEH IKUT BERUBAH saat rupa disetel: logo, dan
     * susunan menu sidebar. Keduanya diminta tetap apa adanya.
     */
    public function test_logo_dan_susunan_menu_tidak_berubah(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('alt="Logo Inspektorat Jabar"', $isi);
        $this->assertStringContainsString('data:image/webp;base64,UklGRhYZAABXRUJQVlA4WAoAAAAQAAAAXwAAaAAA', $isi);

        // Urutan butir menu tingkat atas untuk superadmin.
        $urutan = [
            'Dashboard', 'Rincian Realisasi', 'Analisis dan Tren', 'Nota Pencairan Dana (NPD)',
            'Pengembalian', 'Inventarisasi SPJ', 'Data Realisasi SP2D', 'Surat Perintah',
            'Data Kepegawaian', 'Gaji dan Tunjangan', 'Log Aktivitas', 'Setting', 'Profil Saya',
        ];

        $menu = substr($isi, (int) strpos($isi, '<nav class="sb-menu">'));
        $menu = substr($menu, 0, (int) strpos($menu, '</nav>'));

        $posisi = -1;
        foreach ($urutan as $label) {
            $baru = strpos($menu, $label, $posisi + 1);
            $this->assertNotFalse($baru, "Menu '{$label}' hilang dari sidebar.");
            $this->assertGreaterThan($posisi, $baru, "Urutan menu berubah pada '{$label}'.");
            $posisi = $baru;
        }
    }
}
