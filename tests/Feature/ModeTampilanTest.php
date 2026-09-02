<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ganti Mode: tiga pilihan tampilan (Default, Gelap, Terang).
 *
 * "Default" berarti TANPA atribut data-tema pada elemen akar - rangka navy
 * seperti sedia kala. Gelap dan Terang masing-masing memasang data-tema.
 */
class ModeTampilanTest extends TestCase
{
    use RefreshDatabase;

    private function halaman(): string
    {
        $user = User::create([
            'username' => 'penguji-tema',
            'nama' => 'Penguji Tema',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'test-only-password',
        ]);

        return $this->actingAs($user)->get(route('dashboard.index'))->assertOk()->getContent();
    }

    public function test_menu_ganti_mode_menawarkan_tiga_pilihan(): void
    {
        $html = $this->halaman();

        foreach (['default', 'gelap', 'terang'] as $nilai) {
            $this->assertStringContainsString('data-tema-pilih="'.$nilai.'"', $html,
                "Pilihan mode {$nilai} tidak ada di menu Ganti Mode.");
        }

        $this->assertStringContainsString('>Default<', $html);
        $this->assertStringContainsString('>Gelap<', $html);
        $this->assertStringContainsString('>Terang<', $html);
        $this->assertStringContainsString('id="tb-menu-tema"', $html);
    }

    /**
     * Skrip pembuka dijalankan sebelum badan halaman digambar supaya tidak
     * ada kedipan; ia harus mengenali 'terang', bukan hanya 'gelap'.
     */
    public function test_skrip_pembuka_memasang_kedua_mode_bukan_hanya_gelap(): void
    {
        $html = $this->halaman();

        $this->assertStringContainsString("tema==='gelap'||tema==='terang'", $html);
        $this->assertStringContainsString("setAttribute('data-tema',tema)", $html);
    }

    /** Mode Terang menukar token rangka, bukan menyisir ulang tiap aturan. */
    public function test_mode_terang_menukar_token_sidebar_dan_bilah_atas(): void
    {
        $html = $this->halaman();

        $this->assertStringContainsString(':root[data-tema="terang"]{', $html);

        // Sidebar dan bilah atas jadi putih, tulisannya navy.
        $this->assertStringContainsString('--sb-bg:#ffffff;', $html);
        $this->assertStringContainsString('--sb-teks-kuat:var(--navy);', $html);
        $this->assertStringContainsString('--tb-kiri:#ffffff;', $html);
        $this->assertStringContainsString('--tb-teks:var(--navy);', $html);
    }

    /**
     * Warna rangka harus lewat token. Kalau ada yang tertinggal sebagai nilai
     * heksadesimal langsung, mode Terang akan menyisakan tulisan putih di
     * atas latar putih.
     */
    public function test_rangka_aplikasi_memakai_token_bukan_warna_langsung(): void
    {
        $html = $this->halaman();

        foreach ([
            '.sidebar{width:255px;flex:0 0 auto;background:var(--sb-bg);',
            'color:var(--sb-teks);cursor:pointer;',
            '.sb-item:hover{background:var(--sb-hover);color:var(--sb-teks-kuat);}',
            '.sb-head .t1{color:var(--sb-teks-kuat);',
            '.tb-ketik{flex:1 1 auto;min-width:0;color:var(--tb-teks);',
            '.tb-ikon:hover{background:var(--tb-chip-hover);}',
        ] as $potongan) {
            $this->assertStringContainsString($potongan, $html,
                'Aturan rangka ini tidak lagi memakai token: '.$potongan);
        }

        // Tidak boleh ada lagi putih/transparan-putih yang dipatok langsung
        // pada aturan sidebar maupun bilah atas.
        $this->assertDoesNotMatchRegularExpression(
            '/\.(sb-[a-z-]*|tb-[a-z-]*|sidebar|topbar)[^{]*\{[^}]*(#fff\b|rgba\(255,255,255)/',
            $html,
            'Masih ada warna putih yang dipatok langsung di aturan sidebar/bilah atas.'
        );
    }
}
