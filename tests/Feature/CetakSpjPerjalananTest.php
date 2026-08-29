<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cetak SPJ Perjalanan Dinas — layanan mandiri tanpa login.
 * Port dari cetakSPJPerjalanan() di gas-lama/CodePerjalanan.gs: empat jawaban
 * berjenjang, lalu tombol Daftar Penerimaan & SPD Rampung bila sudah Selesai.
 */
class CetakSpjPerjalananTest extends TestCase
{
    use RefreshDatabase;

    private function sp(string $nomor = '087/PW.02.01/Sekre'): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => $nomor,
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
            'dipantau' => true,
            'sumber_npd' => true,
        ]);
    }

    /** Penomoran berurutan, bukan acak: dua NPD dalam satu test tidak boleh bernomor sama. */
    private int $urutNpd = 0;

    private function npd(SuratPerintah $sp, string $status, string $jenis = 'pd'): Npd
    {
        $this->urutNpd++;

        $master = MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Uji SPJ',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji SPJ',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Uji SPJ',
            'kode_rekening' => sprintf('5.1.02.05.01.%04d', $this->urutNpd),
            'rekening' => 'Belanja Uji SPJ',
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);

        $npd = Npd::create([
            'jenis' => $jenis,
            'master_anggaran_id' => $master->id,
            'surat_perintah_id' => $sp->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_lengkap' => sprintf('%02d/NPD-Keu.1.IBC/7/2026', $this->urutNpd),
            'tanggal_npd' => '2026-07-22',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 3_000_000,
            'terbilang' => 'tiga juta rupiah',
            'status' => $status,
            'detail_json' => ['uraian_sp' => 'Reviu LKPD'],
        ]);

        $npd->tim()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Auditor Ahli Muda',
            'nip' => '199001012010011001',
            'rekening' => '100200300',
            'bbm_liter' => 0,
            'bbm_tarif' => 0,
            'tol' => 150_000,
            'tiket' => 0,
            'representatif' => 0,
            'is_penerima' => true,
        ]);

        return $npd;
    }

    // ---------------- Empat jawaban berjenjang ----------------

    public function test_halaman_dapat_dibuka_tanpa_login(): void
    {
        $this->get(route('cetak-spj.index'))
            ->assertOk()
            ->assertSee('Cetak SPJ Perjalanan Dinas')
            ->assertSee('Nomor Surat Perintah');
    }

    public function test_nomor_sp_tidak_dikenal_ditolak(): void
    {
        $this->get(route('cetak-spj.index', ['nomor_sp' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Surat Perintah tidak ditemukan.');
    }

    public function test_sp_tanpa_npd_tertaut_memberi_pesan_belum_dibuat(): void
    {
        $sp = $this->sp();

        $this->get(route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]))
            ->assertOk()
            ->assertSee('Nota Pencairan Dana terkait Surat Perintah tersebut belum dibuat.');
    }

    public function test_npd_belum_selesai_memberi_pesan_sedang_proses(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Verifikasi - Verifikator');

        $this->get(route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]))
            ->assertOk()
            ->assertSee('Nota Pencairan Dana sedang dalam proses.')
            // Frasa "SPD Rampung" juga muncul di kalimat pengantar halaman,
            // jadi yang diperiksa adalah tidak adanya TAUTAN unduhannya.
            ->assertDontSee(route('cetak-spj.spd', $npd), false)
            ->assertDontSee(route('cetak-spj.daftar', $npd), false);
    }

    public function test_npd_selesai_menampilkan_rincian_anggota_dan_tombol_dokumen(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Selesai');

        $this->get(route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]))
            ->assertOk()
            ->assertSee($npd->nomor_lengkap)
            ->assertSee('Perjalanan Dinas')
            ->assertSee('Budi Santoso')
            ->assertSee('Daftar Penerimaan')
            ->assertSee('SPD Rampung')
            // Nominal per anggota dihitung dari komponennya (tol 150.000).
            ->assertSee('150.000');
    }

    public function test_npd_transport_ditandai_sebagai_reimburse(): void
    {
        $sp = $this->sp();
        $this->npd($sp, 'Selesai', 'tr');

        $this->get(route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]))
            ->assertOk()
            ->assertSee('Reimburse Transportasi');
    }

    public function test_hanya_npd_selesai_yang_ditampilkan_saat_sebagian_masih_proses(): void
    {
        $sp = $this->sp();
        $selesai = $this->npd($sp, 'Selesai');
        $proses = $this->npd($sp, 'Draft NPD - BPP');

        $this->get(route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]))
            ->assertOk()
            ->assertSee($selesai->nomor_lengkap)
            ->assertDontSee($proses->nomor_lengkap);
    }

    // ---------------- Pengamanan unduhan ----------------

    public function test_dokumen_hanya_dilayani_untuk_npd_selesai_yang_tertaut_sp(): void
    {
        $sp = $this->sp();
        $selesai = $this->npd($sp, 'Selesai');
        $proses = $this->npd($sp, 'Draft NPD - BPP');

        $this->get(route('cetak-spj.daftar', $selesai))->assertOk();
        $this->get(route('cetak-spj.spd', $selesai))->assertOk();

        // Belum selesai: tidak boleh diunduh publik walau nomornya ditebak.
        $this->get(route('cetak-spj.daftar', $proses))->assertNotFound();
        $this->get(route('cetak-spj.spd', $proses))->assertNotFound();
    }

    public function test_npd_selesai_tanpa_tautan_sp_tidak_bisa_dicetak_publik(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Selesai');
        $npd->update(['surat_perintah_id' => null]);

        $this->get(route('cetak-spj.daftar', $npd))->assertNotFound();
    }

    public function test_jenis_npd_selain_perjalanan_dinas_ditolak(): void
    {
        $sp = $this->sp();
        $npd = $this->npd($sp, 'Selesai');
        $npd->update(['jenis' => 'bj']);

        $this->get(route('cetak-spj.daftar', $npd))->assertNotFound();
    }

    // ---------------- Menu ----------------

    public function test_submenu_muncul_untuk_role_yang_login_maupun_tamu(): void
    {
        $pptk = User::create([
            'username' => 'pptk-cetakspj',
            'nama' => 'PPTK Cetak SPJ',
            'role' => 'pptk',
            'password' => 'rahasia-uji',
        ]);

        $this->actingAs($pptk)->get(route('surat-perintah.monitoring'))
            ->assertOk()
            ->assertSee(route('cetak-spj.index'), false);

        // Tamu (role layanan) juga mendapat submenu ini, sama seperti GAS.
        $this->get(route('surat-perintah.monitoring'))
            ->assertOk()
            ->assertSee(route('cetak-spj.index'), false);
    }
}
