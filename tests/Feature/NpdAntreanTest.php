<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antrean Persetujuan NPD (BPP) dan Verifikasi NPD (Verifikator), port dari
 * getNPDuntukBPP() / getNPDuntukVerifikator() di gas-lama/CodeRevisi.gs.
 */
class NpdAntreanTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function buatNpd(string $status): Npd
    {
        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0099',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => $status,
        ]);
    }

    public function test_persetujuan_default_hanya_menampilkan_npd_yang_memerlukan_tindakan_bpp(): void
    {
        $bpp = $this->buatUser('bpp', 'antrean-bpp');
        $draftPptk = $this->buatNpd('Draft NPD - PPTK');
        $draftBpp = $this->buatNpd('Draft NPD - BPP');

        $this->actingAs($bpp)->get(route('npd.persetujuan'))
            ->assertOk()
            ->assertSee($draftBpp->status)
            ->assertViewHas('npds', fn ($npds) => $npds->count() === 1
                && $npds->first()->is($draftBpp));

        $this->actingAs($bpp)->get(route('npd.persetujuan', ['status' => 'semua']))
            ->assertOk()
            ->assertSee($draftPptk->status)
            ->assertSee($draftBpp->status)
            ->assertViewHas('npds', fn ($npds) => $npds->count() === 2);
    }

    public function test_verifikasi_default_hanya_menampilkan_npd_yang_memerlukan_tindakan_verifikator(): void
    {
        $verifikator = $this->buatUser('verifikator', 'antrean-verif');
        $draftPptk = $this->buatNpd('Draft NPD - PPTK');
        $verifikasi = $this->buatNpd('Verifikasi - Verifikator');

        $this->actingAs($verifikator)->get(route('npd.verifikasi'))
            ->assertOk()
            ->assertSee($verifikasi->status)
            ->assertViewHas('npds', fn ($npds) => $npds->count() === 1 && $npds->first()->is($verifikasi));

        $this->actingAs($verifikator)->get(route('npd.verifikasi', ['status' => 'semua']))
            ->assertOk()
            ->assertSee($draftPptk->status)
            ->assertSee($verifikasi->status)
            ->assertViewHas('npds', fn ($npds) => $npds->count() === 2);
    }

    public function test_verifikator_ditolak_akses_antrean_persetujuan_dan_sebaliknya(): void
    {
        $bpp = $this->buatUser('bpp', 'salah-antrean-bpp');
        $verifikator = $this->buatUser('verifikator', 'salah-antrean-verif');

        $this->actingAs($verifikator)->get(route('npd.persetujuan'))->assertForbidden();
        $this->actingAs($bpp)->get(route('npd.verifikasi'))->assertForbidden();
    }

    public function test_superadmin_bisa_akses_kedua_antrean(): void
    {
        $superadmin = $this->buatUser('superadmin', 'superadmin-antrean');
        $this->buatNpd('Draft NPD - BPP');

        $this->actingAs($superadmin)->get(route('npd.persetujuan'))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.verifikasi'))->assertOk();
    }

    /**
     * Penyaring jenis & status yang dulu melayang di atas tabel sudah dibuang -
     * pekerjaannya diambil alih baris penyaring di dalam tabel. Penyaring
     * peladen sendiri TIDAK dibuang: antrean tetap dibatasi pada NPD yang
     * memerlukan tindakan, dan ?status=semua tetap bisa dipakai.
     */
    public function test_penyaring_melayang_di_atas_tabel_sudah_dibuang(): void
    {
        $superadmin = $this->buatUser('superadmin', 'superadmin-saring');
        $this->buatNpd('Draft NPD - BPP');

        foreach (['npd.index', 'npd.persetujuan', 'npd.verifikasi'] as $rute) {
            $this->actingAs($superadmin)->get(route($rute))
                ->assertOk()
                ->assertDontSee('-- Semua Jenis --')
                ->assertDontSee('-- Semua Status --')
                ->assertSee('kolom-saring', false);
        }
    }

    /**
     * Sel Status memakai pil berwarna yang sama persis dengan Data NPD, plus
     * pil "Catatan" di bawahnya - bukan teks telanjang. Yang dibuang cuma
     * kotak abu pembungkusnya, karena itulah yang membuat kolom sempit ini
     * terasa berdesakan.
     */
    public function test_sel_status_memakai_pil_berwarna_dan_pil_catatan(): void
    {
        $bpp = $this->buatUser('bpp', 'bpp-status-pil');
        $npd = $this->buatNpd('Draft NPD - BPP');
        $npd->update(['catatan' => 'Mohon dilengkapi lampirannya']);

        $this->actingAs($bpp)->get(route('npd.persetujuan'))
            ->assertOk()
            ->assertSee('class="stat-kolom"', false)
            ->assertSee('class="badge st-npd-bpp"', false)
            ->assertSee('class="stat-cat"', false)
            ->assertSee('class="kol-npd"', false)
            ->assertDontSee('class="stat-cell"', false)
            ->assertDontSee('Terdapat Catatan')
            ->assertSee('Catatan');
    }

    /**
     * Warna pil status dipetakan dari STATUS_BADGE_CLASS - Selesai hijau,
     * Dibatalkan oranye, dan seterusnya - sama seperti Data NPD.
     */
    public function test_setiap_status_memakai_kelas_warnanya_sendiri(): void
    {
        $superadmin = $this->buatUser('superadmin', 'superadmin-warna-status');

        foreach (Npd::STATUS_LIST as $status) {
            $this->buatNpd($status);
        }

        $halaman = $this->actingAs($superadmin)->get(route('npd.index'))->assertOk();

        foreach (Npd::STATUS_BADGE_CLASS as $kelas) {
            $halaman->assertSee('class="badge '.$kelas.'"', false);
        }
    }

    public function test_bpp_klik_dari_antrean_ke_detail_lalu_kembali(): void
    {
        $bpp = $this->buatUser('bpp', 'klik-bpp');
        $npd = $this->buatNpd('Draft NPD - BPP');

        // Aksi workflow (Teruskan ke Verifikator) tampil sebagai ikon di
        // daftar antrean itu sendiri — bukan lagi di halaman detail, supaya
        // tidak ada tombol aksi yang terduplikasi di dua tempat.
        $this->actingAs($bpp)->get(route('npd.persetujuan'))
            ->assertOk()
            ->assertSee('Teruskan ke Verifikator', false)
            ->assertSee(route('npd.show', $npd), false);

        $this->actingAs($bpp)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee(route('npd.persetujuan'), false);
    }
}
