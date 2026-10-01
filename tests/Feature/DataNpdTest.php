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
     * Kolom Kode Rekening memuat kode beserta namanya, sama seperti kolom
     * Sub Kegiatan - bukan sekadar angka kodenya.
     */
    public function test_kolom_kode_rekening_memuat_kode_dan_nama_rekening(): void
    {
        $this->npd('Selesai');

        $this->actingAs($this->user)->get(route('npd.data'))->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->first()['kode_rekening'] === '5.1.02.01.01.0024 Belanja Alat Tulis Kantor');
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
        // Sejak kolom Uraian ditambahkan (adopsi GAS #92a), jatahnya diambil
        // dari kolom-kolom teks yang masih longgar. Nominal 12,5% dan Status
        // 13% SENGAJA tidak ikut dipersempit - keduanya hasil pengukuran di
        // atas, dan isinya nowrap.
        $lebar = '<col style="width:9%;"><col style="width:12%;"><col style="width:11%;"><col style="width:10.5%;">';
        $lebar2 = '<col style="width:12%;"><col style="width:12.5%;"><col style="width:13%;"><col style="width:12%;"><col style="width:8%;">';

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

    public function test_kolom_no_dokumen_jatuh_ke_nomor_sp_sebelum_npd_bernomor(): void
    {
        // Nomor NPD baru terbit setelah verifikator menetapkannya. Sebelum
        // itu, yang dikenal petugas adalah nomor Surat Perintahnya - bukan
        // "NPD #17" yang tidak berarti apa-apa di meja kerja.
        $sp = \App\Models\SuratPerintah::create([
            'nomor_sp' => '123/SP/DOK/2026',
            'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 Juli 2026',
            'keterangan' => 'Uji nomor dokumen',
            'file_url' => 'sp/dok.pdf',
            'status_sp' => 'Baru',
            'status' => \App\Models\SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => \App\Models\SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true,
            'dipantau' => true,
        ]);

        $npd = $this->npd('Draft NPD - PPTK');
        $npd->forceFill(['surat_perintah_id' => $sp->id])->save();

        $this->assertSame('123/SP/DOK/2026', $npd->fresh()->nomorDokumen());

        // Begitu nomor NPD terbit, nomor itu yang menang.
        $npd->forceFill(['nomor_urut' => 9, 'nomor_lengkap' => '9/NPD-Keu.2.IBC/VII/2026'])->save();

        $this->assertSame('9/NPD-Keu.2.IBC/VII/2026', $npd->fresh()->nomorDokumen());
    }

    public function test_uraian_barang_jasa_jatuh_ke_keterangan_penerima(): void
    {
        // Barang/Jasa tidak punya uraian di tingkat NPD - yang ada hanya
        // Keterangan per baris penerima. Tanpa langkah terakhir ini, kolom
        // Uraian selalu kosong untuk seluruh NPD BJ.
        $npd = $this->npd('Selesai');
        $npd->penerima()->create([
            'nama' => 'CV Sumber Rejeki',
            'keterangan' => 'Pembelian alat tulis kantor triwulan III',
            'bruto' => 1_500_000,
        ]);

        $this->assertSame(
            'Pembelian alat tulis kantor triwulan III',
            $npd->fresh()->load('penerima')->uraianRingkas()
        );
    }

    public function test_uraian_kosong_ditampilkan_sebagai_strip(): void
    {
        $this->assertSame('-', $this->npd('Selesai')->load('penerima')->uraianRingkas());
    }

    public function test_daftar_memuat_kolom_no_dokumen_dan_uraian(): void
    {
        $this->npd('Selesai');

        foreach (['npd.data', 'npd.index'] as $rute) {
            $this->actingAs($this->user)->get(route($rute))->assertOk()
                // Tanpa tag pembungkus: kepala kolom pertama bisa memuat kotak
                // centang mode massal, jadi yang dijaga adalah judulnya.
                ->assertSee('No. Dokumen', false)
                ->assertSee('<th>Uraian</th>', false)
                // Dicek sebagai kepala kolom, bukan teks lepas: "Nomor NPD"
                // masih dipakai sebagai label di modal Kirim Notifikasi.
                ->assertDontSee('<th>Nomor NPD</th>', false);
        }
    }
}
