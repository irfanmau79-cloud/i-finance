<?php

namespace App\Models;

use App\Imports\PkptUploadImport;
use App\Models\Concerns\StagingKedaluwarsa;
use App\Support\BidangOrganisasi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * Batch import Data PKPT dengan alur preview/dry-run, pola sama dengan
 * VendorImport/PegawaiImport - berkas di-parse ke staging dulu dan tabel
 * `pkpt` TIDAK disentuh sampai user menekan Konfirmasi Simpan.
 *
 * Baris dikenali dari kombinasi TAHUN + UNIT KERJA + NOMOR. Penomoran PKPT
 * memang hanya unik di dalam unitnya ("1" ada di tiap Irban), jadi nomor saja
 * tidak cukup - memakainya sendirian akan membuat kegiatan Irban II menimpa
 * kegiatan Irban I bernomor sama.
 */
#[Fillable([
    'user_id',
    'nama_file',
    'status',
    'tahun',
    'total_baris',
    'jumlah_baru',
    'jumlah_update',
    'jumlah_ditolak',
    'expires_at',
    'committed_at',
])]
class PkptImport extends Model
{
    use StagingKedaluwarsa;

    protected $table = 'pkpt_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    public const MAKS_BARIS = 5000;

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(PkptImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @throws ValidationException kalau berkas kosong atau melebihi batas baris.
     */
    public static function buatDariUpload(UploadedFile $file, int $tahun, ?int $userId): self
    {
        $sheet = new PkptUploadImport;
        Excel::import($sheet, $file);

        $baris = $sheet->rows->filter(
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        );

        if ($baris->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pada sheet pertama.']);
        }

        if ($baris->count() > self::MAKS_BARIS) {
            throw ValidationException::withMessages([
                'file' => sprintf('File berisi %d baris data, melebihi batas maksimum %d baris per import.', $baris->count(), self::MAKS_BARIS),
            ]);
        }

        return DB::transaction(function () use ($file, $tahun, $userId, $baris) {
            $import = self::create([
                'user_id' => $userId,
                'nama_file' => $file->getClientOriginalName(),
                'status' => self::STATUS_STAGED,
                'tahun' => $tahun,
                'total_baris' => $baris->count(),
                'expires_at' => now()->addMinutes(self::menitKedaluwarsa()),
            ]);

            $kunciTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2;

                $hasil = self::evaluasiBaris($tahun, [
                    'nomor' => $row['nomor'] ?? null,
                    'unit_kerja' => $row['unit_kerja'] ?? null,
                    'area' => $row['area_pengawasan_dan_pembinaan'] ?? null,
                    'jenis_kegiatan' => $row['jenis_kegiatan'] ?? null,
                    'tujuan' => $row['tujuan_dan_sasaran'] ?? null,
                    'ruang_lingkup' => $row['ruang_lingkup'] ?? null,
                    'jumlah_tim' => $row['jumlah_tim'] ?? null,
                    'estimasi_anggaran' => $row['estimasi_anggaran'] ?? null,
                    'realisasi' => $row['realisasi'] ?? null,
                    'rencana_pelaksanaan' => $row['rencana_pelaksanaan'] ?? null,
                    'pelaksanaan' => $row['pelaksanaan'] ?? null,
                    'jumlah_laporan' => $row['jumlah_laporan'] ?? null,
                    'terlaksana' => $row['terlaksana'] ?? null,
                ]);

                if ($hasil['aksi'] !== PkptImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['unit_kerja'].'|'.$hasil['nomor']);

                    if (isset($kunciTerlihat[$kunci])) {
                        $hasil['aksi'] = PkptImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Nomor PKPT ganda untuk unit yang sama dengan baris {$kunciTerlihat[$kunci]} pada file ini.";
                    } else {
                        $kunciTerlihat[$kunci] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    PkptImportRow::AKSI_BARU => $baru++,
                    PkptImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                $import->baris()->create($hasil + ['nomor_baris' => $nomorBaris]);
            }

            $import->update(['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak]);

            return $import;
        });
    }

    /**
     * @throws RuntimeException kalau batch sudah diproses, kedaluwarsa, atau ada baris gagal disimpan.
     */
    public function konfirmasi(): array
    {
        if ($this->status !== self::STATUS_STAGED) {
            throw new RuntimeException('Import ini sudah diproses sebelumnya.');
        }

        if ($this->kedaluwarsa()) {
            throw new RuntimeException('Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.');
        }

        return DB::transaction(function () {
            $baruAkhir = 0;
            $updateAkhir = 0;
            $ditolakTambahan = 0;

            $barisTerkena = $this->baris()
                ->whereIn('aksi', [PkptImportRow::AKSI_BARU, PkptImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $hasil = self::evaluasiBaris($this->tahun, [
                    'nomor' => $baris->nomor,
                    'unit_kerja' => $baris->unit_kerja,
                    'area' => $baris->area,
                    'jenis_kegiatan' => $baris->jenis_kegiatan,
                    'tujuan' => $baris->tujuan,
                    'ruang_lingkup' => $baris->ruang_lingkup,
                    'jumlah_tim' => $baris->jumlah_tim,
                    'estimasi_anggaran' => $baris->estimasi_anggaran,
                    'realisasi' => $baris->realisasi,
                    'rencana_pelaksanaan' => $baris->rencana_pelaksanaan,
                    'pelaksanaan' => $baris->pelaksanaan,
                    'jumlah_laporan' => $baris->jumlah_laporan,
                    'terlaksana' => $baris->terlaksana ? 'Ya' : 'Tidak',
                ]);

                if ($hasil['aksi'] === PkptImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => PkptImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $model = Pkpt::updateOrCreate(
                        ['tahun' => $this->tahun, 'unit_kerja' => $hasil['unit_kerja'], 'nomor' => $hasil['nomor']],
                        [
                            'area' => $hasil['area'],
                            'jenis_kegiatan' => $hasil['jenis_kegiatan'],
                            'tujuan' => $hasil['tujuan'],
                            'ruang_lingkup' => $hasil['ruang_lingkup'],
                            'jumlah_tim' => $hasil['jumlah_tim'],
                            'estimasi_anggaran' => $hasil['estimasi_anggaran'],
                            'realisasi' => $hasil['realisasi'],
                            'rencana_pelaksanaan' => $hasil['rencana_pelaksanaan'],
                            'pelaksanaan' => $hasil['pelaksanaan'],
                            'jumlah_laporan' => $hasil['jumlah_laporan'],
                            'terlaksana' => $hasil['terlaksana'],
                        ]
                    );
                } catch (Throwable $e) {
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                $model->wasRecentlyCreated ? $baruAkhir++ : $updateAkhir++;

                $baris->update(['pkpt_id' => $model->id]);
            }

            $this->update([
                'status' => self::STATUS_COMMITTED,
                'committed_at' => now(),
                'jumlah_baru' => $baruAkhir,
                'jumlah_update' => $updateAkhir,
                'jumlah_ditolak' => $this->jumlah_ditolak + $ditolakTambahan,
            ]);

            return ['baru' => $baruAkhir, 'update' => $updateAkhir, 'ditolak_tambahan' => $ditolakTambahan];
        });
    }

    /**
     * Evaluasi satu baris mentah terhadap isi tabel pkpt SAAT INI. Dipakai
     * saat parse awal (preview) maupun saat konfirmasi (re-validasi).
     */
    private static function evaluasiBaris(int $tahun, array $mentah): array
    {
        $nomor = trim((string) ($mentah['nomor'] ?? ''));
        $unitMentah = trim((string) ($mentah['unit_kerja'] ?? ''));
        // Ejaan unit di berkas PKPT tidak seragam ("Irban I", "IRBAN 1",
        // "Inspektur Pembantu I"). Dibakukan supaya urutan unit, chart, dan
        // penyaringan modul Kebutuhan memakai satu ejaan yang sama. Yang tidak
        // dikenali TIDAK ditolak - disimpan apa adanya, seperti GAS.
        $unit = BidangOrganisasi::petakan($unitMentah) ?? $unitMentah;

        $dasar = [
            'nomor' => $nomor,
            'unit_kerja' => $unit,
            'area' => self::teks($mentah['area'] ?? null),
            'jenis_kegiatan' => self::teks($mentah['jenis_kegiatan'] ?? null),
            'tujuan' => self::teks($mentah['tujuan'] ?? null),
            'ruang_lingkup' => self::teks($mentah['ruang_lingkup'] ?? null),
            'jumlah_tim' => self::teks($mentah['jumlah_tim'] ?? null),
            'estimasi_anggaran' => self::angka($mentah['estimasi_anggaran'] ?? null),
            'realisasi' => self::angka($mentah['realisasi'] ?? null),
            'rencana_pelaksanaan' => self::teks($mentah['rencana_pelaksanaan'] ?? null),
            'pelaksanaan' => self::teks($mentah['pelaksanaan'] ?? null),
            'jumlah_laporan' => self::teks($mentah['jumlah_laporan'] ?? null),
            'terlaksana' => self::terlaksana($mentah['terlaksana'] ?? null),
            'pkpt_id' => null,
        ];

        if ($nomor === '') {
            return $dasar + ['aksi' => PkptImportRow::AKSI_DITOLAK, 'alasan' => 'Nomor kosong - nomor dipakai sebagai penanda baris.'];
        }

        if ($unit === '') {
            return $dasar + ['aksi' => PkptImportRow::AKSI_DITOLAK, 'alasan' => 'Unit Kerja kosong.'];
        }

        if ($dasar['area'] === null && $dasar['jenis_kegiatan'] === null) {
            return $dasar + ['aksi' => PkptImportRow::AKSI_DITOLAK, 'alasan' => 'Area dan Jenis Kegiatan dua-duanya kosong.'];
        }

        $ada = Pkpt::query()
            ->where(['tahun' => $tahun, 'unit_kerja' => $unit, 'nomor' => $nomor])
            ->lockForUpdate()
            ->first();

        if (! $ada) {
            return $dasar + ['aksi' => PkptImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['pkpt_id'] = $ada->id;

        return $dasar + ['aksi' => PkptImportRow::AKSI_UPDATE, 'alasan' => null];
    }

    private static function teks(mixed $nilai): ?string
    {
        $teks = trim((string) ($nilai ?? ''));

        return $teks !== '' ? $teks : null;
    }

    /** Angka dari sel yang bisa saja ditulis "Rp 1.250.000,00". */
    private static function angka(mixed $nilai): float
    {
        if (is_numeric($nilai)) {
            return (float) $nilai;
        }

        $teks = str_replace(['.', ' '], '', (string) $nilai);
        $teks = str_replace(',', '.', $teks);
        $teks = preg_replace('/[^0-9.\-]/', '', $teks);

        return is_numeric($teks) ? (float) $teks : 0.0;
    }

    /** Sama tolerannya dengan _pkptTerlaksana() di GAS. */
    private static function terlaksana(mixed $nilai): bool
    {
        if (is_bool($nilai)) {
            return $nilai;
        }

        return in_array(mb_strtolower(trim((string) $nilai)), ['true', 'ya', '1', 'v', '✓', 'sudah', 'terlaksana'], true);
    }
}
