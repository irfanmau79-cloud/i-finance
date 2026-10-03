<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\PelimpahanVerifikator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikator terikat ke Sub Kegiatan lewat menu Pelimpahan.
 *
 * - Tiap Sub Kegiatan ditetapkan ke satu AKUN Verifikator.
 * - Antrean Verifikasi akun itu hanya memuat NPD Sub Kegiatan miliknya.
 * - Verifikasi & Kembalikan ke BPP hanya oleh akun itu (atau superadmin).
 * - Sub Kegiatan tanpa Verifikator TIDAK bisa diverifikasi siapa pun,
 *   termasuk superadmin, dan sistem memberi tahu untuk menetapkannya dulu.
 */
class VerifikatorSubKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    private function user(string $role, string $nama): User
    {
        $this->urut++;

        return User::create([
            'username' => 'vsk-'.$role.'-'.$this->urut,
            'nama' => $nama,
            'role' => $role,
            'password' => 'rahasia',
            'aktif' => true,
        ]);
    }

    private function anggaran(string $sub): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Verifikator',
            'kegiatan' => 'Kegiatan Verifikator',
            'sub_kegiatan' => $sub,
            'kode_rekening' => '5.1.02.01.01.0024',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    private function npd(MasterAnggaran $anggaran, string $status = 'Verifikasi - Verifikator'): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => $status,
        ]);
    }

    /** Persis yang dikirim tabel Distribusi Sub Kegiatan di halaman Pelimpahan. */
    private function scope(MasterAnggaran $anggaran): string
    {
        return base64_encode(json_encode(['program' => $anggaran->program_normal, 'sub_kegiatan' => $anggaran->sub_kegiatan_normal]));
    }

    private function tetapkan(User $superadmin, MasterAnggaran $anggaran, ?User $verifikator): void
    {
        $this->actingAs($superadmin)->post(route('pelimpahan.sub-kegiatan.set'), [
            'rows' => [['scope' => $this->scope($anggaran), 'verifikator_user_id' => $verifikator?->id ?? '']],
        ])->assertSessionHasNoErrors();
    }

    // ---------------- Pelimpahan ----------------

    public function test_superadmin_menetapkan_mengganti_dan_mengosongkan_verifikator_tanpa_rantai_kpa(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $budi = $this->user('verifikator', 'Budi Verifikator');
        $a = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');

        // Hanya kolom Verifikator - KPA/PPTK sub kegiatan ini belum diset sama sekali.
        $this->tetapkan($admin, $a, $andi);
        $this->assertSame($andi->id, PelimpahanVerifikator::untukMasterAnggaran($a)?->id);

        // Mengganti tidak menimpa: baris lama dinonaktifkan, riwayatnya tetap ada.
        $this->tetapkan($admin, $a, $budi);
        $this->assertSame($budi->id, PelimpahanVerifikator::untukMasterAnggaran($a)?->id);
        $this->assertSame(2, PelimpahanVerifikator::count());
        $this->assertSame(1, PelimpahanVerifikator::aktif()->count());

        // Dikosongkan.
        $this->tetapkan($admin, $a, null);
        $this->assertNull(PelimpahanVerifikator::untukMasterAnggaran($a));
        $this->assertSame(0, PelimpahanVerifikator::aktif()->count());

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'Set Pelimpahan Sub Kegiatan']);
    }

    public function test_yang_bisa_ditetapkan_hanya_akun_aktif_ber_role_verifikator(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $a = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $bpp = $this->user('bpp', 'Bukan Verifikator');
        $nonaktif = $this->user('verifikator', 'Verifikator Nonaktif');
        $nonaktif->update(['aktif' => false]);

        foreach ([$bpp, $nonaktif] as $salah) {
            $this->actingAs($admin)->post(route('pelimpahan.sub-kegiatan.set'), [
                'rows' => [['scope' => $this->scope($a), 'verifikator_user_id' => $salah->id]],
            ])->assertSessionHasErrors();
        }

        $this->assertSame(0, PelimpahanVerifikator::count());
    }

    public function test_halaman_pelimpahan_memuat_kolom_verifikator_dan_peringatan_yang_belum(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $a = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $this->anggaran('6.01.01.2.02 Sub Kegiatan Beta');
        $this->tetapkan($admin, $a, $andi);

        $this->actingAs($admin)->get(route('pelimpahan.index'))
            ->assertOk()
            ->assertSee('<th>Verifikator</th>', false)
            ->assertSee('class="dsk-verif" data-awal="'.$andi->id.'"', false)
            ->assertSee('1 Sub Kegiatan belum memiliki Verifikator.');

        // Penyaring "Belum ada Verifikator" hanya menyisakan Beta.
        $this->actingAs($admin)->get(route('pelimpahan.index', ['status' => 'tanpa_verifikator']))
            ->assertOk()
            ->assertViewHas('subKegiatanList', fn ($daftar) => collect($daftar->items())->pluck('sub_kegiatan_normal')
                ->map(fn ($nama) => str_contains($nama, 'Beta') ? 'Beta' : $nama)->all() === ['Beta']);

        // Penyaring per akun Verifikator.
        $this->actingAs($admin)->get(route('pelimpahan.index', ['verifikator_user_id' => $andi->id]))
            ->assertOk()
            ->assertViewHas('subKegiatanList', fn ($daftar) => collect($daftar->items())->pluck('sub_kegiatan_normal')
                ->map(fn ($nama) => str_contains($nama, 'Alpha') ? 'Alpha' : $nama)->all() === ['Alpha']);
    }

    // ---------------- Antrean ----------------

    public function test_antrean_tiap_verifikator_hanya_memuat_sub_kegiatan_miliknya(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $budi = $this->user('verifikator', 'Budi Verifikator');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $beta = $this->anggaran('6.01.01.2.02 Sub Kegiatan Beta');
        $gamma = $this->anggaran('6.01.01.2.03 Sub Kegiatan Gamma');
        $this->tetapkan($admin, $alpha, $andi);
        $this->tetapkan($admin, $beta, $budi);

        $npdAlpha = $this->npd($alpha);
        $npdBeta = $this->npd($beta);
        $npdGamma = $this->npd($gamma); // tanpa Verifikator

        $ids = fn ($npds) => $npds->pluck('id')->sort()->values()->all();

        $this->actingAs($andi)->get(route('npd.verifikasi'))->assertOk()
            ->assertViewHas('npds', fn ($npds) => $ids($npds) === [$npdAlpha->id])
            ->assertSee('1 NPD</b> menunggu verifikasi tetapi Sub Kegiatannya belum punya Verifikator', false);

        $this->actingAs($budi)->get(route('npd.verifikasi'))->assertOk()
            ->assertViewHas('npds', fn ($npds) => $ids($npds) === [$npdBeta->id]);

        // Superadmin memantau semuanya, beserta nama Verifikator tiap baris.
        $this->actingAs($admin)->get(route('npd.verifikasi'))->assertOk()
            ->assertViewHas('npds', fn ($npds) => $ids($npds) === [$npdAlpha->id, $npdBeta->id, $npdGamma->id])
            ->assertSee('<span class="stat-verif" title="Verifikator Sub Kegiatan ini">Andi Verifikator</span>', false)
            ->assertSee('Verifikator belum ditetapkan')
            ->assertSee(route('pelimpahan.index', ['status' => 'tanpa_verifikator']), false);
    }

    // ---------------- Transisi ----------------

    public function test_hanya_verifikator_yang_ditetapkan_yang_bisa_memverifikasi(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $budi = $this->user('verifikator', 'Budi Verifikator');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $this->tetapkan($admin, $alpha, $andi);
        $npd = $this->npd($alpha);

        $this->actingAs($budi)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '01/NPD/2026'])
            ->assertSessionHasErrors(['aksi' => 'NPD ini ditugaskan ke Verifikator Andi Verifikator, bukan ke akun Anda.']);
        $this->actingAs($budi)->post(route('npd.transisi', $npd), ['aksi' => 'kembali_bpp', 'catatan' => 'Revisi'])
            ->assertSessionHasErrors('aksi');
        $this->actingAs($budi)->get(route('npd.coret', $npd))->assertForbidden();
        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);

        $this->actingAs($andi)->get(route('npd.coret', $npd))->assertOk();
        $this->actingAs($andi)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '01/NPD/2026'])
            ->assertSessionHasNoErrors();

        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);
        $this->assertSame($andi->id, $npd->historiStatus()->where('aksi', 'verifikasi')->value('user_id'));
    }

    public function test_sub_kegiatan_tanpa_verifikator_tidak_bisa_diverifikasi_siapa_pun(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $npd = $this->npd($alpha);
        $pesan = 'Verifikator untuk Sub Kegiatan NPD ini belum ditetapkan. Tetapkan dulu Verifikatornya di menu Pelimpahan.';

        foreach ([$andi, $admin] as $user) {
            $this->actingAs($user)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '02/NPD/2026'])
                ->assertSessionHasErrors(['aksi' => $pesan]);
        }

        // Detail NPD memberi tahu, dan tidak menawarkan tombol coret.
        $this->actingAs($admin)->get(route('npd.show', $npd))->assertOk()
            ->assertSee('belum bisa diverifikasi</b>', false)
            ->assertSee('Belum ditetapkan')
            ->assertDontSee(route('npd.coret', $npd), false);

        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);

        // Setelah ditetapkan, alurnya jalan lagi.
        $this->tetapkan($admin, $alpha, $andi);
        $this->actingAs($andi)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '02/NPD/2026'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Draft NPD - BPP', $npd->fresh()->status);
    }

    public function test_verifikasi_massal_superadmin_melewati_npd_tanpa_verifikator(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $beta = $this->anggaran('6.01.01.2.02 Sub Kegiatan Beta');
        $this->tetapkan($admin, $alpha, $andi);
        $ada = $this->npd($alpha);
        $tanpa = $this->npd($beta);

        $this->actingAs($admin)->post(route('npd.transisi-massal'), [
            'aksi' => 'verifikasi',
            'npd' => [$ada->id, $tanpa->id],
            'nomor' => [$ada->id => '10/NPD/2026', $tanpa->id => '11/NPD/2026'],
        ])->assertSessionHasErrors('massal');

        $this->assertSame('Draft NPD - BPP', $ada->fresh()->status);
        $this->assertSame('Verifikasi - Verifikator', $tanpa->fresh()->status);
        $this->assertNull($tanpa->fresh()->nomor_lengkap);
    }

    public function test_meneruskan_ke_verifikator_yang_belum_ada_memberi_peringatan(): void
    {
        $bpp = $this->user('bpp', 'BPP Uji');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $npd = $this->npd($alpha, 'Draft NPD - BPP');

        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, 'Verifikator untuk Sub Kegiatan NPD ini belum ditetapkan'));

        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);
    }

    public function test_detail_npd_menyebut_verifikator_dan_yang_memverifikasi(): void
    {
        $admin = $this->user('superadmin', 'Admin');
        $andi = $this->user('verifikator', 'Andi Verifikator');
        $budi = $this->user('verifikator', 'Budi Verifikator');
        $alpha = $this->anggaran('6.01.01.2.01 Sub Kegiatan Alpha');
        $this->tetapkan($admin, $alpha, $andi);
        $npd = $this->npd($alpha);

        $this->actingAs($andi)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '03/NPD/2026'])
            ->assertSessionHasNoErrors();

        // Penugasan dipindah SESUDAH diverifikasi: "Diverifikasi oleh" tetap Andi.
        $this->tetapkan($admin, $alpha, $budi);

        $isi = $this->actingAs($admin)->get(route('npd.show', $npd))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Verifikator<\/span>\s*<span class="v">\s*Budi Verifikator/', $isi);
        $this->assertMatchesRegularExpression('/Diverifikasi oleh<\/span><span class="v">Andi Verifikator<\/span>/', $isi);
    }
}
