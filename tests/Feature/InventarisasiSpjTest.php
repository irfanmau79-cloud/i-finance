<?php

namespace Tests\Feature;

use App\Models\ArsipSpj;
use App\Models\BantexSpj;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SpjDetail;
use App\Models\SuratPerintah;
use App\Models\Tagging;
use App\Models\User;
use App\Models\Vendor;
use App\Services\InventarisasiSpjService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarisasiSpjTest extends TestCase
{
    use RefreshDatabase;

    public function test_bantex_dapat_dibuat_dan_tetap_muncul_saat_kosong(): void
    {
        $user = $this->user('superadmin');

        $this->actingAs($user)->post(route('inventarisasi-spj.bantex.store'), [
            'nomor' => '7',
            'nama' => 'PDTT Irban II',
        ])->assertSessionHasNoErrors()
            // Dulu bantexnya tersimpan tetapi halamannya berakhir galat 500
            // (pencatatan audit memanggil method yang tidak ada).
            ->assertRedirect()
            ->assertSessionHas('success');

        // Nomor disimpan dua digit, dan label lokasinya "07 - PDTT Irban II".
        $this->assertSame('07', BantexSpj::where('nama', 'PDTT Irban II')->firstOrFail()->nomor);

        $data = app(InventarisasiSpjService::class)->data([]);
        $this->assertSame(1, $data['jumlah_lokasi']);
        $this->assertSame('07 - PDTT Irban II', $data['lokasi'][0]['lokasi']);
        $this->assertSame(0, $data['lokasi'][0]['jumlah_dokumen']);
    }

    /**
     * Menghapus Bantex/Box hanya membuang wadahnya. NPD di dalamnya tetap
     * ada dan kembali belum terinventarisasi; yang di bantex lain tidak
     * tersentuh.
     */
    public function test_hapus_bantex_mengembalikan_isinya_ke_belum_terinventarisasi(): void
    {
        $admin = $this->user('superadmin');
        $hapus = BantexSpj::create(['nomor' => '07', 'nama' => 'Box Dihapus', 'aktif' => true, 'dibuat_oleh' => $admin->id]);
        $tetap = BantexSpj::create(['nomor' => '08', 'nama' => 'Box Tetap', 'aktif' => true, 'dibuat_oleh' => $admin->id]);

        $npdBerlabel = $this->npd('6.01.01.2.01 Sub Hapus A', '5.1.02.01.01.0101', null, 1_000_000);
        $npdNamaPolos = $this->npd('6.01.01.2.02 Sub Hapus B', '5.1.02.01.01.0102', null, 2_000_000);
        $npdLain = $this->npd('6.01.01.2.03 Sub Hapus C', '5.1.02.01.01.0103', null, 3_000_000);

        $arsip = fn (Npd $npd, string $lokasi) => ArsipSpj::create([
            'npd_id' => $npd->id, 'jenis_dokumen' => 'NPD', 'lokasi' => $lokasi,
            'ditetapkan_oleh' => $admin->id, 'ditetapkan_at' => now(), 'aktif' => true,
        ]);
        $arsip($npdBerlabel, '07 - Box Dihapus');
        // Ditata sebelum bantex bernomor: lokasinya masih nama polos.
        $arsip($npdNamaPolos, 'Box Dihapus');
        $arsip($npdLain, '08 - Box Tetap');

        SpjDetail::create(['npd_id' => $npdBerlabel->id, 'lokasi' => '07 - Box Dihapus', 'status' => SpjDetail::STATUS_LENGKAP, 'catatan' => 'Sudah diperiksa']);

        // Tombolnya ada di halaman, dan hanya untuk yang boleh mengelola.
        $this->actingAs($admin)->get(route('inventarisasi-spj.index'))->assertOk()->assertSee('id="inv-hapus-bantex"', false);
        $this->actingAs($this->user('pptk'))->get(route('inventarisasi-spj.index'))->assertOk()->assertDontSee('id="inv-hapus-bantex"', false);
        $this->actingAs($this->user('bpp'))->delete(route('inventarisasi-spj.bantex.destroy', $hapus))->assertForbidden();
        $this->assertModelExists($hapus);

        $this->actingAs($admin)->delete(route('inventarisasi-spj.bantex.destroy', $hapus))
            ->assertRedirect(route('inventarisasi-spj.index'))
            ->assertSessionHas('success', 'Bantex/Box 07 - Box Dihapus dihapus. 2 NPD di dalamnya kembali belum terinventarisasi.');

        $this->assertModelMissing($hapus);
        $this->assertModelExists($tetap);
        $this->assertModelExists($npdBerlabel);
        $this->assertModelExists($npdNamaPolos);

        // Arsipnya jadi histori (tidak aktif), bukan dihapus.
        $this->assertSame(0, ArsipSpj::whereIn('npd_id', [$npdBerlabel->id, $npdNamaPolos->id])->where('aktif', true)->count());
        $this->assertSame(2, ArsipSpj::whereIn('npd_id', [$npdBerlabel->id, $npdNamaPolos->id])->count());
        $this->assertSame(1, ArsipSpj::where('npd_id', $npdLain->id)->where('aktif', true)->count());

        // Lokasi di rincian dikosongkan; status dan catatannya tetap.
        $detail = SpjDetail::where('npd_id', $npdBerlabel->id)->sole();
        $this->assertNull($detail->lokasi);
        $this->assertSame(SpjDetail::STATUS_LENGKAP, $detail->status);
        $this->assertSame('Sudah diperiksa', $detail->catatan);

        $data = app(InventarisasiSpjService::class)->data([]);
        $perLokasi = collect($data['lokasi'])->keyBy('lokasi');
        $this->assertFalse($perLokasi->has('07 - Box Dihapus'));
        $this->assertFalse($perLokasi->has('Box Dihapus'));
        $this->assertSame(2, $perLokasi['(Tanpa Lokasi)']['jumlah_npd']);
        $this->assertSame(1, $perLokasi['08 - Box Tetap']['jumlah_npd']);
        $this->assertDatabaseHas('audit_log', ['user_id' => $admin->id, 'aktivitas' => 'Hapus Bantex/Box SPJ']);
    }

    /** "9" dan "09" adalah nomor yang sama - yang kedua harus ditolak. */
    public function test_nomor_penyimpanan_wajib_dan_tidak_boleh_kembar(): void
    {
        $user = $this->user('superadmin');

        $this->actingAs($user)->post(route('inventarisasi-spj.bantex.store'), ['nama' => 'Tanpa Nomor'])
            ->assertSessionHasErrors('nomor');

        $this->actingAs($user)->post(route('inventarisasi-spj.bantex.store'), ['nomor' => '09', 'nama' => 'Box A'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('inventarisasi-spj.bantex.store'), ['nomor' => '9', 'nama' => 'Box B'])
            ->assertSessionHasErrors('nomor');

        $this->assertSame(1, BantexSpj::count());
    }

    public function test_lokasi_dapat_ditetapkan_dan_dipindahkan_tanpa_menghapus_histori(): void
    {
        $user = $this->user('superadmin');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', 'Tag A');
        BantexSpj::create(['nama' => 'Bantex A-01', 'aktif' => true]);
        BantexSpj::create(['nama' => 'Bantex B-02', 'aktif' => true]);

        $this->actingAs($user)->post(route('npd.arsip-spj.store', $npd), ['jenis_dokumen' => 'NPD', 'lokasi' => 'Bantex A-01', 'catatan' => 'Awal'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('npd.arsip-spj.store', $npd), ['jenis_dokumen' => 'NPD', 'lokasi' => 'Bantex B-02', 'catatan' => 'Pindah'])->assertSessionHasNoErrors();

        $this->assertSame(2, ArsipSpj::where('npd_id', $npd->id)->count());
        $this->assertDatabaseHas('arsip_spj', ['npd_id' => $npd->id, 'lokasi' => 'Bantex A-01', 'aktif' => false]);
        $this->assertDatabaseHas('arsip_spj', ['npd_id' => $npd->id, 'lokasi' => 'Bantex B-02', 'aktif' => true]);
        $this->assertDatabaseHas('audit_log', ['user_id' => $user->id, 'aktivitas' => 'Pindahkan Lokasi SPJ']);
    }

    public function test_satu_npd_dapat_memiliki_beberapa_jenis_dokumen_dan_nominal_total_tidak_dihitung_ganda(): void
    {
        $user = $this->user('superadmin');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', 'Tag A', 1_500_000);
        foreach (['Rak A', 'Rak B'] as $nama) {
            BantexSpj::create(['nama' => $nama, 'aktif' => true]);
        }
        foreach ([['NPD', 'Rak A'], ['Lampiran NPD', 'Rak A'], ['SPD Rampung', 'Rak B']] as [$jenis, $lokasi]) {
            $this->actingAs($user)->post(route('npd.arsip-spj.store', $npd), ['jenis_dokumen' => $jenis, 'lokasi' => $lokasi]);
        }
        $data = app(InventarisasiSpjService::class)->data([]);
        $this->assertSame(3, $data['jumlah_dokumen']);
        $this->assertSame(2, $data['jumlah_lokasi']);
        $this->assertSame(1_500_000.0, $data['total_nominal']);
        $this->assertSame(2, collect($data['lokasi'])->firstWhere('lokasi', 'Rak A')['jumlah_dokumen']);
    }

    public function test_filter_dan_pengecualian_gaji_tunjangan_asn_mengikuti_gas(): void
    {
        $satu = $this->npd('6.01.02.1.01 Pengawasan Satu', '5.1.02.01', 'Tag A');
        $dua = $this->npd('6.01.03.1.02 Pengawasan Dua', '5.1.02.02', 'Tag B');
        $this->npd('6.01.01.1.02.0001 Penyediaan Gaji dan Tunjangan ASN', '5.1.01.01', 'Gaji');
        ArsipSpj::create(['npd_id' => $satu->id, 'jenis_dokumen' => 'NPD', 'lokasi' => 'Rak Satu', 'ditetapkan_at' => now(), 'aktif' => true]);
        ArsipSpj::create(['npd_id' => $dua->id, 'jenis_dokumen' => 'NPD', 'lokasi' => 'Rak Dua', 'ditetapkan_at' => now(), 'aktif' => true]);

        $service = app(InventarisasiSpjService::class);
        $data = $service->data(['bulan' => 7, 'kode_rekening' => '5.1.02.02', 'tagging' => 'Tag B', 'cari' => 'Rak Dua']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame($dua->id, $data['rows'][0]['npd_id']);
        $this->assertSame(2, $service->data([])['jumlah_dokumen']);
    }

    public function test_akses_menu_dan_perubahan_lokasi_dijaga_backend(): void
    {
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        $sekretaris = $this->user('sekretaris');
        $perencanaan = $this->user('perencanaan');
        $this->actingAs($sekretaris)->get(route('inventarisasi-spj.index'))->assertOk()->assertSee('Inventarisasi SPJ');
        $this->actingAs($perencanaan)->get(route('inventarisasi-spj.index'))->assertForbidden();
        $this->actingAs($sekretaris)->post(route('npd.arsip-spj.store', $npd), ['jenis_dokumen' => 'NPD', 'lokasi' => 'X'])->assertForbidden();
        $this->assertSame(0, ArsipSpj::count());
    }

    // ---------------- Tabel Detail SPJ: bidang & nomor SP default ----------------

    public function test_detail_spj_bidang_default_dari_pegawai_penerima_dan_vendor_jadi_sekretariat(): void
    {
        // npd() sudah membuat 1 penerima generik ("Penerima Uji") - hapus dulu supaya
        // penerima yang dibuat di sini (dengan pegawai_id/vendor_id) yang jadi ->first().
        $pegawai = Pegawai::create(['nama' => 'Auditor Uji', 'nip' => '111222', 'jabatan' => 'Auditor', 'bidang' => 'Inspektur Pembantu II', 'aktif' => true]);
        $npdPegawai = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        $npdPegawai->penerima()->delete();
        $npdPegawai->penerima()->create(['nama' => $pegawai->nama, 'pegawai_id' => $pegawai->id, 'bruto' => 1_000_000]);

        $vendor = Vendor::create(['nama' => 'CV Uji', 'aktif' => true]);
        $npdVendor = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        $npdVendor->penerima()->delete();
        $npdVendor->penerima()->create(['nama' => $vendor->nama, 'vendor_id' => $vendor->id, 'bruto' => 1_000_000]);

        $data = app(InventarisasiSpjService::class)->data([]);
        $detail = collect($data['detail_spj'])->keyBy('npd_id');

        $this->assertSame('Inspektur Pembantu II', $detail[$npdPegawai->id]['bidang']);
        $this->assertSame('Sekretariat', $detail[$npdVendor->id]['bidang']);
    }

    public function test_detail_spj_nomor_sp_dari_surat_perintah_terkait_atau_kosong_bila_tidak_ditautkan(): void
    {
        $sp = SuratPerintah::create([
            'nomor_sp' => 'SP-UJI-001', 'tanggal_sp' => '2026-07-01', 'unit_kerja' => 'Sekretariat', 'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji', 'tujuan_transfer' => 'Tujuan', 'irban_dibayar' => false, 'rincian_tgl_bayar' => '-',
            'keterangan' => 'Uji', 'file_url' => 'sp/uji.pdf', 'status_sp' => 'Baru',
        ]);
        $ditautkan = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        $ditautkan->update(['surat_perintah_id' => $sp->id]);
        $manual = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);

        $data = app(InventarisasiSpjService::class)->data([]);
        $detail = collect($data['detail_spj'])->keyBy('npd_id');

        $this->assertSame('SP-UJI-001', $detail[$ditautkan->id]['nomor_sp']);
        $this->assertNull($detail[$manual->id]['nomor_sp']);
    }

    // ---------------- Edit & Restore ----------------

    /**
     * Pengelola SPJ hanya boleh mengubah tiga hal: Lokasi Penyimpanan, Status
     * SPJ, dan Catatan. Kolom lain (Bulan, Nomor SP, Nominal, Koordinator,
     * Bidang, Uraian) sekarang murni hasil hitung dari NPD - dikirim pun
     * diabaikan, tidak ikut tersimpan sebagai penimpa.
     */
    public function test_pengelola_spj_hanya_dapat_mengubah_lokasi_status_dan_catatan(): void
    {
        $pengelola = $this->user('pengelola_spj');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        BantexSpj::create(['nomor' => '03', 'nama' => 'Bantex C', 'aktif' => true]);

        $response = $this->actingAs($pengelola)->put(route('inventarisasi-spj.detail.update', $npd), [
            'bulan' => 5, 'nomor_sp' => 'SP-MANUAL-1', 'koordinator' => 'Koordinator Manual',
            'lokasi' => '03 - Bantex C', 'status' => 'lengkap', 'catatan' => 'Sudah lengkap dokumennya',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $detail = SpjDetail::where('npd_id', $npd->id)->firstOrFail();
        $this->assertSame('03 - Bantex C', $detail->lokasi);
        $this->assertSame('lengkap', $detail->status);
        $this->assertSame('Sudah lengkap dokumennya', $detail->catatan);
        $this->assertSame($pengelola->id, $detail->diedit_oleh);

        // Kiriman untuk kolom hitung diabaikan.
        $this->assertNull($detail->bulan);
        $this->assertNull($detail->nomor_sp);
        $this->assertNull($detail->koordinator);

        $data = app(InventarisasiSpjService::class)->data([]);
        $row = collect($data['detail_spj'])->firstWhere('npd_id', $npd->id);
        $this->assertSame('03 - Bantex C', $row['lokasi']);
        $this->assertSame('lengkap', $row['status']);
        $this->assertSame('Lengkap', $row['status_label']);
    }

    /** Empat status: Lengkap, Belum Lengkap, Dikembalikan, Tidak Ditemukan. */
    public function test_empat_status_spj_diterima_dan_selain_itu_ditolak(): void
    {
        $pengelola = $this->user('pengelola_spj');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);

        foreach (array_keys(SpjDetail::STATUS) as $status) {
            $this->actingAs($pengelola)
                ->put(route('inventarisasi-spj.detail.update', $npd), ['status' => $status])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('tidak_ditemukan', SpjDetail::where('npd_id', $npd->id)->firstOrFail()->status);

        $this->actingAs($pengelola)
            ->put(route('inventarisasi-spj.detail.update', $npd), ['status' => 'entah'])
            ->assertSessionHasErrors('status');
    }

    public function test_kpi_menghitung_npd_lengkap_dan_belum_lengkap_beserta_persentasenya(): void
    {
        $pengelola = $this->user('pengelola_spj');
        $a = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.02', null);

        $this->actingAs($pengelola)->put(route('inventarisasi-spj.detail.update', $a), ['status' => 'lengkap']);

        $kpi = app(InventarisasiSpjService::class)->data([])['kpi'];

        $this->assertSame(2, $kpi['jumlah_npd']);
        $this->assertSame(1, $kpi['lengkap']);
        $this->assertSame(50.0, $kpi['lengkap_persen']);
        $this->assertSame(1, $kpi['belum_lengkap']);
        $this->assertSame(50.0, $kpi['belum_lengkap_persen']);
    }

    /**
     * Hanya superadmin dan Pengelola SPJ yang mengubah isi Inventarisasi SPJ.
     * Pemegang menu lainnya - termasuk Bendahara Pengeluaran dan BPP yang
     * dulu boleh - membuka halamannya tanpa tombol ubah, dan rutenya menolak.
     */
    public function test_selain_superadmin_dan_pengelola_spj_hanya_membaca(): void
    {
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);

        foreach (['superadmin', 'pengelola_spj'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('inventarisasi-spj.index'))
                ->assertOk()->assertViewHas('bolehEditDetail', true);
            $this->actingAs($user)->put(route('inventarisasi-spj.detail.update', $npd), [
                'status' => 'belum_lengkap',
            ])->assertSessionHasNoErrors();
        }

        foreach (['bendahara_pengeluaran', 'bpp', 'pptk', 'sekretaris', 'kasubbag', 'pengawas'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('inventarisasi-spj.index'))
                ->assertOk()->assertViewHas('bolehEditDetail', false);
            $this->actingAs($user)->get(route('inventarisasi-spj.rincian', $npd))->assertOk();

            $this->actingAs($user)->put(route('inventarisasi-spj.detail.update', $npd), [
                'status' => 'lengkap',
            ])->assertForbidden();
            $this->actingAs($user)->post(route('inventarisasi-spj.detail.restore', $npd))->assertForbidden();
            $this->actingAs($user)->post(route('inventarisasi-spj.bantex.store'), [
                'nomor' => '11', 'nama' => 'Box '.$role,
            ])->assertForbidden();
        }

        $this->assertSame('belum_lengkap', SpjDetail::where('npd_id', $npd->id)->firstOrFail()->status);
        $this->assertSame(0, BantexSpj::count());

        // Verifikator tidak lagi memegang menu ini sama sekali.
        $this->actingAs($this->user('verifikator'))->get(route('inventarisasi-spj.index'))->assertForbidden();
    }

    public function test_edit_detail_spj_ditolak_bila_npd_belum_selesai(): void
    {
        $pengelola = $this->user('pengelola_spj');
        $master = MasterAnggaran::create(['program' => 'P', 'kegiatan' => 'K', 'sub_kegiatan' => '6.01.02.1.01 Uji', 'kode_rekening' => '5.1.02.09', 'pagu' => 1_000_000, 'aktif' => true]);
        $npd = Npd::create(['jenis' => 'bj', 'master_anggaran_id' => $master->id, 'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-10', 'nominal' => 500_000, 'terbilang' => 'uji', 'status' => 'Draft NPD - PPTK']);

        $this->actingAs($pengelola)->put(route('inventarisasi-spj.detail.update', $npd), ['status' => 'lengkap'])->assertStatus(422);
    }

    private function user(string $role): User
    {
        return User::create(['username' => 'inv-'.$role, 'nama' => $role, 'role' => $role, 'password' => 'rahasia']);
    }

    private function npd(string $sub, string $kode, ?string $tag, float $nominal = 1_000_000): Npd
    {
        $tagging = $tag ? Tagging::firstOrCreate(['nama' => $tag]) : null;
        $master = MasterAnggaran::create(['program' => 'Program', 'kegiatan' => 'Kegiatan', 'sub_kegiatan' => $sub, 'kode_rekening' => $kode,
            'tagging_id' => $tagging?->id, 'pagu' => 10_000_000, 'aktif' => true]);
        $npd = Npd::create(['jenis' => 'bj', 'master_anggaran_id' => $master->id, 'keu' => str_starts_with($sub, '6.01.01') ? '1' : '2',
            'bulan' => 7, 'tahun' => 2026, 'nomor_lengkap' => uniqid('NPD/'), 'tanggal_npd' => '2026-07-10', 'nominal' => $nominal,
            'terbilang' => 'uji', 'status' => 'Selesai', 'detail_json' => ['uraian' => 'Belanja pengujian']]);
        $npd->penerima()->create(['nama' => 'Penerima Uji', 'bruto' => $nominal]);

        return $npd;
    }
}
