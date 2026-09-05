<?php

namespace Tests\Feature;

use App\Models\KebutuhanAnggaran;
use App\Models\Pkpt;
use App\Models\User;
use App\Services\KebutuhanAnggaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KebutuhanAnggaranTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.str_replace('_', '', $role),
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatPkpt(array $override = []): Pkpt
    {
        return Pkpt::create(array_replace([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nomor' => '1',
            'unit_kerja' => 'Inspektur Pembantu I',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'estimasi_anggaran' => 1_000_000,
            'realisasi' => 0,
            'rencana_pelaksanaan' => 'Maret',
            'terlaksana' => false,
        ], $override));
    }

    /** Satu kegiatan lengkap siap kirim. */
    private function payload(array $override = [], array $rincianOverride = []): array
    {
        return ['kegiatan' => [array_replace([
            'nomor_pkpt' => '1',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'tanggal_mulai' => '2026-03-02',
            'tanggal_selesai' => '2026-03-05',
            'total_transport' => 500_000,
            'rincian' => [array_replace([
                'jenis_anggota' => 'Eselon III / Golongan IV',
                'jumlah_orang' => 3,
                'hari_dalam' => 2,
                'tarif_uh_dalam' => 100_000,
                'hari_luar' => 3,
                'tarif_uh_luar' => 200_000,
                'jumlah_malam' => 2,
                'tarif_akomodasi' => 570_000,
            ], $rincianOverride)],
        ], $override)]];
    }

    private function buatKebutuhan(string $unit, array $override = []): KebutuhanAnggaran
    {
        $kebutuhan = KebutuhanAnggaran::create(array_replace([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'unit_kerja' => $unit,
            'user_id' => null,
            'dalam_pkpt' => true,
            'nomor_pkpt' => '1',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'tanggal_mulai' => '2026-03-02',
            'tanggal_selesai' => '2026-03-05',
            'tarif_uh_dalam' => '100.000',
            'total_uh_dalam' => 200_000,
            'total_estimasi' => 200_000,
        ], $override));

        $kebutuhan->rincian()->create([
            'urutan' => 1,
            'jenis_anggota' => 'Eselon III / Golongan IV',
            'jumlah_orang' => 2,
            'hari_dalam' => 2,
            'tarif_uh_dalam' => 100_000,
            'jumlah_uh_dalam' => 200_000,
            'estimasi_kebutuhan' => 200_000,
        ]);

        return $kebutuhan;
    }

    // ---------------- Perhitungan ----------------

    public function test_seluruh_angka_dihitung_ulang_di_server(): void
    {
        $hitung = app(KebutuhanAnggaranService::class)->hitungKegiatan([
            'total_transport' => 500_000,
            'rincian' => [
                ['hari_dalam' => 2, 'tarif_uh_dalam' => 100_000, 'hari_luar' => 3, 'tarif_uh_luar' => 200_000, 'jumlah_malam' => 2, 'tarif_akomodasi' => 570_000],
                ['hari_dalam' => 1, 'tarif_uh_dalam' => 170_000, 'hari_luar' => 0, 'tarif_uh_luar' => 0, 'jumlah_malam' => 0, 'tarif_akomodasi' => 0],
            ],
        ]);

        $this->assertSame(370_000.0, $hitung['total_uh_dalam']);   // 200.000 + 170.000
        $this->assertSame(600_000.0, $hitung['total_uh_luar']);
        $this->assertSame(1_140_000.0, $hitung['total_akomodasi']);
        $this->assertSame(500_000.0, $hitung['total_transport']);
        $this->assertSame(2_610_000.0, $hitung['total_estimasi']);

        // Tarif berbeda-beda digabung menaik, hanya yang dipakai.
        $this->assertSame('100.000; 170.000', $hitung['tarif_uh_dalam']);
        $this->assertSame('200.000', $hitung['tarif_uh_luar']);
    }

    public function test_transport_dihitung_sekali_per_kegiatan_bukan_per_rincian(): void
    {
        $service = app(KebutuhanAnggaranService::class);
        $satu = ['hari_dalam' => 1, 'tarif_uh_dalam' => 100_000];

        $satuRincian = $service->hitungKegiatan(['total_transport' => 500_000, 'rincian' => [$satu]]);
        $duaRincian = $service->hitungKegiatan(['total_transport' => 500_000, 'rincian' => [$satu, $satu]]);

        $this->assertSame(600_000.0, $satuRincian['total_estimasi']);
        // Rincian bertambah 100.000, transport TETAP 500.000 - tidak berlipat.
        $this->assertSame(700_000.0, $duaRincian['total_estimasi']);
        $this->assertSame(500_000.0, $duaRincian['total_transport']);
    }

    // ---------------- Formulir ----------------

    public function test_formulir_hanya_menawarkan_pkpt_belum_terlaksana_milik_unit_sendiri(): void
    {
        $this->buatPkpt(['nomor' => '1', 'area' => 'Kesehatan', 'terlaksana' => false]);
        $this->buatPkpt(['nomor' => '2', 'area' => 'Sudah Jalan', 'terlaksana' => true]);
        $this->buatPkpt(['nomor' => '3', 'area' => 'Unit Lain', 'unit_kerja' => 'Inspektur Pembantu IV', 'terlaksana' => false]);

        $bahan = app(KebutuhanAnggaranService::class)->bahanFormulir('Inspektur Pembantu I');

        $this->assertSame(['Kesehatan'], array_column($bahan['belum'], 'area'));
        // Opsi Area/Jenis tetap memuat kegiatan yang sudah terlaksana - dipakai
        // untuk kegiatan sejenis di periode berikutnya - tapi tidak unit lain.
        $this->assertSame(['Kesehatan', 'Sudah Jalan'], $bahan['area']);
    }

    public function test_formulir_hanya_untuk_role_irban(): void
    {
        $this->actingAs($this->buatUser('irban1'))->get(route('kebutuhan.create'))
            ->assertOk()
            ->assertSee('Inspektur Pembantu I');

        // Perencanaan boleh melihat rekapnya, tapi tidak boleh mengisi.
        $this->actingAs($this->buatUser('perencanaan'))->get(route('kebutuhan.create'))->assertForbidden();
        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))->get(route('kebutuhan.create'))->assertForbidden();
    }

    // ---------------- Simpan ----------------

    public function test_irban_menyimpan_kegiatan_beserta_rinciannya(): void
    {
        $irban = $this->buatUser('irban2');

        $this->actingAs($irban)
            ->post(route('kebutuhan.store'), $this->payload())
            ->assertRedirect(route('kebutuhan.index'));

        $kebutuhan = KebutuhanAnggaran::with('rincian')->sole();

        $this->assertSame('Inspektur Pembantu II', $kebutuhan->unit_kerja);
        $this->assertSame($irban->id, $kebutuhan->user_id);
        $this->assertTrue($kebutuhan->dalam_pkpt);
        $this->assertSame(200_000.0, (float) $kebutuhan->total_uh_dalam);
        $this->assertSame(600_000.0, (float) $kebutuhan->total_uh_luar);
        $this->assertSame(1_140_000.0, (float) $kebutuhan->total_akomodasi);
        $this->assertSame(500_000.0, (float) $kebutuhan->total_transport);
        $this->assertSame(2_440_000.0, (float) $kebutuhan->total_estimasi);
        $this->assertCount(1, $kebutuhan->rincian);
        $this->assertSame(1_940_000.0, (float) $kebutuhan->rincian->first()->estimasi_kebutuhan);
    }

    public function test_unit_kerja_diambil_dari_role_bukan_dari_kiriman(): void
    {
        $irban = $this->buatUser('irban3');

        // Isian mencoba menyebut unit lain; harus diabaikan sepenuhnya.
        $payload = $this->payload();
        $payload['kegiatan'][0]['unit_kerja'] = 'Inspektur Pembantu I';

        $this->actingAs($irban)->post(route('kebutuhan.store'), $payload)->assertRedirect();

        $this->assertSame('Inspektur Pembantu III', KebutuhanAnggaran::sole()->unit_kerja);
    }

    public function test_kegiatan_di_luar_pkpt_wajib_berketerangan(): void
    {
        $irban = $this->buatUser('irban1');

        $this->actingAs($irban)
            ->post(route('kebutuhan.store'), $this->payload(['luar_pkpt' => 1, 'nomor_pkpt' => '', 'keterangan' => '']))
            ->assertSessionHasErrors('kegiatan.0.keterangan');

        $this->assertSame(0, KebutuhanAnggaran::count());

        $this->actingAs($irban)
            ->post(route('kebutuhan.store'), $this->payload([
                'luar_pkpt' => 1, 'nomor_pkpt' => '', 'keterangan' => 'Pendampingan khusus',
            ]))
            ->assertSessionHasNoErrors();

        $kebutuhan = KebutuhanAnggaran::sole();
        $this->assertFalse($kebutuhan->dalam_pkpt);
        $this->assertNull($kebutuhan->nomor_pkpt);
        $this->assertSame('Pendampingan khusus', $kebutuhan->keterangan);
    }

    public function test_kegiatan_pkpt_tanpa_pilihan_apa_pun_ditolak(): void
    {
        $this->actingAs($this->buatUser('irban1'))
            ->post(route('kebutuhan.store'), $this->payload(['nomor_pkpt' => '', 'area' => '']))
            ->assertSessionHasErrors('kegiatan.0.nomor_pkpt');
    }

    public function test_tanggal_selesai_tidak_boleh_mendahului_tanggal_mulai(): void
    {
        $this->actingAs($this->buatUser('irban1'))
            ->post(route('kebutuhan.store'), $this->payload([
                'tanggal_mulai' => '2026-03-10', 'tanggal_selesai' => '2026-03-02',
            ]))
            ->assertSessionHasErrors('kegiatan.0.tanggal_selesai');
    }

    public function test_tarif_uang_harian_di_luar_standar_biaya_ditolak(): void
    {
        $this->actingAs($this->buatUser('irban1'))
            ->post(route('kebutuhan.store'), $this->payload([], ['tarif_uh_dalam' => 999_000]))
            ->assertSessionHasErrors('kegiatan.0.rincian.0.tarif_uh_dalam');

        // Akomodasi sebaliknya: memang boleh di luar daftar (isian manual).
        $this->actingAs($this->buatUser('irban4'))
            ->post(route('kebutuhan.store'), $this->payload([], ['tarif_akomodasi' => 812_500]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1_625_000.0, (float) KebutuhanAnggaran::sole()->total_akomodasi);
    }

    public function test_rincian_tanpa_hari_dan_malam_ditolak(): void
    {
        $this->actingAs($this->buatUser('irban1'))
            ->post(route('kebutuhan.store'), $this->payload([], [
                'hari_dalam' => 0, 'hari_luar' => 0, 'jumlah_malam' => 0,
            ]))
            ->assertSessionHasErrors('kegiatan.0.rincian.0.hari_dalam');
    }

    public function test_role_bukan_irban_tidak_dapat_menyimpan(): void
    {
        $this->actingAs($this->buatUser('perencanaan'))
            ->post(route('kebutuhan.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, KebutuhanAnggaran::count());
    }

    // ---------------- Rekap & hapus ----------------

    public function test_irban_hanya_melihat_datanya_sendiri_sedangkan_perencanaan_melihat_semua(): void
    {
        $this->buatKebutuhan('Inspektur Pembantu I', ['area' => 'Punya Irban I']);
        $this->buatKebutuhan('Inspektur Pembantu IV', ['area' => 'Punya Irban IV']);

        $this->actingAs($this->buatUser('irban1'))->get(route('kebutuhan.index'))
            ->assertOk()
            ->assertSee('Punya Irban I')
            ->assertDontSee('Punya Irban IV');

        $this->actingAs($this->buatUser('perencanaan'))->get(route('kebutuhan.index'))
            ->assertOk()
            ->assertSee('Punya Irban I')
            ->assertSee('Punya Irban IV')
            ->assertSee('Menampilkan seluruh unit');
    }

    public function test_irban_hanya_dapat_menghapus_data_unitnya_sendiri(): void
    {
        $milikSendiri = $this->buatKebutuhan('Inspektur Pembantu I');
        $milikUnitLain = $this->buatKebutuhan('Inspektur Pembantu IV');
        $irban = $this->buatUser('irban1');

        $this->actingAs($irban)->delete(route('kebutuhan.destroy', $milikUnitLain))->assertForbidden();
        $this->assertModelExists($milikUnitLain);

        $this->actingAs($irban)->delete(route('kebutuhan.destroy', $milikSendiri))
            ->assertRedirect(route('kebutuhan.index'));
        $this->assertModelMissing($milikSendiri);
        // Rinciannya ikut terhapus lewat cascade, tidak meninggalkan yatim.
        $this->assertSame(0, $milikSendiri->rincian()->count());
    }

    public function test_perencanaan_tidak_dapat_menghapus_data_siapa_pun(): void
    {
        $kebutuhan = $this->buatKebutuhan('Inspektur Pembantu I');

        $this->actingAs($this->buatUser('perencanaan'))
            ->delete(route('kebutuhan.destroy', $kebutuhan))
            ->assertForbidden();

        $this->assertModelExists($kebutuhan);
    }

    public function test_export_kebutuhan_dapat_diunduh(): void
    {
        $this->buatKebutuhan('Inspektur Pembantu I');

        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->get(route('manajemen-data.export', 'kebutuhan-anggaran'))
            ->assertOk();
    }

    // ---------------- Role Irban di Manajemen Users ----------------

    public function test_role_irban_dapat_dipilih_dan_disimpan_di_manajemen_users(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        // Dropdown role di formulir berasal dari config('akses.role_label'),
        // sedangkan penyimpanannya divalidasi User::ROLE_OPTIONS dan dibatasi
        // ENUM kolom role. Ketiganya harus sepakat - dulu justru di sinilah
        // role Irban tersendat.
        $this->actingAs($superadmin)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Inspektur Pembantu Investigasi');

        $this->actingAs($superadmin)->post(route('users.store'), [
            'username' => 'irban-investigasi',
            'nama' => 'Irban Investigasi',
            'role' => 'irban_inv',
            'password' => 'rahasia-sekali',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['username' => 'irban-investigasi', 'role' => 'irban_inv']);

        foreach (User::ROLE_IRBAN as $role) {
            $this->assertContains($role, User::ROLE_OPTIONS, "Role {$role} tidak ada di ROLE_OPTIONS.");
            $this->assertArrayHasKey($role, config('akses.role_label'), "Role {$role} tidak punya label.");
            $this->assertArrayHasKey($role, config('akses.menu'), "Role {$role} tidak punya daftar menu.");
        }
    }
}
