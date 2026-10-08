<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Dialog konfirmasi & pemberitahuan memakai dialog i-Finance
 * (layouts/partials/dialog), bukan confirm()/alert()/prompt() peramban.
 */
class DialogIFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'dialog-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    /**
     * Penjaga: tidak boleh ada lagi dialog bawaan peramban di tampilan mana
     * pun. Yang ditolak adalah PEMANGGILAN-nya - tulisan "confirm()" di dalam
     * komentar tidak ikut terjaring karena kurungnya kosong.
     */
    public function test_tidak_ada_lagi_dialog_bawaan_peramban_di_tampilan(): void
    {
        $pola = '/(?<![\w.$])(?:window\.)?(confirm|alert|prompt)\s*\(\s*[^)\s]/';
        $temuan = [];

        foreach ((new Finder)->files()->in(resource_path('views'))->name('*.blade.php') as $berkas) {
            foreach (explode("\n", $berkas->getContents()) as $i => $baris) {
                if (preg_match($pola, $baris, $cocok)) {
                    $temuan[] = $berkas->getRelativePathname().':'.($i + 1).' -> '.$cocok[1].'()';
                }
            }
        }

        $this->assertSame([], $temuan, "Masih ada dialog bawaan peramban; pakai data-konfirmasi / iFinance.konfirmasi():\n".implode("\n", $temuan));
    }

    public function test_dialog_tersedia_di_halaman_berlogin_maupun_halaman_layanan(): void
    {
        $halaman = $this->actingAs($this->user('superadmin'))->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.iFinance.konfirmasi = function', $halaman);
        $this->assertStringContainsString('window.iFinance.beritahu = function', $halaman);
        // Gayanya ikut terkirim, termasuk pita merek di tepi atas dialog.
        $this->assertStringContainsString('.ifd-pita{', $halaman);
        $this->assertStringContainsString('i-Finance &middot; Inspektorat Daerah Provinsi Jawa Barat', $halaman);

        auth()->logout();

        // Halaman tanpa login (Monitoring SP) memakai layout yang sama.
        $layanan = $this->lolosGerbangLayanan()->get(route('surat-perintah.monitoring'))->assertOk()->getContent();
        $this->assertStringContainsString('window.iFinance.konfirmasi = function', $layanan);
        $this->assertStringContainsString("iFinance.beritahu('Gagal menyimpan pemberitahuan. Silakan coba lagi.');", $layanan);
    }

    public function test_formulir_hapus_memakai_atribut_data_konfirmasi(): void
    {
        $superadmin = $this->user('superadmin');
        // Nama dengan tanda petik: dulu masuk ke dalam teks JavaScript di
        // atribut onsubmit dan bisa memutusnya; sebagai atribut biasa aman.
        $lain = User::create(['username' => "o'brien", 'nama' => 'Pengguna Lain', 'role' => 'pptk', 'password' => 'rahasia-uji']);

        $halaman = $this->actingAs($superadmin)->get(route('users.index'))->assertOk();

        $halaman->assertSee('data-konfirmasi="Yakin ingin menghapus PERMANEN user o&#039;brien? Tindakan ini tidak bisa dibatalkan."', false);
        $halaman->assertDontSee('onsubmit="return confirm', false);

        // Tanpa JavaScript pun formulirnya tetap formulir biasa yang menghapus.
        $this->actingAs($superadmin)->delete(route('users.destroy', $lain))->assertRedirect();
        $this->assertNull(User::find($lain->id));
    }

    public function test_pesan_berbaris_banyak_ditulis_sebagai_baris_baru_di_atribut(): void
    {
        $isi = file_get_contents(resource_path('views/gaji-tunjangan/rekonsiliasi.blade.php'));

        // "\n" milik JavaScript tidak berarti apa-apa di atribut HTML.
        $this->assertStringContainsString('data-konfirmasi="Kunci periode {{ $periode }}?&#10;&#10;Status Tunjangan Keluarga', $isi);
        $this->assertDoesNotMatchRegularExpression('/data-konfirmasi="[^"]*\\\\n/', $isi);
    }
}
