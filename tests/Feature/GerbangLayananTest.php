<?php

namespace Tests\Feature;

use App\Helpers\GuestSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang Pengguna Layanan.
 *
 * Pengguna Layanan tidak punya akun dan tidak mendaftar, tetapi sejak
 * aplikasi dihosting halaman-halamannya tidak boleh terbuka bebas: harus
 * lewat satu kata sandi bersama yang dimasukkan di halaman login.
 *
 * Yang dikunci di sini adalah pemeriksaannya di PELADEN. Jendela kata sandi
 * di halaman login cuma tampilan - kalau pengecekannya hanya di peramban,
 * siapa pun tinggal membuka /sp/input langsung.
 */
class GerbangLayananTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function halamanLayanan(): array
    {
        return [
            route('sp.input.create'),
            route('surat-perintah.monitoring'),
            route('cetak-spj.index'),
            route('tunjangan.form'),
        ];
    }

    public function test_halaman_layanan_tertutup_sebelum_kata_sandi_dimasukkan(): void
    {
        foreach ($this->halamanLayanan() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->assertFalse(GuestSession::isActive());
    }

    public function test_kirim_orderan_sp_juga_ditolak_tanpa_kata_sandi(): void
    {
        $this->post(route('sp.input.store'), [])->assertRedirect(route('login'));
    }

    public function test_kata_sandi_benar_membuka_seluruh_halaman_layanan(): void
    {
        $this->post(route('layanan.masuk'), ['sandi' => 'itprovjabar'])
            ->assertRedirect(route('sp.input.create'))
            ->assertSessionHasNoErrors();

        foreach ($this->halamanLayanan() as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame(
            1,
            AuditLog::where('aktivitas', 'Login')->where('role', 'layanan')->count()
        );
    }

    public function test_kata_sandi_salah_ditolak_dan_tidak_membuka_apa_pun(): void
    {
        $this->post(route('layanan.masuk'), ['sandi' => 'salah-sekali'])
            ->assertSessionHasErrors('sandi');

        $this->get(route('sp.input.create'))->assertRedirect(route('login'));

        $this->assertSame(
            1,
            AuditLog::where('aktivitas', 'Login Gagal')->where('role', 'layanan')->count()
        );
    }

    public function test_kata_sandi_kosong_ditolak(): void
    {
        $this->post(route('layanan.masuk'), [])->assertSessionHasErrors('sandi');
        $this->get(route('sp.input.create'))->assertRedirect(route('login'));
    }

    public function test_kata_sandi_mengikuti_konfigurasi_bukan_nilai_yang_dipaku_di_kode(): void
    {
        config(['akses.sandi_layanan' => 'sandi-server-lain']);

        $this->post(route('layanan.masuk'), ['sandi' => 'itprovjabar'])->assertSessionHasErrors('sandi');
        $this->post(route('layanan.masuk'), ['sandi' => 'sandi-server-lain'])->assertSessionHasNoErrors();
    }

    public function test_user_yang_login_tidak_perlu_kata_sandi_layanan(): void
    {
        $pptk = User::create([
            'username' => 'pptk-gerbang',
            'nama' => 'PPTK Gerbang',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);

        foreach ($this->halamanLayanan() as $url) {
            $this->actingAs($pptk)->get($url)->assertOk();
        }
    }

    public function test_keluar_menutup_kembali_gerbangnya(): void
    {
        $this->post(route('layanan.masuk'), ['sandi' => 'itprovjabar'])->assertRedirect(route('sp.input.create'));
        $this->get(route('sp.input.create'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->get(route('sp.input.create'))->assertRedirect(route('login'));
    }

    public function test_percobaan_kata_sandi_dibatasi_lima_kali_per_menit(): void
    {
        $klien = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);

        for ($i = 0; $i < 5; $i++) {
            $klien->post(route('layanan.masuk'), ['sandi' => 'coba-tebak'])->assertRedirect();
        }

        $klien->post(route('layanan.masuk'), ['sandi' => 'coba-tebak'])->assertTooManyRequests();
    }

    public function test_halaman_login_menyediakan_jendela_kata_sandi_layanan(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk sebagai Pengguna Layanan')
            ->assertSee('Masukkan Kata Sandi')
            ->assertSee(route('layanan.masuk'), false)
            // Kata sandinya TIDAK boleh ikut terkirim ke peramban.
            ->assertDontSee('itprovjabar');
    }

    public function test_jendela_terbuka_sendiri_setelah_ditolak_gerbang(): void
    {
        // Kunjungan biasa: jendela diam, tidak dipanggil.
        $this->get(route('login'))->assertOk()->assertDontSee('tampilkan();', false);

        $this->get(route('sp.input.create'))->assertRedirect(route('login'));

        // Setelah ditolak gerbang, jendelanya langsung terbuka sendiri.
        $this->get(route('login'))->assertOk()->assertSee('tampilkan();', false);
    }
}
