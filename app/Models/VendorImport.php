<?php

namespace App\Models;

use App\Imports\VendorUploadImport;
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
 * Batch import Vendor dengan alur preview/dry-run, port 1:1 dari pola
 * PegawaiImport/MasterAnggaranImport. Pencocokan baris "baru" vs "update"
 * berdasarkan Nama (unik pada tabel vendor) - Nama yang sudah ada
 * diperlakukan sebagai UPDATE (menimpa field yang ada di file).
 */
#[Fillable([
    'user_id',
    'nama_file',
    'status',
    'total_baris',
    'jumlah_baru',
    'jumlah_update',
    'jumlah_ditolak',
    'expires_at',
    'committed_at',
])]
class VendorImport extends Model
{
    protected $table = 'vendor_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    public const MAKS_BARIS = 5000;

    public const MENIT_KEDALUWARSA = 30;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(VendorImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kedaluwarsa(): bool
    {
        return $this->status === self::STATUS_STAGED && $this->expires_at->isPast();
    }

    public static function bersihkanKedaluwarsa(): int
    {
        return self::where('status', self::STATUS_STAGED)->where('expires_at', '<', now())->delete();
    }

    /**
     * @throws ValidationException kalau file kosong atau melebihi batas jumlah baris.
     */
    public static function buatDariUpload(UploadedFile $file, ?int $userId): self
    {
        $sheet = new VendorUploadImport;
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

        return DB::transaction(function () use ($file, $userId, $baris) {
            $import = self::create([
                'user_id' => $userId,
                'nama_file' => $file->getClientOriginalName(),
                'status' => self::STATUS_STAGED,
                'total_baris' => $baris->count(),
                'expires_at' => now()->addMinutes(self::MENIT_KEDALUWARSA),
            ]);

            $namaTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2;

                $hasil = self::evaluasiBaris([
                    'nama' => $row['nama'] ?? null,
                    'rekening' => $row['rekening'] ?? null,
                    'npwp' => $row['npwp'] ?? null,
                    'status_pkp' => $row['status_pkp'] ?? null,
                    'jenis_usaha' => $row['jenis_usaha'] ?? null,
                    'aktif' => $row['aktif'] ?? null,
                ]);

                if ($hasil['aksi'] !== VendorImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['nama']);

                    if (isset($namaTerlihat[$kunci])) {
                        $hasil['aksi'] = VendorImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Duplikat Nama dengan baris {$namaTerlihat[$kunci]} pada file ini.";
                    } else {
                        $namaTerlihat[$kunci] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    VendorImportRow::AKSI_BARU => $baru++,
                    VendorImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'aksi' => $hasil['aksi'],
                    'alasan' => $hasil['alasan'],
                    'nama' => $hasil['nama'],
                    'rekening' => $hasil['rekening'],
                    'npwp' => $hasil['npwp'],
                    'pkp' => $hasil['pkp'],
                    'jenis_usaha' => $hasil['jenis_usaha'],
                    'aktif' => $hasil['aktif'],
                    'vendor_id' => $hasil['vendor_id'],
                ]);
            }

            $import->update(['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak]);

            return $import;
        });
    }

    /**
     * @throws RuntimeException kalau batch sudah diproses, kedaluwarsa, atau ada baris gagal disimpan fatal.
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
                ->whereIn('aksi', [VendorImportRow::AKSI_BARU, VendorImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $hasil = self::evaluasiBaris([
                    'nama' => $baris->nama,
                    'rekening' => $baris->rekening,
                    'npwp' => $baris->npwp,
                    'status_pkp' => $baris->pkp ? 'pkp' : 'non-pkp',
                    'jenis_usaha' => $baris->jenis_usaha,
                    'aktif' => $baris->aktif ? 'ya' : 'tidak',
                ]);

                if ($hasil['aksi'] === VendorImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => VendorImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $model = Vendor::updateOrCreate(
                        ['nama' => $hasil['nama']],
                        [
                            'rekening' => $hasil['rekening'],
                            'npwp' => $hasil['npwp'],
                            'pkp' => $hasil['pkp'],
                            'jenis_usaha' => $hasil['jenis_usaha'],
                            'aktif' => $hasil['aktif'],
                        ]
                    );
                } catch (Throwable $e) {
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                if ($model->wasRecentlyCreated) {
                    $baruAkhir++;
                } else {
                    $updateAkhir++;
                }

                $baris->update(['vendor_id' => $model->id]);
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
     * Evaluasi satu baris data mentah terhadap kondisi vendor SAAT INI.
     * Dipakai baik saat parse awal (preview) maupun saat konfirmasi
     * (re-validasi).
     */
    private static function evaluasiBaris(array $mentah): array
    {
        $nama = trim((string) ($mentah['nama'] ?? ''));
        $rekening = trim((string) ($mentah['rekening'] ?? ''));
        $npwp = trim((string) ($mentah['npwp'] ?? ''));
        $statusPkp = mb_strtolower(trim((string) ($mentah['status_pkp'] ?? '')));
        $pkp = $statusPkp === 'pkp';
        $jenisUsaha = trim((string) ($mentah['jenis_usaha'] ?? ''));
        $aktif = mb_strtolower(trim((string) ($mentah['aktif'] ?? ''))) !== 'tidak';

        $dasar = [
            'nama' => $nama,
            'rekening' => $rekening !== '' ? $rekening : null,
            'npwp' => $npwp !== '' ? $npwp : null,
            'pkp' => $pkp,
            'jenis_usaha' => $jenisUsaha !== '' ? $jenisUsaha : null,
            'aktif' => $aktif,
            'vendor_id' => null,
        ];

        if ($nama === '') {
            return $dasar + ['aksi' => VendorImportRow::AKSI_DITOLAK, 'alasan' => 'Nama kosong.'];
        }

        if (mb_strlen($nama) > 255) {
            return $dasar + ['aksi' => VendorImportRow::AKSI_DITOLAK, 'alasan' => 'Nama melebihi 255 karakter.'];
        }

        $existing = Vendor::where('nama', $nama)->lockForUpdate()->first();

        if (! $existing) {
            return $dasar + ['aksi' => VendorImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['vendor_id'] = $existing->id;

        return $dasar + ['aksi' => VendorImportRow::AKSI_UPDATE, 'alasan' => null];
    }
}
