<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Services\SuratPerintahTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data SP (dua toggle) + Monitoring SP (timeline progres).
 * Port dari setPantauSP/setSumberNPD dan _susunTimelineSP di
 * gas-lama/CodeSuratPerintah.gs.
 */
class SuratPerintahMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Halaman layanan kini di balik gerbang kata sandi bersama. Yang diuji
        // di berkas ini isi halamannya, bukan gerbangnya - gerbangnya punya
        // GerbangLayananTest sendiri.
        $this->lolosGerbangLayanan();
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'uji-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function sp(array $override = []): SuratPerintah
    {
        return SuratPerintah::create(array_replace([
            'nomor_sp' => '087/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Koordinator',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'pengajuan' => 'Uang Harian, Akomodasi',
            'dipantau' => true,
            'sumber_npd' => true,
        ], $override));
    }

    private function masterAnggaran(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Uji SP',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji SP',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Uji SP',
            'kode_rekening' => '5.1.02.05.01.7001',
            'rekening' => 'Belanja Uji SP',
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    private function npd(SuratPerintah $sp, string $status = 'Draft NPD - PPTK'): Npd
    {
        return Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $this->masterAnggaran()->id,
            'surat_perintah_id' => $sp->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_lengkap' => '01/NPD-Keu.1.IBC/7/2026',
            'tanggal_npd' => '2026-07-22',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 3_000_000,
            'terbilang' => 'tiga juta rupiah',
            'status' => $status,
        ]);
    }

    // ---------------- Toggle Data SP ----------------

    public function test_dua_toggle_tampil_dan_bekerja_terpisah(): void
    {
        $pptk = $this->user('pptk');
        $sp = $this->sp();

        $this->actingAs($pptk)->get(route('surat-perintah.index'))
            ->assertOk()
            ->assertSee('Monitoring SP')
            ->assertSee('Sumber NPD');

        $this->actingAs($pptk)->patch(route('surat-perintah.toggle-sumber-npd', $sp))
            ->assertOk()->assertJson(['sumber_npd' => false]);

        $sp->refresh();
        $this->assertFalse($sp->sumber_npd);
        $this->assertTrue($sp->dipantau, 'Mematikan Sumber NPD tidak boleh ikut menyembunyikan SP dari Monitoring.');

        $this->actingAs($pptk)->patch(route('surat-perintah.toggle-pantau', $sp))
            ->assertOk()->assertJson(['dipantau' => false]);

        $this->assertFalse($sp->fresh()->dipantau);
    }

    public function test_bpp_boleh_mengatur_sumber_npd_tapi_tidak_toggle_monitoring(): void
    {
        $bpp = $this->user('bpp');
        $sp = $this->sp();

        $this->actingAs($bpp)->patch(route('surat-perintah.toggle-sumber-npd', $sp))->assertOk();
        $this->actingAs($bpp)->patch(route('surat-perintah.toggle-pantau', $sp))->assertForbidden();

        $this->assertTrue($sp->fresh()->dipantau);
    }

    public function test_verifikator_tidak_boleh_mengubah_kedua_toggle(): void
    {
        $verifikator = $this->user('verifikator');
        $sp = $this->sp();

        $this->actingAs($verifikator)->patch(route('surat-perintah.toggle-sumber-npd', $sp))->assertForbidden();
        $this->actingAs($verifikator)->patch(route('surat-perintah.toggle-pantau', $sp))->assertForbidden();
    }

    public function test_sumber_npd_mati_menyembunyikan_sp_dari_pembuatan_npd_perjalanan_dinas(): void
    {
        $pptk = $this->user('pptk');
        $sp = $this->sp();

        $this->actingAs($pptk)->get(route('npd.pd.create'))->assertOk()
            ->assertViewHas('suratPerintahList', fn ($daftar) => $daftar->contains('id', $sp->id));

        $sp->update(['sumber_npd' => false]);

        $this->actingAs($pptk)->get(route('npd.pd.create'))->assertOk()
            ->assertViewHas('suratPerintahList', fn ($daftar) => ! $daftar->contains('id', $sp->id));
    }

    public function test_dipantau_mati_menyembunyikan_sp_dari_monitoring_saja(): void
    {
        $pptk = $this->user('pptk');
        $sp = $this->sp();

        $this->actingAs($pptk)->get(route('surat-perintah.monitoring'))->assertOk()->assertSee($sp->nomor_sp);

        $sp->update(['dipantau' => false]);

        $this->actingAs($pptk)->get(route('surat-perintah.monitoring'))->assertOk()->assertDontSee($sp->nomor_sp);
        // Data SP adalah arsip lengkap: tetap menampilkan SP yang tak dipantau.
        $this->actingAs($pptk)->get(route('surat-perintah.index'))->assertOk()->assertSee($sp->nomor_sp);
    }

    // ---------------- Timeline ----------------

    public function test_timeline_menandai_titik_sesuai_histori_npd(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp);

        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'teruskan', 'Draft NPD - BPP', 'Verifikasi - Verifikator');
        $npd->catatHistoriStatus(null, 'verifikasi', 'Verifikasi - Verifikator', 'Draft NPD - BPP');

        $tl = app(SuratPerintahTimelineService::class)->untukSatu($sp);
        $label = array_column($tl['titik'], 'label');

        $this->assertSame([
            'SP Diterima', 'NPD Dibuat', 'Diperiksa BPP', 'Verifikasi',
            'Revisi', 'Persetujuan NPD & Proses IBC', 'Selesai',
        ], $label);

        $tercapai = array_combine($label, array_column($tl['titik'], 'tercapai'));
        $this->assertTrue($tercapai['SP Diterima']);
        $this->assertTrue($tercapai['NPD Dibuat']);
        $this->assertTrue($tercapai['Diperiksa BPP']);
        $this->assertTrue($tercapai['Verifikasi']);
        $this->assertTrue($tercapai['Persetujuan NPD & Proses IBC']);
        $this->assertFalse($tercapai['Selesai']);

        // Sudah lewat verifikator tanpa pernah dikembalikan.
        $revisi = collect($tl['titik'])->firstWhere('label', 'Revisi');
        $this->assertTrue($revisi['tercapai']);
        $this->assertNull($revisi['ts']);
        $this->assertSame('Tanpa revisi', $revisi['catatan']);

        $this->assertSame($npd->nomor_lengkap, $tl['nomor_npd']);
        $this->assertTrue($tl['ada_npd']);
    }

    public function test_titik_revisi_memakai_pengembalian_terakhir(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp);

        $npd->catatHistoriStatus(null, 'teruskan', 'Draft NPD - BPP', 'Verifikasi - Verifikator');
        $npd->catatHistoriStatus(null, 'kembali_pptk', 'Draft NPD - BPP', 'Draft NPD - PPTK', 'revisi pertama');
        $npd->catatHistoriStatus(null, 'kembali_pptk', 'Draft NPD - BPP', 'Draft NPD - PPTK', 'revisi kedua');

        $revisi = collect(app(SuratPerintahTimelineService::class)->untukSatu($sp)['titik'])
            ->firstWhere('label', 'Revisi');

        $this->assertTrue($revisi['tercapai']);
        $this->assertNotNull($revisi['ts'], 'Revisi yang benar-benar terjadi harus punya waktu.');
        $this->assertSame('', $revisi['catatan']);
    }

    // ---------------- Ringkasan keadaan di bawah timeline ----------------

    /**
     * Timeline menunjukkan sudah sampai mana; ringkasan menjelaskan apa
     * artinya sekarang. Yang menentukan tahapnya adalah STATUS NPD, bukan
     * titik terakhir yang tercapai - dokumen yang bolak-balik revisi membuat
     * urutan titik tidak lagi mencerminkan kronologi.
     */
    private function ringkasan(SuratPerintah $sp): array
    {
        return app(SuratPerintahTimelineService::class)->untukSatu($sp)['ringkasan'];
    }

    public function test_ringkasan_sp_belum_punya_npd(): void
    {
        $r = $this->ringkasan($this->sp());

        $this->assertSame('SP Diterima', $r['label']);
        $this->assertSame('Surat Perintah telah diterima oleh PPTK dan menunggu NPD dibuat.', $r['narasi']);
        $this->assertSame('jalan', $r['nada']);
    }

    public function test_ringkasan_mengikuti_penamaan_spj_untuk_pengajuan_transport_saja(): void
    {
        $r = $this->ringkasan($this->sp(['pengajuan' => 'Transport']));

        $this->assertSame('SPJ Diterima', $r['label'], 'Label ringkasan harus seiring titik pertama timeline.');
        $this->assertSame('Surat Perintah telah diterima oleh PPTK dan menunggu NPD dibuat.', $r['narasi']);
    }

    public function test_ringkasan_npd_baru_dibuat(): void
    {
        $sp = $this->sp();
        $this->npd($sp, 'Draft NPD - PPTK');

        $r = $this->ringkasan($sp);

        $this->assertSame('NPD Dibuat', $r['label']);
        $this->assertSame('NPD telah dibuat oleh PPTK namun belum diserahkan kepada BPP atau dalam tahap revisi.', $r['narasi']);
    }

    public function test_ringkasan_diperiksa_bpp(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');

        $r = $this->ringkasan($sp);

        $this->assertSame('Diperiksa BPP', $r['label']);
        $this->assertSame('NPD sedang diperiksa oleh BPP sebelum disetujui dan diteruskan ke KPA.', $r['narasi']);
    }

    public function test_ringkasan_verifikasi(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Verifikasi - Verifikator');
        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'teruskan', 'Draft NPD - BPP', 'Verifikasi - Verifikator');

        $r = $this->ringkasan($sp);

        $this->assertSame('Verifikasi', $r['label']);
        $this->assertSame('NPD sedang diverifikasi oleh Verifikator.', $r['narasi']);
    }

    public function test_ringkasan_revisi_dibedakan_dari_npd_baru_dibuat(): void
    {
        $sp = $this->sp();
        // Statusnya kembali ke 'Draft NPD - PPTK', sama persis dengan NPD yang
        // baru dibuat - yang membedakan hanya pernah dikembalikan BPP.
        $npd = $this->npd($sp, 'Draft NPD - PPTK');
        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'kembali_pptk', 'Draft NPD - BPP', 'Draft NPD - PPTK', 'lampiran kurang');

        $r = $this->ringkasan($sp);

        $this->assertSame('Revisi', $r['label']);
        $this->assertSame('NPD sedang direvisi oleh PPTK.', $r['narasi']);
        $this->assertSame('revisi', $r['nada']);
    }

    public function test_ringkasan_persetujuan_dan_proses_ibc(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'NPD Disetujui - BPP');
        $npd->catatHistoriStatus(null, 'verifikasi', 'Verifikasi - Verifikator', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'setuju', 'Draft NPD - BPP', 'NPD Disetujui - BPP');

        $r = $this->ringkasan($sp);

        $this->assertSame('Persetujuan NPD & Proses IBC', $r['label']);
        $this->assertSame('NPD disetujui oleh BPP, menunggu validasi KPA dan transaksi IBC.', $r['narasi']);
    }

    public function test_ringkasan_selesai(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Selesai');
        $npd->catatHistoriStatus(null, 'selesai', 'NPD Disetujui - BPP', 'Selesai');

        $r = $this->ringkasan($sp);

        $this->assertSame('Selesai', $r['label']);
        $this->assertSame('Transaksi telah selesai dieksekusi.', $r['narasi']);
        $this->assertSame('selesai', $r['nada']);
    }

    public function test_ringkasan_npd_dibatalkan_tidak_terbaca_seolah_masih_berjalan(): void
    {
        $sp = $this->sp();
        $this->npd($sp, 'Dibatalkan');

        $r = $this->ringkasan($sp);

        $this->assertSame('Dibatalkan', $r['label']);
        $this->assertSame('batal', $r['nada']);
    }

    /**
     * Dokumen yang sudah bolak-balik: pernah dikembalikan, lalu maju lagi
     * sampai verifikator. Titik "Revisi" tetap tercapai di timeline, tapi
     * ringkasannya harus mengikuti di meja siapa dokumen berada SEKARANG.
     */
    public function test_ringkasan_mengikuti_keadaan_terkini_bukan_titik_terakhir(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Verifikasi - Verifikator');
        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'kembali_pptk', 'Draft NPD - BPP', 'Draft NPD - PPTK', 'perbaiki nominal');
        $npd->catatHistoriStatus(null, 'ajukan_bpp', 'Draft NPD - PPTK', 'Draft NPD - BPP');
        $npd->catatHistoriStatus(null, 'teruskan', 'Draft NPD - BPP', 'Verifikasi - Verifikator');

        $tl = app(SuratPerintahTimelineService::class)->untukSatu($sp);

        $this->assertTrue(
            collect($tl['titik'])->firstWhere('label', 'Revisi')['tercapai'],
            'Riwayat revisinya tetap tercatat di timeline.'
        );
        $this->assertSame('Verifikasi', $tl['ringkasan']['label'], 'Ringkasan harus mengikuti keadaan sekarang.');
    }

    public function test_halaman_monitoring_menampilkan_ringkasan(): void
    {
        $pptk = $this->user('pptk');
        $sp = $this->sp();
        $this->npd($sp, 'Draft NPD - PPTK');

        $this->actingAs($pptk)->get(route('surat-perintah.monitoring'))
            ->assertOk()
            ->assertSee('NPD telah dibuat oleh PPTK namun belum diserahkan kepada BPP atau dalam tahap revisi.');
    }

    public function test_titik_pertama_jadi_spj_diterima_bila_pengajuannya_hanya_transport(): void
    {
        $hanyaTransport = $this->sp(['pengajuan' => 'Transport']);
        $reimburse = $this->sp([
            'nomor_sp' => '087/PW.02.01/Sekre (Reimburse)',
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'pengajuan' => 'Transport',
        ]);
        $campuran = $this->sp(['nomor_sp' => '088/PW.02.01/Sekre', 'pengajuan' => 'Uang Harian, Transport']);

        $service = app(SuratPerintahTimelineService::class);

        $this->assertSame('SPJ Diterima', $service->untukSatu($hanyaTransport)['titik'][0]['label']);
        $this->assertSame('SPJ Diterima', $service->untukSatu($reimburse)['titik'][0]['label']);
        $this->assertSame('SP Diterima', $service->untukSatu($campuran)['titik'][0]['label']);
    }

    public function test_timeline_tampil_di_halaman_monitoring_termasuk_untuk_tamu(): void
    {
        $sp = $this->sp();
        $this->npd($sp);

        // Monitoring SP publik: tanpa login pun timeline ikut tersaji.
        $this->get(route('surat-perintah.monitoring'))
            ->assertOk()
            ->assertSee($sp->nomor_sp)
            ->assertSee('Timeline Progres')
            ->assertSee('Diperiksa BPP')
            ->assertSee('Persetujuan NPD &amp; Proses IBC', false);
    }

    public function test_sp_tanpa_npd_ditandai_belum_dibuat(): void
    {
        $sp = $this->sp();

        $tl = app(SuratPerintahTimelineService::class)->untukSatu($sp);

        $this->assertFalse($tl['ada_npd']);
        $this->assertSame('', $tl['nomor_npd']);
        $this->assertTrue($tl['titik'][0]['tercapai'], 'SP yang sudah masuk selalu terhitung diterima.');
        $this->assertFalse($tl['titik'][1]['tercapai']);

        $this->get(route('surat-perintah.monitoring'))->assertOk()->assertSee('(NPD belum dibuat)');
    }
}
