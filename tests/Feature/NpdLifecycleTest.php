<?php

namespace Tests\Feature;

use App\Helpers\Terbilang;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'lifecycle-'.$role.'-'.User::count(),
            'nama' => ucfirst($role),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function anggaran(float $pagu = 20_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Lifecycle',
            'kegiatan' => 'Kegiatan Lifecycle',
            'sub_kegiatan' => '6.01.01.2.01 Sub Lifecycle',
            'kode_rekening' => '5.1.02.01.01.9999',
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function payloadBj(MasterAnggaran $anggaran, float $bruto = 1_000_000): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => [[
                'nama' => 'Penerima Lifecycle',
                'bruto' => $bruto,
                'ppn' => 0,
                'biaya_ku_rtgs' => 0,
                'keterangan' => 'Uji lifecycle',
            ]],
        ];
    }

    private function payloadPd(MasterAnggaran $anggaran, SuratPerintah $sp, float $tarif = 500_000): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'surat_perintah_id' => $sp->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_sp' => $sp->nomor_sp,
            'tanggal_sp' => '2026-07-18',
            'uraian_sp' => 'Perjalanan lifecycle',
            'berangkat_dari' => 'Bekasi',
            'tujuan' => 'Bandung',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-21',
            'penerima_index' => 0,
            'tim' => [[
                'nama' => 'Anggota Lifecycle',
                'paket' => [[
                    'cluster' => 'A',
                    'wilayah' => 'Bandung',
                    'lama_hari' => 2,
                    'tarif_uh' => $tarif,
                    'malam' => 0,
                    'tarif_akom' => 0,
                ]],
            ]],
        ];
    }

    private function sp(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '099/SP/LIFECYCLE/2026',
            'tanggal_sp' => '2026-07-18',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 Juli 2026',
            'keterangan' => 'Perjalanan lifecycle',
            'file_url' => 'sp/lifecycle.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
        ]);
    }

    public function test_lifecycle_lengkap_mencatat_histori_berurutan_dalam_setiap_transisi(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $verifikator = $this->user('verifikator');

        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($this->anggaran()));
        $npd = Npd::sole();

        $this->actingAs($pptk)->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp']);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);
        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 8]);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'setuju']);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'selesai']);

        $npd->refresh();
        $this->assertSame('Selesai', $npd->status);
        $this->assertSame('08/NPD-Keu.1.IBC/7/2026', $npd->nomor_lengkap);
        $this->assertSame(
            ['buat', 'ajukan_bpp', 'teruskan', 'verifikasi', 'setuju', 'selesai'],
            $npd->historiStatus()->pluck('aksi')->all()
        );
        $this->assertSame([1, 2, 3, 4, 5, 6], $npd->historiStatus()->pluck('nomor_urut')->all());
    }

    public function test_edit_barang_jasa_hanya_di_draft_pptk_dan_menghitung_ulang_nominal_terbilang(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $anggaran = $this->anggaran();
        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran));
        $npd = Npd::sole();

        $this->actingAs($pptk)->get(route('npd.bj.edit', $npd))->assertOk()->assertSee('Edit Nota Pencairan Dana Barang/Jasa');
        $this->actingAs($pptk)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, 1_750_000))
            ->assertRedirect(route('npd.show', $npd));

        $npd->refresh();
        $this->assertSame(1_750_000.0, (float) $npd->nominal);
        $this->assertSame(Terbilang::rupiah(1_750_000), $npd->terbilang);
        $this->assertSame(['buat', 'edit'], $npd->historiStatus()->pluck('aksi')->all());

        $npd->update(['status' => 'Draft NPD - BPP']);
        $this->actingAs($pptk)->get(route('npd.bj.edit', $npd))->assertForbidden();
        $this->actingAs($bpp)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, 500_000))->assertForbidden();
    }

    public function test_edit_perjalanan_mengganti_detail_tim_dan_menghitung_ulang_nominal(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran();
        $sp = $this->sp();
        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $sp));
        $npd = Npd::sole();

        $payload = $this->payloadPd($anggaran, $sp, 750_000);
        $payload['tujuan'] = 'Bogor';
        $this->actingAs($pptk)->put(route('npd.pd.update', $npd), $payload)
            ->assertRedirect(route('npd.show', $npd));

        $npd->refresh();
        $this->assertSame(1_500_000.0, (float) $npd->nominal);
        $this->assertSame('Bogor', $npd->detail_json['tujuan']);
        $this->assertSame(Terbilang::rupiah(1_500_000), $npd->terbilang);
        $this->assertSame(['buat', 'edit'], $npd->historiStatus()->pluck('aksi')->all());
    }

    public function test_pembatalan_draft_soft_delete_membebaskan_anggaran_dan_mengembalikan_status_sp(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran();
        $sp = $this->sp();
        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $sp));
        $npd = Npd::sole();
        $this->assertSame(1_000_000.0, $anggaran->fresh()->danaTerikatNpd());

        $this->actingAs($pptk)->followingRedirects()
            ->delete(route('npd.destroy', $npd), ['alasan' => 'Duplikat input'])
            ->assertOk()
            ->assertSee('NPD berhasil dibatalkan dengan aman.');

        $batal = Npd::withTrashed()->findOrFail($npd->id);
        $this->assertTrue($batal->trashed());
        $this->assertSame('Dibatalkan', $batal->status);
        $this->assertSame(['buat', 'batalkan'], $batal->historiStatus()->pluck('aksi')->all());
        $this->assertSame(0.0, $anggaran->fresh()->danaTerikatNpd());
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $sp->fresh()->status);
    }

    public function test_npd_yang_sudah_masuk_workflow_tidak_dihapus_dan_hanya_superadmin_dapat_membatalkan(): void
    {
        $pptk = $this->user('pptk');
        $superadmin = $this->user('superadmin');
        $anggaran = $this->anggaran();
        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran));
        $npd = Npd::sole();
        $npd->update(['status' => 'Selesai', 'nomor_urut' => 3, 'nomor_lengkap' => '03/NPD-Keu.1.IBC/7/2026']);

        $this->actingAs($pptk)->delete(route('npd.destroy', $npd), ['alasan' => 'Tidak berwenang'])->assertForbidden();
        $this->actingAs($superadmin)->delete(route('npd.destroy', $npd), ['alasan' => 'Dokumen dibatalkan'])->assertRedirect('/npd');

        $npd->refresh();
        $this->assertFalse($npd->trashed());
        $this->assertSame('Dibatalkan', $npd->status);
        $this->assertNull($npd->nomor_urut);
        $this->assertSame('batalkan', $npd->historiStatus()->reorder('nomor_urut', 'desc')->value('aksi'));

        $this->actingAs($superadmin)->followingRedirects()
            ->delete(route('npd.destroy', $npd), ['alasan' => 'Klik ulang'])
            ->assertOk()
            ->assertSee('sudah dibatalkan sebelumnya');
    }

    public function test_hanya_superadmin_dapat_menghapus_npd_secara_permanen(): void
    {
        $pptk = $this->user('pptk');
        $superadmin = $this->user('superadmin');
        $anggaran = $this->anggaran();
        $npd = Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => 'KEU1', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-27', 'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 100_000, 'terbilang' => 'Seratus ribu rupiah',
            'status' => 'Dibatalkan', 'dibuat_oleh' => $superadmin->id,
        ]);
        $npd->catatHistoriStatus($superadmin, 'buat', null, 'Dibatalkan');

        $this->actingAs($pptk)->delete(route('npd.destroy-permanent', $npd), [
            'alasan_permanen' => 'Tidak berwenang',
        ])->assertForbidden();

        $this->actingAs($superadmin)->delete(route('npd.destroy-permanent', $npd), [
            'alasan_permanen' => 'Data uji harus dibersihkan',
        ])->assertRedirect('/npd');

        $this->assertNull(Npd::withTrashed()->find($npd->id));
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'Hapus Permanen NPD']);
    }

    public function test_konflik_nomor_tahunan_ditolak_dengan_pesan_ramah(): void
    {
        $verifikator = $this->user('verifikator');
        $anggaran = $this->anggaran();
        $terpakai = Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '1', 'bulan' => 6, 'tahun' => 2026,
            'nomor_urut' => 9, 'nomor_lengkap' => '09/NPD-Keu.1.IBC/6/2026', 'tanggal_npd' => '2026-06-20',
            'nominal' => 100_000, 'terbilang' => 'seratus ribu rupiah', 'status' => 'Draft NPD - BPP',
        ]);
        $target = Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '1', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-20', 'nominal' => 100_000, 'terbilang' => 'seratus ribu rupiah',
            'status' => 'Verifikasi - Verifikator',
        ]);

        $response = $this->actingAs($verifikator)->post(route('npd.transisi', $target), ['aksi' => 'verifikasi', 'nomor_urut' => 9]);

        $response->assertSessionHasErrors('nomor_urut');
        $this->assertStringContainsString('sudah dipakai', session('errors')->first('nomor_urut'));
        $this->assertSame('Verifikasi - Verifikator', $target->fresh()->status);
        $this->assertSame(9, $terpakai->fresh()->nomor_urut);
    }

    public function test_status_dikembalikan_tidak_lagi_menjadi_sumber_kebenaran(): void
    {
        $this->assertNotContains('Dikembalikan', Npd::STATUS_LIST);
        $this->assertContains('Dibatalkan', Npd::STATUS_LIST);
    }

    public function test_constraint_database_menolak_nomor_sama_pada_keu_dan_tahun_yang_sama(): void
    {
        $anggaran = $this->anggaran();
        $dasar = [
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '1', 'tahun' => 2026,
            'nomor_urut' => 12, 'nomor_lengkap' => '12/NPD-Keu.1.IBC/6/2026',
            'nominal' => 100_000, 'terbilang' => 'seratus ribu rupiah', 'status' => 'Draft NPD - BPP',
        ];
        Npd::create($dasar + ['bulan' => 6, 'tanggal_npd' => '2026-06-20']);

        $this->expectException(QueryException::class);
        Npd::create($dasar + [
            'bulan' => 7,
            'tanggal_npd' => '2026-07-20',
            'nomor_lengkap' => '12/NPD-Keu.1.IBC/7/2026',
        ]);
    }
}
