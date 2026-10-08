<?php

namespace Tests\Feature;

use App\Helpers\GuestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ringkasan role yang disepakati kantor (Oktober 2026), diuji baris demi
 * baris: tiap menu dibuka oleh SETIAP role, dan hasilnya harus cocok dengan
 * daftar di bawah - 200 untuk yang berhak, 403 untuk yang tidak.
 *
 * Daftar ini sengaja DITULIS ULANG di sini, tidak dibaca dari
 * config/akses.php. Kalau diambil dari config, test-nya hanya membuktikan
 * config sama dengan dirinya sendiri; yang perlu dibuktikan adalah config
 * DAN penjagaan rutenya sama dengan ringkasan yang disepakati.
 */
class RingkasanRoleTest extends TestCase
{
    use RefreshDatabase;

    private const IRBAN = ['irban1', 'irban2', 'irban3', 'irban4', 'irban_inv'];

    /** "Pimpinan" pada ringkasan: seluruh pejabat struktural. */
    private const PIMPINAN = ['inspektur', 'sekretaris', 'kasubbag', 'inspektur_pembantu', ...self::IRBAN];

    private const KEUANGAN = ['superadmin', 'bendahara_pengeluaran', 'bpp', 'pptk', 'verifikator'];

    private const LOGIN = [...self::KEUANGAN, ...self::PIMPINAN, 'perencanaan', 'kepegawaian', 'pengawas', 'pengelola_spj'];

    private const SEMUA = [...self::LOGIN, 'layanan'];

    /**
     * Nama rute -> role yang boleh membukanya.
     *
     * @return array<string, array<int, string>>
     */
    private function ringkasan(): array
    {
        $pemantau = [...self::KEUANGAN, ...self::PIMPINAN, 'pengawas'];
        $realisasi = [...self::KEUANGAN, ...self::PIMPINAN, 'perencanaan', 'pengawas'];
        // Monitoring PKPT & Data Kebutuhan: Superadmin, Perencanaan, Pengawas,
        // ditambah para Inspektur Pembantu yang mengisi Estimasi Kebutuhan.
        $pkpt = ['superadmin', 'perencanaan', 'pengawas', 'inspektur_pembantu', ...self::IRBAN];
        $penyajianGaji = self::SEMUA;

        return [
            // Dashboard
            'dashboard.index' => self::SEMUA,
            'dashboard.perjalanan.index' => $realisasi,
            'tunjangan.dashboard' => [...self::KEUANGAN, ...self::PIMPINAN, 'kepegawaian', 'pengawas'],
            'dashboard.spj.index' => $pemantau,
            // Dari Pimpinan hanya Inspektur Daerah, Sekretaris, dan Kasubbag TU.
            'dashboard.npd.index' => [...self::KEUANGAN, 'inspektur', 'sekretaris', 'kasubbag', 'pengawas'],

            // Rincian Realisasi
            'rincian.index' => $realisasi,
            'rincian.periodik' => $realisasi,

            // Analisis dan Tren
            'analisis.index' => $realisasi,
            'simulasi-anggaran.index' => $realisasi,
            'simulasi-realisasi.index' => $realisasi,
            'pkpt.index' => $pkpt,
            'kebutuhan.index' => $pkpt,
            'kebutuhan.create' => self::IRBAN,

            // Nota Pencairan Dana (NPD)
            'npd.data' => $pemantau,
            'npd.index' => ['superadmin', 'pptk'],
            'npd.persetujuan' => ['superadmin', 'bendahara_pengeluaran', 'bpp'],
            'npd.verifikasi' => ['superadmin', 'verifikator'],
            'rekanan.index' => self::KEUANGAN,

            // Pengembalian
            'pengembalian.create' => ['superadmin', 'bendahara_pengeluaran', 'bpp', 'verifikator'],
            'pengembalian.index' => ['superadmin', 'bendahara_pengeluaran', 'bpp', 'verifikator'],

            'inventarisasi-spj.index' => ['superadmin', 'pptk', 'bpp', 'bendahara_pengeluaran', 'sekretaris', 'kasubbag', 'pengawas', 'pengelola_spj'],

            // Data Realisasi SP2D
            'spm.up-gu.index' => $pemantau,
            'spm.ls.index' => $pemantau,

            // Surat Perintah. Input SP untuk role yang login; Pengguna Layanan
            // memakai formulir publiknya sendiri (diuji terpisah di bawah).
            'surat-perintah.create' => array_values(array_diff(self::LOGIN, ['pengawas'])),
            'surat-perintah.index' => $pemantau,
            'surat-perintah.rekap-pembayaran' => $pemantau,
            'surat-perintah.monitoring' => self::SEMUA,
            'cetak-spj.index' => self::SEMUA,
            'segera.sp-cetaksppd' => self::SEMUA,

            // Data Kepegawaian
            'tunjangan.pegawai.index' => [...self::KEUANGAN, ...self::PIMPINAN, 'kepegawaian', 'perencanaan', 'pengawas'],
            'tunjangan.data.index' => ['superadmin', 'bendahara_pengeluaran', 'pptk', 'kepegawaian', 'pengawas'],
            'tunjangan.monitoring' => self::SEMUA,

            // Gaji dan Tunjangan
            'gaji-tunjangan.tabel.gaji' => $penyajianGaji,
            'gaji-tunjangan.tabel.beban' => $penyajianGaji,
            'gaji-tunjangan.tabel.kondisi' => $penyajianGaji,
            'gaji-tunjangan.tabel.total' => $penyajianGaji,
            'gaji-tunjangan.rincian.create' => self::SEMUA,
            'gaji-tunjangan.rincian.index' => ['superadmin', 'bendahara_pengeluaran'],
            'gaji-tunjangan.rekonsiliasi' => ['superadmin', 'bendahara_pengeluaran'],
            'gaji-tunjangan.rekap-potensi' => ['superadmin', 'bendahara_pengeluaran', 'kasubbag', 'sekretaris', 'inspektur'],

            'audit-log.index' => ['superadmin'],

            // Setting
            'pelimpahan.index' => ['superadmin'],
            'manajemen-data.index' => ['superadmin'],
            'users.index' => ['superadmin'],

            'profil.show' => self::LOGIN,
        ];
    }

    /** Masuk sebagai role itu: akun sungguhan, atau sesi tamu untuk Pengguna Layanan. */
    private function sebagai(string $role): static
    {
        if ($role === 'layanan') {
            return $this->withSession([GuestSession::kunciSesi() => true]);
        }

        return $this->actingAs(User::create([
            'username' => 'ringkasan-'.$role,
            'nama' => 'Uji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]));
    }

    public function test_daftar_role_di_test_ini_mencakup_seluruh_role_aplikasi(): void
    {
        $this->assertEqualsCanonicalizing(self::LOGIN, User::ROLE_OPTIONS);
        $this->assertEqualsCanonicalizing(self::SEMUA, array_keys(config('akses.menu')));
        $this->assertEqualsCanonicalizing(self::SEMUA, array_keys(config('akses.role_label')));
    }

    /**
     * Inti berkas ini. Satu test per role supaya kegagalannya langsung
     * menyebut role mana yang menyimpang.
     */
    #[DataProvider('daftarRole')]
    public function test_setiap_menu_terbuka_dan_tertutup_sesuai_ringkasan(string $role): void
    {
        $this->sebagai($role);

        foreach ($this->ringkasan() as $rute => $bolehBuka) {
            $respons = $this->get(route($rute));

            if (in_array($role, $bolehBuka, true)) {
                $respons->assertOk();
            } else {
                $this->assertSame(
                    403,
                    $respons->getStatusCode(),
                    "Role {$role} seharusnya DITOLAK di {$rute}, tetapi mendapat {$respons->getStatusCode()}."
                );
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function daftarRole(): array
    {
        return array_combine(self::SEMUA, array_map(fn (string $role) => [$role], self::SEMUA));
    }

    /**
     * Sidebar tidak boleh menawarkan menu yang rutenya menolak: tiap tautan
     * di sidebar sebuah role harus bisa dibuka role itu.
     */
    #[DataProvider('daftarRole')]
    public function test_setiap_tautan_sidebar_bisa_dibuka_role_pemiliknya(string $role): void
    {
        $this->sebagai($role);

        $isi = $this->get(route('dashboard.index'))->assertOk()->getContent();
        $mulai = strpos($isi, '<nav class="sb-menu">');
        $this->assertNotFalse($mulai, 'Blok sidebar tidak ditemukan.');
        $nav = substr($isi, $mulai, strpos($isi, '</nav>', $mulai) - $mulai);

        preg_match_all('/<a class="sb-item[^"]*" href="([^"]+)"/', $nav, $cocok);
        $this->assertNotEmpty($cocok[1], "Sidebar role {$role} kosong.");

        foreach (array_unique($cocok[1]) as $url) {
            $status = $this->get(html_entity_decode($url))->getStatusCode();
            $this->assertSame(200, $status, "Role {$role}: tautan sidebar {$url} menghasilkan {$status}.");
        }
    }

    public function test_pengguna_layanan_dan_pengawas_pada_dua_formulir_isian(): void
    {
        // Pengguna Layanan mengisi orderan SP lewat formulir publiknya.
        $this->sebagai('layanan')->get(route('sp.input.create'))->assertOk();
        $this->get(route('tunjangan.form'))->assertOk();
        $this->flushSession();

        // Pengawas hanya membaca: kedua formulir isian tidak ia pegang.
        $menu = config('akses.menu.pengawas');
        $this->assertNotContains('sp-input', $menu);
        $this->assertNotContains('tk-form', $menu);
        $this->sebagai('pengawas')->get(route('sp.input.create'))->assertForbidden();
    }

    // ---------------- Role baru: Pengelola SPJ ----------------

    public function test_pengelola_spj_terdaftar_sebagai_role_login(): void
    {
        $this->assertSame('pengelola_spj', User::ROLE_PENGELOLA_SPJ);
        $this->assertContains(User::ROLE_PENGELOLA_SPJ, User::ROLE_OPTIONS);
        $this->assertSame('Pengelola SPJ', config('akses.role_label.pengelola_spj'));

        // Di luar Inventarisasi SPJ, menunya hanya yang terbuka untuk semua role.
        $this->assertSame([
            'dashboard',
            'invspj',
            'sp-input', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd',
            'tk-form', 'tk-monitor',
            'gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak',
            'profil',
        ], config('akses.menu.pengelola_spj'));

        $this->assertSame(['superadmin', 'pengelola_spj'], config('akses.kelola.invspj'));
    }

    /** Kolom users.role adalah ENUM - role baru harus ikut terdaftar di skema. */
    public function test_superadmin_dapat_membuat_akun_pengelola_spj(): void
    {
        $this->sebagai('superadmin')->post(route('users.store'), [
            'username' => 'staf-spj',
            'nama' => 'Staf Pengelola SPJ',
            'role' => User::ROLE_PENGELOLA_SPJ,
            'password' => 'kata-sandi-uji',
            'password_confirmation' => 'kata-sandi-uji',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'staf-spj', 'role' => User::ROLE_PENGELOLA_SPJ]);
    }

    // ---------------- Gerbang privasi Rincian Penghasilan ----------------

    /** Selain superadmin dan Bendahara Pengeluaran wajib NIP + 4 digit rekening. */
    public function test_hanya_superadmin_dan_bendahara_pengeluaran_yang_bebas_gerbang_rincian_penghasilan(): void
    {
        $this->assertSame(['superadmin', 'bendahara_pengeluaran'], config('gaji_tunjangan.role_data_penuh'));

        foreach (['superadmin', 'bendahara_pengeluaran'] as $role) {
            $this->sebagai($role)->get(route('gaji-tunjangan.tabel.gaji'))
                ->assertOk()->assertDontSee('Verifikasi Identitas');
        }

        // Inspektur, Sekretaris, dan Kasubbag dulu bebas gerbang.
        foreach (['inspektur', 'sekretaris', 'kasubbag', 'kepegawaian', 'pengelola_spj'] as $role) {
            $this->sebagai($role)->get(route('gaji-tunjangan.tabel.gaji'))
                ->assertOk()->assertSee('Verifikasi Identitas');
        }
    }

    // ---------------- Middleware 'kelola' ----------------

    public function test_helper_boleh_kelola_mengikuti_config(): void
    {
        $this->sebagai('bendahara_pengeluaran');
        $this->assertTrue(boleh_kelola('spm'));
        $this->assertFalse(boleh_kelola('invspj'));
        // Kunci yang tidak terdaftar tidak punya pengelola - bukan terbuka.
        $this->assertFalse(boleh_kelola('tidak-ada'));

        $this->sebagai('pengelola_spj');
        $this->assertTrue(boleh_kelola('invspj'));
        $this->assertFalse(boleh_kelola('spm'));
        $this->assertTrue(pegang_menu('invspj'));
        $this->assertFalse(pegang_menu('npd-data'));
    }
}
