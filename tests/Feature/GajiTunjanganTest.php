<?php

namespace Tests\Feature;

use App\Models\GajiInduk;
use App\Models\Tpp;
use App\Models\User;
use App\Services\GajiTunjanganService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Empat sub-menu tabel Data Gaji & Tunjangan beserta gerbang privasinya.
 */
class GajiTunjanganTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'gt-'.$role.'-'.uniqid(),
            'nama' => 'Uji '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    /** @param  array<string, mixed>  $ubah */
    private function gaji(array $ubah = []): GajiInduk
    {
        return GajiInduk::create(array_merge([
            'bulan' => 8, 'tahun' => 2026,
            'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'nip' => '196611041990032003',
            'golongan' => 'IV/c',
            'pppk_pns' => 'PNS',
            'nama_jabatan' => 'AUDITOR AHLI MADYA',
            'nomor_rekening_bank_pegawai' => '0006235352100',
            'belanja_gaji_pokok' => 5866400,
            'perhitungan_suami_istri' => 586640,
            'perhitungan_anak' => 0,
            'belanja_tunjangan_fungsional' => 1290000,
            'belanja_tunjangan_beras' => 144840,
            'belanja_pembulatan_gaji' => 63,
            'jumlah_gaji_tunjangan' => 7887943,
            'tunjangan_jaminan_hari_tua' => 516243,
            'iwp_1_persen' => 120000,
            'jumlah_potongan' => 636243,
            'jumlah_ditransfer' => 7251700,
        ], $ubah));
    }

    /** @param  array<string, mixed>  $ubah */
    private function tpp(string $jenis, array $ubah = []): Tpp
    {
        return Tpp::create(array_merge([
            'jenis' => $jenis, 'bulan' => 8, 'tahun' => 2026,
            'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'nip' => '196611041990032003',
            'golongan' => 'IV/c',
            'pns_pppk' => 'PNS',
            'nama_jabatan' => 'AUDITOR AHLI MADYA',
            'jumlah_ditransfer' => $jenis === 'beban' ? 21653682 : 4192500,
            'nilai_kinerja' => 98.74,
            'tpp_maksimum' => 21931000,
            'koperasi_praja' => $jenis === 'beban' ? 150000 : null,
            'zakat_praja' => $jenis === 'beban' ? 25000 : null,
        ], $ubah));
    }

    public function test_keempat_sub_menu_terbuka_untuk_role_data_penuh(): void
    {
        $this->gaji();
        $this->tpp('beban');
        $this->tpp('kondisi');

        $user = $this->user(User::ROLE_BENDAHARA_PENGELUARAN);

        foreach (['gaji', 'beban', 'kondisi', 'total'] as $jenis) {
            $this->actingAs($user)
                ->get(route('gaji-tunjangan.tabel.'.$jenis, ['bulan' => 8, 'tahun' => 2026]))
                ->assertOk()
                ->assertSee('ELYNA S. LAURA SIAHAAN, S.K.p.,MH')
                ->assertDontSee('Verifikasi Identitas');
        }
    }

    public function test_role_di_luar_daftar_kena_gerbang_dan_datanya_tidak_dikirim(): void
    {
        $this->gaji();
        $this->gaji(['nip' => '196706161989021001', 'nama_pegawai' => 'MOCH IWAN SETIAWAN']);

        $halaman = $this->actingAs($this->user(User::ROLE_PPTK))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Verifikasi Identitas');

        // Yang penting bukan sekadar formnya muncul: nama pegawai mana pun
        // tidak boleh ikut terkirim ke peramban sebelum verifikasi.
        $halaman->assertDontSee('ELYNA')->assertDontSee('MOCH IWAN SETIAWAN');
    }

    public function test_verifikasi_nip_dan_empat_digit_rekening_membuka_baris_sendiri_saja(): void
    {
        $this->gaji();
        $this->gaji(['nip' => '196706161989021001', 'nama_pegawai' => 'MOCH IWAN SETIAWAN', 'nomor_rekening_bank_pegawai' => '0007756100100']);

        $user = $this->user(User::ROLE_PPTK);

        $this->actingAs($user)
            ->post(route('gaji-tunjangan.verifikasi'), ['nip' => '196611041990032003', 'rek4' => '2100'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('ELYNA S. LAURA SIAHAAN, S.K.p.,MH')
            ->assertDontSee('MOCH IWAN SETIAWAN');
    }

    public function test_verifikasi_ditolak_bila_rekening_tidak_cocok(): void
    {
        $this->gaji();

        $this->actingAs($this->user(User::ROLE_PPTK))
            ->post(route('gaji-tunjangan.verifikasi'), ['nip' => '196611041990032003', 'rek4' => '9999'])
            ->assertSessionHasErrors('nip');
    }

    public function test_verifikasi_menolak_rekening_bukan_empat_angka(): void
    {
        $this->gaji();

        $this->actingAs($this->user(User::ROLE_PPTK))
            ->post(route('gaji-tunjangan.verifikasi'), ['nip' => '196611041990032003', 'rek4' => 'abc'])
            ->assertSessionHasErrors('nip');
    }

    public function test_ganti_nip_mengunci_kembali_halaman(): void
    {
        $this->gaji();
        $user = $this->user(User::ROLE_PPTK);

        $this->actingAs($user)->post(route('gaji-tunjangan.verifikasi'), ['nip' => '196611041990032003', 'rek4' => '2100']);
        $this->actingAs($user)->post(route('gaji-tunjangan.ganti-nip'));

        $this->actingAs($user)
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertSee('Verifikasi Identitas');
    }

    public function test_bruto_satu_dihitung_dari_pokok_suami_istri_dan_anak(): void
    {
        $this->gaji(['perhitungan_anak' => 117328]);

        $rows = app(GajiTunjanganService::class)->data('gaji', 'bulan', 8, 2026)['rows'];

        $this->assertEqualsWithDelta(5866400 + 586640 + 117328, $rows[0]['bruto1'], 0.01);
    }

    public function test_mode_kumulatif_menjumlah_per_pegawai_tanpa_menjumlah_persentase(): void
    {
        $this->tpp('beban', ['bulan' => 7, 'jumlah_ditransfer' => 1000000, 'nilai_kinerja' => 98.74, 'tpp_maksimum' => 21931000]);
        $this->tpp('beban', ['bulan' => 8, 'jumlah_ditransfer' => 2000000, 'nilai_kinerja' => 97.10, 'tpp_maksimum' => 21931000]);

        $rows = app(GajiTunjanganService::class)->data('beban', 'tahun', null, 2026)['rows'];

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(3000000, $rows[0]['netto'], 0.01);
        // Prosentase Kinerja & Besaran TPP 100% adalah nilai referensi -
        // menjumlahkannya akan menghasilkan angka seperti "195,84%".
        $this->assertEqualsWithDelta(98.74, $rows[0]['persen'], 0.01);
        $this->assertEqualsWithDelta(21931000, $rows[0]['besaran100'], 0.01);
    }

    public function test_total_penghasilan_menggabungkan_tiga_sumber_berdasarkan_nip(): void
    {
        $this->gaji();
        $this->tpp('beban');
        $this->tpp('kondisi');

        // Pegawai yang hanya ada di TPP Beban tetap muncul (union by NIP).
        $this->tpp('beban', ['nip' => '199001011990011001', 'nama_pegawai' => 'PPPK UJI', 'pns_pppk' => 'PPPK', 'golongan' => 'IX', 'jumlah_ditransfer' => 3000000]);

        $rows = app(GajiTunjanganService::class)->data('total', 'bulan', 8, 2026)['rows'];

        $this->assertCount(2, $rows);

        $elyna = collect($rows)->firstWhere('nip', '196611041990032003');
        $this->assertEqualsWithDelta(7887943, $elyna['gaji_bruto'], 0.01);
        $this->assertEqualsWithDelta(21653682, $elyna['tpp_bruto'], 0.01);
        $this->assertEqualsWithDelta(4192500, $elyna['tol_bruto'], 0.01);
        $this->assertEqualsWithDelta(7887943 + 21653682 + 4192500, $elyna['total_bruto'], 0.01);
        // Iuran 1% + iuran 8% dari Gaji Induk.
        $this->assertEqualsWithDelta(516243 + 120000, $elyna['pot_iuran'], 0.01);
        // Koperasi Praja & Zakat hanya dari TPP Beban Kerja.
        $this->assertEqualsWithDelta(150000, $elyna['pot_koperasi'], 0.01);
        $this->assertEqualsWithDelta(25000, $elyna['pot_zakat'], 0.01);

        // PPPK selalu di urutan paling akhir.
        $this->assertSame('PPPK UJI', $rows[1]['nama']);
    }

    public function test_pencarian_menyaring_berdasarkan_nama_nip_atau_jabatan(): void
    {
        $this->gaji();
        $this->gaji(['nip' => '196706161989021001', 'nama_pegawai' => 'MOCH IWAN SETIAWAN']);

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026, 'q' => 'IWAN']))
            ->assertOk()
            ->assertSee('MOCH IWAN SETIAWAN')
            ->assertDontSee('ELYNA');
    }

    public function test_tabel_dibatasi_sepuluh_pegawai_per_halaman(): void
    {
        foreach (range(1, 12) as $i) {
            $this->gaji([
                'nip' => sprintf('19660101199003%04d', $i),
                'nama_pegawai' => 'PEGAWAI KE '.$i,
            ]);
        }

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk();

        $this->assertSame(10, substr_count($halaman->getContent(), 'PEGAWAI KE '));
    }

    public function test_menu_tetap_dijaga_config_akses(): void
    {
        $user = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($user)->get(route('gaji-tunjangan.tabel.total'))->assertOk();

        config(['akses.menu.superadmin' => array_values(array_diff(config('akses.menu.superadmin'), ['gt-total']))]);
        $this->actingAs($user)->get(route('gaji-tunjangan.tabel.total'))->assertForbidden();
    }

    /*
     * ================================================================
     * Tampilan hasil adopsi UI dari GAS (gtTabelGaji/gtTabelTPP/
     * gtTabelTotal). Yang diuji bukan gayanya, melainkan susunan kolom
     * dan format angka - keduanya mudah berubah tanpa disadari saat
     * partial kolom/baris disunting.
     * ================================================================
     */

    public function test_gaji_induk_memakai_kolom_gabungan_seperti_gas(): void
    {
        $this->gaji();

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk();

        // Header GAS menumpuk beberapa nilai dalam satu kolom, bukan satu
        // kolom per nilai seperti tabel Laravel yang lama.
        $halaman->assertSee('Nama / NIP<br>No Rek / Jabatan', false)
            ->assertSee('Status<br>GOL/R', false)
            ->assertSee('Gaji Pokok<br>Tj Suami/Istri<br>Tj Anak<br>Bruto 1', false)
            ->assertSee('Beras / IWP 8%<br>IWP 1% / PPh', false)
            ->assertSee('Jumlah<br>Dibayarkan', false);

        // Identitas pegawai jadi satu blok .gt-peg di kolom pertama.
        $halaman->assertSee('gt-peg', false)
            ->assertSee('0006235352100');
    }

    public function test_nominal_tanpa_desimal_seperti_gtfmt(): void
    {
        $this->gaji();

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk();

        // gtFmt() memakai toLocaleString('id-ID'): pemisah ribuan titik,
        // tanpa dua angka di belakang koma seperti fmt_rupiah().
        $halaman->assertSee('7.251.700')->assertDontSee('7.251.700,00');
    }

    public function test_pengurang_ikp_hanya_muncul_di_tpp_kondisi_kerja(): void
    {
        $this->tpp('beban');
        $this->tpp('kondisi');

        $user = $this->user(User::ROLE_SUPERADMIN);

        // colPot di gtTabelTPP: Beban Kerja hanya punya satu kolom potongan.
        $this->actingAs($user)
            ->get(route('gaji-tunjangan.tabel.beban', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk()
            ->assertDontSee('Pengurang<br>IKP', false);

        $this->actingAs($user)
            ->get(route('gaji-tunjangan.tabel.kondisi', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Pengurang<br>IKP', false);
    }

    public function test_prosentase_kinerja_mengikuti_format_tofixed_gas(): void
    {
        $this->tpp('beban');
        $this->tpp('beban', ['nip' => '196706161989021001', 'nama_pegawai' => 'BULAT SERATUS', 'nilai_kinerja' => 100]);

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.beban', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk();

        // GAS: pv % 1 === 0 ? pv+'%' : pv.toFixed(2)+'%'.
        $halaman->assertSee('98.74%')->assertSee('>100%<', false);
    }

    public function test_baris_info_menyebut_jumlah_pegawai_dan_periode(): void
    {
        $this->gaji();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026, 'q' => 'ELYNA']))
            ->assertOk()
            ->assertSee('1 pegawai &middot; Agustus 2026', false)
            ->assertSee('pencarian "ELYNA"', false);
    }

    public function test_mode_kumulatif_menyebut_periode_kumulatif(): void
    {
        $this->gaji();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['mode' => 'tahun', 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Kumulatif 2026');
    }

    public function test_pager_gas_muncul_saat_data_lebih_dari_sepuluh(): void
    {
        foreach (range(1, 25) as $i) {
            $this->gaji([
                'nip' => sprintf('19660101199003%04d', $i),
                'nama_pegawai' => 'PEGAWAI KE '.$i,
            ]);
        }

        $halaman = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk();

        // gtRenderPager(): "Hal 1/3" plus tombol lompat halaman.
        $halaman->assertSee('gt-pager', false)->assertSee('Hal 1/3');
    }

    public function test_pager_menyebut_jumlah_saat_hanya_satu_halaman(): void
    {
        $this->gaji();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get(route('gaji-tunjangan.tabel.gaji', ['bulan' => 8, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Menampilkan 1 pegawai');
    }
}
