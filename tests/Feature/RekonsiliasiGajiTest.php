<?php

namespace Tests\Feature;

use App\Models\AnggotaKeluarga;
use App\Models\GajiInduk;
use App\Models\Pegawai;
use App\Models\RekonsiliasiKunci;
use App\Models\RekonsiliasiKunciBaris;
use App\Models\TunjanganKeluarga;
use App\Models\User;
use App\Services\RekonsiliasiGajiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Rekonsiliasi Gaji Induk.
 *
 * Dua hal yang dijaga ketat di sini: (1) angka potensi kelebihan
 * pembayarannya benar, karena itu yang jadi dasar penagihan ke pegawai, dan
 * (2) log status tidak bisa disentuh selain oleh superadmin - itu seluruh
 * alasan fitur ini ada.
 */
class RekonsiliasiGajiTest extends TestCase
{
    use RefreshDatabase;

    private const POKOK = 5000000.0;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'rk-'.$role.'-'.uniqid(),
            'nama' => 'Uji '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    /**
     * Pegawai beserta status Tunjangan Keluarga-nya.
     *
     * @param  int  $anak  jumlah anak yang berhak (lahir tahun ini, jelas di bawah batas usia)
     */
    private function pegawai(string $nama, string $nip, bool $pasangan, int $anak = 0): Pegawai
    {
        $pegawai = Pegawai::create([
            'nama' => $nama, 'nip' => $nip, 'jabatan' => 'Auditor',
            'bidang' => 'Irban I', 'aktif' => true,
        ]);

        $keluarga = TunjanganKeluarga::create(['pegawai_id' => $pegawai->id]);

        if ($pasangan) {
            AnggotaKeluarga::create([
                'tunjangan_keluarga_id' => $keluarga->id, 'hubungan' => 'pasangan',
                'nama' => 'Pasangan '.$nama, 'status_tunjangan' => true,
            ]);
        }

        for ($i = 1; $i <= $anak; $i++) {
            AnggotaKeluarga::create([
                'tunjangan_keluarga_id' => $keluarga->id, 'hubungan' => 'anak',
                'nama' => 'Anak '.$i.' '.$nama,
                'tanggal_lahir' => '2020-01-0'.$i,
                'status_tunjangan' => true,
            ]);
        }

        return $pegawai;
    }

    /** Baris Gaji Induk dengan nominal tunjangan sesuai jumlah jiwa yang DIBAYAR. */
    private function gaji(string $nip, string $nama, int $pasangan, int $anak, int $bulan = 1): GajiInduk
    {
        return GajiInduk::create([
            'bulan' => $bulan, 'tahun' => 2026,
            'nama_pegawai' => $nama, 'nip' => $nip,
            'golongan' => 'III/a', 'pppk_pns' => 'PNS', 'nama_jabatan' => 'Auditor',
            'belanja_gaji_pokok' => self::POKOK,
            'perhitungan_suami_istri' => $pasangan * 0.10 * self::POKOK,
            'perhitungan_anak' => $anak * 0.02 * self::POKOK,
        ]);
    }

    private function service(): RekonsiliasiGajiService
    {
        return app(RekonsiliasiGajiService::class);
    }

    /*
     * ---------------- Tanggal penggajian ----------------
     */

    public function test_tanggal_penggajian_melewati_akhir_pekan_dan_libur_nasional(): void
    {
        // 1 Januari 2026 = Kamis, tetapi Tahun Baru -> geser ke Jumat 2 Jan.
        $this->assertSame('2026-01-02', $this->service()->tanggalPenggajian(1, 2026)->toDateString());

        // 1 Maret 2026 = Minggu -> geser ke Senin 2 Maret.
        $this->assertSame('2026-03-02', $this->service()->tanggalPenggajian(3, 2026)->toDateString());

        // 1 Agustus 2026 = Sabtu -> geser ke Senin 3 Agustus.
        $this->assertSame('2026-08-03', $this->service()->tanggalPenggajian(8, 2026)->toDateString());

        // 1 Oktober 2026 = Kamis biasa -> tidak digeser.
        $this->assertSame('2026-10-01', $this->service()->tanggalPenggajian(10, 2026)->toDateString());
    }

    public function test_libur_tambahan_di_config_ikut_diperhitungkan(): void
    {
        // Libur yang bergerak tiap tahun memang harus diisi manual.
        config(['gaji_tunjangan.hari_libur' => ['2026-10-01' => 'Cuti Bersama Uji']]);

        $this->assertSame('2026-10-02', $this->service()->tanggalPenggajian(10, 2026)->toDateString());
    }

    /*
     * ---------------- Pembacaan status penggajian ----------------
     */

    public function test_status_penggajian_dibalik_dari_nominal_tunjangan(): void
    {
        $service = $this->service();

        // Tunjangan pasangan 10% dan anak 2% per anak dari gaji pokok.
        $this->assertSame('K/2', $service->statusPenggajian($this->gaji('1', 'A', 1, 2))['status']);
        $this->assertSame('TK/0', $service->statusPenggajian($this->gaji('2', 'B', 0, 0))['status']);
        $this->assertSame('TK/1', $service->statusPenggajian($this->gaji('3', 'C', 0, 1))['status']);

        // Tanpa baris gaji sama sekali.
        $this->assertFalse($service->statusPenggajian(null)['ada']);
        $this->assertSame('-', $service->statusPenggajian(null)['status']);
    }

    public function test_penggajian_tiga_anak_tidak_dipangkas_jadi_dua(): void
    {
        // Membatasi di angka dua justru menyembunyikan kelebihan bayarnya.
        $this->assertSame('K/3', $this->service()->statusPenggajian($this->gaji('4', 'D', 1, 3))['status']);
    }

    /*
     * ---------------- Perhitungan potensi kelebihan ----------------
     */

    public function test_potensi_kelebihan_satu_anak_sesuai_contoh_kantor(): void
    {
        // Log K/1 tetapi penggajian K/2: (2% x 5.000.000) + 72.420.
        $this->assertEqualsWithDelta(
            100000 + 72420,
            $this->service()->potensiKelebihan(self::POKOK, 0, 1),
            0.01,
        );
    }

    public function test_potensi_kelebihan_selisih_pasangan(): void
    {
        // (10% x 5.000.000) + 72.420.
        $this->assertEqualsWithDelta(
            500000 + 72420,
            $this->service()->potensiKelebihan(self::POKOK, 1, 0),
            0.01,
        );
    }

    public function test_potensi_kelebihan_menjumlah_pasangan_dan_anak(): void
    {
        // 1 pasangan + 2 anak = 3 jiwa, berasnya 3 x 72.420.
        $this->assertEqualsWithDelta(
            500000 + 200000 + (3 * 72420),
            $this->service()->potensiKelebihan(self::POKOK, 1, 2),
            0.01,
        );
    }

    public function test_penggajian_membayar_lebih_sedikit_bukan_kelebihan(): void
    {
        // Kekurangan bayar tidak diklaim sebagai potensi kelebihan.
        $this->assertSame(0.0, $this->service()->potensiKelebihan(self::POKOK, 0, 0));
    }

    /*
     * ---------------- Penguncian & isi tabel ----------------
     */

    public function test_kunci_memotret_status_seluruh_pegawai_aktif(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $this->pegawai('BUDI', '222', pasangan: false, anak: 0);
        Pegawai::create(['nama' => 'PENSIUN', 'nip' => '333', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => false]);

        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));

        $this->assertSame('2026-01-02', $kunci->tanggal_penggajian->toDateString());

        $status = $kunci->baris()->orderBy('nama')->pluck('status_tk', 'nama')->all();

        // Pegawai non-aktif tidak ikut dipotret.
        $this->assertSame(['ANI' => 'K/1', 'BUDI' => 'TK/0'], $status);
    }

    public function test_tabel_membandingkan_log_dengan_penggajian_dan_menaksir_kelebihan(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $this->pegawai('BUDI', '222', pasangan: true, anak: 2);

        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));

        // ANI dibayar sebagai K/2 padahal berhak K/1 -> kelebihan satu anak.
        $this->gaji('111', 'ANI', 1, 2);
        // BUDI dibayar tepat.
        $this->gaji('222', 'BUDI', 1, 2);

        $baris = collect($this->service()->baris($kunci))->keyBy('nama');

        $this->assertSame('K/1', $baris['ANI']['status_tk']);
        $this->assertSame('K/2', $baris['ANI']['status_penggajian']);
        $this->assertSame(1, $baris['ANI']['selisih_jiwa']);
        $this->assertEqualsWithDelta(100000 + 72420, $baris['ANI']['kelebihan'], 0.01);

        $this->assertSame('K/2', $baris['BUDI']['status_penggajian']);
        $this->assertSame(0, $baris['BUDI']['selisih_jiwa']);
        $this->assertSame(0.0, $baris['BUDI']['kelebihan']);
    }

    public function test_pegawai_tanpa_baris_gaji_tidak_dihitung_kelebihan(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));

        $baris = $this->service()->baris($kunci)[0];

        $this->assertFalse($baris['ada_gaji']);
        $this->assertSame('-', $baris['status_penggajian']);
        $this->assertSame(0.0, $baris['kelebihan']);
    }

    public function test_log_tidak_ikut_berubah_saat_data_tunjangan_keluarga_disunting(): void
    {
        // Inti fitur ini: potret awal bulan harus kebal terhadap perubahan
        // data setelahnya, supaya status tidak bisa dirapikan belakangan.
        $pegawai = $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));
        $this->gaji('111', 'ANI', 1, 2);

        // Anak kedua ditambahkan BELAKANGAN agar seolah-olah sudah berhak.
        AnggotaKeluarga::create([
            'tunjangan_keluarga_id' => TunjanganKeluarga::where('pegawai_id', $pegawai->id)->value('id'),
            'hubungan' => 'anak', 'nama' => 'Anak Susulan',
            'tanggal_lahir' => '2021-05-05', 'status_tunjangan' => true,
        ]);

        $baris = $this->service()->baris($kunci->fresh())[0];

        $this->assertSame('K/1', $baris['status_tk'], 'Log harus tetap K/1 sesuai potret awal bulan.');
        $this->assertEqualsWithDelta(100000 + 72420, $baris['kelebihan'], 0.01);
    }

    /*
     * ---------------- Alur kerja kantor ----------------
     */

    public function test_periode_bisa_dikunci_sebelum_bulannya_berjalan(): void
    {
        // Alur sesungguhnya: SPM gaji Oktober dibuat 28 September, jadi data
        // kepegawaian dikunci hari itu juga - sebelum bulannya mulai dan
        // sebelum berkas Gaji Oktober diunggah.
        $this->travelTo('2026-09-28 10:00:00');

        $pegawai = $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $admin = $this->user(User::ROLE_SUPERADMIN);

        $this->actingAs($admin)
            ->post(route('gaji-tunjangan.rekonsiliasi.kunci'), ['bulan' => 10, 'tahun' => 2026])
            ->assertSessionHasNoErrors();

        $kunci = RekonsiliasiKunci::where('bulan', 10)->firstOrFail();

        // Potretnya diambil 28 September, tetapi acuan kelayakan usia anak
        // tetap tanggal penggajian Oktober.
        $this->assertSame('2026-09-28', $kunci->dikunci_at->toDateString());
        $this->assertSame('2026-10-01', $kunci->tanggal_penggajian->toDateString());
        $this->assertSame('K/1', $kunci->baris()->firstOrFail()->status_tk);

        // 30 September anak kedua ditambahkan - setelah dikunci.
        $this->travelTo('2026-09-30 09:00:00');
        AnggotaKeluarga::create([
            'tunjangan_keluarga_id' => TunjanganKeluarga::where('pegawai_id', $pegawai->id)->value('id'),
            'hubungan' => 'anak', 'nama' => 'Anak Susulan',
            'tanggal_lahir' => '2022-02-02', 'status_tunjangan' => true,
        ]);

        // 1 Oktober berkas Gaji Oktober diunggah, membayar dua anak.
        $this->travelTo('2026-10-01 08:00:00');
        $this->gaji('111', 'ANI', 1, 2, bulan: 10);

        $baris = $this->service()->baris($kunci->fresh())[0];

        $this->assertSame('K/1', $baris['status_tk'], 'Log harus tetap kondisi 28 September.');
        $this->assertSame('K/2', $baris['status_penggajian']);
        $this->assertEqualsWithDelta(100000 + 72420, $baris['kelebihan'], 0.01);
    }

    public function test_rekonsiliasi_bulan_lampau_memakai_data_tunjangan_keluarga_hari_ini(): void
    {
        // Pengunggahan susulan Januari s.d. September: potretnya baru dibuat
        // sekarang, jadi memakai data Tunjangan Keluarga SAAT INI. Batas usia
        // anak tetap dinilai pada tanggal penggajian bulan itu.
        $this->travelTo('2026-09-15 10:00:00');

        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $this->gaji('111', 'ANI', 1, 2, bulan: 1);

        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));

        $this->assertSame('2026-01-02', $kunci->tanggal_penggajian->toDateString());
        $this->assertSame('2026-09-15', $kunci->dikunci_at->toDateString());

        $baris = $this->service()->baris($kunci)[0];
        $this->assertSame('K/1', $baris['status_tk']);
        $this->assertEqualsWithDelta(100000 + 72420, $baris['kelebihan'], 0.01);
    }

    public function test_halaman_memperingatkan_bila_periode_dikunci_terlambat(): void
    {
        $this->travelTo('2026-09-15 10:00:00');

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.rekonsiliasi', ['bulan' => 1, 'tahun' => 2026]))
            ->assertOk();

        $halaman->assertSee('Perhatian');

        // Periode yang tanggal penggajiannya belum lewat tidak diperingati.
        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.rekonsiliasi', ['bulan' => 10, 'tahun' => 2026]))
            ->assertOk()
            ->assertDontSee('Perhatian');
    }

    /*
     * ---------------- Otorisasi ----------------
     */

    public function test_halaman_hanya_untuk_superadmin_dan_bendahara_pengeluaran(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_BENDAHARA_PENGELUARAN] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('gaji-tunjangan.rekonsiliasi'))->assertOk();
        }

        foreach ([User::ROLE_PPTK, User::ROLE_VERIFIKATOR, 'kasubbag'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('gaji-tunjangan.rekonsiliasi'))->assertForbidden();
        }
    }

    public function test_hanya_superadmin_yang_boleh_mengunci_menyunting_dan_menghapus(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));
        $baris = $kunci->baris()->firstOrFail();

        // Bendahara Pengeluaran boleh melihat, tetapi tidak menyentuh log.
        $bp = $this->user(User::ROLE_BENDAHARA_PENGELUARAN);

        $this->actingAs($bp)->post(route('gaji-tunjangan.rekonsiliasi.kunci'), ['bulan' => 2, 'tahun' => 2026])
            ->assertForbidden();
        $this->actingAs($bp)->put(route('gaji-tunjangan.rekonsiliasi.sunting', $baris), [
            'status_tk' => 'K/2', 'catatan_suntingan' => 'coba-coba',
        ])->assertForbidden();
        $this->actingAs($bp)->delete(route('gaji-tunjangan.rekonsiliasi.hapus', $kunci))->assertForbidden();

        $this->assertSame('K/1', $baris->fresh()->status_tk);
        $this->assertSame(1, RekonsiliasiKunci::count());
        $this->assertSame(0, RekonsiliasiKunci::where('bulan', 2)->count());
    }

    public function test_tombol_kunci_tidak_tampil_untuk_bendahara_pengeluaran(): void
    {
        $this->actingAs($this->user(User::ROLE_BENDAHARA_PENGELUARAN))
            ->get(route('gaji-tunjangan.rekonsiliasi', ['bulan' => 1, 'tahun' => 2026]))
            ->assertOk()
            ->assertDontSee('Kunci Periode')
            ->assertSee('Hubungi superadmin');
    }

    public function test_superadmin_menyunting_log_wajib_menyertakan_alasan(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));
        $baris = $kunci->baris()->firstOrFail();
        $admin = $this->user(User::ROLE_SUPERADMIN);

        $this->actingAs($admin)
            ->put(route('gaji-tunjangan.rekonsiliasi.sunting', $baris), ['status_tk' => 'K/2'])
            ->assertSessionHasErrors('catatan_suntingan');

        $this->assertSame('K/1', $baris->fresh()->status_tk);

        $this->actingAs($admin)
            ->put(route('gaji-tunjangan.rekonsiliasi.sunting', $baris), [
                'status_tk' => 'K/2',
                'catatan_suntingan' => 'SK kelahiran anak kedua terlambat diinput.',
            ])
            ->assertSessionHasNoErrors();

        $baris->refresh();

        $this->assertSame('K/2', $baris->status_tk);
        $this->assertSame(2, $baris->jumlah_anak);
        $this->assertSame($admin->id, $baris->disunting_oleh);
        $this->assertNotNull($baris->disunting_at);

        // Penyuntingan log wajib meninggalkan jejak di audit trail.
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'Sunting Log Rekonsiliasi Gaji Induk']);
    }

    public function test_status_suntingan_dibatasi_pilihan_yang_sah(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));
        $baris = $kunci->baris()->firstOrFail();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->put(route('gaji-tunjangan.rekonsiliasi.sunting', $baris), [
                'status_tk' => 'K/9', 'catatan_suntingan' => 'mengada-ada',
            ])
            ->assertSessionHasErrors('status_tk');
    }

    public function test_periode_tidak_bisa_dikunci_dua_kali(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $admin = $this->user(User::ROLE_SUPERADMIN);

        $this->actingAs($admin)->post(route('gaji-tunjangan.rekonsiliasi.kunci'), ['bulan' => 1, 'tahun' => 2026])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('gaji-tunjangan.rekonsiliasi.kunci'), ['bulan' => 1, 'tahun' => 2026])
            ->assertSessionHasErrors('bulan');

        $this->assertSame(1, RekonsiliasiKunci::count());
    }

    public function test_hapus_kunci_menghapus_seluruh_barisnya(): void
    {
        $this->pegawai('ANI', '111', pasangan: true, anak: 1);
        $this->pegawai('BUDI', '222', pasangan: false, anak: 0);

        $kunci = $this->service()->kunci(1, 2026, $this->user(User::ROLE_SUPERADMIN));
        $this->assertSame(2, RekonsiliasiKunciBaris::count());

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->delete(route('gaji-tunjangan.rekonsiliasi.hapus', $kunci))
            ->assertRedirect();

        $this->assertSame(0, RekonsiliasiKunci::count());
        $this->assertSame(0, RekonsiliasiKunciBaris::count(), 'Baris log harus ikut terhapus.');
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'Hapus Kunci Rekonsiliasi Gaji Induk']);
    }

    public function test_menu_tetap_dijaga_config_akses(): void
    {
        $user = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($user)->get(route('gaji-tunjangan.rekonsiliasi'))->assertOk();

        config(['akses.menu.superadmin' => array_values(array_diff(config('akses.menu.superadmin'), ['gt-rekon']))]);
        $this->actingAs($user)->get(route('gaji-tunjangan.rekonsiliasi'))->assertForbidden();
    }
}
