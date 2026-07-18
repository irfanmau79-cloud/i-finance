<?php

namespace App\Console\Commands;

use App\Helpers\AuditLog;
use App\Imports\MasterDataImport;
use App\Imports\RawSheetImport;
use App\Models\DataTambahan;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\Tagging;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportMaster extends Command
{
    protected $signature = 'import:master {path? : Lokasi file xlsx sumber (default: storage/app/import/i-Finance - Inspektorat Daerah.xlsx)}';

    protected $description = 'Import data master Anggaran, Pegawai, dan Data Tambahan dari file Excel i-Finance';

    public function handle(): int
    {
        $path = $this->argument('path') ?? storage_path('app/import/i-Finance - Inspektorat Daerah.xlsx');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->info("Membaca file: {$path}");

        $import = new MasterDataImport();

        try {
            Excel::import($import, $path);
        } catch (Throwable $e) {
            $this->error('Gagal membaca file Excel: '.$e->getMessage());

            return self::FAILURE;
        }

        $pegawaiVendor = $this->runPegawaiVendorInTransaction($import->pegawai);

        $results = [
            'Master Anggaran' => $this->runInTransaction('Master Anggaran', fn () => $this->importMasterAnggaran($import->masterAnggaran)),
            'Pegawai' => $pegawaiVendor['pegawai'],
            'Vendor' => $pegawaiVendor['vendor'],
            'Data Tambahan' => $this->runInTransaction('Data Tambahan', fn () => $this->importDataTambahan($import->dataTambahan)),
        ];

        $this->printSummary($results);

        $this->catatAuditLog($path, $results);

        return self::SUCCESS;
    }

    /** Catat aktivitas import ke audit log, dilakukan sebagai user "Sistem" (dijalankan lewat CLI, bukan sesi login). */
    private function catatAuditLog(string $path, array $results): void
    {
        $ringkasan = [];

        foreach ($results as $label => $r) {
            $ringkasan[] = "{$label}: insert {$r['insert']}, update {$r['update']}, dinonaktifkan {$r['dinonaktifkan']}, dilewati {$r['dilewati']}, error ".count($r['errors']);
        }

        AuditLog::catatSebagai(
            'Sistem',
            'sistem',
            'Import Master',
            "File: {$path}. ".implode(' | ', $ringkasan)
        );
    }

    /**
     * Jalankan satu import dalam database transaction. Kalau ada error fatal
     * di tengah, seluruh perubahan pada import ini di-rollback.
     */
    private function runInTransaction(string $label, \Closure $callback): array
    {
        $this->info("Memproses: {$label}...");

        try {
            return DB::transaction($callback);
        } catch (Throwable $e) {
            $this->error("Import {$label} gagal dan di-rollback seluruhnya: ".$e->getMessage());

            return [
                'dibaca' => 0,
                'insert' => 0,
                'update' => 0,
                'dinonaktifkan' => 0,
                'dilewati' => 0,
                'errors' => ["FATAL - seluruh import di-rollback: ".$e->getMessage()],
            ];
        }
    }

    /**
     * Pegawai dan Vendor berasal dari sheet yang sama sehingga dibungkus
     * dalam satu transaction (gagal salah satu, dua-duanya di-rollback),
     * tapi tetap dilaporkan sebagai dua baris ringkasan terpisah.
     */
    private function runPegawaiVendorInTransaction(RawSheetImport $sheet): array
    {
        $this->info('Memproses: Pegawai & Vendor...');

        try {
            return DB::transaction(fn () => $this->importPegawaiAndVendor($sheet));
        } catch (Throwable $e) {
            $this->error('Import Pegawai & Vendor gagal dan di-rollback seluruhnya: '.$e->getMessage());

            $fatal = [
                'dibaca' => 0,
                'insert' => 0,
                'update' => 0,
                'dinonaktifkan' => 0,
                'dilewati' => 0,
                'errors' => ["FATAL - seluruh import di-rollback: ".$e->getMessage()],
            ];

            return ['pegawai' => $fatal, 'vendor' => $fatal];
        }
    }

    /**
     * Kolom Master Anggaran adalah hasil IMPORTRANGE Google Sheets yang
     * diekspor sebagai formula "=IFERROR(__xludf.DUMMYFUNCTION(...), nilai)".
     * PhpSpreadsheet tidak bisa menghitung ulang fungsi custom itu, jadi
     * nilai sebenarnya diambil dari argumen kedua IFERROR (nilai cache).
     */
    private function extractFormulaValue(mixed $raw): mixed
    {
        if (! is_string($raw) || $raw === '' || $raw[0] !== '=') {
            return $raw;
        }

        if (preg_match('/,\s*(?:"((?:[^"]|"")*)"|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?))\)\s*$/u', $raw, $m)) {
            if (($m[1] ?? '') !== '') {
                return str_replace('""', '"', $m[1]);
            }
            if (($m[2] ?? '') !== '') {
                return (float) $m[2];
            }
        }

        return null;
    }

    /**
     * Baris kosong di ekor sheet (tidak ada isi sama sekali di kolom yang
     * dipakai) dibuang supaya tidak membengkakkan angka "dibaca"/"dilewati"
     * dengan baris-baris padding yang memang tidak berisi data.
     */
    private function trimTrailingBlankRows(Collection $rows, array $columns): Collection
    {
        $lastIndex = -1;

        foreach ($rows as $i => $row) {
            foreach ($columns as $c) {
                $v = $row[$c] ?? null;
                if ($v !== null && trim((string) $v) !== '') {
                    $lastIndex = $i;
                    break;
                }
            }
        }

        return $lastIndex >= 0 ? $rows->slice(0, $lastIndex + 1) : $rows->slice(0, 0);
    }

    private function importMasterAnggaran(RawSheetImport $sheet): array
    {
        $rows = $this->trimTrailingBlankRows($sheet->rows, [0, 1, 2, 3, 4, 5]);
        $startRow = $sheet->startRow();

        $insert = 0;
        $update = 0;
        $dilewati = 0;
        $errors = [];
        $seenIds = [];
        $keyToFirstRow = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $startRow + $i;

            $program = trim((string) ($this->extractFormulaValue($row[0] ?? null) ?? ''));
            $kegiatan = trim((string) ($this->extractFormulaValue($row[1] ?? null) ?? ''));
            $subKegiatan = trim((string) ($this->extractFormulaValue($row[2] ?? null) ?? ''));
            $kodeRekeningFull = trim((string) ($this->extractFormulaValue($row[3] ?? null) ?? ''));
            $taggingName = trim((string) ($this->extractFormulaValue($row[4] ?? null) ?? ''));
            $paguRaw = $this->extractFormulaValue($row[5] ?? null);

            // Sel kode rekening berisi "kode\ndeskripsi" - hanya kodenya yang disimpan.
            $kodeRekening = trim(explode("\n", $kodeRekeningFull, 2)[0]);

            if ($subKegiatan === '' || $kodeRekening === '') {
                $dilewati++;

                continue;
            }

            if (mb_strlen($kodeRekening) > 50) {
                $errors[] = "Baris {$rowNumber}: kode_rekening melebihi 50 karakter, dilewati.";
                $dilewati++;

                continue;
            }

            $pagu = is_numeric($paguRaw) ? (float) $paguRaw : null;

            if ($pagu === null) {
                $errors[] = "Baris {$rowNumber}: nilai pagu tidak valid, dilewati.";
                $dilewati++;

                continue;
            }

            try {
                $taggingId = null;

                if ($taggingName !== '') {
                    $taggingId = Tagging::firstOrCreate(['nama' => $taggingName])->id;
                }

                // Kunci unik: sub_kegiatan + kode_rekening + tagging_id. Sub
                // kegiatan dan kode rekening yang sama boleh muncul lebih dari
                // sekali selama taggingnya berbeda - itu bukan duplikat.
                $key = $subKegiatan.'|'.$kodeRekening.'|'.($taggingId ?? 'NULL');

                if (isset($keyToFirstRow[$key])) {
                    $errors[] = "Baris {$rowNumber}: kunci (sub_kegiatan+kode_rekening+tagging_id) sama dengan baris {$keyToFirstRow[$key]} - baris ini meng-update baris tersebut, bukan insert baru.";
                } else {
                    $keyToFirstRow[$key] = $rowNumber;
                }

                $model = MasterAnggaran::updateOrCreate(
                    ['sub_kegiatan' => $subKegiatan, 'kode_rekening' => $kodeRekening, 'tagging_id' => $taggingId],
                    [
                        'program' => $program,
                        'kegiatan' => $kegiatan,
                        'pagu' => $pagu,
                        'aktif' => true,
                    ]
                );

                $seenIds[] = $model->id;

                if ($model->wasRecentlyCreated) {
                    $insert++;
                } else {
                    $update++;
                }
            } catch (Throwable $e) {
                $errors[] = "Baris {$rowNumber}: gagal disimpan - {$e->getMessage()}";
            }
        }

        $dinonaktifkan = 0;

        if (! empty($seenIds)) {
            $dinonaktifkan = MasterAnggaran::whereNotIn('id', $seenIds)->where('aktif', true)->update(['aktif' => false]);
        } elseif ($rows->isNotEmpty()) {
            $errors[] = 'Tidak ada baris valid yang berhasil diproses - proses nonaktifkan dilewati demi keamanan.';
        }

        return [
            'dibaca' => $rows->count(),
            'insert' => $insert,
            'update' => $update,
            'dinonaktifkan' => $dinonaktifkan,
            'dilewati' => $dilewati,
            'errors' => $errors,
        ];
    }

    /**
     * Sheet "Nama Pegawai" berisi campuran pegawai (punya NIP) dan
     * vendor/pihak ketiga (tanpa NIP, mis. "PT. Wijaya Lestari"). Baris
     * dengan NIP masuk tabel pegawai, baris tanpa NIP masuk tabel vendor
     * (upsert berdasarkan nama). Tidak ada lagi placeholder NIP berbasis
     * nomor baris - itu berbahaya karena berubah kalau file diexport ulang
     * dengan urutan baris berbeda, menyebabkan upsert membuat duplikat.
     */
    private function importPegawaiAndVendor(RawSheetImport $sheet): array
    {
        $rows = $this->trimTrailingBlankRows($sheet->rows, [1, 2, 5, 6]);
        $startRow = $sheet->startRow();

        $pegawaiDibaca = 0;
        $pegInsert = 0;
        $pegUpdate = 0;
        $pegDilewati = 0;
        $pegErrors = [];
        $seenNips = [];

        $vendorDibaca = 0;
        $venInsert = 0;
        $venUpdate = 0;
        $venDilewati = 0;
        $venErrors = [];
        $seenVendorNames = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $startRow + $i;

            $nama = trim((string) ($row[1] ?? ''));
            $nip = trim((string) ($row[2] ?? ''));
            $jabatan = trim((string) ($row[5] ?? ''));
            $bidang = trim((string) ($row[6] ?? ''));

            if ($nama === '') {
                $pegDilewati++;

                continue;
            }

            if ($nip !== '') {
                $pegawaiDibaca++;

                if (mb_strlen($nip) > 30) {
                    $pegErrors[] = "Baris {$rowNumber}: NIP melebihi 30 karakter, dilewati.";
                    $pegDilewati++;

                    continue;
                }

                try {
                    $model = Pegawai::updateOrCreate(
                        ['nip' => $nip],
                        [
                            'nama' => $nama,
                            'jabatan' => $jabatan,
                            'bidang' => $bidang,
                            'aktif' => true,
                        ]
                    );

                    $seenNips[] = $nip;

                    if ($model->wasRecentlyCreated) {
                        $pegInsert++;
                    } else {
                        $pegUpdate++;
                    }
                } catch (Throwable $e) {
                    $pegErrors[] = "Baris {$rowNumber}: gagal disimpan - {$e->getMessage()}";
                }
            } else {
                $vendorDibaca++;

                if (mb_strlen($nama) > 255) {
                    $venErrors[] = "Baris {$rowNumber}: nama vendor melebihi 255 karakter, dilewati.";
                    $venDilewati++;

                    continue;
                }

                try {
                    $model = Vendor::updateOrCreate(
                        ['nama' => $nama],
                        ['aktif' => true]
                    );

                    $seenVendorNames[] = $nama;

                    if ($model->wasRecentlyCreated) {
                        $venInsert++;
                    } else {
                        $venUpdate++;
                    }
                } catch (Throwable $e) {
                    $venErrors[] = "Baris {$rowNumber}: gagal disimpan - {$e->getMessage()}";
                }
            }
        }

        $pegDinonaktifkan = 0;

        if (! empty($seenNips)) {
            $pegDinonaktifkan = Pegawai::whereNotIn('nip', $seenNips)->where('aktif', true)->update(['aktif' => false]);
        } elseif ($pegawaiDibaca > 0) {
            $pegErrors[] = 'Tidak ada baris pegawai valid yang berhasil diproses - proses nonaktifkan dilewati demi keamanan.';
        }

        $venDinonaktifkan = 0;

        if (! empty($seenVendorNames)) {
            $venDinonaktifkan = Vendor::whereNotIn('nama', $seenVendorNames)->where('aktif', true)->update(['aktif' => false]);
        } elseif ($vendorDibaca > 0) {
            $venErrors[] = 'Tidak ada baris vendor valid yang berhasil diproses - proses nonaktifkan dilewati demi keamanan.';
        }

        return [
            'pegawai' => [
                'dibaca' => $pegawaiDibaca,
                'insert' => $pegInsert,
                'update' => $pegUpdate,
                'dinonaktifkan' => $pegDinonaktifkan,
                'dilewati' => $pegDilewati,
                'errors' => $pegErrors,
            ],
            'vendor' => [
                'dibaca' => $vendorDibaca,
                'insert' => $venInsert,
                'update' => $venUpdate,
                'dinonaktifkan' => $venDinonaktifkan,
                'dilewati' => $venDilewati,
                'errors' => $venErrors,
            ],
        ];
    }

    private function importDataTambahan(RawSheetImport $sheet): array
    {
        $rows = $this->trimTrailingBlankRows($sheet->rows, [0, 1, 2, 3, 4]);
        $startRow = $sheet->startRow();

        $insert = 0;
        $update = 0;
        $dilewati = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $startRow + $i;

            $program = trim((string) ($row[0] ?? ''));
            $noDpa = trim((string) ($row[1] ?? ''));
            $kpa = trim((string) ($row[2] ?? ''));
            $pptk = trim((string) ($row[3] ?? ''));
            $bpp = trim((string) ($row[4] ?? ''));

            if ($program === '') {
                $dilewati++;

                continue;
            }

            try {
                $model = DataTambahan::updateOrCreate(
                    ['program' => $program],
                    [
                        'no_dpa' => $noDpa,
                        'kpa' => $kpa,
                        'pptk' => $pptk,
                        'bpp' => $bpp,
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $insert++;
                } else {
                    $update++;
                }
            } catch (Throwable $e) {
                $errors[] = "Baris {$rowNumber}: gagal disimpan - {$e->getMessage()}";
            }
        }

        return [
            'dibaca' => $rows->count(),
            'insert' => $insert,
            'update' => $update,
            'dinonaktifkan' => 0,
            'dilewati' => $dilewati,
            'errors' => $errors,
        ];
    }

    private function printSummary(array $results): void
    {
        $this->newLine();
        $this->info('=== Ringkasan Import ===');

        $rows = [];
        foreach ($results as $label => $r) {
            $rows[] = [
                $label,
                $r['dibaca'],
                $r['insert'],
                $r['update'],
                $r['dinonaktifkan'],
                $r['dilewati'],
                count($r['errors']),
            ];
        }

        $this->table(
            ['Import', 'Dibaca', 'Insert', 'Update', 'Dinonaktifkan', 'Dilewati', 'Error'],
            $rows
        );

        foreach ($results as $label => $r) {
            if (empty($r['errors'])) {
                continue;
            }

            $this->newLine();
            $this->warn("Catatan/Error - {$label}:");
            foreach ($r['errors'] as $error) {
                $this->line(" - {$error}");
            }
        }
    }
}
