<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Services\RekapPembayaranSpService as Rekap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rekapitulasi Pembayaran SP: per nomor SP, komponen (Uang Harian, Akomodasi,
 * Transport) tercentang bila NPD-nya memuat nilai komponen itu - sebagai
 * "proses" selama NPD belum Selesai, "selesai" setelahnya.
 */
class RekapPembayaranSpTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'rekap-'.$role.'-'.User::count(),
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function sp(string $nomor, array $override = []): SuratPerintah
    {
        return SuratPerintah::create(array_replace([
            'nomor_sp' => $nomor,
            'tanggal_sp' => '2026-07-20',
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Koordinator '.$nomor,
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Keterangan '.$nomor,
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'pengajuan' => 'Uang Harian, Akomodasi, Transport',
            'dipantau' => true,
            'sumber_npd' => true,
        ], $override));
    }

    private function anggaran(): MasterAnggaran
    {
        $this->urut++;

        return MasterAnggaran::create([
            'program' => 'Program Uji Rekap',
            'kegiatan' => 'Kegiatan Uji Rekap',
            'sub_kegiatan' => '6.01.02.1.01.0001 Sub Kegiatan Uji Rekap',
            'kode_rekening' => sprintf('5.1.02.05.01.%04d', $this->urut),
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    /**
     * NPD Perjalanan Dinas dengan satu anggota. $isi menentukan komponennya:
     * uang_harian (lama_hari x tarif_uh), akomodasi (malam x tarif_akom),
     * transport (tol).
     *
     * @param  array{uang_harian?: bool, akomodasi?: bool, transport?: bool}  $isi
     */
    private function npdPd(SuratPerintah $sp, string $status, array $isi): Npd
    {
        $npd = Npd::create([
            'jenis' => 'pd', 'master_anggaran_id' => $this->anggaran()->id, 'surat_perintah_id' => $sp->id,
            'keu' => '2', 'bulan' => 7, 'tahun' => 2026, 'tanggal_npd' => '2026-07-22',
            'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000, 'terbilang' => 'satu juta rupiah',
            'status' => $status,
            'nomor_lengkap' => $status === 'Selesai' ? sprintf('%02d/NPD-Keu.2.IBC/7/2026', $this->urut) : null,
        ]);

        $anggota = $npd->tim()->create([
            'nama' => 'Anggota '.$this->urut, 'bbm_liter' => 0, 'bbm_tarif' => 0,
            'tol' => ($isi['transport'] ?? false) ? 150_000 : 0, 'tiket' => 0, 'representatif' => 0, 'is_penerima' => true,
        ]);

        $anggota->paket()->create([
            'cluster' => 'A', 'wilayah' => 'Kabupaten Bandung',
            'lama_hari' => ($isi['uang_harian'] ?? false) ? 2 : 0, 'tarif_uh' => 200_000,
            'malam' => ($isi['akomodasi'] ?? false) ? 1 : 0, 'tarif_akom' => 500_000,
        ]);

        return $npd;
    }

    /** @return array<string, ?string> status tiap komponen untuk satu nomor SP */
    private function statusKomponen(string $nomorSp): array
    {
        $baris = app(Rekap::class)->baris()->firstWhere('nomor_sp', $nomorSp);
        $this->assertNotNull($baris, "SP {$nomorSp} tidak ada di rekap.");

        return array_map(fn (array $sel) => $sel['status'], $baris['pembayaran']);
    }

    public function test_sp_tanpa_npd_semua_kotaknya_kosong(): void
    {
        $this->sp('001/SP');

        $this->assertSame(
            [Rekap::UANG_HARIAN => null, Rekap::AKOMODASI => null, Rekap::TRANSPORT => null],
            $this->statusKomponen('001/SP')
        );
    }

    public function test_komponen_tercentang_sesuai_isi_npd_dan_membedakan_draft_dari_selesai(): void
    {
        $draft = $this->sp('010/SP');
        $this->npdPd($draft, 'Draft NPD - PPTK', ['uang_harian' => true, 'akomodasi' => true]);

        $selesai = $this->sp('011/SP');
        $this->npdPd($selesai, 'Selesai', ['uang_harian' => true, 'transport' => true]);

        // NPD yang masih Draft sudah mencentang, tetapi hanya komponen yang
        // benar-benar ada nilainya - Transport tetap kosong.
        $this->assertSame(
            [Rekap::UANG_HARIAN => Rekap::PROSES, Rekap::AKOMODASI => Rekap::PROSES, Rekap::TRANSPORT => null],
            $this->statusKomponen('010/SP')
        );

        $this->assertSame(
            [Rekap::UANG_HARIAN => Rekap::SELESAI, Rekap::AKOMODASI => null, Rekap::TRANSPORT => Rekap::SELESAI],
            $this->statusKomponen('011/SP')
        );
    }

    public function test_setiap_status_sebelum_selesai_dihitung_sebagai_proses(): void
    {
        foreach (['Draft NPD - PPTK', 'Draft NPD - BPP', 'Verifikasi - Verifikator', 'NPD Disetujui - BPP'] as $i => $status) {
            $nomor = '02'.$i.'/SP';
            $this->npdPd($this->sp($nomor), $status, ['uang_harian' => true]);

            $this->assertSame(Rekap::PROSES, $this->statusKomponen($nomor)[Rekap::UANG_HARIAN], "Status \"{$status}\" seharusnya tercentang sebagai proses.");
        }
    }

    public function test_npd_dibatalkan_tidak_mencentang_apa_pun(): void
    {
        $sp = $this->sp('030/SP');
        $this->npdPd($sp, 'Dibatalkan', ['uang_harian' => true, 'akomodasi' => true, 'transport' => true]);

        $this->assertSame(
            [Rekap::UANG_HARIAN => null, Rekap::AKOMODASI => null, Rekap::TRANSPORT => null],
            $this->statusKomponen('030/SP')
        );
    }

    public function test_sp_asli_dan_duplikatnya_digabung_jadi_satu_baris(): void
    {
        $asli = $this->sp('040/SP');
        $duplikat = $this->sp('040/SP', ['duplikat_ke' => 1, 'tujuan_transfer' => 'Koordinator Duplikat']);

        // Uang harian & akomodasi sudah Selesai lewat SP asli; transport
        // menyusul lewat duplikatnya dan masih Draft.
        $this->npdPd($asli, 'Selesai', ['uang_harian' => true, 'akomodasi' => true]);
        $this->npdPd($duplikat, 'Draft NPD - BPP', ['transport' => true]);

        $semua = app(Rekap::class)->baris();
        $this->assertCount(1, $semua->where('nomor_sp', '040/SP'));

        $baris = $semua->firstWhere('nomor_sp', '040/SP');
        $this->assertSame(2, $baris['jumlah_npd']);
        // Identitas baris diambil dari SP aslinya, bukan duplikat.
        $this->assertSame('Koordinator 040/SP', $baris['koordinator']);

        $this->assertSame(
            [Rekap::UANG_HARIAN => Rekap::SELESAI, Rekap::AKOMODASI => Rekap::SELESAI, Rekap::TRANSPORT => Rekap::PROSES],
            $this->statusKomponen('040/SP')
        );
    }

    public function test_selesai_menang_bila_satu_komponen_dibayar_lewat_dua_npd(): void
    {
        $asli = $this->sp('050/SP');
        $duplikat = $this->sp('050/SP', ['duplikat_ke' => 1]);

        $this->npdPd($asli, 'Draft NPD - PPTK', ['uang_harian' => true]);
        $this->npdPd($duplikat, 'Selesai', ['uang_harian' => true]);

        $this->assertSame(Rekap::SELESAI, $this->statusKomponen('050/SP')[Rekap::UANG_HARIAN]);
    }

    public function test_npd_kontribusi_diklat_mode_perjalanan_ikut_dihitung(): void
    {
        $sp = $this->sp('060/SP');

        $npd = Npd::create([
            'jenis' => 'kd', 'mode_kd' => 'perjalanan', 'master_anggaran_id' => $this->anggaran()->id,
            'surat_perintah_id' => $sp->id, 'keu' => '2', 'bulan' => 8, 'tahun' => 2026, 'tanggal_npd' => '2026-08-06',
            'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 2_350_000, 'terbilang' => '-', 'status' => 'Draft NPD - PPTK',
        ]);
        $npd->peserta()->create([
            'nama' => 'Peserta Diklat', 'hari_uh' => 5, 'tarif_uh' => 400_000,
            'volume_akomodasi' => 0, 'tarif_akomodasi' => 600_000, 'transport' => 350_000,
        ]);

        $this->assertSame(
            [Rekap::UANG_HARIAN => Rekap::PROSES, Rekap::AKOMODASI => null, Rekap::TRANSPORT => Rekap::PROSES],
            $this->statusKomponen('060/SP')
        );
    }

    public function test_halaman_menampilkan_kolom_dan_tanda_centang_yang_dibedakan(): void
    {
        $this->sp('070/SP');
        $this->npdPd($this->sp('071/SP'), 'Draft NPD - PPTK', ['uang_harian' => true]);
        $selesai = $this->npdPd($this->sp('072/SP'), 'Selesai', ['uang_harian' => true]);

        $halaman = $this->actingAs($this->user('pptk'))->get(route('surat-perintah.rekap-pembayaran'))->assertOk();

        $halaman->assertSeeInOrder([
            '>Nomor SP</th>', '>Unit Kerja</th>', '>Koordinator Pembayaran</th>', '>Keterangan</th>', '>Pembayaran</th>',
            '>Uang Harian</th>', '>Akomodasi</th>', '>Transport</th>',
        ], false);
        $halaman->assertSee('Koordinator 072/SP');
        $halaman->assertSee('Keterangan 072/SP');

        $isi = $halaman->getContent();
        // 3 SP x 3 komponen = 9 kotak di tabel: 1 selesai, 1 proses, 7 kosong.
        $this->assertSame(1, substr_count($isi, 'data-status="selesai"'));
        $this->assertSame(1, substr_count($isi, 'data-status="proses"'));
        $this->assertSame(7, substr_count($isi, 'data-status="kosong"'));
        // Kedua centang memakai kelas berbeda, jadi tampilannya bisa dibedakan.
        $this->assertStringContainsString('class="rk-cek selesai" data-komponen="uang_harian"', $isi);
        $this->assertStringContainsString('class="rk-cek proses" data-komponen="uang_harian"', $isi);
        // Nomor NPD-nya tersedia sebagai keterangan kotak.
        $halaman->assertSee($selesai->nomor_lengkap.' (Selesai)');
    }

    public function test_sub_menu_tampil_di_sidebar_dan_hanya_untuk_pemegang_kuncinya(): void
    {
        $this->actingAs($this->user('bpp'))->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('>Rekapitulasi Pembayaran SP</a>', false)
            // Urutan sub menu: Input SP, Data SP, Monitoring SP, lalu Rekapitulasi.
            ->assertSeeInOrder(['>Input SP</a>', '>Data SP</a>', '>Monitoring SP</a>', '>Rekapitulasi Pembayaran SP</a>', '>Cetak SPJ Perjalanan Dinas</a>'], false)
            ->assertSee(route('surat-perintah.rekap-pembayaran'), false);

        // Pembacanya sama dengan Data SP: Perencanaan dan Kepegawaian tidak ikut.
        foreach (['perencanaan', 'kepegawaian', 'pengelola_spj'] as $role) {
            $aktor = $this->user($role);
            $this->actingAs($aktor)->get(route('surat-perintah.rekap-pembayaran'))->assertForbidden();
            $this->actingAs($aktor)->get(route('dashboard.index'))
                ->assertDontSee(route('surat-perintah.rekap-pembayaran'), false);
        }

        // Pengawas (baca-saja) boleh membukanya.
        $this->actingAs($this->user('pengawas'))->get(route('surat-perintah.rekap-pembayaran'))->assertOk();
    }
}
