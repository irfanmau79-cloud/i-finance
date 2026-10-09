<?php

namespace Tests\Feature;

use App\Models\ChatBaca;
use App\Models\ChatPesan;
use App\Models\ChatRuang;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chat internal antar akun: percakapan pribadi 1-ke-1 dan ruang per role.
 * Hanya untuk akun berlogin - Pengguna Layanan tidak ikut.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $nama = null): User
    {
        return User::create([
            'username' => 'chat-'.$role.'-'.User::count(),
            'nama' => $nama ?? 'Akun '.$role.' '.User::count(),
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function kirim(User $pengirim, ChatRuang $ruang, string $isi)
    {
        return $this->actingAs($pengirim)->postJson(route('chat.kirim', $ruang), ['isi' => $isi]);
    }

    // ---------------- Percakapan pribadi ----------------

    public function test_dua_akun_bisa_saling_berkirim_pesan_pribadi(): void
    {
        $pptk = $this->user('pptk', 'Budi PPTK');
        $bpp = $this->user('bpp', 'Sari BPP');

        // Membuka kontak membuat ruangnya dan mengarahkan ke sana.
        $this->actingAs($pptk)->post(route('chat.pribadi', $bpp))->assertRedirect();
        $ruang = ChatRuang::where('jenis', ChatRuang::JENIS_PRIBADI)->sole();
        $this->assertSame([$pptk->id, $bpp->id], [$ruang->user_a_id, $ruang->user_b_id]);

        $this->kirim($pptk, $ruang, 'Halo Bu, NPD 12 sudah saya ajukan.')
            ->assertOk()
            ->assertJsonPath('pesan.isi', 'Halo Bu, NPD 12 sudah saya ajukan.')
            ->assertJsonPath('pesan.milik_saya', true);

        // Penerima melihatnya sebagai belum dibaca, di daftar dan di lencana.
        $ringkas = $this->actingAs($bpp)->getJson(route('chat.ringkas'))->assertOk();
        $ringkas->assertJsonPath('belum', 1);
        $this->assertSame('Budi PPTK', collect($ringkas->json('ruang'))->firstWhere('id', $ruang->id)['nama']);

        // Membuka ruangnya menandai dibaca dan menampilkan pesannya.
        $this->actingAs($bpp)->get(route('chat.index', ['ruang' => $ruang->id]))
            ->assertOk()
            ->assertSee('Budi PPTK')
            ->assertSee('Halo Bu, NPD 12 sudah saya ajukan.');
        $this->actingAs($bpp)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 0);

        // Balasan sampai ke pengirim pertama.
        $this->kirim($bpp, $ruang, 'Siap, saya cek.')->assertOk()->assertJsonPath('pesan.milik_saya', true);
        $this->actingAs($pptk)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 1);

        // Membuka kontak yang sama dari sisi sebaliknya memakai ruang yang SAMA.
        $this->actingAs($bpp)->post(route('chat.pribadi', $pptk))
            ->assertRedirect(route('chat.index', ['ruang' => $ruang->id]));
        $this->assertSame(1, ChatRuang::where('jenis', ChatRuang::JENIS_PRIBADI)->count());
    }

    public function test_percakapan_pribadi_tertutup_bagi_akun_lain_termasuk_superadmin(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $superadmin = $this->user('superadmin');
        $ruang = app(ChatService::class)->ruangPribadi($pptk, $bpp);
        $this->kirim($pptk, $ruang, 'Rahasia dua orang')->assertOk();

        foreach ([$superadmin, $this->user('verifikator')] as $orangLain) {
            $this->actingAs($orangLain)->get(route('chat.index', ['ruang' => $ruang->id]))->assertNotFound();
            $this->actingAs($orangLain)->getJson(route('chat.pesan', $ruang))->assertNotFound();
            $this->kirim($orangLain, $ruang, 'Menyusup')->assertNotFound();
            // Tidak muncul di daftar mereka dan tidak menambah angka belum dibaca.
            $this->actingAs($orangLain)->getJson(route('chat.ringkas'))
                ->assertJsonPath('belum', 0)
                ->assertJsonMissing(['id' => $ruang->id, 'jenis' => 'pribadi']);
        }

        $this->assertSame(1, ChatPesan::count());
    }

    public function test_tidak_bisa_chat_dengan_diri_sendiri_atau_akun_nonaktif(): void
    {
        $pptk = $this->user('pptk');
        $nonaktif = $this->user('bpp');
        $nonaktif->forceFill(['aktif' => false])->save();

        $this->actingAs($pptk)->post(route('chat.pribadi', $pptk))->assertNotFound();
        $this->actingAs($pptk)->post(route('chat.pribadi', $nonaktif))->assertNotFound();
        $this->assertSame(0, ChatRuang::where('jenis', ChatRuang::JENIS_PRIBADI)->count());

        // Akun nonaktif juga tidak ditawarkan sebagai kontak.
        $this->actingAs($pptk)->get(route('chat.index'))->assertOk()->assertDontSee($nonaktif->nama);
    }

    // ---------------- Ruang per role ----------------

    public function test_pesan_ke_ruang_role_dibaca_semua_pemegang_role_itu_dan_bisa_dibalas(): void
    {
        $pptk = $this->user('pptk', 'Budi PPTK');
        $bppSatu = $this->user('bpp', 'Sari BPP');
        $bppDua = $this->user('bpp', 'Tono BPP');
        $verifikator = $this->user('verifikator');

        $this->actingAs($pptk)->post(route('chat.role'), ['role' => 'bpp'])->assertRedirect();
        $ruang = ChatRuang::where('role', 'bpp')->sole();

        $this->kirim($pptk, $ruang, 'Mohon dicek NPD yang menunggu di meja BPP.')->assertOk();

        // Kedua akun BPP menerimanya; Verifikator tidak terganggu.
        foreach ([$bppSatu, $bppDua] as $bpp) {
            $ringkas = $this->actingAs($bpp)->getJson(route('chat.ringkas'))->assertOk();
            $ringkas->assertJsonPath('belum', 1);
            $baris = collect($ringkas->json('ruang'))->firstWhere('id', $ruang->id);
            $this->assertSame('Ruang Bendahara Pengeluaran Pembantu (BPP)', $baris['nama']);
            $this->assertStringContainsString('Budi PPTK: Mohon dicek', $baris['cuplikan']);
        }
        $this->actingAs($verifikator)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 0);

        // Salah satu BPP membalas di ruang yang sama.
        $this->actingAs($bppSatu)->get(route('chat.index', ['ruang' => $ruang->id]))
            ->assertOk()
            ->assertSee('Semua akun bisa membaca dan menulis di ruang ini.');
        $this->kirim($bppSatu, $ruang, 'Sedang saya proses.')->assertOk();

        // PPTK (bukan BPP) mengikuti ruang itu sejak menulis di sana, jadi balasannya sampai.
        $this->actingAs($pptk)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 1);
        // BPP kedua kini punya dua pesan belum dibaca: dari PPTK dan dari rekannya.
        $this->actingAs($bppDua)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 2);

        // Nama pengirim ikut dikirim supaya bisa ditampilkan di ruang ramai.
        $this->actingAs($bppDua)->getJson(route('chat.pesan', $ruang))
            ->assertOk()
            ->assertJsonPath('pesan.0.pengirim', 'Budi PPTK')
            ->assertJsonPath('pesan.1.pengirim', 'Sari BPP')
            ->assertJsonPath('pesan.1.milik_saya', false);
    }

    public function test_ruang_role_sendiri_selalu_ada_di_daftar_dan_ruang_role_lain_baru_muncul_setelah_dibuka(): void
    {
        $verifikator = $this->user('verifikator');

        $awal = collect($this->actingAs($verifikator)->getJson(route('chat.ringkas'))->json('ruang'));
        $this->assertSame(['Ruang Verifikator'], $awal->pluck('nama')->all());

        // Ruang PPTK ramai, tetapi Verifikator belum pernah membukanya.
        $pptk = $this->user('pptk');
        $ruangPptk = app(ChatService::class)->ruangRole('pptk');
        $this->kirim($pptk, $ruangPptk, 'Obrolan internal PPTK')->assertOk();

        $this->actingAs($verifikator)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 0);
        $this->assertCount(1, $this->actingAs($verifikator)->getJson(route('chat.ringkas'))->json('ruang'));

        // Setelah dibuka, ruang itu masuk daftarnya dan pesan berikutnya dihitung.
        $this->actingAs($verifikator)->get(route('chat.index', ['ruang' => $ruangPptk->id]))->assertOk();
        $this->kirim($pptk, $ruangPptk, 'Pesan sesudah Verifikator ikut')->assertOk();

        $ringkas = $this->actingAs($verifikator)->getJson(route('chat.ringkas'));
        $ringkas->assertJsonPath('belum', 1);
        $this->assertCount(2, $ringkas->json('ruang'));
    }

    public function test_role_yang_tidak_dikenal_ditolak(): void
    {
        $pptk = $this->user('pptk');

        $this->actingAs($pptk)->post(route('chat.role'), ['role' => 'layanan'])->assertSessionHasErrors('role');
        $this->actingAs($pptk)->post(route('chat.role'), ['role' => 'ngarang'])->assertSessionHasErrors('role');
        $this->assertSame(0, ChatRuang::where('role', 'layanan')->count());
    }

    // ---------------- Siapa yang boleh ----------------

    public function test_pengguna_layanan_tanpa_akun_tidak_bisa_memakai_chat(): void
    {
        $pptk = $this->user('pptk');
        $ruang = app(ChatService::class)->ruangRole('pptk');

        // Belum login sama sekali.
        $this->get(route('chat.index'))->assertRedirect();
        // Dialihkan ke login (aplikasi ini mengalihkan juga permintaan JSON).
        $this->postJson(route('chat.kirim', $ruang), ['isi' => 'Tanpa akun'])->assertRedirect();

        // Sudah lolos gerbang Pengguna Layanan, tetapi tetap bukan akun.
        $layanan = $this->lolosGerbangLayanan();
        $layanan->get(route('chat.index'))->assertForbidden();
        $layanan->get(route('surat-perintah.monitoring'))->assertOk()->assertDontSee('id="tb-chat"', false);

        $this->assertNotContains('chat', config('akses.menu.layanan'));
        $this->assertSame(0, ChatPesan::count());
        $this->assertNotNull($pptk);
    }

    public function test_pengawas_yang_baca_saja_tetap_boleh_ikut_chat(): void
    {
        $pengawas = $this->user('pengawas', 'Pak Pengawas');
        $pptk = $this->user('pptk');

        $this->assertContains('pengawas', config('akses.role_baca_saja'));

        $this->actingAs($pengawas)->post(route('chat.pribadi', $pptk))->assertRedirect();
        $ruang = ChatRuang::where('jenis', ChatRuang::JENIS_PRIBADI)->sole();

        $this->kirim($pengawas, $ruang, 'Mohon penjelasan atas NPD nomor 7.')->assertOk();
        $this->actingAs($pptk)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 1);
    }

    public function test_semua_role_berakun_memegang_menu_chat_dan_ikonnya_tampil_di_bilah_atas(): void
    {
        foreach (User::ROLE_OPTIONS as $role) {
            $this->assertContains('chat', config('akses.menu')[$role], "Role {$role} belum memegang menu chat.");
        }

        $bpp = $this->user('bpp');
        $pptk = $this->user('pptk');
        $ruang = app(ChatService::class)->ruangPribadi($bpp, $pptk);
        $this->kirim($pptk, $ruang, 'Satu')->assertOk();
        $this->kirim($pptk, $ruang, 'Dua')->assertOk();

        // Ikon dan angka belum-dibaca ada di halaman mana pun, bukan hanya di Chat.
        $this->actingAs($bpp)->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('id="tb-chat"', false)
            ->assertSee('<span class="lc-angka" id="tb-chat-angka">2</span>', false)
            ->assertSee('Chat, 2 pesan belum dibaca', false);
    }

    // ---------------- Pesan ----------------

    public function test_pesan_kosong_dan_terlalu_panjang_ditolak(): void
    {
        $pptk = $this->user('pptk');
        $ruang = app(ChatService::class)->ruangRole('pptk');

        $this->kirim($pptk, $ruang, '')->assertStatus(422);
        $this->kirim($pptk, $ruang, "   \n  ")->assertStatus(422);
        $this->kirim($pptk, $ruang, str_repeat('a', ChatPesan::MAKS_PANJANG + 1))->assertStatus(422);
        $this->assertSame(0, ChatPesan::count());

        $this->kirim($pptk, $ruang, str_repeat('a', ChatPesan::MAKS_PANJANG))->assertOk();
        $this->assertSame(1, ChatPesan::count());
    }

    public function test_isi_pesan_tidak_dijalankan_sebagai_html(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $ruang = app(ChatService::class)->ruangPribadi($pptk, $bpp);
        $jahat = '<script>alert("x")</script><b>tebal</b>';

        $this->kirim($pptk, $ruang, $jahat)->assertOk()->assertJsonPath('pesan.isi', $jahat);

        $isi = $this->actingAs($bpp)->get(route('chat.index', ['ruang' => $ruang->id]))->assertOk()->getContent();

        // Di halaman, pesannya hanya ada sebagai data JSON yang ter-escape -
        // tidak pernah sebagai tag yang hidup - dan dipasang lewat textContent.
        $this->assertStringNotContainsString($jahat, $isi);
        // Bentuk ter-escape-nya dirakit di sini (JSON_HEX_TAG), sama dengan @json.
        $this->assertStringContainsString(trim(json_encode('<script>alert', JSON_HEX_TAG), '"'), $isi);
        $this->assertStringContainsString('isi.textContent = p.isi;', $isi);
        $this->assertStringNotContainsString('innerHTML = p.isi', $isi);
    }

    public function test_polling_hanya_mengembalikan_pesan_sesudah_yang_terakhir_dilihat_dan_menandainya_dibaca(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $ruang = app(ChatService::class)->ruangPribadi($pptk, $bpp);

        $pertama = $this->kirim($pptk, $ruang, 'Pertama')->json('pesan.id');
        $this->kirim($pptk, $ruang, 'Kedua')->assertOk();
        $this->kirim($pptk, $ruang, 'Ketiga')->assertOk();

        $baru = $this->actingAs($bpp)->getJson(route('chat.pesan', ['ruang' => $ruang->id, 'sesudah' => $pertama]))->assertOk();
        $this->assertSame(['Kedua', 'Ketiga'], array_column($baru->json('pesan'), 'isi'));

        // Yang datang lewat polling langsung dianggap dibaca.
        $this->actingAs($bpp)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 0);

        // Tidak ada yang baru -> daftar kosong, dan penanda baca tidak mundur.
        $terakhir = (int) $ruang->fresh()->pesan_terakhir_id;
        $this->actingAs($bpp)->getJson(route('chat.pesan', ['ruang' => $ruang->id, 'sesudah' => $terakhir]))
            ->assertOk()
            ->assertJsonCount(0, 'pesan');
        app(ChatService::class)->tandaiDibaca($ruang->fresh(), $bpp, 1);
        $this->assertSame($terakhir, (int) ChatBaca::where('user_id', $bpp->id)->sole()->dibaca_sampai_id);
    }

    public function test_pesan_lama_tetap_terbaca_setelah_akun_pengirimnya_dihapus(): void
    {
        $pptk = $this->user('pptk', 'Budi PPTK');
        $bpp = $this->user('bpp');
        $ruang = app(ChatService::class)->ruangRole('bpp');
        $this->kirim($pptk, $ruang, 'Pesan dari akun yang kelak dihapus')->assertOk();

        $pptk->delete();

        // Masih dihitung belum dibaca walau pengirimnya sudah tidak ada...
        $this->actingAs($bpp)->getJson(route('chat.ringkas'))->assertJsonPath('belum', 1);
        // ...dan namanya tetap tercantum saat pesannya dibuka.
        $this->actingAs($bpp)->getJson(route('chat.pesan', $ruang))
            ->assertOk()
            ->assertJsonPath('pesan.0.pengirim', 'Budi PPTK')
            ->assertJsonPath('pesan.0.isi', 'Pesan dari akun yang kelak dihapus');
    }
}
