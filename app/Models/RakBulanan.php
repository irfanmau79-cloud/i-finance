<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = target Rencana Anggaran Kas (RAK) untuk satu Kode Rekening
 * dalam satu Sub Kegiatan pada satu bulan tertentu (nilai BULANAN, bukan
 * kumulatif - lihat migration create_rak_bulanan_table).
 *
 * Sejak Prompt 12A, identitas RAK adalah
 * (tahun, sub_kegiatan_kunci, kode_rekening, bulan) - TIDAK melibatkan
 * Tagging maupun master_anggaran_id. Satu Sub Kegiatan + Kode Rekening bisa
 * punya banyak baris master_anggaran (satu per Tagging, untuk kebutuhan
 * dana terikat/realisasi per NPD), tapi semuanya berbagi SATU rangkaian
 * RAK Jan-Des yang sama.
 */
#[Fillable(['sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening', 'tahun', 'bulan', 'target'])]
class RakBulanan extends Model
{
    protected $table = 'rak_bulanan';

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rak) {
            $rak->sub_kegiatan_kunci = MasterAnggaran::normalisasiKunci($rak->sub_kegiatan);
        });
    }

    /**
     * Pagu gabungan lintas Tagging untuk KONTEKS TAMPILAN saja (bukan bagian
     * dari identitas/perhitungan RAK) - jumlah pagu seluruh baris
     * master_anggaran aktif pada Sub Kegiatan + Kode Rekening yang sama.
     */
    public static function paguGabungan(string $subKegiatanKunci, string $kodeRekening): float
    {
        return (float) MasterAnggaran::where('sub_kegiatan_kunci', $subKegiatanKunci)
            ->where('kode_rekening_bersih', $kodeRekening)
            ->where('aktif', true)
            ->sum('pagu');
    }
}
