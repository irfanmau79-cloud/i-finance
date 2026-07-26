<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdHistoriStatus;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Services\SpjDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSpjTest extends TestCase
{
    use RefreshDatabase;

    private const KODE_BIASA = '5.1.02.04.001.00001';

    private const KODE_DALAM_KOTA = '5.1.02.04.001.00003';

    private const KODE_LAIN = '5.1.02.05.01.0001';

    public function test_hanya_kode_rekening_perjalanan_dinas_yang_masuk_dashboard(): void
    {
        $biasa = $this->npd(self::KODE_BIASA, 'Irban IV', 'Selesai');
        $dalamKota = $this->npd(self::KODE_DALAM_KOTA, 'Inspektur Pembantu Investigasi', 'Selesai');
        $this->npd(self::KODE_LAIN, 'Sekretariat', 'Selesai');

        $hasil = app(SpjDashboardService::class)->ringkasan([], 2026);

        $this->assertSame(2, $hasil['total']);
        $this->assertSame(['Inspektur Pembantu IV', 'Inspektur Pembantu Investigasi'], collect($hasil['bidang'])->pluck('bidang')->all());
        $this->assertSame([$biasa->id, $dalamKota->id], collect($hasil['rows'])->pluck('id')->sort()->values()->all());
    }

    public function test_verifikasi_dan_pembatalan_tercatat_tanpa_mengubah_status_npd(): void
    {
        $verifikator = $this->user('verifikator', 'spj-verifikator');
        $npd = $this->npd(self::KODE_BIASA, 'Sekretariat', 'Selesai');

        $this->actingAs($verifikator)->post(route('dashboard.spj.verify', $npd), ['aksi' => 'verifikasi'])->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertNotNull($npd->spj_verified_at);
        $this->assertSame($verifikator->id, $npd->spj_verified_by);
        $this->assertSame('Selesai', $npd->status);
        $this->assertDatabaseHas('npd_histori_status', ['npd_id' => $npd->id, 'aksi' => 'verifikasi_spj', 'status_asal' => 'Selesai', 'status_tujuan' => 'Selesai']);
        $this->assertDatabaseHas('audit_log', ['user_id' => $verifikator->id, 'aktivitas' => 'Verifikasi SPJ']);

        $this->actingAs($verifikator)->post(route('dashboard.spj.verify', $npd), ['aksi' => 'batalkan'])->assertSessionHasNoErrors();
        $this->assertNull($npd->fresh()->spj_verified_at);
        $this->assertSame('Selesai', $npd->fresh()->status);
        $this->assertSame(['verifikasi_spj', 'batalkan_verifikasi_spj'], NpdHistoriStatus::where('npd_id', $npd->id)->orderBy('nomor_urut')->pluck('aksi')->all());
        $this->assertSame(2, AuditLog::where('user_id', $verifikator->id)->count());
    }

    public function test_prasyarat_kode_rekening_status_dan_otorisasi_ditegakkan_di_backend(): void
    {
        $verifikator = $this->user('verifikator', 'spj-v');
        $pptk = $this->user('pptk', 'spj-pptk');
        $tanpaMenu = $this->user('perencanaan', 'spj-plan');
        $draft = $this->npd(self::KODE_BIASA, 'Sekretariat', 'Draft NPD - PPTK');
        $lain = $this->npd(self::KODE_LAIN, 'Sekretariat', 'Selesai');

        $this->actingAs($verifikator)->post(route('dashboard.spj.verify', $draft), ['aksi' => 'verifikasi'])->assertSessionHasErrors('aksi');
        $this->actingAs($verifikator)->post(route('dashboard.spj.verify', $lain), ['aksi' => 'verifikasi'])->assertSessionHasErrors('aksi');
        $this->actingAs($pptk)->post(route('dashboard.spj.verify', $lain), ['aksi' => 'verifikasi'])->assertForbidden();
        $this->actingAs($tanpaMenu)->get(route('dashboard.spj.index'))->assertForbidden();
        $this->actingAs($verifikator)->get(route('dashboard.spj.index'))->assertOk()->assertSee('Dashboard SPJ Perjalanan Dinas');
        $this->assertNull($draft->fresh()->spj_verified_at);
        $this->assertNull($lain->fresh()->spj_verified_at);
    }

    public function test_filter_bidang_status_dan_pencarian(): void
    {
        $verified = $this->npd(self::KODE_BIASA, 'Irban I', 'Selesai', '001/NPD/2026');
        $verified->forceFill(['spj_verified_at' => now(), 'spj_verified_by' => $this->user('superadmin', 'spj-admin')->id])->save();
        $this->npd(self::KODE_DALAM_KOTA, 'Sekretariat', 'Selesai', '002/NPD/2026');

        $service = app(SpjDashboardService::class);
        $hasil = $service->ringkasan(['bidang' => 'Inspektur Pembantu I', 'status' => 'terverifikasi', 'cari' => '001/npd'], 2026);

        $this->assertSame(1, $hasil['total']);
        $this->assertSame($verified->id, $hasil['rows'][0]['id']);
        $this->assertSame(100.0, $hasil['persen']);
    }

    private function user(string $role, string $username): User
    {
        return User::create(['username' => $username, 'nama' => $username, 'role' => $role, 'password' => 'rahasia']);
    }

    private function npd(string $kodeRekening, string $unit, string $status, ?string $nomor = null): Npd
    {
        $anggaran = MasterAnggaran::create([
            'program' => 'Program Uji', 'kegiatan' => 'Kegiatan', 'sub_kegiatan' => '6.01.02.1.01 Sub Kegiatan Uji '.uniqid(),
            'kode_rekening' => $kodeRekening, 'pagu' => 10_000_000, 'aktif' => true,
        ]);
        $sp = SuratPerintah::create([
            'nomor_sp' => uniqid('SP-'), 'tanggal_sp' => '2026-01-02', 'unit_kerja' => $unit, 'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji', 'tujuan_transfer' => 'Tujuan', 'irban_dibayar' => false, 'rincian_tgl_bayar' => '-',
            'keterangan' => 'Uji', 'file_url' => 'sp/uji.pdf', 'status_sp' => 'Baru',
        ]);

        return Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'surat_perintah_id' => $sp->id,
            'keu' => '1', 'bulan' => 1, 'tahun' => 2026,
            'nomor_lengkap' => $nomor, 'tanggal_npd' => '2026-01-10', 'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah', 'status' => $status, 'detail_json' => ['uraian' => 'Uraian uji'],
        ]);
    }
}
