<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Siaran;
use App\Models\User;
use App\Services\LoncengService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Lonceng notifikasi di bilah atas: pekerjaan per role dan siaran superadmin. */
class LoncengNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    private function user(string $role): User
    {
        return User::create(['username' => 'lc-'.$role.'-'.User::count(), 'nama' => 'Akun '.$role.' '.User::count(), 'role' => $role, 'password' => 'rahasia-uji']);
    }

    private function anggaran(): MasterAnggaran
    {
        $this->urut++;

        return MasterAnggaran::create([
            'program' => 'Program Lonceng', 'kegiatan' => 'Kegiatan Lonceng',
            'sub_kegiatan' => '6.01.01.2.0'.$this->urut.' Sub Lonceng '.$this->urut,
            'kode_rekening' => '5.1.02.01.01.00'.$this->urut, 'pagu' => 100_000_000, 'aktif' => true,
        ]);
    }

    private function npd(string $status, ?MasterAnggaran $anggaran = null, array $lain = []): Npd
    {
        return Npd::create(array_merge([
            'jenis' => 'bj', 'master_anggaran_id' => ($anggaran ?? $this->anggaran())->id, 'keu' => '1', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-15', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000, 'terbilang' => 'uji', 'status' => $status,
        ], $lain));
    }

    public function test_tiap_role_alur_npd_melihat_jumlah_npd_di_mejanya(): void
    {
        $bpp = $this->user('bpp');
        $pptk = $this->user('pptk');
        $verifikator = $this->user('verifikator');
        $verifikatorLain = $this->user('verifikator');

        $milikVerifikator = $this->anggaran();
        $this->tetapkanVerifikator($verifikator);
        $milikLain = $this->anggaran();   // Sub Kegiatan tanpa Verifikator

        $this->npd('Draft NPD - PPTK');
        $this->npd('Draft NPD - PPTK');
        $this->npd('Draft NPD - BPP');
        $this->npd('Draft NPD - BPP');
        $this->npd('Draft NPD - BPP');
        // Sudah disetujui: bukan lagi "untuk diperiksa dan disetujui".
        $this->npd('NPD Disetujui - BPP');
        $this->npd('Verifikasi - Verifikator', $milikVerifikator);
        $this->npd('Verifikasi - Verifikator', $milikLain);
        // NPD historis tidak mengikuti alur.
        $this->npd('Draft NPD - BPP', null, ['sumber_data' => 'import_historis']);
        $this->npd('Selesai');

        $lonceng = app(LoncengService::class);

        $this->assertSame(
            ['jumlah' => 3, 'teks' => 'Terdapat 3 Nota Pencairan Dana untuk diperiksa dan disetujui', 'url' => route('npd.persetujuan')],
            $lonceng->untuk($bpp)['pekerjaan']
        );
        $this->assertSame(
            ['jumlah' => 2, 'teks' => 'Terdapat 2 Draft Nota Pencairan Dana, mohon dipantau/ditindaklanjuti', 'url' => route('npd.index')],
            $lonceng->untuk($pptk)['pekerjaan']
        );
        // Verifikator hanya menghitung NPD Sub Kegiatan yang ditugaskan kepadanya.
        $this->assertSame(
            ['jumlah' => 1, 'teks' => 'Terdapat 1 Nota Pencairan Dana untuk diverifikasi.', 'url' => route('npd.verifikasi')],
            $lonceng->untuk($verifikator)['pekerjaan']
        );
        $this->assertNull($lonceng->untuk($verifikatorLain)['pekerjaan']);

        // Role lain: loncengnya ada, isinya kosong.
        $this->assertNull($lonceng->untuk($this->user('inspektur'))['pekerjaan']);
        $this->assertSame(0, $lonceng->untuk($this->user('pengawas'))['belum']);

        $halaman = $this->actingAs($bpp)->get(route('dashboard.index'))->assertOk();
        $halaman->assertSee('id="tb-lonceng"', false)
            ->assertSee('Terdapat 3 Nota Pencairan Dana untuk diperiksa dan disetujui')
            ->assertSee('id="tb-lonceng-angka">1</span>', false);
    }

    public function test_lonceng_tampil_untuk_semua_role_termasuk_yang_belum_punya_isi(): void
    {
        foreach (['superadmin', 'bendahara_pengeluaran', 'inspektur', 'irban2', 'perencanaan', 'kepegawaian', 'pengawas', 'pengelola_spj'] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard.index'))->assertOk()
                ->assertSee('id="tb-lonceng"', false)
                ->assertSee('Belum ada notifikasi.')
                ->assertSee('id="tb-lonceng-angka" hidden', false);
        }

        // Pengguna Layanan (tanpa akun) juga mendapat loncengnya.
        auth()->logout();
        $this->lolosGerbangLayanan()->get(route('tunjangan.monitoring'))->assertOk()->assertSee('id="tb-lonceng"', false);
    }

    public function test_broadcast_superadmin_muncul_belum_dibaca_sampai_diklik(): void
    {
        $admin = $this->user('superadmin');
        $bpp = $this->user('bpp');
        $pengawas = $this->user('pengawas');

        // Hanya superadmin yang boleh mengirim.
        $this->actingAs($bpp)->post(route('siaran.store'), ['pesan' => 'Bukan dari superadmin'])->assertForbidden();
        $this->actingAs($admin)->post(route('siaran.store'), ['pesan' => ''])->assertSessionHasErrors('pesan');
        $this->actingAs($admin)->from(route('dashboard.index'))
            ->post(route('siaran.store'), ['pesan' => 'Tutup buku bulan ini tanggal 28.'])
            ->assertRedirect(route('dashboard.index'))->assertSessionHas('success');

        $siaran = Siaran::sole();
        $this->assertSame($admin->id, $siaran->dibuat_oleh);
        $this->assertDatabaseHas('audit_log', ['user_id' => $admin->id, 'aktivitas' => 'Kirim Broadcast']);

        $lonceng = app(LoncengService::class);

        // Pengirimnya sendiri tidak dihitung belum dibaca.
        $this->assertSame(0, $lonceng->untuk($admin)['belum']);
        $this->assertTrue($lonceng->untuk($admin)['siaran'][0]['dibaca']);

        // Role lain, termasuk yang hanya membaca: belum dibaca.
        foreach ([$bpp, $pengawas] as $akun) {
            $isi = $lonceng->untuk($akun);
            $this->assertSame(1, $isi['belum']);
            $this->assertFalse($isi['siaran'][0]['dibaca']);
            $this->assertSame('Tutup buku bulan ini tanggal 28.', $isi['siaran'][0]['pesan']);
        }

        $this->actingAs($pengawas)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Tutup buku bulan ini tanggal 28.')
            ->assertSee('data-siaran-baca="'.route('siaran.baca', $siaran).'"', false)
            ->assertSee('id="tb-lonceng-angka">1</span>', false)
            // Formulir kirim dan tombol hapus hanya milik superadmin.
            ->assertDontSee('Kirim Broadcast')
            ->assertDontSee('lc-hapus"', false);

        // Diklik -> dibaca, untuk akun itu saja. Diklik dua kali tidak galat.
        $this->actingAs($pengawas)->postJson(route('siaran.baca', $siaran))->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($pengawas)->postJson(route('siaran.baca', $siaran))->assertOk();
        $this->assertSame(1, DB::table('siaran_dibaca')->where('user_id', $pengawas->id)->count());
        $this->assertSame(0, $lonceng->untuk($pengawas)['belum']);
        $this->assertSame(1, $lonceng->untuk($bpp)['belum']);

        $this->actingAs($pengawas)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Tutup buku bulan ini tanggal 28.')
            ->assertDontSee('data-siaran-baca=', false);

        // Superadmin melihat formulir kirim dan tombol hapus; role lain tidak bisa menghapus.
        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Kirim Broadcast')
            ->assertSee('action="'.route('siaran.destroy', $siaran).'"', false);
        $this->actingAs($bpp)->delete(route('siaran.destroy', $siaran))->assertForbidden();
        $this->actingAs($admin)->delete(route('siaran.destroy', $siaran))->assertSessionHas('success');
        $this->assertSame(0, Siaran::count());
        $this->assertSame(0, DB::table('siaran_dibaca')->count());
    }

    /**
     * Lonceng ada di rangka setiap halaman. Tabelnya belum ada (kode sudah
     * ditarik, migrate belum dijalankan) tidak boleh menjatuhkan aplikasi.
     */
    public function test_halaman_tetap_terbuka_walau_tabel_siaran_belum_ada(): void
    {
        \Illuminate\Support\Facades\Schema::drop('siaran_dibaca');
        \Illuminate\Support\Facades\Schema::drop('siaran');

        $this->assertSame(0, app(LoncengService::class)->untuk($this->user('bpp'))['belum']);
        $this->actingAs($this->user('inspektur'))->get(route('dashboard.index'))->assertOk()
            ->assertSee('id="tb-lonceng"', false)
            ->assertSee('Belum ada notifikasi.');
    }

    public function test_pengguna_layanan_melihat_siaran_tanpa_penanda_dan_tidak_bisa_menandai(): void
    {
        $admin = $this->user('superadmin');
        $siaran = Siaran::create(['pesan' => 'Layanan libur tanggal 17.', 'dibuat_oleh' => $admin->id]);

        $isi = app(LoncengService::class)->untuk(null);
        $this->assertSame(0, $isi['belum']);
        $this->assertFalse($isi['bisa_tandai']);
        $this->assertTrue($isi['siaran'][0]['dibaca']);

        $this->lolosGerbangLayanan()->get(route('tunjangan.monitoring'))->assertOk()
            ->assertSee('Layanan libur tanggal 17.')
            ->assertDontSee('data-siaran-baca=', false);

        // Tanpa akun tidak ada bacaan yang bisa dicatat.
        $this->lolosGerbangLayanan()->post(route('siaran.baca', $siaran))->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('siaran_dibaca')->count());
    }
}
