<?php

namespace App\Services;

use App\Models\KebutuhanAnggaran;
use App\Models\Pkpt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Modul Estimasi Kebutuhan Kegiatan Pengawasan.
 *
 * Port dari getPKPTUnit()/simpanKebutuhan() di CodeKebutuhan.gs, dengan satu
 * perbedaan yang disengaja: SELURUH angka dihitung ulang di sini. GAS memakai
 * angka yang dikirim browser apa adanya - padahal jumlah uang harian, total
 * akomodasi, dan total estimasi semuanya turunan dari isian yang sudah
 * dikirim, jadi menerimanya dari klien hanya menambah cara untuk salah.
 */
class KebutuhanAnggaranService
{
    /**
     * Bahan formulir untuk satu unit: kegiatan PKPT yang belum terlaksana,
     * plus pilihan Area & Jenis Kegiatan yang pernah dipakai unit tsb.
     *
     * @return array{belum: array<int, array<string, mixed>>, area: array<int, string>, jenis: array<int, string>}
     */
    public function bahanFormulir(string $unit, ?int $tahun = null): array
    {
        $baris = Pkpt::query()->tahun($tahun)->where('unit_kerja', $unit)->get();

        return [
            'belum' => $baris
                ->where('terlaksana', false)
                ->sortBy(fn (Pkpt $p) => (float) preg_replace('/[^0-9.\-]/', '', (string) $p->nomor))
                ->map(fn (Pkpt $p) => [
                    'nomor' => (string) $p->nomor,
                    'area' => (string) $p->area,
                    'jenis' => (string) $p->jenis_kegiatan,
                    'estimasi' => (float) $p->estimasi_anggaran,
                    'rencana' => (string) $p->rencana_pelaksanaan,
                ])
                ->values()
                ->all(),
            'area' => $baris->pluck('area')->filter()->unique()->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values()->all(),
            'jenis' => $baris->pluck('jenis_kegiatan')->filter()->unique()->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values()->all(),
        ];
    }

    /**
     * Simpan beberapa kegiatan sekaligus. Unit kerja SELALU diambil dari
     * parameter (yang berasal dari role penyimpan), tidak pernah dari isian -
     * seorang Irban tidak bisa menuliskan kebutuhan atas nama unit lain.
     *
     * @param  array<int, array<string, mixed>>  $kegiatanList
     * @return int jumlah kegiatan tersimpan
     */
    public function simpan(User $user, string $unit, array $kegiatanList, ?int $tahun = null): int
    {
        $tahun ??= (int) config('anggaran.tahun_aktif');

        return DB::transaction(function () use ($user, $unit, $kegiatanList, $tahun) {
            foreach ($kegiatanList as $kegiatan) {
                $hitung = $this->hitungKegiatan($kegiatan);
                $dalamPkpt = ! (bool) ($kegiatan['luar_pkpt'] ?? false);

                $induk = KebutuhanAnggaran::create([
                    'tahun' => $tahun,
                    'unit_kerja' => $unit,
                    'user_id' => $user->id,
                    'dalam_pkpt' => $dalamPkpt,
                    'nomor_pkpt' => $dalamPkpt ? ($kegiatan['nomor_pkpt'] ?? null) : null,
                    'area' => $kegiatan['area'] ?? null,
                    'jenis_kegiatan' => $kegiatan['jenis_kegiatan'] ?? null,
                    'keterangan' => $dalamPkpt ? null : ($kegiatan['keterangan'] ?? null),
                    'tanggal_mulai' => $kegiatan['tanggal_mulai'],
                    'tanggal_selesai' => $kegiatan['tanggal_selesai'],
                    'tarif_uh_dalam' => $hitung['tarif_uh_dalam'],
                    'tarif_uh_luar' => $hitung['tarif_uh_luar'],
                    'total_uh_dalam' => $hitung['total_uh_dalam'],
                    'total_uh_luar' => $hitung['total_uh_luar'],
                    'total_akomodasi' => $hitung['total_akomodasi'],
                    'total_transport' => $hitung['total_transport'],
                    'total_estimasi' => $hitung['total_estimasi'],
                ]);

                $induk->rincian()->createMany($hitung['rincian']);
            }

            return count($kegiatanList);
        });
    }

    /**
     * Hitung ulang seluruh angka satu kegiatan dari isian mentahnya.
     *
     * @return array<string, mixed>
     */
    public function hitungKegiatan(array $kegiatan): array
    {
        $rincian = [];
        $totalUhDalam = 0.0;
        $totalUhLuar = 0.0;
        $totalAkomodasi = 0.0;
        $tarifDalam = [];
        $tarifLuar = [];

        foreach (array_values($kegiatan['rincian'] ?? []) as $i => $baris) {
            $hariDalam = (int) ($baris['hari_dalam'] ?? 0);
            $hariLuar = (int) ($baris['hari_luar'] ?? 0);
            $malam = (int) ($baris['jumlah_malam'] ?? 0);
            $tUhDalam = (float) ($baris['tarif_uh_dalam'] ?? 0);
            $tUhLuar = (float) ($baris['tarif_uh_luar'] ?? 0);
            // Tarif akomodasi boleh di luar daftar (isian manual); yang dipakai
            // adalah nilai manualnya begitu pilihan "Isi Manual" dipakai.
            $tAkom = (float) ($baris['tarif_akomodasi'] ?? 0);

            $jumlahUhDalam = $hariDalam * $tUhDalam;
            $jumlahUhLuar = $hariLuar * $tUhLuar;
            $jumlahAkom = $malam * $tAkom;

            $totalUhDalam += $jumlahUhDalam;
            $totalUhLuar += $jumlahUhLuar;
            $totalAkomodasi += $jumlahAkom;

            if ($tUhDalam > 0) {
                $tarifDalam[(string) $tUhDalam] = $tUhDalam;
            }
            if ($tUhLuar > 0) {
                $tarifLuar[(string) $tUhLuar] = $tUhLuar;
            }

            $rincian[] = [
                'urutan' => $i + 1,
                'jenis_anggota' => $baris['jenis_anggota'] ?? null,
                'jumlah_orang' => (int) ($baris['jumlah_orang'] ?? 0),
                'hari_dalam' => $hariDalam,
                'tarif_uh_dalam' => $tUhDalam,
                'jumlah_uh_dalam' => $jumlahUhDalam,
                'hari_luar' => $hariLuar,
                'tarif_uh_luar' => $tUhLuar,
                'jumlah_uh_luar' => $jumlahUhLuar,
                'jumlah_malam' => $malam,
                'tarif_akomodasi' => $tAkom,
                'total_akomodasi' => $jumlahAkom,
                'estimasi_kebutuhan' => $jumlahUhDalam + $jumlahUhLuar + $jumlahAkom,
            ];
        }

        $totalTransport = (float) ($kegiatan['total_transport'] ?? 0);

        return [
            'rincian' => $rincian,
            'tarif_uh_dalam' => $this->gabungTarif($tarifDalam),
            'tarif_uh_luar' => $this->gabungTarif($tarifLuar),
            'total_uh_dalam' => $totalUhDalam,
            'total_uh_luar' => $totalUhLuar,
            'total_akomodasi' => $totalAkomodasi,
            'total_transport' => $totalTransport,
            'total_estimasi' => $totalUhDalam + $totalUhLuar + $totalAkomodasi + $totalTransport,
        ];
    }

    /**
     * Tarif yang dipakai kegiatan, dari kecil ke besar: "100.000; 170.000".
     * Kosong kalau tidak ada tarif sama sekali.
     *
     * @param  array<string, float>  $tarif
     */
    private function gabungTarif(array $tarif): ?string
    {
        if ($tarif === []) {
            return null;
        }

        sort($tarif);

        return implode('; ', array_map(fn (float $t) => number_format($t, 0, ',', '.'), $tarif));
    }
}
