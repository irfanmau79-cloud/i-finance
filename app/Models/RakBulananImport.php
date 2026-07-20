<?php

namespace App\Models;

use App\Imports\RakBulananUploadImport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

/**
 * Batch import RAK Bulanan dengan alur preview/dry-run yang sama dengan
 * MasterAnggaranImport/SpmImport (Prompt 10/11). File yang diupload
 * berformat LEBAR (satu baris per mata anggaran, kolom Januari..Desember,
 * lihat app/Imports/RakBulananUploadImport.php) - lebih praktis diisi
 * manual daripada 12 baris terpisah per mata anggaran. Setiap baris lebar
 * di-explode menjadi sampai 12 baris staging (satu per bulan yang terisi)
 * di rak_bulanan_import_rows, supaya evaluasi & preview tetap granular per
 * bulan meski sumbernya lebar - lihat migration create_rak_bulanan_table
 * untuk penjelasan lengkap kenapa tabel akhirnya per-bulan, bukan per-tahun.
 */
#[Fillable([
    'user_id',
    'tahun',
    'nama_file',
    'status',
    'total_baris',
    'jumlah_baru',
    'jumlah_update',
    'jumlah_ditolak',
    'expires_at',
    'committed_at',
])]
class RakBulananImport extends Model
{
    protected $table = 'rak_bulanan_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    /** Batas baris LEBAR (per mata anggaran) - satu baris bisa explode jadi sampai 12 baris staging. */
    public const MAKS_BARIS = 2000;

    public const MENIT_KEDALUWARSA = 30;

    private const BULAN_KOLOM = [
        1 => 'januari', 2 => 'februari', 3 => 'maret', 4 => 'april',
        5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'agustus',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'desember',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(RakBulananImportRow::class, 'import_id');
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
    public static function buatDariUpload(UploadedFile $file, int $tahun, ?int $userId): self
    {
        $sheet = new RakBulananUploadImport();
        Excel::import($sheet, $file);

        $barisLebar = $sheet->rows->filter(
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        );

        if ($barisLebar->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pada sheet pertama.']);
        }

        if ($barisLebar->count() > self::MAKS_BARIS) {
            throw ValidationException::withMessages([
                'file' => sprintf('File berisi %d baris mata anggaran, melebihi batas maksimum %d baris per import.', $barisLebar->count(), self::MAKS_BARIS),
            ]);
        }

        return DB::transaction(function () use ($file, $tahun, $userId, $barisLebar) {
            $import = self::create([
                'user_id' => $userId,
                'tahun' => $tahun,
                'nama_file' => $file->getClientOriginalName(),
                'status' => self::STATUS_STAGED,
                'expires_at' => now()->addMinutes(self::MENIT_KEDALUWARSA),
            ]);

            $kunciTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($barisLebar as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2; // baris 1 = header

                $subKegiatan = trim((string) ($row['sub_kegiatan'] ?? ''));
                $kodeRekening = trim((string) ($row['kode_rekening'] ?? ''));
                $taggingNama = trim((string) ($row['tagging'] ?? ''));
                $taggingNama = $taggingNama === '' ? null : $taggingNama;

                [$masterAnggaran, $alasanGagal] = self::resolveMasterAnggaran($subKegiatan, $kodeRekening, $taggingNama);

                $duplikat = $masterAnggaran && isset($kunciTerlihat[$masterAnggaran->id]);

                if ($duplikat) {
                    $alasanGagal = "Duplikat mata anggaran dengan baris {$kunciTerlihat[$masterAnggaran->id]} pada file ini.";
                } elseif ($masterAnggaran) {
                    $kunciTerlihat[$masterAnggaran->id] = $nomorBaris;
                }

                foreach (self::BULAN_KOLOM as $bulan => $kolom) {
                    $nilaiMentah = $row[$kolom] ?? null;

                    if (! self::adaNilai($nilaiMentah)) {
                        continue; // bulan tidak diisi - dilewati, bukan ditolak
                    }

                    if ($alasanGagal !== null) {
                        $import->baris()->create([
                            'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                            'aksi' => RakBulananImportRow::AKSI_DITOLAK, 'alasan' => $alasanGagal,
                            'sub_kegiatan' => $subKegiatan, 'kode_rekening' => $kodeRekening, 'tagging_nama' => $taggingNama,
                            'master_anggaran_id' => $masterAnggaran?->id, 'target' => null, 'rak_bulanan_id' => null,
                        ]);
                        $ditolak++;

                        continue;
                    }

                    $target = self::normalisasiAngka($nilaiMentah);

                    if ($target === null || $target < 0) {
                        $import->baris()->create([
                            'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                            'aksi' => RakBulananImportRow::AKSI_DITOLAK,
                            'alasan' => 'Nilai bulan ini bukan angka non-negatif yang valid.',
                            'sub_kegiatan' => $subKegiatan, 'kode_rekening' => $kodeRekening, 'tagging_nama' => $taggingNama,
                            'master_anggaran_id' => $masterAnggaran->id, 'target' => null, 'rak_bulanan_id' => null,
                        ]);
                        $ditolak++;

                        continue;
                    }

                    $existing = RakBulanan::where('master_anggaran_id', $masterAnggaran->id)
                        ->where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->first();

                    $import->baris()->create([
                        'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                        'aksi' => $existing ? RakBulananImportRow::AKSI_UPDATE : RakBulananImportRow::AKSI_BARU,
                        'alasan' => null,
                        'sub_kegiatan' => $subKegiatan, 'kode_rekening' => $kodeRekening, 'tagging_nama' => $taggingNama,
                        'master_anggaran_id' => $masterAnggaran->id, 'target' => $target, 'rak_bulanan_id' => $existing?->id,
                    ]);

                    $existing ? $update++ : $baru++;
                }
            }

            $import->update([
                'total_baris' => $baru + $update + $ditolak,
                'jumlah_baru' => $baru,
                'jumlah_update' => $update,
                'jumlah_ditolak' => $ditolak,
            ]);

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
            $barisTerkena = $this->baris()
                ->whereIn('aksi', [RakBulananImportRow::AKSI_BARU, RakBulananImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->orderBy('bulan')
                ->lockForUpdate()
                ->get();

            $baruAkhir = 0;
            $updateAkhir = 0;
            $ditolakTambahan = 0;

            foreach ($barisTerkena as $baris) {
                [$masterAnggaran, $alasanGagal] = self::resolveMasterAnggaran($baris->sub_kegiatan, $baris->kode_rekening, $baris->tagging_nama);

                if ($alasanGagal !== null) {
                    $baris->update(['aksi' => RakBulananImportRow::AKSI_DITOLAK, 'alasan' => $alasanGagal]);
                    $ditolakTambahan++;

                    continue;
                }

                $target = (float) $baris->target;

                if ($target < 0) {
                    $baris->update(['aksi' => RakBulananImportRow::AKSI_DITOLAK, 'alasan' => 'Nilai bulan ini bukan angka non-negatif yang valid.']);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $rak = RakBulanan::updateOrCreate(
                        ['master_anggaran_id' => $masterAnggaran->id, 'tahun' => $this->tahun, 'bulan' => $baris->bulan],
                        ['target' => $target]
                    );
                } catch (Throwable $e) {
                    // Dilempar ulang di DALAM transaction supaya seluruh batch
                    // (termasuk baris lain yang sudah disimpan) rollback.
                    throw new RuntimeException("Baris {$baris->nomor_baris} (bulan {$baris->bulan}): gagal disimpan - {$e->getMessage()}");
                }

                $rak->wasRecentlyCreated ? $baruAkhir++ : $updateAkhir++;

                $baris->update(['master_anggaran_id' => $masterAnggaran->id, 'rak_bulanan_id' => $rak->id]);
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
     * Cocokkan (Sub Kegiatan, Kode Rekening, Tagging) ke master_anggaran
     * AKTIF yang sudah ada - tidak pernah membuat baris baru di sana ("Master
     * anggaran wajib ditemukan"). Dipakai baik saat parse (preview) maupun
     * konfirmasi (re-validasi) supaya keduanya tidak bisa berbeda hasil.
     *
     * @return array{0: ?MasterAnggaran, 1: ?string} [mata anggaran, alasan gagal]
     */
    private static function resolveMasterAnggaran(string $subKegiatan, string $kodeRekening, ?string $taggingNama): array
    {
        if ($subKegiatan === '' || $kodeRekening === '') {
            return [null, 'Sub Kegiatan atau Kode Rekening kosong.'];
        }

        $taggingId = null;

        if ($taggingNama !== null) {
            $taggingId = Tagging::where('nama', $taggingNama)->value('id');

            if ($taggingId === null) {
                return [null, "Tagging '{$taggingNama}' tidak ditemukan."];
            }
        }

        $masterAnggaran = MasterAnggaran::where('sub_kegiatan', $subKegiatan)
            ->where('kode_rekening', $kodeRekening)
            ->where('tagging_id', $taggingId)
            ->where('aktif', true)
            ->lockForUpdate()
            ->first();

        if (! $masterAnggaran) {
            return [null, 'Mata anggaran tidak ditemukan atau tidak aktif untuk kombinasi Sub Kegiatan + Kode Rekening + Tagging tersebut.'];
        }

        return [$masterAnggaran, null];
    }

    private static function adaNilai(mixed $nilai): bool
    {
        return $nilai !== null && trim((string) $nilai) !== '';
    }

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
