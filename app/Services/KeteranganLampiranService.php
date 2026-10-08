<?php

namespace App\Services;

use App\Helpers\NpdPerjalananHitung;
use Carbon\Carbon;

/**
 * Perakit Uraian (Keterangan) pada Lampiran NPD.
 *
 * Teks ini tercetak di dokumen yang ditandatangani, jadi ia hanya boleh punya
 * SATU implementasi. Sebelumnya kalimatnya dirangkai langsung di dalam
 * NpdController saat menyiapkan PDF; sejak formulir menampilkan pratinjaunya,
 * perakitnya dipindah ke sini supaya yang dilihat petugas di layar dan yang
 * keluar di PDF berasal dari kode yang sama persis. Pratinjau yang menyimpang
 * dari hasil cetak lebih berbahaya daripada tidak ada pratinjau sama sekali.
 *
 * Seluruh method bekerja di atas array biasa - bukan Eloquent - supaya bisa
 * dipanggil baik dari NPD yang sudah tersimpan maupun dari isian formulir yang
 * belum disimpan.
 */
class KeteranganLampiranService
{
    /**
     * Kode rekening Belanja Perjalanan Dinas DALAM KOTA.
     *
     * Uraian pada dokumen mengutip nama mata anggarannya, jadi NPD yang
     * dibebankan ke rekening ini berbunyi "Belanja Perjalanan Dinas Dalam
     * Kota", bukan "... Biasa". Dipetakan dari KODE, bukan dari nama
     * rekening: namanya teks bebas hasil import dan bisa berubah ejaannya,
     * sedangkan dokumen yang sudah ditandatangani tidak boleh ikut berubah
     * bunyinya. Kode lain tetap memakai frasa "Biasa" seperti sebelumnya.
     */
    public const KODE_REKENING_DALAM_KOTA = '5.1.02.04.001.00003';

    public const BELANJA_BIASA = 'Belanja Perjalanan Dinas Biasa';

    public const BELANJA_DALAM_KOTA = 'Belanja Perjalanan Dinas Dalam Kota';

    public static function frasaBelanja(?string $kodeRekeningBersih): string
    {
        return trim((string) $kodeRekeningBersih) === self::KODE_REKENING_DALAM_KOTA
            ? self::BELANJA_DALAM_KOTA
            : self::BELANJA_BIASA;
    }

    public static function tanggalIndo(?string $tanggal): string
    {
        return $tanggal ? Carbon::parse($tanggal)->translatedFormat('d F Y') : '';
    }

    /**
     * Komponen biaya yang benar-benar terpakai pada satu tim perjalanan,
     * dirangkai jadi frasa: "uang harian", "uang harian dan akomodasi",
     * "uang harian, akomodasi dan transport", dan seterusnya.
     *
     * @param  array<int, array<string, mixed>>  $tim
     * @return array{komp_str: string, uraian_biaya: string}
     */
    public static function komponenPd(array $tim, ?string $kodeRekeningBersih = null): array
    {
        $totUh = 0.0;
        $totAk = 0.0;
        $totTr = 0.0;
        $totRp = 0.0;

        foreach ($tim as $anggota) {
            $h = NpdPerjalananHitung::hitungAnggota($anggota);
            $totUh += $h['jml_harian'];
            $totAk += $h['jml_akom'];
            $totTr += $h['jml_transport'];
            $totRp += $h['representatif'];
        }

        $komp = [];
        if ($totUh > 0) {
            $komp[] = 'uang harian';
        }
        if ($totAk > 0) {
            $komp[] = 'akomodasi';
        }
        if ($totTr > 0) {
            $komp[] = 'transport';
        }
        if ($totRp > 0) {
            $komp[] = 'uang representatif';
        }

        $kompStr = match (true) {
            count($komp) === 1 => $komp[0],
            count($komp) > 1 => implode(', ', array_slice($komp, 0, -1)).' dan '.end($komp),
            default => '',
        };

        return [
            'komp_str' => $kompStr,
            'uraian_biaya' => 'Pembayaran '.self::frasaBelanja($kodeRekeningBersih).($kompStr !== '' ? " ({$kompStr})" : ''),
        ];
    }

    /**
     * Uraian baku Lampiran NPD Perjalanan Dinas dan Transport.
     *
     * @param  array<string, mixed>  $detail
     * @param  array<int, array<string, mixed>>  $tim
     */
    public static function pd(array $detail, array $tim, string $namaPenerima, ?string $kodeRekeningBersih = null): string
    {
        $kompStr = self::komponenPd($tim)['komp_str'];

        return 'Transfer Pembayaran '.self::frasaBelanja($kodeRekeningBersih)
            .($kompStr !== '' ? " ({$kompStr})" : '')
            .' terhitung tanggal '.self::tanggalIndo($detail['tanggal_berangkat'] ?? null)
            .' s.d '.self::tanggalIndo($detail['tanggal_pulang'] ?? null)
            .' dalam rangka '.($detail['uraian_sp'] ?? '')
            .', berdasarkan Surat Perintah Nomor: '.($detail['nomor_sp'] ?? '')
            .' tanggal '.self::tanggalIndo($detail['tanggal_sp'] ?? null)
            .' an. '.$namaPenerima;
    }

    /**
     * Uraian baku Lampiran NPD Kontribusi Diklat. Kalimat pembukanya berbeda
     * antara mode Kontribusi dan mode Perjalanan Dinas.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function kd(array $detail, ?string $modeKd, string $atasNama): string
    {
        $periode = 'terhitung tanggal '.self::tanggalIndo($detail['tanggal_mulai'] ?? null)
            .' s.d '.self::tanggalIndo($detail['tanggal_selesai'] ?? null);

        $pembuka = $modeKd === 'perjalanan'
            ? 'Transfer Pembayaran Belanja Perjalanan Dinas'
            : 'Transfer Pembayaran Belanja Kontribusi Diklat';

        return $pembuka
            .' dalam rangka Mengikuti '.($detail['nama_pelatihan'] ?? '')
            .' '.$periode
            .' an. '.$atasNama;
    }

    /**
     * Nama yang disebut di belakang "an." pada NPD Kontribusi Diklat, SATU
     * per baris Lampiran: tiap penerima transfer mendapat barisnya sendiri,
     * dan Uraian baris itu hanya menyebut nama penerimanya - bukan seluruh
     * penerima digabung (keputusan Irfan, Oktober 2026: baris Fajar Lazuardi
     * berbunyi "an. FAJAR LAZUARDI", bukan "an. AGUS SURYANA, FAJAR LAZUARDI").
     *
     * Tanpa daftar penerima transfer (mode Kontribusi, atau NPD lama) hasilnya
     * satu nama: penerima tunggalnya. Nama kosong dilewati.
     *
     * @param  array<int, array<string, mixed>>  $penerimaTransfer
     * @return array<int, string>
     */
    public static function atasNamaKd(array $penerimaTransfer, string $penerimaTunggal): array
    {
        $nama = array_values(array_filter(array_map(
            fn ($p) => trim((string) ($p['nama'] ?? '')),
            $penerimaTransfer
        ), fn ($n) => $n !== ''));

        return $nama !== [] ? $nama : [$penerimaTunggal];
    }
}
