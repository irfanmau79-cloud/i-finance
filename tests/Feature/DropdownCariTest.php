<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dropdown yang bisa dicari.
 *
 * Komponennya satu: layouts/partials/select-cari memasang dirinya pada tiap
 * <select data-cari>. Test ini menjaga dua hal yang gampang lepas saat halaman
 * disunting: komponennya benar-benar ikut terkirim di tiap layout, dan
 * dropdown berdaftar panjang tetap ditandai `data-cari` (bukan kembali jadi
 * <select> polos yang harus digulir).
 */
class DropdownCariTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function masterAnggaran(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Dropdown',
            'kegiatan' => 'Kegiatan Uji Dropdown',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0001',
            'tagging_id' => null,
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    public function test_komponen_select_cari_ikut_di_semua_layout(): void
    {
        foreach (['app', 'standalone', 'standalone-wide'] as $layout) {
            $isi = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));

            $this->assertStringContainsString(
                "@include('layouts.partials.select-cari')",
                $isi,
                "Layout {$layout} kehilangan komponen dropdown pencarian."
            );
        }
    }

    public function test_halaman_input_sp_memakai_dropdown_pencarian_untuk_nama_anggota(): void
    {
        Pegawai::create([
            'nama' => 'Budi Santoso',
            'nip' => '198001012000031001',
            'jabatan' => 'Auditor Ahli Muda',
            'bidang' => 'Inspektur Pembantu I',
            'aktif' => true,
        ]);

        $response = $this->lolosGerbangLayanan()->get(route('sp.input.create'));

        $response->assertStatus(200);
        // Komponennya ikut terkirim walau halaman ini dibuka tanpa login.
        $response->assertSee('select[data-cari]', false);
        // Nama anggota: <select> polos sebelumnya, kini bisa diketik untuk mencari.
        $response->assertSee('<select data-cari data-nama-select>', false);
        // Jabatan/bidang jadi baris kedua pada tiap pilihan, bukan disambung ke nama.
        $response->assertSee('data-sub=', false);
        $response->assertSee('<select id="sp_induk_id" name="sp_induk_id" data-cari>', false);
    }

    public function test_dropdown_mata_anggaran_npd_bisa_dicari(): void
    {
        $this->masterAnggaran();
        $pptk = $this->user('pptk', 'uji-pptk-dropdown');

        $response = $this->actingAs($pptk)->get(route('npd.bj.create'));

        $response->assertStatus(200);
        foreach (['maf-program', 'maf-kegiatan', 'maf-sub', 'maf-kode', 'maf-tagging'] as $id) {
            $response->assertSee('id="'.$id.'" data-cari', false);
        }
    }

    public function test_dropdown_mata_anggaran_spm_ls_bisa_dicari(): void
    {
        $this->masterAnggaran();
        $bp = $this->user('bendahara_pengeluaran', 'uji-bp-dropdown');

        $response = $this->actingAs($bp)->get(route('spm.ls.create'));

        // Program/Kegiatan/Sub Kegiatan dipilih sekali di kepala formulir;
        // Kode Rekening & Tagging dicetak per baris oleh JavaScript.
        $response->assertStatus(200);
        foreach (['ma-program', 'ma-kegiatan', 'ma-sub'] as $id) {
            $response->assertSee('id="'.$id.'" data-cari', false);
        }
        $response->assertSee('<select data-cari data-ma-kode>', false);
        $response->assertSee('<select data-cari data-ma-tagging disabled>', false);
    }
}
