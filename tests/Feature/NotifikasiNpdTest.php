<?php

namespace Tests\Feature;

use App\Helpers\NomorWhatsapp;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdNotifikasi;
use App\Models\NpdPenerima;
use App\Models\NpdTim;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotifikasiNpdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aksi "Kirim Notifikasi" di Data NPD: menyiapkan pesan WhatsApp pencairan
 * untuk satu penerima tujuan transfer, lewat deep link wa.me (petugas yang
 * menekan Kirim di WhatsApp-nya sendiri).
 */
class NotifikasiNpdTest extends TestCase
{
    use RefreshDatabase;

    private MasterAnggaran $master;

    /** Nomor NPD unik pada tabel npd, jadi tiap NPD uji butuh urutannya sendiri. */
    private int $urut = 0;

    private int $urutUser = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = MasterAnggaran::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'kode_program' => '6.01', 'program' => 'Program Penunjang',
            'kode_kegiatan' => '6.01.01', 'kegiatan' => 'Kegiatan Satu',
            'kode_sub_kegiatan' => '6.01.01.2.01', 'sub_kegiatan' => 'Sub Kegiatan Satu',
            'kode_rekening' => '5.1.02.01.01.0024', 'rekening' => 'Belanja Alat Tulis Kantor',
            'pagu' => 100_000_000, 'aktif' => true,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'uji-'.$role.'-'.(++$this->urutUser),
            'nama' => 'Petugas '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    private function npd(string $status = 'Selesai', array $atribut = []): Npd
    {
        return Npd::create(array_merge([
            'jenis' => 'bj',
            'master_anggaran_id' => $this->master->id,
            'keu' => '2',
            'bulan' => 7,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nomor_lengkap' => '900/'.(1234 + ++$this->urut).'/NPD/ITDA',
            'tanggal_npd' => config('anggaran.tahun_aktif').'-07-10',
            'nominal' => 1_500_000,
            'terbilang' => 'satu juta lima ratus ribu rupiah',
            'status' => $status,
        ], $atribut));
    }

    private function pegawai(array $atribut = []): Pegawai
    {
        return Pegawai::create(array_merge([
            'nama' => 'Budi Santoso, S.E.',
            'nip' => '198504102010011005',
            'jabatan' => 'Auditor Muda',
            'bidang' => 'Irban Wilayah I',
            'status_kepegawaian' => Pegawai::STATUS_PNS,
            'nomor_handphone' => '0812-3456-7890',
            'aktif' => true,
        ], $atribut));
    }

    private function suratPerintah(string $tujuanTransfer): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '800/456/ITDA',
            'tanggal_sp' => config('anggaran.tahun_aktif').'-07-01',
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'unit_kerja' => 'Irban Wilayah I',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => $tujuanTransfer,
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '1 - 2 Juli '.config('anggaran.tahun_aktif'),
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
        ]);
    }

    /* ---------------- Normalisasi nomor ---------------- */

    public function test_nomor_handphone_dinormalkan_ke_bentuk_wa_me(): void
    {
        // Nomor yang sama, cara ketik berbeda-beda, harus jadi satu tautan.
        foreach (['0812-3456-7890', '+62 812 3456 7890', '81234567890', '0062 812 3456 7890'] as $ketikan) {
            $this->assertSame('6281234567890', NomorWhatsapp::normalisasi($ketikan), "gagal untuk: {$ketikan}");
        }

        // Kosong dan potongan angka yang mustahil jadi nomor ditolak.
        $this->assertNull(NomorWhatsapp::normalisasi(null));
        $this->assertNull(NomorWhatsapp::normalisasi(''));
        $this->assertNull(NomorWhatsapp::normalisasi('123'));
    }

    /* ---------------- Isi pesan ---------------- */

    public function test_pesan_menyebut_nomor_sp_hanya_bila_npd_punya_sp(): void
    {
        $pegawai = $this->pegawai();

        $sp = $this->suratPerintah($pegawai->nama);

        $denganSp = $this->npd('Selesai', ['surat_perintah_id' => $sp->id, 'nomor_lengkap' => '900/1234/NPD/ITDA']);
        $tanpaSp = $this->npd('Selesai', ['nomor_lengkap' => '900/1235/NPD/ITDA']);

        $service = app(NotifikasiNpdService::class);

        $this->assertSame(
            "Yth. Bapak/Ibu {$pegawai->nama}, NPD Nomor 900/1234/NPD/ITDA atas SP Nomor 800/456/ITDA "
            ."telah selesai ditransaksikan sebesar Rp1.500.000,00.\n\n"
            ."Dokumen Daftar Pembayaran dapat diunduh melalui:\ni-finance.web.id\n\n"
            ."Cara:\n"
            ."1. Masuk sebagai Pengguna Layanan (Tanpa Login)\n"
            ."2. Buka menu Surat Perintah\n"
            ."3. Pilih Cetak SPJ Perjalanan Dinas\n"
            ."4. Masukkan Nomor SP\n\n"
            .'Hatur nuhun Bapak/Ibu.',
            $service->pesan($denganSp)
        );

        // Tanpa SP, frasa "atas SP Nomor ..." hilang seluruhnya - bukan jadi "atas SP Nomor -".
        $this->assertStringNotContainsString('SP Nomor', $service->pesan($tanpaSp));
        $this->assertStringContainsString('NPD Nomor 900/1235/NPD/ITDA telah selesai ditransaksikan sebesar Rp1.500.000,00', $service->pesan($tanpaSp));

        // Penerima tunggal: tidak ada daftar rincian yang hanya mengulang total.
        $this->assertStringNotContainsString('Rincian Penerima', $service->pesan($denganSp));
    }

    /* ---------------- Penentuan tujuan ---------------- */

    public function test_tujuan_diambil_dari_tujuan_transfer_sp_walau_penerima_npd_orang_lain(): void
    {
        $tujuan = $this->pegawai(['nama' => 'Siti Aminah, S.T.', 'nip' => '199001012015012001', 'nomor_handphone' => '081100000001']);
        $lain = $this->pegawai(['nama' => 'Dedi Kurniawan', 'nip' => '198504102010011005', 'nomor_handphone' => '081100000002']);

        // Ditulis bebas tanpa gelar - dicocokkan lewat Pegawai::cariByNama.
        $sp = $this->suratPerintah('Siti Aminah');

        $npd = $this->npd('Selesai', ['surat_perintah_id' => $sp->id]);
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $lain->id, 'nama' => $lain->nama, 'bruto' => 1_500_000]);

        $hasil = app(NotifikasiNpdService::class)->tujuan($npd->fresh());

        $this->assertSame($tujuan->nama, $hasil['nama']);
        $this->assertSame('6281100000001', $hasil['nomor_wa']);
        $this->assertStringContainsString('SP 800/456/ITDA', $hasil['sumber']);
    }

    public function test_tanpa_sp_tujuan_jatuh_ke_penerima_utama_npd(): void
    {
        $pegawai = $this->pegawai(['nomor_handphone' => '081155550000']);
        $npd = $this->npd();
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

        $hasil = app(NotifikasiNpdService::class)->tujuan($npd->fresh());

        $this->assertSame($pegawai->nama, $hasil['nama']);
        $this->assertSame('6281155550000', $hasil['nomor_wa']);
    }

    public function test_perjalanan_dinas_memakai_anggota_bertanda_penerima(): void
    {
        $ketua = $this->pegawai(['nama' => 'Ketua Tim', 'nip' => '199001012015012002', 'nomor_handphone' => '081122223333']);
        $anggota = $this->pegawai(['nama' => 'Anggota Tim', 'nip' => '199001012015012003', 'nomor_handphone' => '081199998888']);

        $npd = $this->npd('Selesai', ['jenis' => 'pd']);
        // Anggota dimasukkan lebih dulu; yang menang tetap yang ditandai penerima.
        NpdTim::create(['npd_id' => $npd->id, 'pegawai_id' => $anggota->id, 'nama' => $anggota->nama, 'is_penerima' => false]);
        NpdTim::create(['npd_id' => $npd->id, 'pegawai_id' => $ketua->id, 'nama' => $ketua->nama, 'is_penerima' => true]);

        $hasil = app(NotifikasiNpdService::class)->tujuan($npd->fresh());

        $this->assertSame('Ketua Tim', $hasil['nama']);
        $this->assertSame('6281122223333', $hasil['nomor_wa']);
    }

    public function test_penerima_vendor_memakai_nomor_handphone_vendor(): void
    {
        $vendor = Vendor::create(['nama' => 'CV Sumber Rejeki', 'nomor_handphone' => '0822-1111-2222', 'aktif' => true]);

        $npd = $this->npd();
        NpdPenerima::create(['npd_id' => $npd->id, 'vendor_id' => $vendor->id, 'nama' => $vendor->nama, 'bruto' => 1_500_000]);

        $hasil = app(NotifikasiNpdService::class)->tujuan($npd->fresh());

        $this->assertSame('CV Sumber Rejeki', $hasil['nama']);
        $this->assertSame('vendor', $hasil['jenis_kontak']);
        $this->assertSame('6282211112222', $hasil['nomor_wa']);
    }

    /* ---------------- Otorisasi & status ---------------- */

    public function test_hanya_bpp_bp_dan_superadmin_yang_boleh_mengirim(): void
    {
        $pegawai = $this->pegawai();
        $npd = $this->npd();
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_BENDAHARA_PENGELUARAN, User::ROLE_BPP] as $role) {
            $this->actingAs($this->user($role))->getJson(route('npd.notifikasi.preview', $npd))->assertOk();
        }

        foreach ([User::ROLE_PPTK, User::ROLE_VERIFIKATOR] as $role) {
            $pengguna = $this->user($role);
            $this->actingAs($pengguna)->getJson(route('npd.notifikasi.preview', $npd))->assertForbidden();
            $this->actingAs($pengguna)->postJson(route('npd.notifikasi.store', $npd))->assertForbidden();
        }
    }

    public function test_npd_yang_belum_selesai_tidak_bisa_dinotifikasi(): void
    {
        $pegawai = $this->pegawai();

        foreach (['Draft NPD - PPTK', 'Verifikasi - Verifikator', 'NPD Disetujui - BPP', 'Dibatalkan'] as $status) {
            $npd = $this->npd($status);
            NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

            $this->actingAs($this->user(User::ROLE_BPP))
                ->postJson(route('npd.notifikasi.store', $npd))
                ->assertForbidden();
        }

        $this->assertSame(0, NpdNotifikasi::count());
    }

    /* ---------------- Alur di layar ---------------- */

    public function test_preview_memuat_tautan_wa_me_berisi_pesan(): void
    {
        $pegawai = $this->pegawai();
        $npd = $this->npd('Selesai', ['nomor_lengkap' => '900/1234/NPD/ITDA']);
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

        $data = $this->actingAs($this->user(User::ROLE_BPP))
            ->getJson(route('npd.notifikasi.preview', $npd))
            ->assertOk()
            ->json();

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $data['tautan']);
        $this->assertStringContainsString(rawurlencode('900/1234/NPD/ITDA'), $data['tautan']);
        $this->assertSame($pegawai->nama, $data['tujuan']['nama']);
        $this->assertSame([], $data['riwayat']);
    }

    public function test_nomor_kosong_menahan_pengiriman_dan_tidak_meninggalkan_jejak(): void
    {
        $pegawai = $this->pegawai(['nomor_handphone' => null]);
        $npd = $this->npd();
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

        $data = $this->actingAs($this->user(User::ROLE_BPP))
            ->getJson(route('npd.notifikasi.preview', $npd))
            ->assertOk()
            ->json();

        // Preview tetap terbuka (supaya bisa memberi tahu apa yang kurang),
        // tapi tanpa tautan - dan pencatatan ditolak.
        $this->assertNull($data['tautan']);
        $this->assertNull($data['tujuan']['nomor_wa']);

        $this->actingAs($this->user(User::ROLE_BPP))
            ->postJson(route('npd.notifikasi.store', $npd))
            ->assertStatus(422);

        $this->assertSame(0, NpdNotifikasi::count());
    }

    public function test_setiap_pengiriman_meninggalkan_jejak_apa_adanya(): void
    {
        $pegawai = $this->pegawai();
        $npd = $this->npd('Selesai', ['nomor_lengkap' => '900/1234/NPD/ITDA']);
        NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);

        $bpp = $this->user(User::ROLE_BPP);
        $this->actingAs($bpp)->postJson(route('npd.notifikasi.store', $npd))->assertOk();

        $jejak = NpdNotifikasi::firstOrFail();
        $this->assertSame($npd->id, $jejak->npd_id);
        $this->assertSame($bpp->id, $jejak->user_id);
        $this->assertSame('6281234567890', $jejak->tujuan_nomor);
        $this->assertSame($pegawai->nama, $jejak->tujuan_nama);
        $this->assertStringContainsString('900/1234/NPD/ITDA', $jejak->pesan);

        // Nomor pegawai berubah setelah itu - jejak lama TIDAK ikut berubah.
        $pegawai->update(['nomor_handphone' => '081999999999']);
        $this->assertSame('6281234567890', $jejak->fresh()->tujuan_nomor);

        // Kirim ulang boleh, tapi tercatat sebagai baris terpisah.
        $data = $this->actingAs($bpp)->postJson(route('npd.notifikasi.store', $npd))->assertOk()->json();
        $this->assertSame(2, NpdNotifikasi::count());
        $this->assertCount(2, $data['riwayat']);
    }

    public function test_tombol_hanya_muncul_di_data_npd_untuk_npd_selesai(): void
    {
        $pegawai = $this->pegawai();
        $selesai = $this->npd();
        $proses = $this->npd('Draft NPD - BPP');

        foreach ([$selesai, $proses] as $npd) {
            NpdPenerima::create(['npd_id' => $npd->id, 'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'bruto' => 1_500_000]);
        }

        $this->actingAs($this->user(User::ROLE_BPP))->get(route('npd.data'))->assertOk()
            // Kerangka tombol & jendela konfirmasinya benar-benar tercetak di halaman.
            ->assertSee('id="wa-mdl-ov"', false)
            ->assertSee('function tombolNotifikasi(r)', false)
            ->assertViewHas('baris', function ($baris) use ($selesai, $proses) {
                $peta = $baris->keyBy('id');

                return $peta[$selesai->id]['boleh_notifikasi'] === true
                    && $peta[$proses->id]['boleh_notifikasi'] === false;
            });

        // PPTK tidak pernah melihat tombolnya, sekalipun NPD-nya sudah Selesai.
        $this->actingAs($this->user(User::ROLE_PPTK))->get(route('npd.data'))->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->every(fn ($r) => $r['boleh_notifikasi'] === false));
    }

    /* ---------------- Multi-penerima (adopsi GAS #68 & #74) ---------------- */

    /**
     * Tim perjalanan dinas dengan nominal per anggota. Nominal diturunkan
     * dari paketnya, bukan diisi langsung - sama seperti saat NPD dibuat.
     */
    private function anggotaPd(Npd $npd, string $nama, string $noHp, float $tarif, int $hari = 1): NpdTim
    {
        $pegawai = $this->pegawai([
            'nama' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'nomor_handphone' => $noHp,
        ]);

        $tim = NpdTim::create([
            'npd_id' => $npd->id,
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama,
            'is_penerima' => false,
        ]);

        if ($tarif > 0) {
            $tim->paket()->create([
                'cluster' => 'A',
                'wilayah' => 'Kota Cimahi',
                'lama_hari' => $hari,
                'tarif_uh' => $tarif,
                'malam' => 0,
                'tarif_akom' => 0,
            ]);
        }

        return $tim;
    }

    public function test_perjalanan_dinas_menotifikasi_setiap_anggota_dengan_nominalnya_sendiri(): void
    {
        $npd = $this->npd('Selesai', ['jenis' => 'pd', 'nomor_lengkap' => '900/9001/NPD/ITDA']);
        $this->anggotaPd($npd, 'Anggota Satu', '081200000001', 200_000, 2);
        $this->anggotaPd($npd, 'Anggota Dua', '081200000002', 300_000);

        $daftar = app(NotifikasiNpdService::class)->daftarTujuan($npd->fresh()->load('tim.paket'));

        $this->assertCount(2, $daftar);
        $this->assertSame('Anggota Satu', $daftar[0]['nama']);
        $this->assertSame(400_000.0, $daftar[0]['nominal']);
        $this->assertSame('6281200000001', $daftar[0]['nomor_wa']);
        $this->assertSame(300_000.0, $daftar[1]['nominal']);
    }

    public function test_penerima_bernilai_nol_dilewati(): void
    {
        // Anggota yang tidak menerima apa pun tidak boleh dikirimi pesan
        // pencairan - itu cuma membingungkan.
        $npd = $this->npd('Selesai', ['jenis' => 'pd']);
        $this->anggotaPd($npd, 'Dapat Uang', '081200000003', 250_000);
        $this->anggotaPd($npd, 'Tidak Dapat', '081200000004', 0);

        $daftar = app(NotifikasiNpdService::class)->daftarTujuan($npd->fresh()->load('tim.paket'));

        $this->assertCount(1, $daftar);
        $this->assertSame('Dapat Uang', $daftar[0]['nama']);
    }

    public function test_pesan_multi_penerima_memuat_total_dan_rincian_bernomor(): void
    {
        $npd = $this->npd('Selesai', ['jenis' => 'pd', 'nomor_lengkap' => '900/9002/NPD/ITDA']);
        $this->anggotaPd($npd, 'Anggota Satu', '081200000005', 200_000);
        $this->anggotaPd($npd, 'Anggota Dua', '081200000006', 300_000);

        $service = app(NotifikasiNpdService::class);
        $npd = $npd->fresh()->load('tim.paket');
        $daftar = $service->daftarTujuan($npd);

        $pesan = $service->pesan($npd, $daftar[1]);

        // Disapa penerima kedua, tetapi nominal pembukanya TOTAL.
        $this->assertStringContainsString('Yth. Bapak/Ibu Anggota Dua,', $pesan);
        $this->assertStringContainsString('sebesar Rp500.000,00.', $pesan);
        $this->assertStringContainsString('Rincian Penerima adalah sebagai berikut:', $pesan);
        $this->assertStringContainsString("\n1. Anggota Satu sebesar Rp200.000,00", $pesan);
        $this->assertStringContainsString("\n2. Anggota Dua sebesar Rp300.000,00", $pesan);

        $this->assertSame(500_000.0, $service->totalTujuan($npd));
    }

    public function test_pratinjau_menyediakan_tautan_terpisah_untuk_tiap_penerima(): void
    {
        $npd = $this->npd('Selesai', ['jenis' => 'pd', 'nomor_lengkap' => '900/9003/NPD/ITDA']);
        $this->anggotaPd($npd, 'Anggota Satu', '081200000007', 200_000);
        $this->anggotaPd($npd, 'Anggota Dua', '081200000008', 300_000);

        $data = $this->actingAs($this->user(User::ROLE_BPP))
            ->getJson(route('npd.notifikasi.preview', $npd))
            ->assertOk()
            ->json();

        $this->assertCount(2, $data['penerima']);
        $this->assertSame('Rp 500.000,00', $data['total_teks']);
        $this->assertStringContainsString('wa.me/6281200000007', $data['penerima'][0]['tautan']);
        $this->assertStringContainsString('wa.me/6281200000008', $data['penerima'][1]['tautan']);
        $this->assertSame('Rp 300.000,00', $data['penerima'][1]['nominal_teks']);
    }

    public function test_jejak_dicatat_atas_nama_penerima_yang_dipilih(): void
    {
        $npd = $this->npd('Selesai', ['jenis' => 'pd', 'nomor_lengkap' => '900/9004/NPD/ITDA']);
        $this->anggotaPd($npd, 'Anggota Satu', '081200000009', 200_000);
        $this->anggotaPd($npd, 'Anggota Dua', '081200000010', 300_000);

        $this->actingAs($this->user(User::ROLE_BPP))
            ->postJson(route('npd.notifikasi.store', $npd), ['penerima' => 1])
            ->assertOk();

        $jejak = NpdNotifikasi::sole();

        $this->assertSame('Anggota Dua', $jejak->tujuan_nama);
        $this->assertSame('6281200000010', $jejak->tujuan_nomor);
    }

    public function test_indeks_penerima_di_luar_daftar_ditolak(): void
    {
        $npd = $this->npd('Selesai', ['jenis' => 'pd']);
        $this->anggotaPd($npd, 'Anggota Satu', '081200000011', 200_000);

        $this->actingAs($this->user(User::ROLE_BPP))
            ->postJson(route('npd.notifikasi.store', $npd), ['penerima' => 9])
            ->assertStatus(422);

        $this->assertSame(0, NpdNotifikasi::count());
    }
}
