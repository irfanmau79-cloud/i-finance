<?php

namespace Tests\Feature;

use App\Models\ArsipSpj;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Tagging;
use App\Models\User;
use App\Services\InventarisasiSpjService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarisasiSpjTest extends TestCase
{
    use RefreshDatabase;

    public function test_lokasi_dapat_ditetapkan_dan_dipindahkan_tanpa_menghapus_histori(): void
    {
        $user = $this->user('superadmin');
        $npd = $this->npd('6.01.02.1.01 Pengawasan', '5.1.02.01', 'Tag A');

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
        $this->actingAs($sekretaris)->get(route('inventarisasi-spj.index'))->assertOk()->assertSee('Rak bantex interaktif');
        $this->actingAs($perencanaan)->get(route('inventarisasi-spj.index'))->assertForbidden();
        $this->actingAs($sekretaris)->post(route('npd.arsip-spj.store', $npd), ['jenis_dokumen' => 'NPD', 'lokasi' => 'X'])->assertForbidden();
        $this->assertSame(0, ArsipSpj::count());
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
