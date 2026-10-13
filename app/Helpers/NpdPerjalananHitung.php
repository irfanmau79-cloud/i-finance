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
     * Nominal BBM satu anggota, dalam rupiah.
     *
     * Dua cara pengisian hidup berdampingan:
     *
     *  - CARA SEKARANG: yang diketik adalah Total Nominal BBM (bbm_nominal),
     *    dan itulah nominalnya. Liter diturunkan darinya - lihat literBbm().
     *  - CARA LAMA: yang diketik liter dan tarif; nominalnya liter x tarif.
     *    NPD yang dibuat sebelum perubahan ini tidak punya bbm_nominal, jadi
     *    tetap dihitung dengan cara ini dan cetakannya tidak berubah.
     *
     * Jenis BBM tambahan ("Tambah Jenis BBM") ikut dijumlahkan - lihat
     * rincianBbm().
     *
     * @param  array{bbm_nominal?: mixed, bbm_liter?: mixed, bbm_tarif?: mixed, bbm_lain?: mixed}  $anggota
     */
    public static function bbm(array $anggota): float
    {
        return (float) array_sum(array_column(self::rincianBbm($anggota), 'nominal'));
    }

    /**
     * Jumlah liter BBM satu anggota, seluruh jenis BBM-nya. Pada cara
     * sekarang liter TIDAK diketik: ia Total Nominal BBM dibagi Tarif BBM
     * per liter.
     *
     * @param  array{bbm_nominal?: mixed, bbm_liter?: mixed, bbm_tarif?: mixed, bbm_lain?: mixed}  $anggota
     */
    public static function literBbm(array $anggota): float
    {
        return (float) array_sum(array_column(self::rincianBbm($anggota), 'liter'));
    }

    /**
     * Liter jenis BBM PERTAMA saja - nilai kolom npd_tim.bbm_liter. Liter
     * jenis tambahan tidak disimpan; selalu dihitung dari nominal : tarif.
     *
     * @param  array{bbm_nominal?: mixed, bbm_liter?: mixed, bbm_tarif?: mixed}  $anggota
     */
    public static function literBbmUtama(array $anggota): float
    {
        if (! self::memakaiNominalBbm($anggota)) {
            return (float) ($anggota['bbm_liter'] ?? 0);
        }

        $tarif = (float) ($anggota['bbm_tarif'] ?? 0);

        return $tarif > 0 ? (float) $anggota['bbm_nominal'] / $tarif : 0.0;
    }

    /**
     * Rincian BBM satu anggota per jenis (tarif): jenis pertama dari
     * bbm_nominal/bbm_liter/bbm_tarif, disusul tambahan dari "Tambah Jenis
     * BBM" (bbm_lain). Jenis yang nominalnya 0 tidak ikut.
     *
     * `liter_diketik` menandai liter cara lama, yang dicetak apa adanya;
     * selain itu liternya hasil bagi nominal : tarif.
     *
     * @param  array{bbm_nominal?: mixed, bbm_liter?: mixed, bbm_tarif?: mixed, bbm_lain?: mixed}  $anggota
     * @return list<array{nominal: float, tarif: float, liter: float, liter_diketik: bool}>
     */
    public static function rincianBbm(array $anggota): array
    {
        $rincian = [];
        $tarif = (float) ($anggota['bbm_tarif'] ?? 0);

        if (self::memakaiNominalBbm($anggota)) {
            $nominal = round((float) $anggota['bbm_nominal']);
            $utama = [
                'nominal' => $nominal, 'tarif' => $tarif,
                'liter' => $tarif > 0 ? (float) $anggota['bbm_nominal'] / $tarif : 0.0,
                'liter_diketik' => false,
            ];
        } else {
            $liter = (float) ($anggota['bbm_liter'] ?? 0);
            $utama = [
                'nominal' => ($liter > 0 && $tarif > 0) ? round($liter * $tarif) : 0.0, 'tarif' => $tarif,
                'liter' => $liter,
                'liter_diketik' => true,
            ];
        }

        if ($utama['nominal'] > 0) {
            $rincian[] = $utama;
        }

        foreach (self::bbmLain($anggota) as $lain) {
            $rincian[] = $lain + [
                'liter' => $lain['tarif'] > 0 ? $lain['nominal'] / $lain['tarif'] : 0.0,
                'liter_diketik' => false,
            ];
        }

        return $rincian;
    }

    /**
     * Jenis BBM tambahan yang benar-benar terisi (nominal > 0), sudah
     * dirapikan. Menerima larik dari formulir/model maupun teks JSON mentah
     * dari potret revisi.
     *
     * @param  array{bbm_lain?: mixed}  $anggota
     * @return list<array{nominal: float, tarif: float}>
     */
    public static function bbmLain(array $anggota): array
    {
        $lain = $anggota['bbm_lain'] ?? [];

        if (is_string($lain)) {
            $lain = json_decode($lain, true);
        }

        $hasil = [];

        foreach (is_array($lain) ? $lain : [] as $baris) {
            $nominal = round((float) (is_array($baris) ? ($baris['nominal'] ?? 0) : 0));

            if ($nominal > 0) {
                $hasil[] = ['nominal' => $nominal, 'tarif' => (float) ($baris['tarif'] ?? 0)];
            }
        }

        return $hasil;
    }

    /** Tarif BBM untuk dicetak: "Rp15.950/liter" (desimal hanya bila ada). */
    public static function formatTarifBbm(float $tarif): string
    {
        $desimal = round($tarif, 2) === round($tarif) ? 0 : 2;

        return 'Rp'.number_format($tarif, $desimal, ',', '.').'/liter';
    }

    /** @param  array{bbm_nominal?: mixed}  $anggota */
    public static function memakaiNominalBbm(array $anggota): bool
    {
        $nominal = $anggota['bbm_nominal'] ?? null;

        return $nominal !== null && $nominal !== '';
    }

    /**
     * @param  array{paket?: array[], bbm_nominal?: mixed, bbm_liter?: mixed, bbm_tarif?: mixed, tol?: mixed, tiket?: mixed, representatif?: mixed}  $anggota
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

        $bbm = self::bbm($anggota);

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
