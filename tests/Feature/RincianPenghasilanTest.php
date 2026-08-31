<?php

namespace Tests\Feature;

use App\Helpers\GuestSession;
use App\Models\GajiInduk;
use App\Models\RincianPenghasilan;
use App\Models\Tpp;
use App\Models\User;
use App\Services\RincianPenghasilanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

/**
 * Surat Keterangan Penghasilan: penomoran, isi surat, cetak PDF, dan hapus.
 *
 * Cetak PDF diuji sungguhan (mPDF dijalankan, hasilnya dibaca) - bukan
 * sekadar status 200 - mengikuti kebijakan CLAUDE.md bahwa dokumen cetak
 * harus dijaga oleh test yang benar-benar me-render, karena surat inilah yang
 * ditandatangani dan dipegang pegawai.
 */
class RincianPenghasilanTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'rp-'.$role.'-'.uniqid(),
            'nama' => 'Uji '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    private function dataSatuBulan(int $bulan = 8): void
    {
        GajiInduk::create([
            'bulan' => $bulan, 'tahun' => 2026,
            'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'nip' => '196611041990032003',
            'golongan' => 'IV/c', 'pppk_pns' => 'PNS',
            'nama_jabatan' => 'PENGAWAS PENYELENGGARAAN URUSAN PEMERINTAHAN DAERAH AHLI MADYA',
            'nomor_rekening_bank_pegawai' => '0006235352100',
            'belanja_gaji_pokok' => 5866400,
            'perhitungan_suami_istri' => 586640,
            'perhitungan_anak' => 0,
            'belanja_tunjangan_jabatan' => 0,
            'belanja_tunjangan_fungsional' => 1290000,
            'belanja_tunjangan_fungsional_umum' => 0,
            'belanja_tunjangan_beras' => 144840,
            'belanja_tunjangan_pph' => 0,
            'belanja_pembulatan_gaji' => 63,
            'jumlah_gaji_tunjangan' => 7887943,
            'tunjangan_jaminan_hari_tua' => 516243,
            'iwp_1_persen' => 120000,
            'pph_21' => 0,
            'jumlah_potongan' => 636243,
            'jumlah_ditransfer' => 7251700,
        ]);

        Tpp::create([
            'jenis' => 'beban', 'bulan' => $bulan, 'tahun' => 2026,
            'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'nip' => '196611041990032003', 'golongan' => 'IV/c', 'pns_pppk' => 'PNS',
            'jumlah_ditransfer' => 21653682,
            'nilai_kinerja' => 98.74, 'tpp_maksimum' => 21931000,
            'koperasi_praja' => 150000, 'zakat_praja' => 25000,
        ]);

        Tpp::create([
            'jenis' => 'kondisi', 'bulan' => $bulan, 'tahun' => 2026,
            'nama_pegawai' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'nip' => '196611041990032003', 'golongan' => 'IV/c', 'pns_pppk' => 'PNS',
            'jumlah_ditransfer' => 4192500,
            'nilai_kinerja' => 100, 'tpp_maksimum' => 4192500,
        ]);
    }

    /** @param  array<string, mixed>  $ubah */
    private function buat(User $user, array $ubah = [])
    {
        return $this->actingAs($user)->post(route('gaji-tunjangan.rincian.store'), array_merge([
            'nip' => '196611041990032003',
            'nama' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'jabatan' => 'PENGAWAS PENYELENGGARAAN URUSAN PEMERINTAHAN DAERAH AHLI MADYA',
            'tahun' => 2026,
            'periode' => [8],
            'penandatangan' => 'irfan',
        ], $ubah));
    }

    public function test_nomor_surat_urut_global_dan_memakai_bulan_pembuatan(): void
    {
        $this->dataSatuBulan();
        $user = $this->user(User::ROLE_SUPERADMIN);

        $this->travelTo(now()->setDate(2026, 8, 31));

        $this->buat($user);
        $this->buat($user, ['periode' => [7]]);

        $nomor = RincianPenghasilan::orderBy('id')->pluck('nomor')->all();

        // Urut 1, 2, ... global - tidak reset per bulan (perubahan 17).
        // mm/yyyy = bulan & tahun PEMBUATAN, bukan periode penghasilan
        // (perubahan 16): dokumen kedua berperiode Juli tetapi tetap /08/.
        $this->assertSame([
            '1/KET.PENGHASILAN/INSPEKTORAT/08/2026',
            '2/KET.PENGHASILAN/INSPEKTORAT/08/2026',
        ], $nomor);
    }

    public function test_rincian_satu_bulan_mengikuti_rumus_gas(): void
    {
        $this->dataSatuBulan();

        $hasil = app(RincianPenghasilanService::class)->rincianSatuBulan('196611041990032003', 8, 2026);

        $this->assertTrue($hasil['ada']);

        // Tunjangan Struktural/Fungsional/Umum = tunjangan jabatan + umum.
        $this->assertEqualsWithDelta(0, $hasil['gaji']['struktural_umum'], 0.01);
        $this->assertEqualsWithDelta(7887943, $hasil['gaji']['bruto'], 0.01);
        // Simpanan WAJIB (TASPEN) = iuran 8%; Iuran BPJS/Askes = iuran 1%.
        $this->assertEqualsWithDelta(516243, $hasil['gaji']['pot_wajib'], 0.01);
        $this->assertEqualsWithDelta(120000, $hasil['gaji']['pot_bpjs'], 0.01);
        $this->assertEqualsWithDelta(7251700, $hasil['gaji']['netto'], 0.01);

        // Penghasilan berbasis kinerja = TPP Beban + TPP Kondisi, potongannya
        // Koperasi Praja + Zakat dari TPP Beban saja.
        $this->assertEqualsWithDelta(21653682 + 4192500, $hasil['kinerja']['bruto'], 0.01);
        $this->assertEqualsWithDelta(175000, $hasil['kinerja']['pot_total'], 0.01);
        $this->assertEqualsWithDelta(21653682 + 4192500 - 175000, $hasil['kinerja']['netto'], 0.01);
    }

    public function test_pdf_satu_periode_tercetak_satu_halaman(): void
    {
        $this->dataSatuBulan();
        $user = $this->user(User::ROLE_SUPERADMIN);

        $this->buat($user);
        $dokumen = RincianPenghasilan::firstOrFail();

        $respons = $this->actingAs($user)->get(route('gaji-tunjangan.rincian.cetak', $dokumen))->assertOk();

        $this->assertSame('application/pdf', $respons->headers->get('Content-Type'));
        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF-', $isi);

        // Satu periode WAJIB muat dalam satu halaman - suratnya dioptimalkan
        // untuk itu (perubahan 11). Kalau meluber, blok tanda tangan
        // elektronik terpisah ke halaman kosong.
        $halaman = (new PdfReader(new PdfParser(StreamReader::createByString($isi))))->getPageCount();
        $this->assertSame(1, $halaman, 'Surat satu periode harus muat satu halaman.');
    }

    public function test_pdf_multi_periode_satu_halaman_per_bulan(): void
    {
        $this->dataSatuBulan(6);
        $this->dataSatuBulan(7);
        $this->dataSatuBulan(8);

        $user = $this->user(User::ROLE_SUPERADMIN);
        $this->buat($user, ['periode' => [6, 7, 8]]);

        $isi = $this->actingAs($user)
            ->get(route('gaji-tunjangan.rincian.cetak', RincianPenghasilan::firstOrFail()))
            ->assertOk()->getContent();

        $halaman = (new PdfReader(new PdfParser(StreamReader::createByString($isi))))->getPageCount();
        $this->assertSame(3, $halaman, 'Tiap periode harus menjadi satu halaman tersendiri.');
    }

    public function test_periode_disimpan_urut_menaik_dan_bebas_duplikat(): void
    {
        $this->dataSatuBulan(7);
        $this->dataSatuBulan(8);

        $this->buat($this->user(User::ROLE_SUPERADMIN), ['periode' => [8, 7, 8]]);

        $this->assertSame([7, 8], RincianPenghasilan::firstOrFail()->periode);
    }

    public function test_penandatangan_dibekukan_sehingga_dokumen_lama_tidak_ikut_berubah(): void
    {
        $this->dataSatuBulan();
        $this->buat($this->user(User::ROLE_SUPERADMIN), ['penandatangan' => 'verri']);

        $dokumen = RincianPenghasilan::firstOrFail();
        $this->assertSame('VERRI RIYANTI, M.S.P.', $dokumen->penandatangan_nama);

        // Daftar penandatangan berganti orang setelah dokumen dibuat.
        config(['gaji_tunjangan.penandatangan.verri' => [
            'nama' => 'PEJABAT BARU, S.E.', 'jabatan' => 'Kepala Subbagian Tata Usaha', 'pangkat' => 'Penata',
        ]]);

        $this->assertSame('VERRI RIYANTI, M.S.P.', $dokumen->fresh()->penandatangan_nama);
    }

    public function test_uang_harian_ditarik_nol_bila_pegawai_tak_punya_perjalanan_dinas(): void
    {
        $this->dataSatuBulan();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->postJson(route('gaji-tunjangan.rincian.uang-harian'), [
                'nip' => '196611041990032003', 'tahun' => 2026, 'periode' => [7, 8],
            ])
            ->assertOk()
            ->assertJson(['nominal' => [7 => 0, 8 => 0]]);
    }

    public function test_periode_wajib_dipilih(): void
    {
        $this->dataSatuBulan();

        $this->buat($this->user(User::ROLE_SUPERADMIN), ['periode' => []])
            ->assertSessionHasErrors('periode');

        $this->assertSame(0, RincianPenghasilan::count());
    }

    public function test_daftar_dan_hapus_hanya_untuk_role_pengelola(): void
    {
        $this->dataSatuBulan();
        $this->buat($this->user(User::ROLE_SUPERADMIN));
        $dokumen = RincianPenghasilan::firstOrFail();

        $this->actingAs($this->user(User::ROLE_PPTK))->get(route('gaji-tunjangan.rincian.index'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_PPTK))
            ->delete(route('gaji-tunjangan.rincian.destroy', $dokumen))->assertForbidden();

        $this->actingAs($this->user(User::ROLE_BENDAHARA_PENGELUARAN))
            ->get(route('gaji-tunjangan.rincian.index'))->assertOk()->assertSee($dokumen->nomor);
    }

    public function test_hapus_membuat_nomor_berikutnya_mundur_satu_urutan(): void
    {
        // Perilaku ini disengaja di GAS: penomoran dihitung dari nomor
        // tertinggi yang MASIH ADA, supaya percobaan cetak tidak membuang nomor.
        $this->dataSatuBulan();
        $user = $this->user(User::ROLE_SUPERADMIN);

        $this->buat($user);
        $this->buat($user);

        $terakhir = RincianPenghasilan::orderByDesc('id')->firstOrFail();
        $this->assertSame(2, $terakhir->nomor_urut);

        $this->actingAs($user)->delete(route('gaji-tunjangan.rincian.destroy', $terakhir))
            ->assertRedirect(route('gaji-tunjangan.rincian.index'));

        $this->buat($user);
        $this->assertSame(2, RincianPenghasilan::orderByDesc('id')->firstOrFail()->nomor_urut);
    }

    public function test_dokumen_orang_lain_tidak_bisa_dicetak_dengan_menebak_id(): void
    {
        // Menu Cetak sengaja terbuka lebar (semua role, termasuk Pengguna
        // Layanan tanpa akun). Tanpa penjaga, menebak nomor ID di URL menjadi
        // jalan membaca surat penghasilan pegawai lain.
        $this->dataSatuBulan();

        // Dokumen dibuat LANGSUNG lewat service, bukan lewat permintaan HTTP:
        // supaya sesi pengujian tidak ikut memegang id dokumen ini dan yang
        // benar-benar diuji adalah penjaganya, bukan sisa sesi pembuatnya.
        $dokumen = app(RincianPenghasilanService::class)->simpan([
            'nip' => '196611041990032003',
            'nama' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'jabatan' => 'AUDITOR',
            'tahun' => 2026,
            'periode' => [8],
            'ada_pd' => false,
            'penandatangan' => 'irfan',
        ], null, 'sistem');

        $this->actingAs($this->user(User::ROLE_PPTK))
            ->get(route('gaji-tunjangan.rincian.cetak', $dokumen))
            ->assertForbidden();

        $this->lolosGerbangLayanan()
            ->withSession([GuestSession::kunciSesi() => true])
            ->get(route('gaji-tunjangan.rincian.cetak', $dokumen))
            ->assertForbidden();

        // Role pengelola tetap boleh - merekalah yang memegang menu Daftar.
        $this->actingAs($this->user(User::ROLE_BENDAHARA_PENGELUARAN))
            ->get(route('gaji-tunjangan.rincian.cetak', $dokumen))
            ->assertOk();
    }

    public function test_pembuat_dokumen_boleh_mencetak_dokumennya_sendiri(): void
    {
        $this->dataSatuBulan();

        // Role di luar pengelola tetap bisa mencetak dokumen yang baru saja
        // ia buat - alur normalnya memang buat lalu langsung cetak.
        $pptk = $this->user(User::ROLE_PPTK);
        $this->buat($pptk)->assertRedirect();

        $this->actingAs($pptk)
            ->get(route('gaji-tunjangan.rincian.cetak', RincianPenghasilan::firstOrFail()))
            ->assertOk();
    }

    public function test_nominal_perjalanan_dinas_terbaca_kembali_per_bulan(): void
    {
        // nominal_pd disimpan sebagai JSON berkunci angka; test ini menjaga
        // agar kuncinya tetap terbaca sebagai bulan setelah pulang-pergi ke
        // basis data, bukan berubah menjadi teks dan jatuh ke nol.
        $this->dataSatuBulan(7);
        $this->dataSatuBulan(8);

        $dokumen = RincianPenghasilan::create([
            'nomor_urut' => 1,
            'nomor' => '1/KET.PENGHASILAN/INSPEKTORAT/08/2026',
            'nip' => '196611041990032003',
            'nama' => 'ELYNA S. LAURA SIAHAAN, S.K.p.,MH',
            'jabatan' => 'AUDITOR',
            'tahun' => 2026,
            'periode' => [7, 8],
            'ada_pd' => true,
            'nominal_pd' => [7 => 1740000, 8 => 900000],
            'total_pd' => 2640000,
            'penandatangan_kunci' => 'irfan',
            'penandatangan_nama' => 'IRFAN MAULANA, S.Ak.',
            'penandatangan_jabatan' => 'Penelaah Teknis Kebijakan',
            'penandatangan_pangkat' => 'Penata Muda',
            'tanggal_dokumen' => '2026-08-31',
        ]);

        $halaman = app(RincianPenghasilanService::class)->halaman($dokumen->fresh());

        $this->assertEqualsWithDelta(1740000, $halaman[0]['nominal_pd'], 0.01);
        $this->assertEqualsWithDelta(900000, $halaman[1]['nominal_pd'], 0.01);
        $this->assertTrue($halaman[0]['tampil_pd']);
        // Jumlah Penghasilan Seluruhnya = gaji netto + kinerja netto + uang harian.
        $this->assertEqualsWithDelta(
            7251700 + (21653682 + 4192500 - 175000) + 1740000,
            $halaman[0]['jumlah_seluruh'],
            0.01
        );
    }

    public function test_form_cetak_terbuka_untuk_pengguna_layanan_tanpa_login(): void
    {
        $this->dataSatuBulan();

        $this->lolosGerbangLayanan()
            ->withSession([GuestSession::kunciSesi() => true])
            ->get(route('gaji-tunjangan.rincian.create'))
            ->assertOk()
            ->assertSee('Cetak Rincian Penghasilan');
    }
}
