<?php

namespace App\Models;

use App\Imports\SpmUploadImport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

/**
 * Batch import SPM (UP/GU atau LS) dengan alur preview/dry-run yang sama
 * dengan MasterAnggaranImport (Prompt 10): upload -> staging (belum
 * menyentuh tabel spm) -> preview -> konfirmasi -> validasi ulang + commit
 * dalam satu transaction. Satu batch selalu satu jenis_spm.
 *
 * LS wajib cocok ke master_anggaran aktif (tidak pernah membuat baris baru
 * di master_anggaran) dan tunduk pada batas anggaran AGREGAT per batch:
 * beberapa baris LS pada file yang sama bisa menyasar mata anggaran yang
 * sama, jadi validasi per-baris saja tidak cukup - lihat terapkanBatasAnggaran().
 */
#[Fillable([
    'user_id',
    'jenis_spm',
    'nama_file',
    'status',
    'total_baris',
    'jumlah_baru',
    'jumlah_update',
    'jumlah_ditolak',
    'expires_at',
    'committed_at',
])]
class SpmImport extends Model
{
    protected $table = 'spm_imports';

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
        return $this->hasMany(SpmImportRow::class, 'import_id');
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
    public static function buatDariUpload(UploadedFile $file, string $jenisSpm, ?int $userId): self
    {
        if (! in_array($jenisSpm, Spm::JENIS_LIST, true)) {
            throw new InvalidArgumentException("Jenis SPM tidak dikenal: {$jenisSpm}");
        }

        $sheet = new SpmUploadImport();
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

        return DB::transaction(function () use ($file, $jenisSpm, $userId, $baris) {
            $import = self::create([
                'user_id' => $userId,
                'jenis_spm' => $jenisSpm,
                'nama_file' => $file->getClientOriginalName(),
                'status' => self::STATUS_STAGED,
                'total_baris' => $baris->count(),
                'expires_at' => now()->addMinutes(self::MENIT_KEDALUWARSA),
            ]);

            $hasilBaris = self::evaluasiSemuaBaris($baris, $jenisSpm);

            foreach ($hasilBaris as $nomorBaris => $hasil) {
                $import->baris()->create(self::kolomBaris($nomorBaris, $hasil));
            }

            $import->update(self::hitungRingkasan($hasilBaris));

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
                ->whereIn('aksi', [SpmImportRow::AKSI_BARU, SpmImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            $hasilBaris = [];

            foreach ($barisTerkena as $baris) {
                $mentah = [
                    'nomor_dokumen' => $baris->nomor_dokumen,
                    'tanggal_dokumen' => $baris->tanggal_dokumen?->format('Y-m-d'),
                    'nomor_sp2d' => $baris->nomor_sp2d,
                    'tanggal_sp2d' => $baris->tanggal_sp2d?->format('Y-m-d'),
                    'sub_kegiatan' => $baris->sub_kegiatan,
                    'kode_rekening' => $baris->kode_rekening,
                    'tagging' => $baris->tagging_nama,
                    'nominal' => (float) $baris->nominal,
                    'ppn' => (float) $baris->ppn,
                    'pph_1' => (float) $baris->pph1,
                    'jenis_pph_1' => $baris->jenis_pph1,
                    'pph_2' => (float) $baris->pph2,
                    'jenis_pph_2' => $baris->jenis_pph2,
                    'penerima' => $baris->penerima,
                    'uraian' => $baris->uraian,
                ];

                $hasil = $this->jenis_spm === 'ls' ? self::evaluasiLs($mentah) : self::evaluasiUpGu($mentah);
                $hasil['_baris'] = $baris;
                $hasilBaris[$baris->nomor_baris] = $hasil;
            }

            if ($this->jenis_spm === 'ls') {
                $hasilBaris = self::terapkanBatasAnggaran($hasilBaris);
            }

            $baruAkhir = 0;
            $updateAkhir = 0;
            $ditolakTambahan = 0;

            foreach ($hasilBaris as $hasil) {
                $barisModel = $hasil['_baris'];

                if ($hasil['aksi'] === SpmImportRow::AKSI_DITOLAK) {
                    $barisModel->update([
                        'aksi' => SpmImportRow::AKSI_DITOLAK,
                        'alasan' => $hasil['alasan'],
                        'sisa_sebelum' => $hasil['sisa_sebelum'],
                        'sisa_sesudah' => $hasil['sisa_sesudah'],
                    ]);
                    $ditolakTambahan++;

                    continue;
                }

                $data = [
                    'tanggal_dokumen' => $hasil['tanggal_dokumen'],
                    'nomor_dokumen' => $hasil['nomor_dokumen'],
                    'tanggal_sp2d' => $hasil['tanggal_sp2d'],
                    'nomor_sp2d' => $hasil['nomor_sp2d'],
                    'master_anggaran_id' => $hasil['master_anggaran_id'],
                    'nominal' => $hasil['nominal'],
                    'ppn' => $hasil['ppn'],
                    'pph1' => $hasil['pph1'],
                    'jenis_pph1' => $hasil['jenis_pph1'],
                    'pph2' => $hasil['pph2'],
                    'jenis_pph2' => $hasil['jenis_pph2'],
                    'penerima' => $hasil['penerima'],
                    'uraian' => $hasil['uraian'],
                ];

                try {
                    if ($hasil['spm_id'] !== null) {
                        $spm = Spm::where('id', $hasil['spm_id'])->lockForUpdate()->firstOrFail();
                        $spm->update($data + ['jenis_spm' => $this->jenis_spm]);
                    } else {
                        $spm = Spm::create($data + ['jenis_spm' => $this->jenis_spm]);
                    }
                } catch (Throwable $e) {
                    // Dilempar ulang di DALAM transaction supaya seluruh batch
                    // (termasuk baris lain yang sudah disimpan) rollback.
                    throw new RuntimeException("Baris {$barisModel->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                if ($hasil['spm_id'] !== null) {
                    $updateAkhir++;
                } else {
                    $baruAkhir++;
                }

                $barisModel->update([
                    'spm_id' => $spm->id,
                    'sisa_sebelum' => $hasil['sisa_sebelum'],
                    'sisa_sesudah' => $hasil['sisa_sesudah'],
                ]);
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

    /** @return array<int, array> keyed by nomor_baris, urut sesuai file. */
    private static function evaluasiSemuaBaris(Collection $baris, string $jenisSpm): array
    {
        $hasilBaris = [];
        $kunciTerlihat = [];

        foreach ($baris as $indeksAsli => $row) {
            $nomorBaris = $indeksAsli + 2; // baris 1 = header

            $mentah = [
                'nomor_dokumen' => $row['nomor_dokumen'] ?? null,
                'tanggal_dokumen' => $row['tanggal_dokumen'] ?? null,
                'nomor_sp2d' => $row['nomor_sp2d'] ?? null,
                'tanggal_sp2d' => $row['tanggal_sp2d'] ?? null,
                'sub_kegiatan' => $row['sub_kegiatan'] ?? null,
                'kode_rekening' => $row['kode_rekening'] ?? null,
                'tagging' => $row['tagging'] ?? null,
                'nominal' => $row['nominal'] ?? null,
                'ppn' => $row['ppn'] ?? null,
                'pph_1' => $row['pph_1'] ?? null,
                'jenis_pph_1' => $row['jenis_pph_1'] ?? null,
                'pph_2' => $row['pph_2'] ?? null,
                'jenis_pph_2' => $row['jenis_pph_2'] ?? null,
                'penerima' => $row['penerima'] ?? null,
                'uraian' => $row['uraian'] ?? null,
            ];

            $hasil = $jenisSpm === 'ls' ? self::evaluasiLs($mentah) : self::evaluasiUpGu($mentah);

            if ($hasil['aksi'] !== SpmImportRow::AKSI_DITOLAK) {
                $kunci = mb_strtolower((string) $hasil['nomor_dokumen']).'|'.$hasil['tanggal_dokumen'];

                if (isset($kunciTerlihat[$kunci])) {
                    $hasil['aksi'] = SpmImportRow::AKSI_DITOLAK;
                    $hasil['alasan'] = "Duplikat Nomor Dokumen + Tanggal Dokumen dengan baris {$kunciTerlihat[$kunci]} pada file ini.";
                } else {
                    $kunciTerlihat[$kunci] = $nomorBaris;
                }
            }

            $hasilBaris[$nomorBaris] = $hasil;
        }

        if ($jenisSpm === 'ls') {
            $hasilBaris = self::terapkanBatasAnggaran($hasilBaris);
        }

        return $hasilBaris;
    }

    /**
     * Field dokumen umum (UP/GU maupun LS) + validasi dasarnya. Tidak
     * menentukan aksi/mata-anggaran - itu urusan evaluasiUpGu()/evaluasiLs().
     */
    private static function dasarDariMentah(array $mentah): array
    {
        return [
            'nomor_dokumen' => trim((string) ($mentah['nomor_dokumen'] ?? '')),
            'tanggal_dokumen' => self::normalisasiTanggal($mentah['tanggal_dokumen'] ?? null),
            'nomor_sp2d' => trim((string) ($mentah['nomor_sp2d'] ?? '')) ?: null,
            'tanggal_sp2d' => self::normalisasiTanggal($mentah['tanggal_sp2d'] ?? null),
            'nominal' => self::normalisasiAngka($mentah['nominal'] ?? null),
            'ppn' => self::normalisasiAngka($mentah['ppn'] ?? null) ?? 0.0,
            'pph1' => self::normalisasiAngka($mentah['pph_1'] ?? null) ?? 0.0,
            'jenis_pph1' => trim((string) ($mentah['jenis_pph_1'] ?? '')) ?: null,
            'pph2' => self::normalisasiAngka($mentah['pph_2'] ?? null) ?? 0.0,
            'jenis_pph2' => trim((string) ($mentah['jenis_pph_2'] ?? '')) ?: null,
            'penerima' => trim((string) ($mentah['penerima'] ?? '')) ?: null,
            'uraian' => trim((string) ($mentah['uraian'] ?? '')) ?: null,
        ];
    }

    private static function dasarDitolak(array $dasar, string $alasan): array
    {
        return $dasar + [
            'aksi' => SpmImportRow::AKSI_DITOLAK,
            'alasan' => $alasan,
            'spm_id' => null,
            'master_anggaran_id' => null,
            'sub_kegiatan' => null,
            'kode_rekening' => null,
            'tagging_nama' => null,
            'nominal_lama' => null,
            'sisa_sebelum' => null,
            'sisa_sesudah' => null,
        ];
    }

    private static function evaluasiUpGu(array $mentah): array
    {
        $dasar = self::dasarDariMentah($mentah);

        if ($dasar['nomor_dokumen'] === '' || $dasar['tanggal_dokumen'] === null) {
            return self::dasarDitolak($dasar, 'Nomor Dokumen atau Tanggal Dokumen kosong/tidak valid.');
        }

        if ($dasar['nominal'] === null || $dasar['nominal'] <= 0) {
            return self::dasarDitolak($dasar, 'Nominal harus lebih dari 0.');
        }

        $existing = Spm::where('jenis_spm', 'up_gu')
            ->where('nomor_dokumen', $dasar['nomor_dokumen'])
            ->whereDate('tanggal_dokumen', $dasar['tanggal_dokumen'])
            ->lockForUpdate()
            ->first();

        return $dasar + [
            'aksi' => $existing ? SpmImportRow::AKSI_UPDATE : SpmImportRow::AKSI_BARU,
            'alasan' => null,
            'spm_id' => $existing?->id,
            'master_anggaran_id' => null,
            'sub_kegiatan' => null,
            'kode_rekening' => null,
            'tagging_nama' => null,
            'nominal_lama' => null,
            'sisa_sebelum' => null,
            'sisa_sesudah' => null,
        ];
    }

    private static function evaluasiLs(array $mentah): array
    {
        $dasar = self::dasarDariMentah($mentah);

        if ($dasar['nomor_dokumen'] === '' || $dasar['tanggal_dokumen'] === null) {
            return self::dasarDitolak($dasar, 'Nomor Dokumen atau Tanggal Dokumen kosong/tidak valid.');
        }

        if ($dasar['nominal'] === null || $dasar['nominal'] <= 0) {
            return self::dasarDitolak($dasar, 'Nominal harus lebih dari 0.');
        }

        $subKegiatan = trim((string) ($mentah['sub_kegiatan'] ?? ''));
        $kodeRekening = trim((string) ($mentah['kode_rekening'] ?? ''));
        $taggingNama = trim((string) ($mentah['tagging'] ?? ''));
        $taggingNama = $taggingNama === '' ? null : $taggingNama;

        if ($subKegiatan === '' || $kodeRekening === '') {
            return self::dasarDitolak($dasar, 'Sub Kegiatan atau Kode Rekening kosong - wajib untuk SPM LS.');
        }

        $taggingId = null;

        if ($taggingNama !== null) {
            $taggingId = Tagging::where('nama', $taggingNama)->value('id');

            if ($taggingId === null) {
                return self::dasarDitolak($dasar, "Tagging '{$taggingNama}' tidak ditemukan.");
            }
        }

        $masterAnggaran = MasterAnggaran::where('sub_kegiatan', $subKegiatan)
            ->where('kode_rekening', $kodeRekening)
            ->where('tagging_id', $taggingId)
            ->where('aktif', true)
            ->lockForUpdate()
            ->first();

        if (! $masterAnggaran) {
            return self::dasarDitolak($dasar, 'Mata anggaran tidak ditemukan atau tidak aktif untuk kombinasi Sub Kegiatan + Kode Rekening + Tagging tersebut.');
        }

        $existing = Spm::where('jenis_spm', 'ls')
            ->where('nomor_dokumen', $dasar['nomor_dokumen'])
            ->whereDate('tanggal_dokumen', $dasar['tanggal_dokumen'])
            ->lockForUpdate()
            ->first();

        return $dasar + [
            'aksi' => $existing ? SpmImportRow::AKSI_UPDATE : SpmImportRow::AKSI_BARU,
            'alasan' => null,
            'spm_id' => $existing?->id,
            'master_anggaran_id' => $masterAnggaran->id,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'tagging_nama' => $taggingNama,
            // "lama" hanya berarti kalau row ini update DAN mata anggarannya
            // TIDAK berubah - kalau LS dipindah ke mata anggaran lain, nominal
            // lama tetap mengurangi mata anggaran LAMA di realisasiLs() sampai
            // baris ini benar-benar di-commit, jadi tidak dikreditkan di sini.
            'nominal_lama' => ($existing && $existing->master_anggaran_id === $masterAnggaran->id) ? (float) $existing->nominal : 0.0,
            'sisa_sebelum' => null,
            'sisa_sesudah' => null,
        ];
    }

    /**
     * Batas anggaran AGREGAT per mata anggaran, diterapkan setelah semua
     * baris LS dievaluasi individual. Diproses berurutan sesuai file supaya
     * beberapa baris yang menyasar mata anggaran sama tidak lolos bersama
     * padahal totalnya melebihi sisa tersedia - baris yang tidak muat
     * ditolak, baris sebelumnya yang sudah muat tetap diterima (greedy,
     * urutan file). Baris yang ditolak TIDAK mengonsumsi sisa anggaran
     * berjalan, supaya baris berikutnya yang lebih kecil masih punya
     * kesempatan.
     *
     * @param  array<int, array>  $hasilBaris
     * @return array<int, array>
     */
    private static function terapkanBatasAnggaran(array $hasilBaris): array
    {
        $sisaBerjalan = [];

        foreach ($hasilBaris as $nomorBaris => $hasil) {
            if ($hasil['aksi'] === SpmImportRow::AKSI_DITOLAK || $hasil['master_anggaran_id'] === null) {
                continue;
            }

            $masterAnggaranId = $hasil['master_anggaran_id'];

            if (! array_key_exists($masterAnggaranId, $sisaBerjalan)) {
                $sisaBerjalan[$masterAnggaranId] = MasterAnggaran::where('id', $masterAnggaranId)->lockForUpdate()->firstOrFail()->sisaTersedia();
            }

            $delta = $hasil['nominal'] - $hasil['nominal_lama'];
            $sisaSebelum = $sisaBerjalan[$masterAnggaranId];
            $sisaSesudah = $sisaSebelum - $delta;

            $hasilBaris[$nomorBaris]['sisa_sebelum'] = $sisaSebelum;
            $hasilBaris[$nomorBaris]['sisa_sesudah'] = $sisaSesudah;

            if ($sisaSesudah < 0) {
                $hasilBaris[$nomorBaris]['aksi'] = SpmImportRow::AKSI_DITOLAK;
                $hasilBaris[$nomorBaris]['alasan'] = sprintf(
                    'Melebihi sisa tersedia mata anggaran setelah memperhitungkan baris lain pada batch ini (sisa Rp %s, kekurangan Rp %s).',
                    fmt_rupiah($sisaSebelum),
                    fmt_rupiah(abs($sisaSesudah))
                );
            } else {
                $sisaBerjalan[$masterAnggaranId] = $sisaSesudah;
            }
        }

        return $hasilBaris;
    }

    private static function kolomBaris(int $nomorBaris, array $hasil): array
    {
        return [
            'nomor_baris' => $nomorBaris,
            'aksi' => $hasil['aksi'],
            'alasan' => $hasil['alasan'],
            'nomor_dokumen' => $hasil['nomor_dokumen'],
            'tanggal_dokumen' => $hasil['tanggal_dokumen'],
            'nomor_sp2d' => $hasil['nomor_sp2d'],
            'tanggal_sp2d' => $hasil['tanggal_sp2d'],
            'sub_kegiatan' => $hasil['sub_kegiatan'],
            'kode_rekening' => $hasil['kode_rekening'],
            'tagging_nama' => $hasil['tagging_nama'],
            'master_anggaran_id' => $hasil['master_anggaran_id'],
            'nominal' => $hasil['nominal'],
            'ppn' => $hasil['ppn'],
            'pph1' => $hasil['pph1'],
            'jenis_pph1' => $hasil['jenis_pph1'],
            'pph2' => $hasil['pph2'],
            'jenis_pph2' => $hasil['jenis_pph2'],
            'penerima' => $hasil['penerima'],
            'uraian' => $hasil['uraian'],
            'sisa_sebelum' => $hasil['sisa_sebelum'],
            'sisa_sesudah' => $hasil['sisa_sesudah'],
            'spm_id' => $hasil['spm_id'],
        ];
    }

    private static function hitungRingkasan(array $hasilBaris): array
    {
        $baru = 0;
        $update = 0;
        $ditolak = 0;

        foreach ($hasilBaris as $hasil) {
            match ($hasil['aksi']) {
                SpmImportRow::AKSI_BARU => $baru++,
                SpmImportRow::AKSI_UPDATE => $update++,
                default => $ditolak++,
            };
        }

        return ['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak];
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

    private static function normalisasiTanggal(mixed $nilai): ?string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_numeric($nilai)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
            } catch (Throwable $e) {
                return null;
            }
        }

        $teks = trim((string) $nilai);

        if ($teks === '') {
            return null;
        }

        try {
            return Carbon::parse($teks)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }
}
