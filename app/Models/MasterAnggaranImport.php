<?php

namespace App\Models;

use App\Imports\MasterAnggaranUploadImport;
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
 * Batch import Pagu/Master Anggaran dengan alur preview/dry-run: upload
 * di-parse ke master_anggaran_import_rows (staging, punya expires_at) tanpa
 * menyentuh master_anggaran sama sekali, lalu user menekan Konfirmasi Simpan
 * untuk memicu konfirmasi() yang memvalidasi ULANG setiap baris terhadap
 * data terkini (bisa berubah selama menunggu di staging) sebelum commit
 * dalam satu transaction.
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
class MasterAnggaranImport extends Model
{
    protected $table = 'master_anggaran_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    /** Batas baris data per file - file lebih besar dari ini ditolak seluruhnya, bukan dipotong. */
    public const MAKS_BARIS = 5000;

    /** Masa berlaku staging sebelum harus upload ulang. */
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
        return $this->hasMany(MasterAnggaranImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kedaluwarsa(): bool
    {
        return $this->status === self::STATUS_STAGED && $this->expires_at->isPast();
    }

    /** Buang batch staging yang sudah kedaluwarsa supaya tabel tidak menumpuk. Aman dipanggil kapan saja. */
    public static function bersihkanKedaluwarsa(): int
    {
        return self::where('status', self::STATUS_STAGED)->where('expires_at', '<', now())->delete();
    }

    /**
     * Parse file yang diupload menjadi baris staging. TIDAK menyentuh
     * master_anggaran sama sekali - murni membaca & mengevaluasi.
     *
     * @throws ValidationException kalau file kosong atau melebihi batas jumlah baris.
     */
    public static function buatDariUpload(UploadedFile $file, ?int $userId): self
    {
        $sheet = new MasterAnggaranUploadImport();
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

            $kunciTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2; // baris 1 = header

                $hasil = self::evaluasiBaris([
                    'program' => $row['program'] ?? null,
                    'kegiatan' => $row['kegiatan'] ?? null,
                    'sub_kegiatan' => $row['sub_kegiatan'] ?? null,
                    'kode_rekening' => $row['kode_rekening'] ?? null,
                    'uraian_rekening' => $row['uraian_rekening'] ?? null,
                    'tagging' => $row['tagging'] ?? null,
                    'pagu' => $row['pagu'] ?? null,
                    'aktif' => $row['aktif'] ?? null,
                ]);

                if ($hasil['aksi'] !== MasterAnggaranImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['sub_kegiatan']).'|'.mb_strtolower($hasil['kode_rekening']).'|'.mb_strtolower((string) $hasil['tagging_nama']);

                    if (isset($kunciTerlihat[$kunci])) {
                        $hasil['aksi'] = MasterAnggaranImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Duplikat kombinasi Sub Kegiatan + Kode Rekening + Tagging dengan baris {$kunciTerlihat[$kunci]} pada file ini.";
                    } else {
                        $kunciTerlihat[$kunci] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    MasterAnggaranImportRow::AKSI_BARU => $baru++,
                    MasterAnggaranImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                $import->baris()->create([
                    'nomor_baris' => $nomorBaris,
                    'aksi' => $hasil['aksi'],
                    'alasan' => $hasil['alasan'],
                    'program' => $hasil['program'],
                    'kegiatan' => $hasil['kegiatan'],
                    'sub_kegiatan' => $hasil['sub_kegiatan'],
                    'kode_rekening' => $hasil['kode_rekening'],
                    'uraian_rekening' => $hasil['uraian_rekening'],
                    'tagging_nama' => $hasil['tagging_nama'],
                    'aktif' => $hasil['aktif'],
                    'pagu_baru' => $hasil['pagu_baru'],
                    'pagu_lama' => $hasil['pagu_lama'],
                    'master_anggaran_id' => $hasil['master_anggaran_id'],
                ]);
            }

            $import->update(['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak]);

            return $import;
        });
    }

    /**
     * Validasi ULANG setiap baris "baru"/"update" terhadap data terkini, lalu
     * commit ke master_anggaran dalam satu transaction. Kalau satu baris
     * gagal disimpan karena error tak terduga (bukan penolakan normal),
     * SELURUH transaction di-rollback - tidak ada penyimpanan sebagian.
     *
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
                ->whereIn('aksi', [MasterAnggaranImportRow::AKSI_BARU, MasterAnggaranImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $hasil = self::evaluasiBaris([
                    'program' => $baris->program,
                    'kegiatan' => $baris->kegiatan,
                    'sub_kegiatan' => $baris->sub_kegiatan,
                    'kode_rekening' => $baris->kode_rekening,
                    'uraian_rekening' => $baris->uraian_rekening,
                    'tagging' => $baris->tagging_nama,
                    'pagu' => (float) $baris->pagu_baru,
                    'aktif' => $baris->aktif ? 'ya' : 'tidak',
                ]);

                if ($hasil['aksi'] === MasterAnggaranImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $taggingId = null;

                    if ($hasil['tagging_nama'] !== null) {
                        $taggingId = Tagging::firstOrCreate(['nama' => $hasil['tagging_nama']])->id;
                    }

                    $model = MasterAnggaran::updateOrCreate(
                        ['sub_kegiatan' => $hasil['sub_kegiatan'], 'kode_rekening' => $hasil['kode_rekening'], 'tagging_id' => $taggingId],
                        [
                            'program' => $hasil['program'],
                            'kegiatan' => $hasil['kegiatan'],
                            'uraian_rekening' => $hasil['uraian_rekening'],
                            'pagu' => $hasil['pagu_baru'],
                            'aktif' => $hasil['aktif'],
                        ]
                    );
                } catch (Throwable $e) {
                    // Sengaja dilempar ulang di DALAM transaction supaya seluruh
                    // batch (termasuk baris lain yang sudah disimpan) rollback.
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                if ($model->wasRecentlyCreated) {
                    $baruAkhir++;
                } else {
                    $updateAkhir++;
                }

                $baris->update(['master_anggaran_id' => $model->id, 'pagu_lama' => $hasil['pagu_lama']]);
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
     * Evaluasi satu baris data mentah terhadap kondisi master_anggaran SAAT
     * INI. Dipakai baik saat parse awal (preview) maupun saat konfirmasi
     * (re-validasi) - satu-satunya sumber aturan bisnis supaya keduanya tidak
     * bisa berbeda hasil.
     */
    private static function evaluasiBaris(array $mentah): array
    {
        $program = trim((string) ($mentah['program'] ?? ''));
        $kegiatan = trim((string) ($mentah['kegiatan'] ?? ''));
        $subKegiatan = trim((string) ($mentah['sub_kegiatan'] ?? ''));
        $kodeRekening = trim((string) ($mentah['kode_rekening'] ?? ''));
        $uraianRekening = trim((string) ($mentah['uraian_rekening'] ?? ''));
        $taggingNama = trim((string) ($mentah['tagging'] ?? ''));
        $taggingNama = $taggingNama === '' ? null : $taggingNama;
        $aktif = mb_strtolower(trim((string) ($mentah['aktif'] ?? ''))) !== 'tidak';

        $dasar = [
            'program' => $program,
            'kegiatan' => $kegiatan,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'uraian_rekening' => $uraianRekening !== '' ? $uraianRekening : null,
            'tagging_nama' => $taggingNama,
            'aktif' => $aktif,
            'pagu_baru' => null,
            'pagu_lama' => null,
            'master_anggaran_id' => null,
        ];

        if ($subKegiatan === '' || $kodeRekening === '') {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Sub Kegiatan atau Kode Rekening kosong.'];
        }

        if (mb_strlen($kodeRekening) > 50) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Kode Rekening melebihi 50 karakter.'];
        }

        $pagu = self::normalisasiAngka($mentah['pagu'] ?? null);
        $dasar['pagu_baru'] = $pagu;

        if ($pagu === null || $pagu < 0) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Pagu bukan angka yang valid.'];
        }

        $taggingId = $taggingNama !== null ? Tagging::where('nama', $taggingNama)->value('id') : null;

        $existing = MasterAnggaran::where('sub_kegiatan', $subKegiatan)
            ->where('kode_rekening', $kodeRekening)
            ->where('tagging_id', $taggingId)
            ->lockForUpdate()
            ->first();

        if (! $existing) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['master_anggaran_id'] = $existing->id;
        $dasar['pagu_lama'] = (float) $existing->pagu;

        $minimum = $existing->danaTerikatNpd() + $existing->realisasiLs();

        if ($pagu < $minimum) {
            return $dasar + [
                'aksi' => MasterAnggaranImportRow::AKSI_DITOLAK,
                'alasan' => sprintf(
                    'Pagu baru (Rp %s) lebih kecil dari dana terikat + realisasi LS saat ini (Rp %s).',
                    fmt_rupiah($pagu),
                    fmt_rupiah($minimum)
                ),
            ];
        }

        return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_UPDATE, 'alasan' => null];
    }

    /** Angka bisa datang sebagai float mentah (dari Export) atau teks berpemisah ribuan/koma (edit manual). */
    private static function normalisasiAngka(mixed $nilai): ?float
    {
        if (is_numeric($nilai)) {
            return (float) $nilai;
        }

        if (is_string($nilai)) {
            $bersih = trim($nilai);
            $bersih = str_replace('.', '', $bersih);
            $bersih = str_replace(',', '.', $bersih);

            if (is_numeric($bersih)) {
                return (float) $bersih;
            }
        }

        return null;
    }
}
