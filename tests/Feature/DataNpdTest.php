<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Data NPD: satu halaman baca-saja berisi ringkasan KPI dan daftar seluruh
 * NPD apa pun statusnya, terpisah dari antrean Pembuatan/Persetujuan/
 * Verifikasi yang masing-masing hanya menampilkan bagiannya sendiri.
 */
class DataNpdTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MasterAnggaran $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'superadmin-datanpd',
            'nama' => 'Superadmin Uji',
            'password' => Hash::make('rahasia123'),
            'role' => User::ROLE_SUPERADMIN,
            'aktif' => true,
        ]);

        $this->master = MasterAnggaran::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'kode_program' => '6.01', 'program' => 'Program Penunjang',
            'kode_kegiatan' => '6.01.01', 'kegiatan' => 'Kegiatan Satu',
            'kode_sub_kegiatan' => '6.01.01.2.01', 'sub_kegiatan' => 'Sub Kegiatan Satu',
            'kode_rekening' => '5.1.02.01.01.0024', 'rekening' => 'Belanja Alat Tulis Kantor',
            'pagu' => 100_000_000, 'aktif' => true,
        ]);
    }

    private function npd(string $status, ?string $dibuat = null): Npd
    {
        $npd = Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $this->master->id,
            'keu' => '2',
            'bulan' => 7,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'tanggal_npd' => config('anggaran.tahun_aktif').'-07-10',
            'nominal' => 1_500_000,
            'terbilang' => 'satu juta lima ratus ribu rupiah',
            'status' => $status,
        ]);

        if ($dibuat !== null) {
            $npd->forceFill(['created_at' => $dibuat])->saveQuietly();
        }

        return $npd->refresh();
    }

    public function test_kpi_menghitung_total_selesai_dan_dalam_proses(): void
    {
        $this->npd('Selesai');
        $this->npd('Selesai');
        $this->npd('Draft NPD - BPP');
        $this->npd('Dibatalkan');

        $this->actingAs($this->user)->get(route('npd.data'))->assertOk()
            ->assertViewHas('kpi', function (array $kpi) {
                // "Dalam Proses" = apa pun selain Selesai, termasuk Dibatalkan.
                return $kpi['total'] === 4
                    && $kpi['selesai'] === 2
                    && $kpi['proses'] === 2;
            });
    }

    /**
     * Draft mengendap: dibuat PPTK, lebih dari 7 hari, dan belum pernah ada
     * aksi. Begitu diteruskan ke BPP, histori statusnya terisi sehingga baris
     * itu keluar dari kategori - itulah yang membedakannya dari sekadar
     * "berstatus Draft NPD - PPTK".
     */
    public function test_draft_lebih_dari_tujuh_hari_hanya_yang_belum_pernah_ada_aksi(): void
    {
        $mengendap = $this->npd('Draft NPD - PPTK', now()->subDays(10)->toDateTimeString());
        $this->npd('Draft NPD - PPTK', now()->subDays(2)->toDateTimeString());

        // Tua, tapi sudah pernah diteruskan lalu dikembalikan ke PPTK.
        $pernahJalan = $this->npd('Draft NPD - PPTK', now()->subDays(30)->toDateTimeString());
        $pernahJalan->catatHistoriStatus($this->user, 'teruskan', 'Draft NPD - PPTK', 'Draft NPD - BPP');

        $halaman = $this->actingAs($this->user)->get(route('npd.data'))->assertOk();

        $halaman->assertViewHas('kpi', fn (array $kpi) => $kpi['draft_mengendap'] === 1);
        $halaman->assertViewHas('baris', function ($baris) use ($mengendap) {
            $tandai = $baris->where('draft_mengendap', true);

            return $tandai->count() === 1 && $tandai->first()['id'] === $mengendap->id;
        });
    }

    public function test_daftar_memuat_kolom_dan_baris_penyaring_manual(): void
    {
        $this->npd('Selesai');

        $halaman = $this->actingAs($this->user)->get(route('npd.data'))->assertOk();

        foreach (['Nomor NPD', 'Sub Kegiatan', 'Kode Rekening', 'Tagging', 'Penerima', 'Nominal', 'Status', 'Aksi'] as $judul) {
            $halaman->assertSee($judul);
        }

        // Baris penyaring per kolom, tanpa tombol Terapkan.
        $halaman->assertSee('kolom-saring', false)
            ->assertSee('data-kolom="nomor_npd"', false)
            ->assertSee('data-kolom="status"', false)
            ->assertDontSee('Terapkan');

        // KPI keempat berupa tombol yang menyaring tabel.
        $halaman->assertSee('id="kpi-draft"', false)
            ->assertSee('Klik untuk menyaring tabel');
    }

    /**
     * Kolom Penerima memakai gaya yang sama dengan Pembuatan NPD: nama tebal
     * (.pen-nm) dengan jenis NPD sebagai baris kecil di bawahnya (.pen-sub).
     */
    public function test_kolom_penerima_memakai_gaya_yang_sama_dengan_pembuatan_npd(): void
    {
        $this->npd('Selesai');

        $this->actingAs($this->user)->get(route('npd.data'))->assertOk()
            ->assertSee('class="pen-nm"', false)
            ->assertSee('class="pen-sub"', false)
            ->assertViewHas('baris', fn ($baris) => $baris->first()['jenis_label'] === Npd::JENIS_LABEL['bj']);
    }

    /**
     * Lebar kolom dikunci karena angkanya hasil pengukuran, bukan selera.
     * Nominal 12,5% (+ padding sel yang sudah dirapatkan) pas untuk nominal
     * NPD terbesar yang ada - sembilan angka, "Rp 180.684.000,00" - dan
     * isinya nowrap, jadi begitu dipersempit lagi angkanya langsung tumpah ke
     * kolom Status. Status 13% pas untuk pil terpanjang
     * "Verifikasi - Verifikator".
     */
    public function test_lebar_kolom_terkunci_sesuai_hasil_pengukuran(): void
    {
        $lebar = '<col style="width:11%;"><col style="width:15%;"><col style="width:13%;"><col style="width:12%;">';
        $lebar2 = '<col style="width:13.5%;"><col style="width:12.5%;"><col style="width:13%;"><col style="width:10%;">';

        // Data NPD dan ketiga antrean NPD harus memakai lebar yang sama persis.
        $this->actingAs($this->user)->get(route('npd.data'))->assertOk()
            ->assertSee($lebar, false)
            ->assertSee($lebar2, false);

        $this->actingAs($this->user)->get(route('npd.index'))->assertOk()
            ->assertSee($lebar, false)
            ->assertSee($lebar2, false);
    }

    public function test_seluruh_npd_muncul_apa_pun_statusnya(): void
    {
        foreach (Npd::STATUS_LIST as $status) {
            $this->npd($status);
        }

        $this->actingAs($this->user)->get(route('npd.data'))->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->pluck('status')->sort()->values()->all()
                === collect(Npd::STATUS_LIST)->sort()->values()->all());
    }

    public function test_akses_mengikuti_config_akses_menu(): void
    {
        $this->actingAs($this->user)->get(route('npd.data'))->assertOk();

        config(['akses.menu.superadmin' => array_values(array_diff(config('akses.menu.superadmin'), ['npd-data']))]);
        $this->actingAs($this->user)->get(route('npd.data'))->assertForbidden();
    }

    public function test_ketiga_antrean_npd_menjadi_sub_menu_satu_modul(): void
    {
        $halaman = $this->actingAs($this->user)->get(route('npd.index'))->assertOk();

        $halaman->assertSee('Nota Pencairan Dana (NPD)')
            ->assertSee('nav-npd-parent', false)
            ->assertSee(route('npd.data'), false)
            ->assertSee('Pembuatan NPD')
            ->assertSee('Persetujuan NPD')
            ->assertSee('Verifikasi NPD');
    }
}
