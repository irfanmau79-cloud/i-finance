<?php

namespace App\Models;

use App\Imports\RincianUploadImport;
use App\Models\Concerns\StagingKedaluwarsa;
use App\Support\AngkaBerkas;
use App\Support\BidangOrganisasi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * Import parsial dokumen SPJ Perjalanan Dinas manual, pola preview/dry-run.
 *
 * Baris dikenali dari Nomor Dokumen + Tahun. Yang sudah ada DIPERBARUI,
 * bukan ditolak - memperbaiki beberapa baris tidak lagi menuntut
 * hapus-semua-lalu-muat-ulang.
 *
 * Tahun diambil dari formulir import, bukan dari kolom berkas.
 */
#[Fillable([
    'user_id', 'nama_file', 'status', 'tahun',
    'total_baris', 'jumlah_baru', 'jumlah_update', 'jumlah_ditolak',
    'expires_at', 'committed_at',
])]
class SpjPerjalananDinasManualImport extends Model
{
    use StagingKedaluwarsa;

    protected $table = 'spj_perjalanan_dinas_manual_imports';

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
        return $this->hasMany(SpjPerjalananDinasManualImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @throws ValidationException
     */
    public static function buatDariUpload(UploadedFile $file, ?int $userId, int $tahun): self
    {
        $sheet = new RincianUploadImport;
        Excel::import($sheet, $file);

        $baris = $sheet->rows->filter(
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        );

        if ($baris->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pada sheet pertama.']);
        }

        if ($baris->count() > self::MAKS_BARIS) {
            throw ValidationException::withMessages([
                'file' => sprintf('File berisi %d baris data, melebihi batas maksimum %d baris.', $baris->count(), self::MAKS_BARIS),
            ]);
        }

        return DB::transaction(function () use ($file, $userId, $tahun, $baris) {
            $import = self::create([
                'user_id' => $userId,
                'nama_file' => $file->getClientOriginalName(),
                'status' => self::STATUS_STAGED,
                'tahun' => $tahun,
                'total_baris' => $baris->count(),
                'expires_at' => now()->addMinutes(self::menitKedaluwarsa()),
            ]);

            $terlihat = [];
            $hitung = ['baru' => 0, 'update' => 0, 'ditolak' => 0];

            foreach ($baris as $indeks => $row) {
                $nomorBaris = $indeks + 2;
                $hasil = self::evaluasiBaris($row, $tahun);

                if ($hasil['aksi'] !== SpjPerjalananDinasManualImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['isi']['nomor_npd']);

                    if (isset($terlihat[$kunci])) {
                        $hasil['aksi'] = SpjPerjalananDinasManualImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Nomor dokumen sama dengan baris {$terlihat[$kunci]} pada berkas ini.";
                    } else {
                        $terlihat[$kunci] = $nomorBaris;
                    }
                }

                $hitung[$hasil['aksi']]++;

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'aksi' => $hasil['aksi'],
                    'alasan' => $hasil['alasan'],
                    'isi' => $hasil['isi'],
                ]);
            }

            $import->update([
                'jumlah_baru' => $hitung['baru'],
                'jumlah_update' => $hitung['update'],
                'jumlah_ditolak' => $hitung['ditolak'],
            ]);

            return $import;
        });
    }

    /**
     * @throws RuntimeException
     *
     * @return array{baru: int, update: int, ditolak_tambahan: int}
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
            $baru = 0;
            $update = 0;

            $barisTerkena = $this->baris()
                ->whereIn('aksi', [SpjPerjalananDinasManualImportRow::AKSI_BARU, SpjPerjalananDinasManualImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $isi = $baris->isi ?? [];

                try {
                    $model = SpjPerjalananDinasManual::updateOrCreate(
                        ['nomor_npd' => $isi['nomor_npd'], 'tahun' => $this->tahun],
                        [
                            'tanggal' => $isi['tanggal'],
                            'nomor_sp' => $isi['nomor_sp'],
                            'sub_kegiatan' => $isi['sub_kegiatan'],
                            'uraian' => $isi['uraian'],
                            'bidang' => $isi['bidang'],
                            'nominal' => $isi['nominal'],
                            'spj_terverifikasi' => $isi['spj_terverifikasi'],
                            'tanggal_verifikasi' => $isi['tanggal_verifikasi'],
                            'diverifikasi_oleh' => $isi['diverifikasi_oleh'],
                            'keterangan' => $isi['keterangan'],
                        ]
                    );
                } catch (Throwable $e) {
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                $model->wasRecentlyCreated ? $baru++ : $update++;
            }

            $this->update([
                'status' => self::STATUS_COMMITTED,
                'committed_at' => now(),
                'jumlah_baru' => $baru,
                'jumlah_update' => $update,
            ]);

            return ['baru' => $baru, 'update' => $update, 'ditolak_tambahan' => 0];
        });
    }

    /**
     * @param  array<string, mixed>|\Illuminate\Support\Collection  $row
     * @return array{aksi: string, alasan: ?string, isi: array<string, mixed>}
     */
    private static function evaluasiBaris(mixed $row, int $tahun): array
    {
        // Maatwebsite menyerahkan tiap baris sebagai Collection.
        $row = collect($row)->all();

        $nomor = trim((string) ($row['nomor_dokumen'] ?? $row['nomor_npd'] ?? ''));
        $bidangMentah = trim((string) ($row['bidang'] ?? ''));
        $bidang = BidangOrganisasi::petakan($bidangMentah, true);
        $terverifikasi = in_array(
            mb_strtolower(trim((string) ($row['status_spj'] ?? ''))),
            ['terverifikasi', 'sudah', 'ya', 'verified'],
            true
        );

        $isi = [
            'nomor_npd' => $nomor,
            'tanggal' => self::tanggal($row['tanggal'] ?? null),
            'nomor_sp' => trim((string) ($row['nomor_sp'] ?? '')) ?: null,
            'sub_kegiatan' => trim((string) ($row['sub_kegiatan'] ?? '')) ?: null,
            'uraian' => trim((string) ($row['uraian'] ?? '')) ?: null,
            'bidang' => $bidang,
            'nominal' => AngkaBerkas::dari($row['nominal'] ?? null),
            'spj_terverifikasi' => $terverifikasi,
            'tanggal_verifikasi' => $terverifikasi ? self::tanggal($row['tanggal_verifikasi'] ?? null) : null,
            'diverifikasi_oleh' => $terverifikasi ? (trim((string) ($row['diverifikasi_oleh'] ?? '')) ?: null) : null,
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];

        $tolak = fn (string $alasan) => ['aksi' => SpjPerjalananDinasManualImportRow::AKSI_DITOLAK, 'alasan' => $alasan, 'isi' => $isi];

        if ($nomor === '') {
            return $tolak('Nomor Dokumen kosong.');
        }

        if ($isi['tanggal'] === null) {
            return $tolak('Tanggal dokumen kosong atau tidak terbaca.');
        }

        // Bidang di luar daftar akan membuat barisnya jatuh ke luar
        // pengelompokan dashboard dan seolah hilang - lebih baik ditolak di
        // sini daripada tersimpan tapi tak pernah terlihat.
        if ($bidang === null) {
            return $tolak('Bidang "'.$bidangMentah.'" tidak dikenali. Pakai salah satu dari: '.implode(', ', BidangOrganisasi::PENGAWASAN).'.');
        }

        $ada = SpjPerjalananDinasManual::where('nomor_npd', $nomor)->where('tahun', $tahun)->exists();

        return [
            'aksi' => $ada ? SpjPerjalananDinasManualImportRow::AKSI_UPDATE : SpjPerjalananDinasManualImportRow::AKSI_BARU,
            'alasan' => null,
            'isi' => $isi,
        ];
    }

    /** Sel tanggal Excel maupun teks "2026-03-14"/"14/03/2026" sama-sama diterima. */
    private static function tanggal(mixed $nilai): ?string
    {
        if (is_int($nilai) || is_float($nilai)) {
            // Angka seri Excel: hari sejak 1899-12-30.
            return Carbon::create(1899, 12, 30)->addDays((int) $nilai)->format('Y-m-d');
        }

        $teks = trim((string) $nilai);

        if ($teks === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $teks)->format('Y-m-d');
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($teks)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
