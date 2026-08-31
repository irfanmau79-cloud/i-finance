<?php

namespace Tests\Unit;

use App\Support\NamaProper;
use PHPUnit\Framework\TestCase;

/**
 * Proper Case pada Surat Keterangan Penghasilan - port _gtProper() di
 * CodeGajiTunjangan.gs (perubahan 15 & 16 di README_PERUBAHAN.txt).
 */
class NamaProperTest extends TestCase
{
    public function test_gelar_setelah_koma_dibiarkan_apa_adanya(): void
    {
        // Inilah alasan fungsi ini bukan ucwords(): "S.Ak." tidak boleh
        // berubah menjadi "S.ak." atau "S.AK.".
        $this->assertSame('Irfan Maulana, S.Ak.', NamaProper::format('IRFAN MAULANA, S.Ak.'));
        $this->assertSame('Verri Riyanti, M.S.P.', NamaProper::format('VERRI RIYANTI, M.S.P.'));
        $this->assertSame('Elyna S. Laura Siahaan, S.K.p.,MH', NamaProper::format('ELYNA S. LAURA SIAHAAN, S.K.p.,MH'));
    }

    public function test_konjungsi_tetap_huruf_kecil_kecuali_di_awal(): void
    {
        $this->assertSame('Pengolah Data dan Informasi', NamaProper::format('PENGOLAH DATA DAN INFORMASI'));
        $this->assertSame('Kepala Subbagian Tata Usaha', NamaProper::format('KEPALA SUBBAGIAN TATA USAHA'));

        // Kata sambung yang kebetulan menjadi kata pertama tetap kapital.
        $this->assertSame('Dari Bidang Pengawasan', NamaProper::format('DARI BIDANG PENGAWASAN'));
    }

    public function test_teks_kosong_dan_null_aman(): void
    {
        $this->assertSame('', NamaProper::format(null));
        $this->assertSame('', NamaProper::format(''));
    }
}
