<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SimulasiRealisasi;
use App\Models\SimulasiRealisasiItem;
use App\Models\SimulasiRealisasiRow;
use App\Models\Spm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simulasi Realisasi: memperkirakan capaian anggaran sampai akhir tahun.
 *
 * Inti yang dijaga: proyeksi = realisasi berjalan + jumlah rencana, realisasi
 * selalu dihitung ulang dari transaksi (tidak pernah di-snapshot), dan
 * simulasi tidak pernah menyentuh data anggaran maupun transaksi.
 */
class SimulasiRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role = User::ROLE_SUPERADMIN): User
    {
        return User::create([
            'username' => 'sim-realisasi-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatAnggaran(float $pagu = 100_000_000, string $tagging = ''): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => '6.01 Program Uji Simulasi',
            'kegiatan' => '6.01.01 Kegiatan Uji Simulasi',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Simulasi',
            'kode_rekening' => '5.1.02.99.88.000'.($tagging === '' ? '1' : '2').' Belanja Uji Simulasi',
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatNpdSelesai(MasterAnggaran $anggaran, float $nominal): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 8,
            'tahun' => 2026,
            'tanggal_npd' => '2026-08-10',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'uji',
            'status' => 'Selesai',
        ]);
    }

    private function buatSimulasi(User $user, string $nama = 'Proyeksi Akhir Tahun'): SimulasiRealisasi
    {
        $this->actingAs($user)->post(route('simulasi-realisasi.store'), ['nama' => $nama])
            ->assertRedirect();

        return SimulasiRealisasi::latest('id')->firstOrFail();
    }

    // ---------------- Pembuatan ----------------

    public function test_simulasi_baru_menyalin_seluruh_mata_anggaran_aktif(): void
    {
        $aktif = $this->buatAnggaran(50_000_000);
        $nonaktif = $this->buatAnggaran(9_000_000, 'x');
        $nonaktif->update(['aktif' => false]);

        $simulasi = $this->buatSimulasi($this->buatUser());

        $this->assertSame(1, $simulasi->rows()->count(), 'Hanya mata anggaran aktif yang ikut.');

        $row = $simulasi->rows()->firstOrFail();
        $this->assertSame($aktif->id, $row->master_anggaran_id);
        $this->assertSame(50_000_000.0, (float) $row->pagu);
        $this->assertSame(0.0, (float) $row->proyeksi_total, 'Simulasi baru belum punya rencana.');
        $this->assertSame(50_000_000.0, (float) $simulasi->total_pagu);
    }

    // ---------------- Rencana bernama ----------------

    public function test_satu_tagging_dapat_menampung_beberapa_rencana_bernama(): void
    {
        $anggaran = $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [
                ['nama' => 'Perjalanan dinas ke Cirebon', 'nominal' => 1_000_000],
                ['nama' => 'Rapat koordinasi triwulan', 'nominal' => 500_000],
                ['nama' => 'Honorarium narasumber', 'nominal' => 250_000],
            ]],
        ])->assertSessionHasNoErrors();

        $items = $row->fresh()->items;
        $this->assertCount(3, $items);
        $this->assertSame('Perjalanan dinas ke Cirebon', $items[0]->nama);
        $this->assertSame('Rapat koordinasi triwulan', $items[1]->nama);
        $this->assertSame([0, 1, 2], $items->pluck('urutan')->all(), 'Urutan pengisian dipertahankan.');

        $this->assertSame(1_750_000.0, (float) $row->fresh()->proyeksi_total);
        $this->assertSame(1_750_000.0, (float) $simulasi->fresh()->total_proyeksi);
    }

    public function test_baris_kosong_dilewati_dan_nama_kosong_diberi_label(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [
                ['nama' => 'Rencana sah', 'nominal' => 400_000],
                ['nama' => '', 'nominal' => 0],          // sisa formulir - dilewati
                ['nama' => '', 'nominal' => 150_000],    // bernominal tapi tanpa nama
            ]],
        ])->assertSessionHasNoErrors();

        $items = $row->fresh()->items;
        $this->assertCount(2, $items, 'Baris yang benar-benar kosong tidak ikut tersimpan.');
        $this->assertSame('Tanpa nama', $items[1]->nama);
        $this->assertSame(550_000.0, (float) $row->fresh()->proyeksi_total);
    }

    public function test_menyimpan_ulang_mengganti_seluruh_rencana_bukan_menumpuk(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $kirim = fn (array $items) => $this->actingAs($user)->put(
            route('simulasi-realisasi.update', $simulasi),
            ['nama' => $simulasi->nama, 'items' => [$row->id => $items]]
        );

        $kirim([['nama' => 'Rencana A', 'nominal' => 1_000_000], ['nama' => 'Rencana B', 'nominal' => 2_000_000]]);
        $this->assertSame(2, SimulasiRealisasiItem::count());

        $kirim([['nama' => 'Rencana C', 'nominal' => 300_000]]);

        $this->assertSame(1, SimulasiRealisasiItem::count(), 'Rencana lama diganti, bukan ditambah.');
        $this->assertSame('Rencana C', $row->fresh()->items->first()->nama);
        $this->assertSame(300_000.0, (float) $row->fresh()->proyeksi_total);
    }

    public function test_menghapus_seluruh_rencana_mengembalikan_total_ke_nol(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Rencana A', 'nominal' => 1_000_000]]],
        ]);
        $this->assertSame(1_000_000.0, (float) $row->fresh()->proyeksi_total);

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), ['nama' => $simulasi->nama]);

        $this->assertSame(0, SimulasiRealisasiItem::count());
        $this->assertSame(0.0, (float) $row->fresh()->proyeksi_total);
        $this->assertSame(0.0, (float) $simulasi->fresh()->total_proyeksi);
    }

    // ---------------- Proyeksi ----------------

    public function test_proyeksi_adalah_realisasi_berjalan_ditambah_rencana(): void
    {
        $anggaran = $this->buatAnggaran(100_000_000);
        $this->buatNpdSelesai($anggaran, 20_000_000);
        Spm::buatLs([
            'nomor_dokumen' => '001/SPM-LS/2026',
            'tanggal_dokumen' => '2026-08-12',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 5_000_000]],
        ]);

        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Perjalanan dinas ke Cirebon', 'nominal' => 15_000_000]]],
        ]);

        $hasil = SimulasiRealisasiRow::lampirkanRealisasi($simulasi->fresh()->rows()->with('items')->get())->first();

        $this->assertSame(25_000_000.0, $hasil->realisasi, 'NPD Selesai 20jt + SPM LS 5jt.');
        $this->assertSame(75_000_000.0, $hasil->sisa_anggaran, 'Pagu 100jt - realisasi 25jt.');
        $this->assertSame(15_000_000.0, (float) $hasil->proyeksi_total);
        $this->assertSame(40_000_000.0, $hasil->realisasi_estimasi);
        $this->assertSame(60_000_000.0, $hasil->sisa_estimasi);
    }

    /** Realisasi dihitung ulang tiap dibuka, bukan dibekukan saat simulasi dibuat. */
    public function test_realisasi_mengikuti_transaksi_terbaru_bukan_snapshot(): void
    {
        $anggaran = $this->buatAnggaran(100_000_000);
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);

        $awal = SimulasiRealisasiRow::lampirkanRealisasi($simulasi->rows()->get())->first();
        $this->assertSame(0.0, $awal->realisasi);
        $this->assertSame(100_000_000.0, $awal->sisa_anggaran);

        // NPD baru muncul SETELAH simulasi dibuat.
        $this->buatNpdSelesai($anggaran, 7_000_000);

        $sesudah = SimulasiRealisasiRow::lampirkanRealisasi($simulasi->fresh()->rows()->get())->first();
        $this->assertSame(7_000_000.0, $sesudah->realisasi);
        $this->assertSame(7_000_000.0, $sesudah->realisasi_estimasi);
    }

    public function test_proyeksi_melebihi_pagu_ditandai_sisa_negatif_tanpa_ditolak(): void
    {
        $anggaran = $this->buatAnggaran(10_000_000);
        $this->buatNpdSelesai($anggaran, 8_000_000);

        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        // Alat what-if: melebihi pagu justru informasi yang dicari, bukan galat.
        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Rencana besar', 'nominal' => 5_000_000]]],
        ])->assertSessionHasNoErrors();

        $hasil = SimulasiRealisasiRow::lampirkanRealisasi($simulasi->fresh()->rows()->get())->first();

        $this->assertSame(13_000_000.0, $hasil->realisasi_estimasi);
        $this->assertSame(-3_000_000.0, $hasil->sisa_estimasi, 'Sisa negatif menandai perkiraan melebihi pagu.');
    }

    public function test_simulasi_tidak_pernah_mengubah_anggaran_maupun_transaksi(): void
    {
        $anggaran = $this->buatAnggaran(100_000_000);
        $this->buatNpdSelesai($anggaran, 20_000_000);

        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Rencana', 'nominal' => 40_000_000]]],
        ]);

        $this->assertSame(100_000_000.0, (float) $anggaran->fresh()->pagu);
        $this->assertSame(20_000_000.0, $anggaran->fresh()->realisasiNpd());
        $this->assertSame(1, Npd::count());
    }

    public function test_nominal_negatif_ditolak(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Rencana minus', 'nominal' => -5000]]],
        ])->assertSessionHasErrors();

        $this->assertSame(0, SimulasiRealisasiItem::count());
    }

    // ---------------- Halaman, unduhan, dan akses ----------------

    public function test_halaman_menampilkan_rencana_dan_tombol_tambah(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Perjalanan dinas ke Cirebon', 'nominal' => 1_000_000]]],
        ]);

        $this->actingAs($user)->get(route('simulasi-realisasi.show', $simulasi))
            ->assertOk()
            ->assertSee('Perjalanan dinas ke Cirebon')
            ->assertSee('class="btn sr-tambah"', false)
            ->assertSee('Proyeksi Capaian Akhir Tahun')
            ->assertSee('Sisa Anggaran (Estimasi)')
            ->assertSee('class="ic-btn ok sr-simpan"', false);
    }

    public function test_excel_dan_pdf_dapat_diunduh(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpdSelesai($anggaran, 1_000_000);
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user, 'Proyeksi Uji');
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => 'Proyeksi Uji',
            'items' => [$row->id => [['nama' => 'Perjalanan dinas ke Cirebon', 'nominal' => 2_000_000]]],
        ]);

        $this->actingAs($user)->get(route('simulasi-realisasi.export-excel', $simulasi))
            ->assertOk()->assertDownload('simulasi-realisasi-proyeksi-uji.xlsx');

        $pdf = $this->actingAs($user)->get(route('simulasi-realisasi.export-pdf', $simulasi));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_role_baca_saja_boleh_melihat_tetapi_tidak_boleh_mengubah(): void
    {
        $this->buatAnggaran();
        $simulasi = $this->buatSimulasi($this->buatUser());
        $pengawas = $this->buatUser(User::ROLE_PENGAWAS);

        $this->actingAs($pengawas)->get(route('simulasi-realisasi.index'))->assertOk();
        $this->actingAs($pengawas)->get(route('simulasi-realisasi.show', $simulasi))
            ->assertOk()->assertDontSee('class="btn sr-tambah"', false);

        $this->actingAs($pengawas)->get(route('simulasi-realisasi.create'))->assertForbidden();
        $this->actingAs($pengawas)->post(route('simulasi-realisasi.store'), ['nama' => 'X'])->assertForbidden();
        $this->actingAs($pengawas)->put(route('simulasi-realisasi.update', $simulasi), ['nama' => 'X'])->assertForbidden();
        $this->actingAs($pengawas)->delete(route('simulasi-realisasi.destroy', $simulasi))->assertForbidden();
    }

    public function test_role_tanpa_menu_analisis_ditolak(): void
    {
        $this->buatAnggaran();
        $simulasi = $this->buatSimulasi($this->buatUser());
        $kepegawaian = $this->buatUser(User::ROLE_KEPEGAWAIAN);

        $this->actingAs($kepegawaian)->get(route('simulasi-realisasi.index'))->assertForbidden();
        $this->actingAs($kepegawaian)->get(route('simulasi-realisasi.show', $simulasi))->assertForbidden();
    }

    public function test_menghapus_simulasi_ikut_menghapus_baris_dan_rencananya(): void
    {
        $this->buatAnggaran();
        $user = $this->buatUser();
        $simulasi = $this->buatSimulasi($user);
        $row = $simulasi->rows()->firstOrFail();

        $this->actingAs($user)->put(route('simulasi-realisasi.update', $simulasi), [
            'nama' => $simulasi->nama,
            'items' => [$row->id => [['nama' => 'Rencana', 'nominal' => 1_000]]],
        ]);

        $this->actingAs($user)->delete(route('simulasi-realisasi.destroy', $simulasi))->assertRedirect();

        $this->assertSame(0, SimulasiRealisasi::count());
        $this->assertSame(0, SimulasiRealisasiRow::count());
        $this->assertSame(0, SimulasiRealisasiItem::count());
    }
}
