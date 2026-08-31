<?php

namespace App\Services;

use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Imports\NpdHistorisUploadImport;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdHistorisImport;
use App\Models\RakBulanan;
use App\Models\SpmDetail;
use App\Models\Tagging;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class NpdHistorisImportService
{
    public const FORMAT_MARKER = 'IFINANCE_NPD_HISTORIS_V1';

    public const HEADERS = [
        'Tanggal NPD', 'Nomor NPD', 'Jenis NPD',
        'Kode Sub Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Rekening',
        'Tagging', 'Penerima', 'Rekening Penerima', 'Nominal Bruto', 'Uraian',
        'PPN', 'PPh1', 'Jenis PPh1', 'PPh2', 'Jenis PPh2', 'Status NPD',
    ];

    /**
     * Kolom yang HARUS ada di baris header.
     *
     * 'Kode Sub Kegiatan' dan 'Rekening' sengaja TIDAK diwajibkan supaya
     * berkas yang terlanjur diunduh dengan template lama (Sub Kegiatan dan
     * Kode Rekening masih menggabungkan kode + nama dalam satu sel) tetap
     * bisa diunggah tanpa diedit - lihat gabungan di validasiBaris().
     * 'Status NPD' opsional karena kosong berarti Selesai.
     */
    public const HEADERS_WAJIB = [
        'Tanggal NPD', 'Nomor NPD', 'Jenis NPD', 'Sub Kegiatan', 'Kode Rekening',
        'Tagging', 'Penerima', 'Rekening Penerima', 'Nominal Bruto', 'Uraian',
        'PPN', 'PPh1', 'Jenis PPh1', 'PPh2', 'Jenis PPh2',
    ];

    private const JENIS = [
        'barang jasa' => 'bj', 'perjalanan dinas' => 'pd', 'transport' => 'tr',
        'narasumber' => 'ns', 'kontribusi diklat' => 'kd',
    ];

    public function __construct(private readonly PenerimaSnapshotResolver $penerimaResolver) {}

    public function stage(UploadedFile $file, int $userId): NpdHistorisImport
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if (NpdHistorisImport::where('file_hash', $hash)->where('status', NpdHistorisImport::STATUS_COMMITTED)->exists()) {
            throw ValidationException::withMessages(['file' => 'File yang sama sudah pernah berhasil diimpor. Reimport diblokir agar batch dan NPD tidak tergandakan.']);
        }

        $reader = new NpdHistorisUploadImport;
        Excel::import($reader, $file);
        $rows = $this->normalisasiWorkbook($reader->rows);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak memiliki baris data.']);
        }
        if ($rows->count() > 5000) {
            throw ValidationException::withMessages(['file' => 'File melebihi batas 5.000 baris.']);
        }

        return DB::transaction(function () use ($file, $hash, $userId, $rows) {
            $import = NpdHistorisImport::create([
                'user_id' => $userId, 'nama_file' => $file->getClientOriginalName(), 'file_hash' => $hash,
                'format_sumber' => 'npd_historis_v1', 'status' => NpdHistorisImport::STATUS_STAGED,
                'expires_at' => now()->addMinutes(NpdHistorisImport::menitKedaluwarsa()),
            ]);

            $seen = [];
            $runningMonthly = [];
            $counts = ['valid' => 0, 'warning' => 0, 'error' => 0, 'duplicate' => 0];
            $totalNominal = $totalPpn = $totalPph = 0;

            foreach ($rows as $index => $raw) {
                $nomorBaris = $index + 5;
                $data = $this->validasiBaris($raw, $nomorBaris, $seen, $runningMonthly);
                $import->baris()->create($data);
                $counts[$data['hasil']]++;
                if (in_array($data['hasil'], ['valid', 'warning'], true)) {
                    $totalNominal += self::cents($data['nominal_bruto']);
                    $totalPpn += self::cents($data['ppn']);
                    $totalPph += self::cents($data['pph1']) + self::cents($data['pph2']);
                }
            }

            $import->update([
                'total_baris' => $rows->count(), 'jumlah_valid' => $counts['valid'],
                'jumlah_warning' => $counts['warning'], 'jumlah_error' => $counts['error'],
                'jumlah_duplikat' => $counts['duplicate'], 'total_nominal' => self::decimal($totalNominal),
                'total_ppn' => self::decimal($totalPpn), 'total_pph' => self::decimal($totalPph),
            ]);

            return $import;
        });
    }

    public function commit(NpdHistorisImport $import): array
    {
        if ($import->status !== NpdHistorisImport::STATUS_STAGED || $import->kedaluwarsa()) {
            throw ValidationException::withMessages(['import' => 'Batch bukan staging aktif atau sudah kedaluwarsa.']);
        }

        $tahunAktif = (int) config('anggaran.tahun_aktif');
        $adaTahunDiLuarScope = $import->baris()->whereIn('hasil', ['valid', 'warning'])
            ->where(fn ($query) => $query->whereNull('tahun')->orWhere('tahun', '!=', $tahunAktif))
            ->exists();
        if ($adaTahunDiLuarScope) {
            throw ValidationException::withMessages([
                'import' => "Batch mengandung NPD di luar Tahun Anggaran {$tahunAktif} dan tidak dapat dikonfirmasi.",
            ]);
        }

        $hasil = DB::transaction(function () use ($import) {
            $import = NpdHistorisImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $berhasil = 0;

            $import->baris()->whereIn('hasil', ['valid', 'warning'])->lockForUpdate()
                ->chunkById(250, function ($rows) use ($import, &$berhasil) {
                    foreach ($rows as $row) {
                        if (Npd::where('import_identity_hash', $row->identity_hash)->exists()) {
                            $row->update(['hasil' => 'duplicate', 'pesan' => ['Dokumen sudah diimpor oleh batch lain.']]);

                            continue;
                        }

                        MasterAnggaran::whereKey($row->master_anggaran_id)->lockForUpdate()->firstOrFail();
                        RakBulanan::whereKey($row->rak_bulanan_id)->lockForUpdate()->firstOrFail();
                        $keu = MasterAnggaran::findOrFail($row->master_anggaran_id)->tentukanKeu();

                        $npd = Npd::create([
                            'jenis' => $row->jenis_kode, 'master_anggaran_id' => $row->master_anggaran_id,
                            'surat_perintah_id' => null, 'keu' => $keu, 'nomor_urut' => null,
                            'bulan' => $row->bulan, 'tahun' => $row->tahun, 'nomor_lengkap' => $row->nomor_npd,
                            'tanggal_npd' => $row->tanggal_npd, 'nominal' => $row->nominal_bruto,
                            'terbilang' => Terbilang::rupiah((float) $row->nominal_bruto), 'status' => $row->status_target,
                            'catatan' => 'Diimpor dari data NPD lama.',
                            'detail_json' => [
                                'legacy_snapshot' => true, 'sumber' => 'Import NPD Historis', 'uraian' => $row->uraian,
                                'tahun_diturunkan_dari_tanggal' => $row->tahun, 'bulan_diturunkan_dari_tanggal' => $row->bulan,
                                'jenis_tanpa_detail_buatan' => true,
                            ],
                            'sumber_data' => 'import_historis', 'import_historis_id' => $import->id,
                            'import_baris' => $row->nomor_baris, 'import_identity_hash' => $row->identity_hash,
                            'tagging_snapshot' => $row->tagging_nama, 'dibuat_oleh' => $import->user_id,
                        ]);

                        $penerima = $npd->penerima()->create([
                            'pegawai_id' => $row->pegawai_id, 'vendor_id' => $row->vendor_id,
                            'nama' => $row->penerima, 'rekening' => $row->rekening_penerima,
                            'bruto' => $row->nominal_bruto, 'ppn' => $row->ppn, 'biaya_ku_rtgs' => 0,
                            'keterangan' => $row->uraian,
                        ]);
                        foreach ([[$row->jenis_pph1, $row->pph1], [$row->jenis_pph2, $row->pph2]] as [$jenis, $nilai]) {
                            if (self::cents($nilai) > 0) {
                                $penerima->pphList()->create(['jenis' => $jenis, 'nilai' => $nilai]);
                            }
                        }

                        $npd->catatHistoriStatus(
                            $import->user, 'Import Data Lama', null, $row->status_target,
                            json_encode(['deskripsi' => 'Diimpor dari data NPD lama', 'batch_id' => $import->id, 'source_row' => $row->nomor_baris], JSON_UNESCAPED_UNICODE)
                        );
                        $row->update(['npd_id' => $npd->id]);
                        $berhasil++;
                    }
                });

            $dilewati = $import->total_baris - $berhasil;
            $import->update([
                'status' => NpdHistorisImport::STATUS_COMMITTED, 'jumlah_berhasil' => $berhasil,
                'jumlah_dilewati' => $dilewati, 'executed_at' => now(),
            ]);

            return ['berhasil' => $berhasil, 'dilewati' => $dilewati];
        });

        AuditLog::catat('Import NPD Historis', "Batch: {$import->id}, Berhasil: {$hasil['berhasil']}, Dilewati: {$hasil['dilewati']}, Nominal: {$import->total_nominal}");

        return $hasil;
    }

    private function normalisasiWorkbook(Collection $rows): Collection
    {
        $rows = $rows->values();
        if (trim((string) ($rows->first()[0] ?? '')) !== self::FORMAT_MARKER) {
            throw ValidationException::withMessages(['file' => 'Marker format IFINANCE_NPD_HISTORIS_V1 tidak ditemukan. Gunakan template resmi Import NPD Historis.']);
        }
        $header = collect($rows->get(3, []))->map(fn ($v) => self::header($v))->all();
        $required = array_map(fn ($v) => self::header($v), self::HEADERS_WAJIB);
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                throw ValidationException::withMessages(['file' => "Kolom wajib {$column} tidak ditemukan."]);
            }
        }

        return $rows->slice(4)->filter(fn ($r) => collect($r)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->map(function ($row) use ($header) {
                $assoc = [];
                foreach ($header as $i => $key) {
                    if ($key !== '') {
                        $assoc[$key] = $row[$i] ?? null;
                    }
                }

                return $assoc;
            })->values();
    }

    private function validasiBaris(array $raw, int $nomorBaris, array &$seen, array &$runningMonthly): array
    {
        $errors = [];
        $warnings = [];
        $tanggal = $this->tanggal($raw['tanggal_npd'] ?? null);
        if (! $tanggal) {
            $errors[] = 'Tanggal NPD tidak valid.';
        }
        $tahun = $tanggal?->year;
        $bulan = $tanggal?->month;
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        $tahunDalamCakupan = $tanggal !== null && $tahun === $tahunAktif;
        if ($tanggal !== null && ! $tahunDalamCakupan) {
            $errors[] = "Tanggal NPD berada pada Tahun Anggaran {$tahun}; import hanya menerima Tahun Anggaran {$tahunAktif}. Mapping master anggaran dan RAK tidak dilakukan.";
        }
        $nomor = trim((string) ($raw['nomor_npd'] ?? ''));
        if ($nomor === '') {
            $errors[] = 'Nomor NPD wajib diisi.';
        } elseif (mb_strlen($nomor) > 100) {
            $errors[] = 'Nomor NPD maksimal 100 karakter.';
        }
        $jenisInput = trim((string) ($raw['jenis_npd'] ?? ''));
        $jenis = $this->jenis($jenisInput);
        if (! $jenis) {
            $errors[] = 'Jenis NPD tidak dikenal; sistem tidak menebak dari kolom lain.';
        }
        $statusInput = trim((string) ($raw['status_npd'] ?? ''));
        $statusNorm = mb_strtolower($statusInput ?: 'selesai');
        $status = match ($statusNorm) {
            'selesai' => 'Selesai', 'batal', 'dibatalkan' => 'Dibatalkan', default => null
        };
        if (! $status) {
            $errors[] = 'Status hanya boleh Selesai atau Batal.';
        }

        $nominal = self::money($raw['nominal_bruto'] ?? null);
        $ppn = self::money($raw['ppn'] ?? 0);
        $pph1 = self::money($raw['pph1'] ?? 0);
        $pph2 = self::money($raw['pph2'] ?? 0);
        if ($nominal === null || self::cents($nominal) <= 0) {
            $errors[] = 'Nominal Bruto harus lebih dari nol.';
        }
        foreach (['PPN' => $ppn, 'PPh1' => $pph1, 'PPh2' => $pph2] as $label => $nilai) {
            if ($nilai === null || self::cents($nilai) < 0) {
                $errors[] = "{$label} harus berupa angka non-negatif.";
            }
        }
        $jenisPph1 = trim((string) ($raw['jenis_pph1'] ?? '')) ?: null;
        $jenisPph2 = trim((string) ($raw['jenis_pph2'] ?? '')) ?: null;
        if (self::cents($pph1) > 0 && ! $jenisPph1) {
            $errors[] = 'Jenis PPh1 wajib saat PPh1 lebih dari nol.';
        }
        if (self::cents($pph2) > 0 && ! $jenisPph2) {
            $errors[] = 'Jenis PPh2 wajib saat PPh2 lebih dari nol.';
        }
        if ($nominal !== null && self::cents($ppn) + self::cents($pph1) + self::cents($pph2) > self::cents($nominal)) {
            $errors[] = 'Total PPN dan PPh tidak boleh melebihi Nominal Bruto.';
        }

        $identity = ($nomor && $tahun && $jenis) ? hash('sha256', mb_strtolower(trim(preg_replace('/\s+/', ' ', $nomor)))."|{$tahun}|{$jenis}") : null;
        if ($identity && isset($seen[$identity])) {
            $errors[] = "Duplikat dokumen dengan baris {$seen[$identity]} dalam file.";
            $duplicate = true;
        } else {
            if ($identity) {
                $seen[$identity] = $nomorBaris;
            }
            $duplicate = $identity ? Npd::where(function ($query) use ($identity, $nomor, $tahun, $jenis) {
                $query->where('import_identity_hash', $identity)
                    ->orWhere(function ($query) use ($nomor, $tahun, $jenis) {
                        $query->where('nomor_lengkap', $nomor)->where('tahun', $tahun)->where('jenis', $jenis);
                    });
            })->exists() : false;
            if ($duplicate) {
                $errors[] = 'Dokumen sudah ada di database; tidak akan ditimpa.';
            }
        }

        // Template sekarang memisahkan Kode Sub Kegiatan dari namanya.
        // Keduanya digabung kembali jadi label utuh "{kode} {nama}" supaya
        // baris staging, laporan, dan pencocokan ke master_anggaran tetap
        // memakai bentuk yang sama seperti template lama - berkas lama yang
        // menggabungkan keduanya dalam satu sel pun tetap terbaca.
        $sub = MasterAnggaran::gabungKodeUraian(
            trim((string) ($raw['kode_sub_kegiatan'] ?? '')),
            trim((string) ($raw['sub_kegiatan'] ?? ''))
        );
        $subKey = MasterAnggaran::normalisasiKunci($sub);
        // Data historis kadang berisi kode+uraian gabungan di kolom Kode
        // Rekening (format sumber lama, sama seperti master_anggaran.
        // kode_rekening sekarang) - ambil bagian kode saja untuk matching &
        // penyimpanan (kolom staging kode_rekening tetap varchar pendek).
        $kode = MasterAnggaran::pisahKodeUraian(trim((string) ($raw['kode_rekening'] ?? '')))[0];
        $taggingNama = trim((string) ($raw['tagging'] ?? '')) ?: null;
        $masters = $tahunDalamCakupan
            ? MasterAnggaran::where('sub_kegiatan_kunci', $subKey)->where('kode_rekening_bersih', $kode)->where('aktif', true)->with('tagging')->get()
            : collect();
        $master = null;
        $taggingId = null;
        $mappingStatus = 'error';
        $rak = null;
        $program = $kegiatan = null;
        $pagu = $rakNilai = $realisasiSebelum = $realisasiProyeksi = $sisaProyeksi = null;
        if (! $tahunDalamCakupan) {
            $mappingStatus = $tanggal ? 'tahun_ditolak' : 'tanggal_tidak_valid';
        } elseif ($masters->isEmpty()) {
            $errors[] = 'Kode Rekening tidak ditemukan dalam Sub Kegiatan aktif tersebut.';
        } elseif ($masters->pluck('program_kunci')->unique()->count() > 1 || $masters->pluck('kegiatan_normal')->unique()->count() > 1) {
            $errors[] = 'Mapping Program/Kegiatan ambigu untuk Sub Kegiatan dan Kode Rekening.';
        } else {
            $program = $masters->first()->programNormal();
            $kegiatan = $masters->first()->kegiatanNormal();
            $tagging = $taggingNama ? Tagging::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($taggingNama)])->first() : null;
            $taggingId = $tagging?->id;
            $master = $tagging ? $masters->firstWhere('tagging_id', $tagging->id) : null;
            if (! $master) {
                $master = $masters->sortBy('id')->first();
                if ($taggingNama) {
                    $warnings[] = 'Tagging tidak memiliki master anggaran exact; disimpan sebagai snapshot tanpa membuat master baru.';
                }
            }
            $rak = ($tahun && $bulan) ? RakBulanan::where('tahun', $tahun)->where('bulan', $bulan)
                ->where('sub_kegiatan_kunci', $subKey)->where('kode_rekening', $kode)->first() : null;
            if (! $rak) {
                $errors[] = 'RAK bulan/tahun untuk Sub Kegiatan + Kode Rekening tidak ditemukan; master tahun lain tidak dipakai.';
            } else {
                $mappingStatus = $taggingNama && ! $tagging ? 'rak_exact_tagging_snapshot' : 'exact';
                $ids = $masters->pluck('id');
                $paguCents = $masters->sum(fn ($m) => self::cents($m->pagu));
                $existingMonthlyCents = self::cents((string) Npd::whereIn('master_anggaran_id', $ids)->where('status', 'Selesai')
                    ->where('tahun', $tahun)->where('bulan', $bulan)->sum('nominal'));
                $key = "{$tahun}|{$bulan}|{$subKey}|{$kode}";
                $beforeCents = $existingMonthlyCents + ($runningMonthly[$key] ?? 0);
                $importCents = $status === 'Selesai' ? self::cents($nominal) : 0;
                $projectedCents = $beforeCents + $importCents;
                if ($status === 'Selesai' && $projectedCents > self::cents((string) $rak->target)) {
                    $warnings[] = 'Proyeksi realisasi bulan melebihi RAK bulanan; RAK tidak diubah dan realisasi tidak dipindahkan.';
                }
                if (! $errors && $status === 'Selesai') {
                    $runningMonthly[$key] = ($runningMonthly[$key] ?? 0) + $importCents;
                }
                $activeNpd = self::cents((string) Npd::whereIn('master_anggaran_id', $ids)->where('status', 'not like', '%batal%')->sum('nominal'));
                // SPM LS sudah direstrukturisasi jadi header (spm) + banyak baris mata
                // anggaran (spm_detail) - master_anggaran_id tidak lagi ada di tabel spm.
                // spm_detail hanya pernah diisi oleh Spm::buatLs()/updateLs(), jadi setiap
                // barisnya pasti milik SPM jenis 'ls' (lihat MasterAnggaran::realisasiLs()
                // dan AnggaranRealisasiService, yang memakai asumsi/pola query yang sama).
                $ls = self::cents((string) SpmDetail::whereIn('master_anggaran_id', $ids)->sum('nominal'));
                $pagu = self::decimal($paguCents);
                $rakNilai = (string) $rak->target;
                $realisasiSebelum = self::decimal($beforeCents);
                $realisasiProyeksi = self::decimal($projectedCents);
                $sisaProyeksi = self::decimal($paguCents - $activeNpd - $ls - $importCents);
            }
        }

        $penerima = trim((string) ($raw['penerima'] ?? ''));
        if ($penerima === '') {
            $errors[] = 'Penerima wajib diisi.';
        } elseif (mb_strlen($penerima) > 255) {
            $errors[] = 'Penerima maksimal 255 karakter.';
        }
        $rekeningPenerima = trim((string) ($raw['rekening_penerima'] ?? '')) ?: null;
        if ($rekeningPenerima && mb_strlen($rekeningPenerima) > 100) {
            $errors[] = 'Rekening Penerima maksimal 100 karakter.';
        }
        $recipient = $penerima ? $this->penerimaResolver->resolve($penerima) : ['status' => 'manual', 'pegawai_id' => null, 'vendor_id' => null];
        if ($recipient['status'] === 'ambigu') {
            $errors[] = 'Penerima exact ditemukan ambigu pada master Pegawai/Vendor.';
        }
        if ($recipient['status'] === 'manual') {
            $warnings[] = 'Penerima tidak ada di master; snapshot nama dan rekening akan dipakai tanpa membuat master baru.';
        }

        $hasil = $duplicate ? 'duplicate' : ($errors ? 'error' : ($warnings ? 'warning' : 'valid'));

        return [
            'nomor_baris' => $nomorBaris, 'hasil' => $hasil, 'pesan' => array_merge($errors, $warnings),
            'identity_hash' => $identity, 'tanggal_npd' => $tanggal?->toDateString(), 'tahun' => $tahun, 'bulan' => $bulan,
            'nomor_npd' => $nomor, 'jenis_input' => $jenisInput, 'jenis_kode' => $jenis,
            'sub_kegiatan' => $sub, 'sub_kegiatan_kunci' => $subKey, 'program' => $program, 'kegiatan' => $kegiatan,
            'kode_rekening' => $kode, 'tagging_nama' => $taggingNama, 'master_anggaran_id' => $master?->id,
            'tagging_id' => $taggingId, 'rak_bulanan_id' => $rak?->id, 'penerima' => $penerima,
            'rekening_penerima' => $rekeningPenerima,
            'pegawai_id' => $recipient['pegawai_id'], 'vendor_id' => $recipient['vendor_id'],
            'penerima_manual' => $recipient['status'] === 'manual', 'nominal_bruto' => $nominal,
            'uraian' => trim((string) ($raw['uraian'] ?? '')), 'ppn' => $ppn ?: '0.00',
            'pph1' => $pph1 ?: '0.00', 'jenis_pph1' => $jenisPph1, 'pph2' => $pph2 ?: '0.00', 'jenis_pph2' => $jenisPph2,
            'status_input' => $statusInput ?: null, 'status_target' => $status,
            'pagu' => $pagu, 'rak_bulan' => $rakNilai, 'realisasi_sebelum' => $realisasiSebelum,
            'realisasi_proyeksi' => $realisasiProyeksi, 'sisa_proyeksi' => $sisaProyeksi, 'mapping_status' => $mappingStatus,
        ];
    }

    private function jenis(string $value): ?string
    {
        $key = trim(preg_replace('/\s+/', ' ', preg_replace('/[\/_-]+/', ' ', mb_strtolower($value))));

        return self::JENIS[$key] ?? null;
    }

    private function tanggal(mixed $value): ?Carbon
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
            }

            return Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private static function header(mixed $value): string
    {
        $normalized = trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim((string) $value)))), '_');
        $compact = str_replace('_', '', $normalized);
        $aliases = [
            'tanggalnpd' => 'tanggal_npd', 'nomornpd' => 'nomor_npd', 'jenisnpd' => 'jenis_npd',
            'subkegiatan' => 'sub_kegiatan', 'kodesubkegiatan' => 'kode_sub_kegiatan',
            'koderekening' => 'kode_rekening', 'rekening' => 'rekening',
            'rekeningpenerima' => 'rekening_penerima', 'nominalbruto' => 'nominal_bruto',
            'ppn' => 'ppn', 'pph1' => 'pph1', 'jenispph1' => 'jenis_pph1',
            'pph2' => 'pph2', 'jenispph2' => 'jenis_pph2', 'statusnpd' => 'status_npd',
            'tagging' => 'tagging', 'penerima' => 'penerima', 'uraian' => 'uraian',
        ];

        return $aliases[$compact] ?? $normalized;
    }

    private static function money(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return '0.00';
        }
        $cents = self::parseCents($value);

        return $cents === null ? null : self::decimal($cents);
    }

    private static function cents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return self::parseCents($value) ?? 0;
    }

    private static function parseCents(mixed $value): ?int
    {
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        $raw = trim((string) $value);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,}))?$/', $raw, $match)) {
            return null;
        }
        $fraction = str_pad(substr($match[3] ?? '', 0, 2), 2, '0');
        $cents = ((int) $match[2] * 100) + (int) $fraction;

        return ($match[1] ?? '') === '-' ? -$cents : $cents;
    }

    private static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
