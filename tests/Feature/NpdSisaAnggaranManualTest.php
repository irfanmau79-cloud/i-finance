<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Sisa Anggaran yang diketik manual saat membuat NPD.
 *
 * Aturan yang dijaga di sini: angka manual HANYA mengganti kolom "SISA
 * ANGGARAN" pada PDF NPD. Tidak satu pun perhitungan sistem (sisa tersedia,
 * dana terikat, realisasi, batas nominal NPD) boleh ikut memakainya — itulah
 * yang membuat fitur ini aman dibuka setahun lalu dikunci kembali.
 */
class NpdSisaAnggaranManualTest extends TestCase
{
    use RefreshDatabase;

    private function pptk(): User
    {
        return User::create([
            'username' => 'uji-pptk-sisa',
            'nama' => 'Penguji PPTK',
            'role' => 'pptk',
            'password' => 'rahasia-uji',
        ]);
    }

    private function masterAnggaran(float $pagu = 100_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Sisa',
            'kegiatan' => 'Kegiatan Uji Sisa',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Sisa',
            'kode_rekening' => '5.1.02.01.01.0001',
            'uraian_rekening' => 'Belanja Uji',
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    /** @param array<string, mixed> $override */
    private function payloadBj(MasterAnggaran $masterAnggaran, array $override = []): array
    {
        return array_replace([
            'master_anggaran_id' => $masterAnggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-08-10',
            'bulan' => 8,
            'tahun' => config('anggaran.tahun_aktif'),
            'penerima' => [[
                'nama' => 'Budi Santoso',
                'bruto' => 5_000_000,
                'keterangan' => 'Pembelian ATK',
            ]],
        ], $override);
    }

    /** Data yang diterima template PDF NPD saat halaman cetak diminta. */
    private function dataPdf(User $user, Npd $npd): array
    {
        $ditangkap = [];
        View::composer('npd.pdf.npd', function ($view) use (&$ditangkap) {
            $ditangkap = $view->getData();
        });

        $this->actingAs($user)->get(route('npd.cetak-npd', $npd))->assertOk();

        return $ditangkap;
    }

    public function test_angka_manual_dipakai_di_pdf_dan_tidak_mengubah_hitungan_sistem(): void
    {
        $pptk = $this->pptk();
        $masterAnggaran = $this->masterAnggaran();

        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $this->payloadBj($masterAnggaran, [
                'sisa_anggaran_manual' => 12_345_678.90,
            ]))
            ->assertSessionHasNoErrors();

        $npd = Npd::firstOrFail();
        $this->assertSame('12345678.90', (string) $npd->sisa_anggaran_manual);

        // PDF memakai angka ketikan, bukan angka sistem.
        $this->assertSame(12_345_678.90, $this->dataPdf($pptk, $npd)['sisaAnggaran']);

        // Sistem tetap menghitung sendiri: pagu 100jt dikurangi NPD 5jt.
        $masterAnggaran->refresh();
        $this->assertSame(5_000_000.0, $masterAnggaran->danaTerikatNpd());
        $this->assertSame(95_000_000.0, $masterAnggaran->sisaTersedia());
    }

    public function test_tanpa_angka_manual_pdf_memakai_angka_sistem(): void
    {
        $pptk = $this->pptk();
        $masterAnggaran = $this->masterAnggaran();

        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $this->payloadBj($masterAnggaran))
            ->assertSessionHasNoErrors();

        $npd = Npd::firstOrFail();
        $this->assertNull($npd->sisa_anggaran_manual);

        $this->assertSame(
            $masterAnggaran->sisaAnggaranSebelum($npd),
            $this->dataPdf($pptk, $npd)['sisaAnggaran']
        );
    }

    public function test_angka_manual_tidak_melonggarkan_batas_nominal_npd(): void
    {
        $pptk = $this->pptk();
        $masterAnggaran = $this->masterAnggaran(4_000_000);

        // Nominal 5jt melebihi pagu 4jt. Mengetik sisa manual yang besar tidak
        // boleh membuatnya lolos - batasnya tetap angka sistem.
        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $this->payloadBj($masterAnggaran, [
                'sisa_anggaran_manual' => 900_000_000,
            ]))
            ->assertSessionHasErrors('penerima');

        $this->assertSame(0, Npd::count());
    }

    public function test_isian_muncul_di_formulir_saat_dibuka_dan_hilang_saat_dikunci(): void
    {
        $pptk = $this->pptk();
        $this->masterAnggaran();

        $this->actingAs($pptk)->get(route('npd.bj.create'))
            ->assertOk()
            ->assertSee('name="sisa_anggaran_manual"', false);

        config(['anggaran.sisa_manual_npd' => false]);

        $this->actingAs($pptk)->get(route('npd.bj.create'))
            ->assertOk()
            ->assertDontSee('name="sisa_anggaran_manual"', false);
    }

    public function test_saat_dikunci_input_baru_diabaikan_tapi_angka_lama_dipertahankan(): void
    {
        $pptk = $this->pptk();
        $masterAnggaran = $this->masterAnggaran();

        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $this->payloadBj($masterAnggaran, [
                'sisa_anggaran_manual' => 7_000_000,
            ]))
            ->assertSessionHasNoErrors();

        $npdLama = Npd::firstOrFail();

        config(['anggaran.sisa_manual_npd' => false]);

        // NPD baru: angka yang tetap dikirim diabaikan.
        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $this->payloadBj($masterAnggaran, [
                'tanggal_npd' => '2026-08-11',
                'sisa_anggaran_manual' => 8_000_000,
            ]))
            ->assertSessionHasNoErrors();

        $npdBaru = Npd::orderByDesc('id')->firstOrFail();
        $this->assertNull($npdBaru->sisa_anggaran_manual);

        // NPD lama: menyunting tanpa isian itu tidak menghapus angkanya,
        // supaya cetak ulang dokumen yang sudah ditandatangani tetap sama.
        $this->actingAs($pptk)
            ->put(route('npd.bj.update', $npdLama), $this->payloadBj($masterAnggaran))
            ->assertSessionHasNoErrors();

        $this->assertSame('7000000.00', (string) $npdLama->refresh()->sisa_anggaran_manual);
    }
}
