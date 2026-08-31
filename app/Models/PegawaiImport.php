<?php

namespace App\Models;

use App\Imports\PegawaiUploadImport;
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
 * Batch import Pegawai dengan alur preview/dry-run, port 1:1 dari pola
 * MasterAnggaranImport: upload di-parse ke pegawai_import_rows (staging)
 * tanpa menyentuh tabel pegawai sama sekali, lalu user menekan Konfirmasi
 * Simpan untuk memicu konfirmasi() yang memvalidasi ULANG setiap baris
 * terhadap data terkini sebelum commit dalam satu transaction.
 *
 * Pencocokan baris "baru" vs "update" berdasarkan NIP (unik) - NIP yang
 * sudah ada di tabel pegawai diperlakukan sebagai UPDATE (menimpa field
 * yang ada di file), bukan ditolak.
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
class PegawaiImport extends Model
{
    protected $table = 'pegawai_imports';

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
        return $this->hasMany(PegawaiImportRow::class, 'import_id');
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
        $sheet = new PegawaiUploadImport;
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

            $nipTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2;

                $hasil = self::evaluasiBaris([
                    'nama' => $row['nama'] ?? null,
                    'nip' => $row['nip'] ?? null,
                    'jabatan' => $row['jabatan'] ?? null,
                    'golongan' => $row['golongan'] ?? null,
                    'pangkat' => $row['pangkat'] ?? null,
                    'bidang' => $row['bidang'] ?? null,
                    'rekening' => $row['rekening'] ?? null,
                    'nomor_handphone' => $row['nomor_handphone'] ?? null,
                    'aktif' => $row['aktif'] ?? null,
                ]);

                if ($hasil['aksi'] !== PegawaiImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['nip']);

                    if (isset($nipTerlihat[$kunci])) {
                        $hasil['aksi'] = PegawaiImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Duplikat NIP dengan baris {$nipTerlihat[$kunci]} pada file ini.";
                    } else {
                        $nipTerlihat[$kunci] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    PegawaiImportRow::AKSI_BARU => $baru++,
                    PegawaiImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'aksi' => $hasil['aksi'],
                    'alasan' => $hasil['alasan'],
                    'nama' => $hasil['nama'],
                    'nip' => $hasil['nip'],
                    'jabatan' => $hasil['jabatan'],
                    'golongan' => $hasil['golongan'],
                    'pangkat' => $hasil['pangkat'],
                    'bidang' => $hasil['bidang'],
                    'rekening' => $hasil['rekening'],
                    'nomor_handphone' => $hasil['nomor_handphone'],
                    'aktif' => $hasil['aktif'],
                    'pegawai_id' => $hasil['pegawai_id'],
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
                ->whereIn('aksi', [PegawaiImportRow::AKSI_BARU, PegawaiImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $hasil = self::evaluasiBaris([
                    'nama' => $baris->nama,
                    'nip' => $baris->nip,
                    'jabatan' => $baris->jabatan,
                    'golongan' => $baris->golongan,
                    'pangkat' => $baris->pangkat,
                    'bidang' => $baris->bidang,
                    'rekening' => $baris->rekening,
                    'nomor_handphone' => $baris->nomor_handphone,
                    'aktif' => $baris->aktif ? 'ya' : 'tidak',
                ]);

                if ($hasil['aksi'] === PegawaiImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => PegawaiImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                $atribut = [
                    'nama' => $hasil['nama'],
                    'jabatan' => $hasil['jabatan'],
                    'golongan' => $hasil['golongan'],
                    'pangkat' => $hasil['pangkat'],
                    'bidang' => $hasil['bidang'],
                    'rekening' => $hasil['rekening'],
                    'aktif' => $hasil['aktif'],
                ];

                // Nomor handphone sengaja TIDAK ikut ditimpa saat selnya kosong:
                // export lama (sebelum kolom ini ada) masih dipakai sebagai
                // berkas import, dan re-import berkas semacam itu tidak boleh
                // diam-diam menghapus nomor yang sudah dikumpulkan.
                if ($hasil['nomor_handphone'] !== null) {
                    $atribut['nomor_handphone'] = $hasil['nomor_handphone'];
                }

                try {
                    $model = Pegawai::updateOrCreate(['nip' => $hasil['nip']], $atribut);
                } catch (Throwable $e) {
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                if ($model->wasRecentlyCreated) {
                    $baruAkhir++;
                } else {
                    $updateAkhir++;
                }

                $baris->update(['pegawai_id' => $model->id]);
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
     * Evaluasi satu baris data mentah terhadap kondisi pegawai SAAT INI.
     * Dipakai baik saat parse awal (preview) maupun saat konfirmasi
     * (re-validasi) - satu-satunya sumber aturan bisnis supaya keduanya
     * tidak bisa berbeda hasil.
     */
    private static function evaluasiBaris(array $mentah): array
    {
        $nama = trim((string) ($mentah['nama'] ?? ''));
        $nip = trim((string) ($mentah['nip'] ?? ''));
        $jabatan = trim((string) ($mentah['jabatan'] ?? ''));
        $golongan = trim((string) ($mentah['golongan'] ?? ''));
        $pangkat = trim((string) ($mentah['pangkat'] ?? ''));
        $bidang = trim((string) ($mentah['bidang'] ?? ''));
        $rekening = trim((string) ($mentah['rekening'] ?? ''));
        $nomorHandphone = trim((string) ($mentah['nomor_handphone'] ?? ''));
        $aktif = mb_strtolower(trim((string) ($mentah['aktif'] ?? ''))) !== 'tidak';

        $dasar = [
            'nama' => $nama,
            'nip' => $nip,
            'jabatan' => $jabatan,
            'golongan' => $golongan !== '' ? $golongan : null,
            'pangkat' => $pangkat !== '' ? $pangkat : null,
            'bidang' => $bidang,
            'rekening' => $rekening !== '' ? $rekening : null,
            'nomor_handphone' => $nomorHandphone !== '' ? $nomorHandphone : null,
            'aktif' => $aktif,
            'pegawai_id' => null,
        ];

        if ($nama === '' || $nip === '' || $jabatan === '' || $bidang === '') {
            return $dasar + ['aksi' => PegawaiImportRow::AKSI_DITOLAK, 'alasan' => 'Nama, NIP, Jabatan, dan Bidang wajib diisi.'];
        }

        if (mb_strlen($nip) > 30) {
            return $dasar + ['aksi' => PegawaiImportRow::AKSI_DITOLAK, 'alasan' => 'NIP melebihi 30 karakter.'];
        }

        $existing = Pegawai::where('nip', $nip)->lockForUpdate()->first();

        if (! $existing) {
            return $dasar + ['aksi' => PegawaiImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['pegawai_id'] = $existing->id;

        return $dasar + ['aksi' => PegawaiImportRow::AKSI_UPDATE, 'alasan' => null];
    }
}
