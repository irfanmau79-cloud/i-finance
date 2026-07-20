<?php

namespace App\Helpers;

/**
 * Port 1:1 dari _hitungAnggota() di gas-lama/CodePerjalanan.gs. Bekerja di
 * atas array biasa (bukan Eloquent) supaya perhitungan identik dipakai baik
 * saat menyimpan (dari request tervalidasi) maupun saat cetak PDF (dari
 * npd_tim/npd_tim_paket yang sudah tersimpan, dikonversi ke bentuk array
 * yang sama lebih dulu).
 */
class NpdPerjalananHitung
{
    /** @param  array{lama_hari?: mixed, tarif_uh?: mixed}  $paket */
    public static function subUh(array $paket): float
    {
        return (float) ($paket['lama_hari'] ?? 0) * (float) ($paket['tarif_uh'] ?? 0);
    }

    /** @param  array{malam?: mixed, tarif_akom?: mixed}  $paket */
    public static function subAkom(array $paket): float
    {
        return (float) ($paket['malam'] ?? 0) * (float) ($paket['tarif_akom'] ?? 0);
    }

    /**
     * @param  array{paket?: array[], bbm_liter?: mixed, bbm_tarif?: mixed, tol?: mixed, tiket?: mixed, representatif?: mixed}  $anggota
     * @return array{paket: array[], jml_harian: float, jml_akom: float, bbm: float, tol: float, tiket: float, jml_transport: float, representatif: float, jumlah: float}
     */
    public static function hitungAnggota(array $anggota): array
    {
        $jmlHarian = 0.0;
        $jmlAkom = 0.0;
        $detailPaket = [];

        foreach (($anggota['paket'] ?? []) as $p) {
            $subUh = self::subUh($p);
            $subAkom = self::subAkom($p);
            $jmlHarian += $subUh;
            $jmlAkom += $subAkom;
            $detailPaket[] = $p + ['sub_uh' => $subUh, 'sub_akom' => $subAkom];
        }

        $bbmLiter = (float) ($anggota['bbm_liter'] ?? 0);
        $bbmTarif = (float) ($anggota['bbm_tarif'] ?? 0);
        $bbm = ($bbmLiter > 0 && $bbmTarif > 0) ? round($bbmLiter * $bbmTarif) : 0.0;

        $tol = (float) ($anggota['tol'] ?? 0);
        $tiket = (float) ($anggota['tiket'] ?? 0);
        $representatif = (float) ($anggota['representatif'] ?? 0);
        $jmlTransport = $bbm + $tol + $tiket;

        return [
            'paket' => $detailPaket,
            'jml_harian' => $jmlHarian,
            'jml_akom' => $jmlAkom,
            'bbm' => $bbm,
            'tol' => $tol,
            'tiket' => $tiket,
            'jml_transport' => $jmlTransport,
            'representatif' => $representatif,
            'jumlah' => $jmlHarian + $jmlAkom + $jmlTransport + $representatif,
        ];
    }

    /** Total nominal NPD = jumlah seluruh anggota tim. */
    public static function totalTim(array $tim): float
    {
        $total = 0.0;

        foreach ($tim as $anggota) {
            $total += self::hitungAnggota($anggota)['jumlah'];
        }

        return $total;
    }
}
