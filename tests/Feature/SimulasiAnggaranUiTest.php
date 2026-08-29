<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\SimulasiAnggaran;
use App\Models\Tagging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Menjaga rupa modul Simulasi Pergeseran/Perubahan: penjelasan panjang
 * dibuang, "Profil Simulasi" sebagai judul langkah pertama, nama simulasi
 * tanpa garis bawah pranala, dan hanya kotak isian angkanya yang menonjol.
 */
class SimulasiAnggaranUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'superadmin-uji',
            'nama' => 'Superadmin Uji',
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_SUPERADMIN,
            'aktif' => true,
        ]);
    }

    private function anggaran(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'kode_program' => '6.01',
            'program' => 'Program Penunjang',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Satu',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Satu',
            'kode_rekening' => '5.1.02.01.01.0024',
            'rekening' => 'Belanja Alat Tulis Kantor',
            'tagging_id' => Tagging::create(['nama' => 'Rutin', 'aktif' => true])->id,
            'pagu' => 10_000_000,
            'aktif' => true,
        ]);
    }

    public function test_daftar_simulasi_tanpa_penjelasan_panjang_dan_namanya_tanpa_garis_bawah(): void
    {
        SimulasiAnggaran::create([
            'nama' => 'Simulasi Pergeseran Semester 2',
            'user_id' => $this->user->id,
        ]);

        $halaman = $this->actingAs($this->user)->get(route('simulasi-anggaran.index'))->assertOk();

        $halaman->assertDontSee('Coba geser/ubah Pagu per mata anggaran secara what-if')
            ->assertSee('Simulasi Pergeseran Semester 2')
            ->assertSee('sim-nama-tautan', false)
            ->assertSee('text-decoration:none', false);
    }

    public function test_langkah_pertama_bernama_profil_simulasi_tanpa_catatan_penyalinan(): void
    {
        $halaman = $this->actingAs($this->user)->get(route('simulasi-anggaran.create'))->assertOk();

        $halaman->assertSee('Profil Simulasi')
            ->assertDontSee('Identitas Simulasi')
            ->assertDontSee('Seluruh mata anggaran aktif akan disalin');

        // Nama di atas, keterangan opsional yang lega di bawahnya.
        $halaman->assertSee('Nama Simulasi')
            ->assertSee('Keterangan')
            ->assertSee('opsional')
            ->assertSee('<textarea id="sim-keterangan"', false);
    }

    public function test_keterangan_boleh_kosong_dan_boleh_panjang(): void
    {
        $this->anggaran();

        $this->actingAs($this->user)
            ->post(route('simulasi-anggaran.store'), ['nama' => 'Tanpa Keterangan'])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('simulasi-anggaran.store'), ['nama' => 'Dengan Keterangan', 'keterangan' => str_repeat('a', 1000)])
            ->assertRedirect();

        $this->assertSame(2, SimulasiAnggaran::count());
        $this->assertNull(SimulasiAnggaran::where('nama', 'Tanpa Keterangan')->first()->keterangan);
    }

    /**
     * Kolom waktu menampilkan perubahan TERAKHIR, bukan tanggal pembuatan -
     * termasuk saat penyimpanan tidak mengubah total sama sekali (satu
     * rekening naik, rekening lain turun dengan nilai yang sama).
     */
    public function test_kolom_waktu_menampilkan_perubahan_terakhir(): void
    {
        $this->anggaran();
        $this->actingAs($this->user)->post(route('simulasi-anggaran.store'), ['nama' => 'Uji Waktu']);

        $simulasi = SimulasiAnggaran::firstOrFail();
        $simulasi->forceFill([
            'created_at' => '2026-01-02 08:00:00',
            'updated_at' => '2026-01-02 08:00:00',
        ])->saveQuietly();

        $this->actingAs($this->user)->get(route('simulasi-anggaran.index'))->assertOk()
            ->assertSee('Terakhir Diubah')
            ->assertDontSee('<th>Tanggal</th>', false)
            ->assertSee('02-01-2026 08:00');

        // Simpan ulang dengan nilai yang sama persis: total tidak berubah,
        // tetapi stempel waktunya tetap harus maju.
        $rows = $simulasi->rows()->pluck('pagu_simulasi', 'id')
            ->map(fn ($nilai) => (string) $nilai)->all();

        $this->actingAs($this->user)->put(route('simulasi-anggaran.update', $simulasi), [
            'nama' => $simulasi->nama,
            'keterangan' => null,
            'rows' => $rows,
        ])->assertRedirect();

        $segar = $simulasi->fresh();

        $this->assertTrue($segar->updated_at->greaterThan($segar->created_at));
        $this->assertSame('2026-01-02 08:00:00', $segar->created_at->format('Y-m-d H:i:s'));

        $this->actingAs($this->user)->get(route('simulasi-anggaran.index'))->assertOk()
            ->assertDontSee('02-01-2026 08:00');
    }

    /**
     * Yang menonjol hanya kotak isian angkanya. Judul kolom, baris Tagging,
     * dan selnya sengaja dibiarkan sama seperti yang lain supaya tabelnya
     * tidak ramai.
     */
    public function test_hanya_kotak_isian_angka_yang_menonjol(): void
    {
        $this->anggaran();

        $this->actingAs($this->user)->post(route('simulasi-anggaran.store'), ['nama' => 'Uji Sorot']);
        $simulasi = SimulasiAnggaran::firstOrFail();

        $halaman = $this->actingAs($this->user)->get(route('simulasi-anggaran.show', $simulasi))->assertOk();

        $halaman->assertSee('<th class="rr-num">Anggaran (Simulasi)</th>', false)
            ->assertSee('sim-rek-input', false)
            ->assertSee('Rutin');

        // Tidak ada penyorotan pada judul kolom, sel, maupun baris Tagging.
        $halaman->assertDontSee('kolom-isi', false)
            ->assertDontSee('sim-tag-tanda', false)
            ->assertDontSee('sim-tag-nama', false);
    }
}
