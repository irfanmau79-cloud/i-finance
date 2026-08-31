<?php

namespace App\Services;

use App\Helpers\AuditLog;
use App\Imports\RawSheetImport;
use App\Models\GajiImport;
use App\Models\GajiInduk;
use App\Models\Tpp;
use App\Support\GajiTunjanganKolom;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

/**
 * Import berkas SIPD Gaji Induk / TPP Beban Kerja / TPP Kondisi Kerja.
 *
 * Alur preview/dry-run seperti import lain di sistem ini: unggah hanya
 * membuat batch staging, tabel tujuan baru tersentuh setelah Konfirmasi.
 *
 * Jenis penghasilan, bulan, dan tahun datang dari DROPDOWN, bukan dari isi
 * berkas - berkas SIPD tidak punya kolom bulan/tahun (di GAS keduanya
 * diketik manual ke sheet). Jadi berkasnya bisa diunggah apa adanya.
 *
 * Konfirmasi bersifat MENIMPA: seluruh baris untuk kombinasi jenis + bulan +
 * tahun yang sama dihapus lebih dulu, lalu diisi ulang dari berkas. Dengan
 * begitu mengunggah ulang bulan yang sama memperbaiki data, bukan
 * menggandakannya.
 */
class GajiTunjanganImportService
{
    public function preview(UploadedFile $file, string $jenis, int $bulan, int $tahun, int $userId): GajiImport
    {
        $reader = new RawSheetImport(1);
        Excel::import($reader, $file);
        $rows = $reader->rows;

        if ($rows->isEmpty()) {
            throw new RuntimeException('Berkas import kosong.');
        }

        $headers = collect($rows->shift());
        $this->pastikanSusunanKolom($headers, $jenis);

        $definisi = GajiTunjanganKolom::definisi($jenis);

        return DB::transaction(function () use ($rows, $definisi, $jenis, $bulan, $tahun, $file, $userId) {
            $import = GajiImport::create([
                'user_id' => $userId,
                'nama_file' => $file->getClientOriginalName(),
                'jenis' => $jenis,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'status' => 'preview',
                'baris_tertimpa' => $this->jumlahTerpakai($jenis, $bulan, $tahun),
            ]);

            $valid = $invalid = 0;
            /** @var array<string, int> $nipTerlihat NIP -> nomor baris pertama */
            $nipTerlihat = [];

            foreach ($rows as $index => $row) {
                $nilai = collect($row)->values();

                if ($nilai->filter(fn ($v) => filled($v))->isEmpty()) {
                    continue;
                }

                $nomorBaris = $index + 2;
                $hasil = $this->evaluasi($nilai, $definisi, $jenis, $bulan, $tahun, $nomorBaris, $nipTerlihat);

                $hasil['valid'] ? $valid++ : $invalid++;

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'valid' => $hasil['valid'],
                    'nama_pegawai' => $hasil['payload']['nama_pegawai'] ?? null,
                    'nip' => $hasil['payload']['nip'] ?? null,
                    'pesan' => $hasil['pesan'],
                    'payload' => $hasil['payload'],
                ]);
            }

            if ($valid + $invalid === 0) {
                throw new RuntimeException('Berkas tidak memuat satu pun baris data.');
            }

            $import->update([
                'total_baris' => $valid + $invalid,
                'baris_valid' => $valid,
                'baris_invalid' => $invalid,
            ]);

            return $import->load('baris');
        });
    }

    /**
     * Simpan batch ke tabel tujuan. Menimpa seluruh data periode yang sama.
     *
     * @return int jumlah baris yang tersimpan
     */
    public function confirm(GajiImport $import): int
    {
        return DB::transaction(function () use ($import) {
            $import = GajiImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($import->status !== 'preview') {
                throw new RuntimeException('Batch import ini sudah pernah dikonfirmasi.');
            }

            if ($import->baris()->where('valid', false)->exists()) {
                throw new RuntimeException('Import tidak dapat dikonfirmasi selama masih ada baris bermasalah.');
            }

            $dihapus = $this->hapusPeriode($import->jenis, $import->bulan, $import->tahun);

            $model = $import->jenis === 'gaji' ? GajiInduk::class : Tpp::class;
            $jumlah = 0;

            foreach ($import->baris()->orderBy('nomor_baris')->cursor() as $baris) {
                $model::create($baris->payload);
                $jumlah++;
            }

            $import->update(['status' => 'committed', 'committed_at' => now()]);

            AuditLog::catat(
                'Import Data Gaji & Tunjangan',
                sprintf(
                    'Batch #%d: %s %s - %d baris disimpan%s (berkas: %s)',
                    $import->id,
                    $import->labelJenis(),
                    $import->labelPeriode(),
                    $jumlah,
                    $dihapus > 0 ? ", {$dihapus} baris lama ditimpa" : '',
                    $import->nama_file
                )
            );

            return $jumlah;
        });
    }

    /** Jumlah baris yang sudah tersimpan untuk sebuah periode. */
    public function jumlahTerpakai(string $jenis, int $bulan, int $tahun): int
    {
        return $jenis === 'gaji'
            ? GajiInduk::query()->where('bulan', $bulan)->where('tahun', $tahun)->count()
            : Tpp::query()->where('jenis', $jenis)->where('bulan', $bulan)->where('tahun', $tahun)->count();
    }

    private function hapusPeriode(string $jenis, int $bulan, int $tahun): int
    {
        return $jenis === 'gaji'
            ? GajiInduk::query()->where('bulan', $bulan)->where('tahun', $tahun)->delete()
            : Tpp::query()->where('jenis', $jenis)->where('bulan', $bulan)->where('tahun', $tahun)->delete();
    }

    /**
     * Penjaga susunan kolom. Jumlah kolom harus tepat dan setiap header harus
     * berada di posisi yang sama seperti template SIPD.
     *
     * Pemeriksaan ini yang membuat pemetaan berbasis posisi aman: kalau SIPD
     * suatu saat menyisipkan atau memindahkan kolom, import berhenti di sini
     * dengan pesan yang menyebut kolomnya - bukan menyimpan angka ke kolom
     * yang salah tanpa ada yang menyadari.
     *
     * @param  Collection<int, mixed>  $headers
     */
    private function pastikanSusunanKolom(Collection $headers, string $jenis): void
    {
        $harapan = GajiTunjanganKolom::header($jenis);
        $label = GajiTunjanganKolom::JENIS[$jenis];

        // Ekor kolom kosong (sering terbawa saat berkas disunting) dibuang
        // dulu supaya tidak dianggap kolom asing.
        $ada = $headers->values()->all();

        while ($ada !== [] && ! filled(end($ada))) {
            array_pop($ada);
        }

        if (count($ada) !== count($harapan)) {
            throw new RuntimeException(sprintf(
                'Berkas %s harus memiliki %d kolom, berkas ini punya %d kolom. Gunakan berkas Template SIPD apa adanya.',
                $label, count($harapan), count($ada)
            ));
        }

        $beda = [];

        foreach ($harapan as $index => $nama) {
            if (GajiTunjanganKolom::normalHeader($ada[$index] ?? '') !== GajiTunjanganKolom::normalHeader($nama)) {
                $beda[] = sprintf(
                    'kolom %s seharusnya "%s" tetapi tertulis "%s"',
                    $this->hurufKolom($index), $nama, trim((string) ($ada[$index] ?? ''))
                );
            }
        }

        if ($beda !== []) {
            throw new RuntimeException(sprintf(
                'Susunan kolom berkas %s tidak sesuai template SIPD: %s.',
                $label, implode('; ', array_slice($beda, 0, 3)).(count($beda) > 3 ? '; dan '.(count($beda) - 3).' kolom lain' : '')
            ));
        }
    }

    /**
     * Ubah satu baris berkas menjadi payload siap simpan + daftar masalahnya.
     *
     * @param  Collection<int, mixed>  $nilai
     * @param  array<int, array{0: string, 1: string}>  $definisi
     * @param  array<string, int>  $nipTerlihat
     * @return array{valid: bool, pesan: array<int, string>, payload: array<string, mixed>}
     */
    private function evaluasi(
        Collection $nilai,
        array $definisi,
        string $jenis,
        int $bulan,
        int $tahun,
        int $nomorBaris,
        array &$nipTerlihat
    ): array {
        $pesan = [];
        $payload = ['bulan' => $bulan, 'tahun' => $tahun];

        if ($jenis !== 'gaji') {
            $payload['jenis'] = $jenis;
        }

        foreach ($definisi as $index => [$kolom, $tipe]) {
            $mentah = $nilai->get($index);

            // Koperasi Praja & Zakat hanya berlaku di TPP Beban Kerja. Pada
            // berkas Kondisi Kerja kolomnya ada tetapi diabaikan.
            if ($jenis === 'kondisi' && in_array($kolom, GajiTunjanganKolom::KOLOM_KHUSUS_BEBAN, true)) {
                $payload[$kolom] = null;

                continue;
            }

            switch ($tipe) {
                case 'teks':
                    $payload[$kolom] = $this->teks($mentah);
                    break;

                case 'tanggal':
                    $payload[$kolom] = $this->tanggal($mentah);
                    break;

                case 'cacah':
                case 'uang':
                case 'persen':
                    $angka = $this->angka($mentah);

                    if ($angka === null) {
                        $pesan[] = sprintf('Kolom "%s" bukan angka: "%s".', $kolom, $this->teks($mentah));
                        $payload[$kolom] = 0;
                        break;
                    }

                    $payload[$kolom] = $tipe === 'cacah' ? (int) round($angka) : $angka;
                    break;
            }
        }

        // --- Aturan wajib isi ---
        if (($payload['nama_pegawai'] ?? '') === '') {
            $pesan[] = 'Nama pegawai kosong.';
        }

        $nip = preg_replace('/\D/', '', (string) ($payload['nip'] ?? '')) ?? '';

        if ($nip === '') {
            $pesan[] = 'NIP kosong atau tidak memuat angka.';
        } elseif (isset($nipTerlihat[$nip])) {
            $pesan[] = sprintf('NIP ganda dengan baris %d.', $nipTerlihat[$nip]);
        } else {
            $nipTerlihat[$nip] = $nomorBaris;
        }

        $payload['nip'] = $nip !== '' ? $nip : $this->teks($payload['nip'] ?? '');

        // Nilai Kinerja wajib ada. Kalau dibiarkan kosong, kolom "Prosentase
        // Kinerja" di layar tampil 0% dan terbaca seperti data rusak - bukan
        // seperti kolom yang memang belum diisi.
        if ($jenis !== 'gaji' && ! filled($nilai->get($this->indexKolom($jenis, 'nilai_kinerja')))) {
            $pesan[] = 'Kolom "nilai kinerja" wajib diisi.';
        }

        return ['valid' => $pesan === [], 'pesan' => $pesan, 'payload' => $payload];
    }

    /** Posisi sebuah kolom tabel di dalam berkas. */
    private function indexKolom(string $jenis, string $kolom): int
    {
        foreach (GajiTunjanganKolom::definisi($jenis) as $index => [$nama]) {
            if ($nama === $kolom) {
                return $index;
            }
        }

        return -1;
    }

    private function teks(mixed $nilai): string
    {
        return trim((string) ($nilai ?? ''));
    }

    /**
     * Angka dari sel. Kosong dianggap 0 - kesepakatan: sel kosong berarti
     * tidak ada nominal, bukan berkas rusak. Hanya isi yang bukan angka yang
     * membuat baris ditolak.
     */
    private function angka(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '') {
            return 0.0;
        }

        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        // Angka bisa datang sebagai teks berformat Indonesia ("1.234.567,89")
        // bila berkasnya pernah disunting dan disimpan ulang.
        $bersih = str_replace([' ', '.'], '', trim((string) $nilai));
        $bersih = str_replace(',', '.', $bersih);

        return is_numeric($bersih) ? (float) $bersih : null;
    }

    /**
     * Tanggal lahir. Kedua format yang benar-benar muncul di berkas SIPD
     * ditangani: serial Excel (berkas Gaji Induk) dan teks "dd-mm-yyyy"
     * (berkas TPP). Yang tidak terbaca disimpan null - tanggal lahir tidak
     * dipakai di modul ini, jadi tidak layak menggagalkan seluruh baris.
     */
    private function tanggal(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $nilai))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim((string) $nilai))->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /** "A", "B", ... "AA" - untuk menyebut kolom dalam pesan kesalahan. */
    private function hurufKolom(int $index): string
    {
        $huruf = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $huruf = chr(65 + ($n - 1) % 26).$huruf;
        }

        return $huruf;
    }
}
