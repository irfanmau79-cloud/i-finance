<?php

namespace App\Models;

use App\Imports\RekapPotensiUploadImport;
use App\Support\AngkaBerkas;
use App\Models\Concerns\StagingKedaluwarsa;
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
 * Batch import Rekap Potensi Pengembalian, pola preview/dry-run yang sama
 * dengan jenis data lain. Baris dikenali dari NIP: NIP yang sudah ada
 * diperlakukan sebagai UPDATE.
 *
 * Kolom Sisa pada berkas DIABAIKAN. Sisa selalu dihitung potensi - setoran,
 * jadi menerimanya dari berkas hanya membuka peluang rekap yang bertentangan
 * dengan dua angka sumbernya sendiri.
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
class RekapPotensiImport extends Model
{
    use StagingKedaluwarsa;

    protected $table = 'rekap_potensi_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    public const MAKS_BARIS = 5000;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(RekapPotensiImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @throws ValidationException kalau berkas kosong atau melebihi batas baris.
     */
    public static function buatDariUpload(UploadedFile $file, ?int $userId): self
    {
        $sheet = new RekapPotensiUploadImport;
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
                'expires_at' => now()->addMinutes(self::menitKedaluwarsa()),
            ]);

            $nipTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2;

                $hasil = self::evaluasiBaris([
                    'nip' => $row['nip'] ?? null,
                    'nama' => $row['nama_pegawai'] ?? $row['nama'] ?? null,
                    'jabatan' => $row['jabatan'] ?? null,
                    'potensi' => $row['potensi_kelebihan_pembayaran'] ?? $row['potensi'] ?? null,
                    'setoran' => $row['setoran'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                ]);

                if ($hasil['aksi'] !== RekapPotensiImportRow::AKSI_DITOLAK) {
                    if (isset($nipTerlihat[$hasil['nip']])) {
                        $hasil['aksi'] = RekapPotensiImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Duplikat NIP dengan baris {$nipTerlihat[$hasil['nip']]} pada file ini.";
                    } else {
                        $nipTerlihat[$hasil['nip']] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    RekapPotensiImportRow::AKSI_BARU => $baru++,
                    RekapPotensiImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'aksi' => $hasil['aksi'],
                    'alasan' => $hasil['alasan'],
                    'nip' => $hasil['nip'],
                    'nama' => $hasil['nama'],
                    'jabatan' => $hasil['jabatan'],
                    'potensi' => $hasil['potensi'],
                    'setoran' => $hasil['setoran'],
                    'keterangan' => $hasil['keterangan'],
                    'rekap_id' => $hasil['rekap_id'],
                ]);
            }

            $import->update(['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak]);

            return $import;
        });
    }

    /**
     * @throws RuntimeException kalau batch sudah diproses atau kedaluwarsa.
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
            $baruAkhir = 0;
            $updateAkhir = 0;
            $ditolakTambahan = 0;

            $barisTerkena = $this->baris()
                ->whereIn('aksi', [RekapPotensiImportRow::AKSI_BARU, RekapPotensiImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $hasil = self::evaluasiBaris([
                    'nip' => $baris->nip,
                    'nama' => $baris->nama,
                    'jabatan' => $baris->jabatan,
                    'potensi' => $baris->potensi,
                    'setoran' => $baris->setoran,
                    'keterangan' => $baris->keterangan,
                ]);

                if ($hasil['aksi'] === RekapPotensiImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => RekapPotensiImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $model = RekapPotensiPengembalian::updateOrCreate(['nip' => $hasil['nip']], [
                        'nama' => $hasil['nama'],
                        'jabatan' => $hasil['jabatan'],
                        'potensi' => $hasil['potensi'],
                        'setoran' => $hasil['setoran'],
                        'keterangan' => $hasil['keterangan'],
                    ]);
                } catch (Throwable $e) {
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                $model->wasRecentlyCreated ? $baruAkhir++ : $updateAkhir++;

                $baris->update(['rekap_id' => $model->id]);
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
     * Evaluasi satu baris mentah terhadap kondisi rekap SAAT INI. Dipakai
     * saat parse awal (preview) maupun saat konfirmasi (re-validasi).
     *
     * @param  array<string, mixed>  $mentah
     * @return array<string, mixed>
     */
    private static function evaluasiBaris(array $mentah): array
    {
        // NIP sering tersimpan dengan spasi atau tanda baca di berkas; yang
        // dipakai sebagai identitas hanya digitnya.
        $nip = preg_replace('/\D/', '', (string) ($mentah['nip'] ?? ''));
        $nama = trim((string) ($mentah['nama'] ?? ''));
        $jabatan = trim((string) ($mentah['jabatan'] ?? ''));
        $keterangan = trim((string) ($mentah['keterangan'] ?? ''));

        $dasar = [
            'nip' => $nip,
            'nama' => $nama,
            'jabatan' => $jabatan !== '' ? $jabatan : null,
            'potensi' => AngkaBerkas::dari($mentah['potensi'] ?? null),
            'setoran' => AngkaBerkas::dari($mentah['setoran'] ?? null),
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'rekap_id' => null,
        ];

        if ($nip === '') {
            return $dasar + ['aksi' => RekapPotensiImportRow::AKSI_DITOLAK, 'alasan' => 'NIP kosong.'];
        }

        if (mb_strlen($nip) > 30) {
            return $dasar + ['aksi' => RekapPotensiImportRow::AKSI_DITOLAK, 'alasan' => 'NIP melebihi 30 digit.'];
        }

        if ($nama === '') {
            return $dasar + ['aksi' => RekapPotensiImportRow::AKSI_DITOLAK, 'alasan' => 'Nama Pegawai kosong.'];
        }

        if ($dasar['setoran'] > $dasar['potensi']) {
            return $dasar + [
                'aksi' => RekapPotensiImportRow::AKSI_DITOLAK,
                'alasan' => 'Setoran melebihi Potensi Kelebihan Pembayaran, sisanya akan menjadi negatif.',
            ];
        }

        $existing = RekapPotensiPengembalian::where('nip', $nip)->lockForUpdate()->first();

        if (! $existing) {
            return $dasar + ['aksi' => RekapPotensiImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['rekap_id'] = $existing->id;

        return $dasar + ['aksi' => RekapPotensiImportRow::AKSI_UPDATE, 'alasan' => null];
    }
}
