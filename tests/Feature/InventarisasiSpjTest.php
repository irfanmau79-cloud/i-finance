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
            'nama' => 'Bantex Kosong A-01',
            'keterangan' => 'Arsip baru',
        ])->assertSessionHasNoErrors();

        $data = app(InventarisasiSpjService::class)->data([]);
        $this->assertSame(1, $data['jumlah_lokasi']);
        $this->assertSame('Bantex Kosong A-01', $data['lokasi'][0]['lokasi']);
        $this->assertSame(0, $data['lokasi'][0]['jumlah_dokumen']);
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
        foreach (['Rak A', 'Rak B'] as $nama) BantexSpj::create(['nama' => $nama, 'aktif' => true]);
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

    public function test_bendahara_dapat_edit_detail_spj_dan_restore_mengembalikan_kolom_hitung_saja(): void
    {
        $bendahara = $this->user('bendahara_pengeluaran');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);
        BantexSpj::create(['nama' => 'Bantex C-03', 'aktif' => true]);

        $response = $this->actingAs($bendahara)->put(route('inventarisasi-spj.detail.update', $npd), [
            'bulan' => 5, 'nomor_sp' => 'SP-MANUAL-1', 'nominal' => 2_500_000, 'koordinator' => 'Koordinator Manual',
            'bidang' => 'Subbagian Tata Usaha', 'uraian' => 'Uraian manual', 'lokasi' => 'Bantex C-03',
            'status' => 'lengkap', 'catatan' => 'Sudah lengkap dokumennya',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $detail = SpjDetail::where('npd_id', $npd->id)->firstOrFail();
        $this->assertSame(5, $detail->bulan);
        $this->assertSame('SP-MANUAL-1', $detail->nomor_sp);
        $this->assertSame('lengkap', $detail->status);
        $this->assertSame('Sudah lengkap dokumennya', $detail->catatan);
        $this->assertSame($bendahara->id, $detail->diedit_oleh);

        $data = app(InventarisasiSpjService::class)->data([]);
        $row = collect($data['detail_spj'])->firstWhere('npd_id', $npd->id);
        $this->assertSame('SP-MANUAL-1', $row['nomor_sp']);
        $this->assertSame('Bantex C-03', $row['lokasi']);
        $this->assertSame('lengkap', $row['status']);

        // Restore: kolom hitung kembali null (dipakai lagi nilai default), status & catatan tidak berubah.
        $this->actingAs($bendahara)->post(route('inventarisasi-spj.detail.restore', $npd))->assertSessionHasNoErrors();
        $detail->refresh();
        $this->assertNull($detail->nomor_sp);
        $this->assertNull($detail->koordinator);
        $this->assertNull($detail->lokasi);
        $this->assertSame('lengkap', $detail->status);
        $this->assertSame('Sudah lengkap dokumennya', $detail->catatan);
    }

    public function test_bpp_boleh_tapi_role_lain_tidak_boleh_edit_detail_spj(): void
    {
        $bpp = $this->user('bpp');
        $pptk = $this->user('pptk');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', null);

        $this->actingAs($bpp)->put(route('inventarisasi-spj.detail.update', $npd), [
            'status' => 'belum_lengkap',
        ])->assertSessionHasNoErrors();

        $this->actingAs($pptk)->put(route('inventarisasi-spj.detail.update', $npd), [
            'status' => 'belum_lengkap',
        ])->assertForbidden();
        $this->actingAs($pptk)->post(route('inventarisasi-spj.detail.restore', $npd))->assertForbidden();
    }

    public function test_edit_detail_spj_ditolak_bila_npd_belum_selesai(): void
    {
        $bendahara = $this->user('bendahara_pengeluaran');
        $master = MasterAnggaran::create(['program' => 'P', 'kegiatan' => 'K', 'sub_kegiatan' => '6.01.02.1.01 Uji', 'kode_rekening' => '5.1.02.09', 'pagu' => 1_000_000, 'aktif' => true]);
        $npd = Npd::create(['jenis' => 'bj', 'master_anggaran_id' => $master->id, 'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-10', 'nominal' => 500_000, 'terbilang' => 'uji', 'status' => 'Draft NPD - PPTK']);

        $this->actingAs($bendahara)->put(route('inventarisasi-spj.detail.update', $npd), ['status' => 'lengkap'])->assertStatus(422);
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
