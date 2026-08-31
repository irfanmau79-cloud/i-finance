<?php

namespace Tests\Unit;

use App\Support\UrutanPegawaiGaji;
use PHPUnit\Framework\TestCase;

/**
 * Pengurutan daftar pegawai Data Gaji & Tunjangan - port _gtCmp() di
 * CodeGajiTunjangan.gs (perubahan 7 di README_PERUBAHAN.txt).
 */
class UrutanPegawaiGajiTest extends TestCase
{
    public function test_skor_golongan_pns_menurun_dari_iv_e_ke_i_a(): void
    {
        $this->assertSame(45, UrutanPegawaiGaji::skorGolPns('IV/e'));
        $this->assertSame(43, UrutanPegawaiGaji::skorGolPns('IV/c'));
        $this->assertSame(31, UrutanPegawaiGaji::skorGolPns('III/a'));
        $this->assertSame(11, UrutanPegawaiGaji::skorGolPns('I/a'));

        // Huruf besar-kecil, spasi, dan tanda garis miring yang hilang tetap terbaca.
        $this->assertSame(43, UrutanPegawaiGaji::skorGolPns(' iv/C '));
        $this->assertSame(41, UrutanPegawaiGaji::skorGolPns('IV'));

        // Golongan yang tidak dikenali tidak boleh menaikkan urutan.
        $this->assertSame(0, UrutanPegawaiGaji::skorGolPns('-'));
        $this->assertSame(0, UrutanPegawaiGaji::skorGolPns(''));
    }

    public function test_grade_pppk_dibaca_sebagai_romawi_murni(): void
    {
        // Golongan PPPK pada berkas SIPD berbentuk romawi tanpa huruf,
        // mis. "IX" dan "VII" - bukan "III/a".
        $this->assertSame(9, UrutanPegawaiGaji::skorGradePppk('IX'));
        $this->assertSame(7, UrutanPegawaiGaji::skorGradePppk('VII'));
        $this->assertSame(0, UrutanPegawaiGaji::skorGradePppk('IV/c'));
    }

    public function test_pns_didahulukan_dan_pppk_selalu_di_akhir(): void
    {
        $hasil = UrutanPegawaiGaji::urutkan([
            ['nama' => 'PPPK Grade IX', 'status' => 'PPPK', 'gol' => 'IX', 'nip' => '199001011990011001'],
            ['nama' => 'PNS III/a', 'status' => 'PNS', 'gol' => 'III/a', 'nip' => '198001011990011001'],
            ['nama' => 'PNS IV/c', 'status' => 'PNS', 'gol' => 'IV/c', 'nip' => '196601011990011001'],
        ]);

        $this->assertSame(['PNS IV/c', 'PNS III/a', 'PPPK Grade IX'], array_column($hasil, 'nama'));
    }

    public function test_golongan_sama_diurut_nip_terlama_lebih_dulu(): void
    {
        // NIP diawali tanggal lahir YYYYMMDD, jadi NIP menaik berarti yang
        // lebih tua lebih dulu.
        $hasil = UrutanPegawaiGaji::urutkan([
            ['nama' => 'Lahir 1968', 'status' => 'PNS', 'gol' => 'IV/c', 'nip' => '196801191997032005'],
            ['nama' => 'Lahir 1966', 'status' => 'PNS', 'gol' => 'IV/c', 'nip' => '196611041990032003'],
            ['nama' => 'Lahir 1967', 'status' => 'PNS', 'gol' => 'IV/c', 'nip' => '196706161989021001'],
        ]);

        $this->assertSame(['Lahir 1966', 'Lahir 1967', 'Lahir 1968'], array_column($hasil, 'nama'));
    }

    public function test_pppk_diurut_grade_tertinggi_lebih_dulu(): void
    {
        $hasil = UrutanPegawaiGaji::urutkan([
            ['nama' => 'Grade VII', 'status' => 'PPPK', 'gol' => 'VII', 'nip' => '199001011990011001'],
            ['nama' => 'Grade IX', 'status' => 'PPPK', 'gol' => 'IX', 'nip' => '199501011990011001'],
        ]);

        $this->assertSame(['Grade IX', 'Grade VII'], array_column($hasil, 'nama'));
    }

    public function test_status_pppk_dikenali_dari_potongan_teks(): void
    {
        // Master pegawai memakai "PPPK Penuh Waktu" / "PPPK Paruh Waktu",
        // berkas SIPD memakai "PPPK" saja. Keduanya harus terbaca sama.
        $this->assertTrue(UrutanPegawaiGaji::isPppk('PPPK'));
        $this->assertTrue(UrutanPegawaiGaji::isPppk('PPPK Penuh Waktu'));
        $this->assertFalse(UrutanPegawaiGaji::isPppk('PNS'));
    }
}
