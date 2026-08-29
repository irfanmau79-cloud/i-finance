<?php

namespace App\Models;

use App\Exports\RakBulananExport;
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
 * berformat LEBAR (satu baris per kombinasi Sub Kegiatan + Kode Rekening,
 * kolom Januari..Desember, lihat app/Imports/RakBulananUploadImport.php) -
 * lebih praktis diisi manual daripada 12 baris terpisah. Setiap baris lebar
 * di-explode menjadi sampai 12 baris staging (satu per bulan yang terisi)
 * di rak_bulanan_import_rows, supaya evaluasi & preview tetap granular per
 * bulan meski sumbernya lebar.
 *
 * Prompt 12A: identitas RAK adalah (tahun, Sub Kegiatan, Kode Rekening) -
 * TIDAK melibatkan Tagging. File format lama yang masih punya kolom Tagging
 * tetap bisa dibaca (kolomnya diabaikan sepenuhnya, hanya dicatat sebagai
 * peringatan header lewat ada_kolom_tagging_lama).
 */
#[Fillable([
    'user_id',
    'tahun',
    'nama_file',
    'ada_kolom_tagging_lama',
    'format_sumber',
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

    /** Batas baris LEBAR (per kombinasi Sub Kegiatan+Kode Rekening) - satu baris bisa explode jadi sampai 12 baris staging. */
    public const MAKS_BARIS = 2000;

    public const MENIT_KEDALUWARSA = 30;

    public const FORMAT_MONTHLY_V2 = 'monthly_v2';

    public const FORMAT_LEGACY_MONTHLY_V1 = 'legacy_laravel_monthly_v1';

    public const FORMAT_LEGACY_GAS_CUMULATIVE_V1 = 'legacy_gas_cumulative_v1';

    private const BULAN_KOLOM = [
        1 => 'januari', 2 => 'februari', 3 => 'maret', 4 => 'april',
        5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'agustus',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'desember',
    ];

    protected function casts(): array
    {
        return [
            'ada_kolom_tagging_lama' => 'boolean',
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
    public static function buatDariUpload(UploadedFile $file, int $tahun, ?int $userId, ?string $formatLegacy = null): self
    {
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        if ($tahun !== $tahunAktif) {
            throw ValidationException::withMessages([
                'tahun' => "Import RAK Bulanan hanya menerima Tahun Anggaran {$tahunAktif}.",
            ]);
        }

        $sheet = new RakBulananUploadImport;
        Excel::import($sheet, $file);

        [$barisLebar, $formatSumber, $adaKolomTaggingLama, $barisDataMulai] = self::normalisasiWorkbook($sheet->rows, $formatLegacy);
        $barisLebar = $barisLebar->filter(
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        );

        if ($barisLebar->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pada sheet pertama.']);
        }

        if ($barisLebar->count() > self::MAKS_BARIS) {
            throw ValidationException::withMessages([
                'file' => sprintf('File berisi %d baris, melebihi batas maksimum %d baris per import.', $barisLebar->count(), self::MAKS_BARIS),
            ]);
        }

        return DB::transaction(function () use ($file, $tahun, $userId, $barisLebar, $adaKolomTaggingLama, $formatSumber, $barisDataMulai) {
            $import = self::create([
                'user_id' => $userId,
                'tahun' => $tahun,
                'nama_file' => $file->getClientOriginalName(),
                'ada_kolom_tagging_lama' => $adaKolomTaggingLama,
                'format_sumber' => $formatSumber,
                'status' => self::STATUS_STAGED,
                'expires_at' => now()->addMinutes(self::MENIT_KEDALUWARSA),
            ]);

            $kunciTerlihat = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($barisLebar as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + $barisDataMulai;

                // Template sekarang memisahkan Kode Sub Kegiatan dari namanya.
                // Keduanya digabung kembali di sini supaya baris staging,
                // tampilan preview, dan pencocokan ke master_anggaran tetap
                // memakai label utuh "{kode} {nama}" seperti format lama -
                // tidak ada kolom staging baru dan file lama tetap terbaca.
                $subKegiatan = MasterAnggaran::gabungKodeUraian(
                    trim((string) ($row['kode_sub_kegiatan'] ?? '')),
                    trim((string) ($row['sub_kegiatan'] ?? ''))
                );
                // File sumber (termasuk hasil export lama) kadang berisi
                // kode+uraian gabungan di kolom Kode Rekening - ambil bagian
                // kode saja untuk matching & penyimpanan (kolom staging
                // kode_rekening tetap varchar pendek, sama format dengan
                // rak_bulanan.kode_rekening).
                $kodeRekening = MasterAnggaran::pisahKodeUraian(trim((string) ($row['kode_rekening'] ?? '')))[0];
                $tahunFile = trim((string) ($row['tahun_anggaran'] ?? ''));

                // Kolom Tagging (kalau ada, format lama) dibaca murni untuk
                // ditampilkan sebagai peringatan - TIDAK PERNAH dipakai untuk
                // mencari/membuat RAK.
                $taggingNamaLama = $adaKolomTaggingLama ? trim((string) ($row['tagging'] ?? '')) : null;
                $taggingNamaLama = ($taggingNamaLama === '' ? null : $taggingNamaLama);

                [$subKegiatanKunci, $kodeRekeningValid, $alasanGagal] = self::resolveSubKegiatanKodeRekening($subKegiatan, $kodeRekening);

                if ($tahunFile !== '' && (! ctype_digit($tahunFile) || (int) $tahunFile !== $tahun)) {
                    $alasanGagal = "Tahun Anggaran pada file ({$tahunFile}) tidak sama dengan tahun import ({$tahun}).";
                }

                $kunciMataAnggaran = $subKegiatanKunci !== null ? $subKegiatanKunci.'|'.$kodeRekeningValid : null;
                $duplikat = $kunciMataAnggaran !== null && isset($kunciTerlihat[$kunciMataAnggaran]);

                if ($duplikat) {
                    $alasanGagal = "Duplikat Sub Kegiatan + Kode Rekening dengan baris {$kunciTerlihat[$kunciMataAnggaran]} pada file ini.";
                } elseif ($kunciMataAnggaran !== null) {
                    $kunciTerlihat[$kunciMataAnggaran] = $nomorBaris;
                }

                $kumulatifSebelumnya = 0.0;
                foreach (self::BULAN_KOLOM as $bulan => $kolom) {
                    $nilaiMentah = $row[$kolom] ?? null;

                    if (! self::adaNilai($nilaiMentah)) {
                        continue; // bulan tidak diisi - dilewati, bukan ditolak
                    }

                    $targetAsli = self::normalisasiAngka($nilaiMentah);

                    if ($alasanGagal !== null) {
                        $import->baris()->create([
                            'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                            'aksi' => RakBulananImportRow::AKSI_DITOLAK, 'alasan' => $alasanGagal,
                            'sub_kegiatan' => $subKegiatan, 'sub_kegiatan_kunci' => $subKegiatanKunci, 'kode_rekening' => $kodeRekening,
                            'tagging_nama' => $taggingNamaLama, 'target_asli' => $targetAsli, 'target' => null, 'rak_bulanan_id' => null,
                        ]);
                        $ditolak++;

                        continue;
                    }

                    $target = $targetAsli;

                    if ($formatSumber === self::FORMAT_LEGACY_GAS_CUMULATIVE_V1 && $targetAsli !== null) {
                        $target = $targetAsli - $kumulatifSebelumnya;
                        $kumulatifSebelumnya = $targetAsli;
                    }

                    if ($target === null || $target < 0) {
                        $import->baris()->create([
                            'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                            'aksi' => RakBulananImportRow::AKSI_DITOLAK,
                            'alasan' => $formatSumber === self::FORMAT_LEGACY_GAS_CUMULATIVE_V1 && $target !== null && $target < 0
                                ? 'Nilai kumulatif menurun dari bulan sebelumnya sehingga hasil konversi bulanan menjadi negatif.'
                                : 'Nilai bulan ini bukan angka non-negatif yang valid.',
                            'sub_kegiatan' => $subKegiatan, 'sub_kegiatan_kunci' => $subKegiatanKunci, 'kode_rekening' => $kodeRekening,
                            'tagging_nama' => $taggingNamaLama, 'target_asli' => $targetAsli, 'target' => null, 'rak_bulanan_id' => null,
                        ]);
                        $ditolak++;

                        continue;
                    }

                    $existing = RakBulanan::where('sub_kegiatan_kunci', $subKegiatanKunci)
                        ->where('kode_rekening', $kodeRekeningValid)
                        ->where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->first();

                    $import->baris()->create([
                        'nomor_baris' => $nomorBaris, 'bulan' => $bulan,
                        'aksi' => $existing ? RakBulananImportRow::AKSI_UPDATE : RakBulananImportRow::AKSI_BARU,
                        'alasan' => null,
                        'sub_kegiatan' => $subKegiatan, 'sub_kegiatan_kunci' => $subKegiatanKunci, 'kode_rekening' => $kodeRekeningValid,
                        'tagging_nama' => $taggingNamaLama, 'target_asli' => $targetAsli, 'target' => $target, 'rak_bulanan_id' => $existing?->id,
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

    private static function normalisasiWorkbook(Collection $rows, ?string $formatLegacy): array
    {
        $rows = $rows->values();
        $marker = trim((string) ($rows->first()[0] ?? ''));
        $headerIndex = 0;

        if ($marker === RakBulananExport::FORMAT_MARKER) {
            // Berkas hasil export versi lama: masih ada baris marker &
            // instruksi di atas header.
            $format = self::FORMAT_MONTHLY_V2;
            $headerIndex = 2;
        } elseif ($marker === 'IFINANCE_RAK_GAS_CUMULATIVE_V1') {
            $format = self::FORMAT_LEGACY_GAS_CUMULATIVE_V1;
            $headerIndex = 2;
        } else {
            $format = $formatLegacy ?: self::FORMAT_LEGACY_MONTHLY_V1;
            if ($format === self::FORMAT_LEGACY_GAS_CUMULATIVE_V1) {
                throw ValidationException::withMessages(['file' => 'Format GAS kumulatif wajib memiliki marker IFINANCE_RAK_GAS_CUMULATIVE_V1.']);
            }
        }

        $header = collect($rows->get($headerIndex, []))->map(fn ($value) => self::normalisasiHeader($value))->all();
        if (! in_array('sub_kegiatan', $header, true) || ! in_array('kode_rekening', $header, true)) {
            throw ValidationException::withMessages(['file' => 'Header Sub Kegiatan dan Kode Rekening tidak ditemukan pada format yang dipilih.']);
        }

        // Export sekarang menaruh header langsung di baris 1 tanpa marker,
        // jadi format resminya dikenali dari TANDA TANGAN HEADER: dua kolom
        // ini hanya ada pada template terbaru. Tanpa ini berkas resmi akan
        // tercatat sebagai "legacy" di log import padahal bukan.
        if ($headerIndex === 0
            && in_array('kode_sub_kegiatan', $header, true)
            && in_array('total_rak', $header, true)) {
            $format = self::FORMAT_MONTHLY_V2;
        }

        $adaTagging = in_array('tagging', $header, true);
        $data = $rows->slice($headerIndex + 1)->map(function ($row) use ($header) {
            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $assoc[$key] = $row[$i] ?? null;
                }
            }

            return $assoc;
        })->values();

        return [$data, $format, $adaTagging, $headerIndex + 2];
    }

    private static function normalisasiHeader(mixed $value): string
    {
        return trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim((string) $value)))), '_');
    }

    /**
     * @throws RuntimeException kalau batch sudah diproses, kedaluwarsa, atau ada baris gagal disimpan fatal.
     */
    public function konfirmasi(): array
    {
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        if ((int) $this->tahun !== $tahunAktif) {
            throw new RuntimeException("Import RAK Bulanan hanya dapat dikonfirmasi untuk Tahun Anggaran {$tahunAktif}.");
        }

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
                [$subKegiatanKunci, $kodeRekening, $alasanGagal] = self::resolveSubKegiatanKodeRekening($baris->sub_kegiatan, $baris->kode_rekening);

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
                        ['sub_kegiatan_kunci' => $subKegiatanKunci, 'kode_rekening' => $kodeRekening, 'tahun' => $this->tahun, 'bulan' => $baris->bulan],
                        ['sub_kegiatan' => $baris->sub_kegiatan, 'target' => $target]
                    );
                } catch (Throwable $e) {
                    // Dilempar ulang di DALAM transaction supaya seluruh batch
                    // (termasuk baris lain yang sudah disimpan) rollback.
                    throw new RuntimeException("Baris {$baris->nomor_baris} (bulan {$baris->bulan}): gagal disimpan - {$e->getMessage()}");
                }

                $rak->wasRecentlyCreated ? $baruAkhir++ : $updateAkhir++;

                $baris->update(['sub_kegiatan_kunci' => $subKegiatanKunci, 'rak_bulanan_id' => $rak->id]);
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
     * Cocokkan (Sub Kegiatan, Kode Rekening) ke master_anggaran AKTIF yang
     * sudah ada - TIDAK PERNAH melibatkan Tagging, dan tidak pernah membuat
     * baris baru di sana ("mata anggaran wajib ditemukan"). Kode Rekening
     * divalidasi harus berada dalam Sub Kegiatan yang sama (bukan pencarian
     * global). Dipakai baik saat parse (preview) maupun konfirmasi
     * (re-validasi) supaya keduanya tidak bisa berbeda hasil.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [sub_kegiatan_kunci, kode_rekening, alasan gagal]
     */
    private static function resolveSubKegiatanKodeRekening(string $subKegiatan, string $kodeRekening): array
    {
        if ($subKegiatan === '' || $kodeRekening === '') {
            return [null, null, 'Sub Kegiatan atau Kode Rekening kosong.'];
        }

        $subKegiatanKunci = MasterAnggaran::normalisasiKunci($subKegiatan);

        $ada = MasterAnggaran::where('sub_kegiatan_kunci', $subKegiatanKunci)
            ->where('kode_rekening_bersih', $kodeRekening)
            ->where('aktif', true)
            ->lockForUpdate()
            ->exists();

        if ($ada) {
            return [$subKegiatanKunci, $kodeRekening, null];
        }

        // Cadangan: cocokkan lewat KODE sub kegiatan saja. Menolong kalau nama
        // sub kegiatan di file sudah tidak persis sama dengan yang tersimpan
        // (mis. diperbarui di DPA berikutnya) - kodenya yang jadi identitas.
        // sub_kegiatan_kunci milik master yang cocok tetap dipakai sebagai
        // kunci RAK supaya identitasnya konsisten dengan baris RAK lama.
        [$kodeSubKegiatan] = MasterAnggaran::pisahKodeUraian($subKegiatan);

        if ($kodeSubKegiatan !== '') {
            $kunciMaster = MasterAnggaran::where('kode_sub_kegiatan', $kodeSubKegiatan)
                ->where('kode_rekening_bersih', $kodeRekening)
                ->where('aktif', true)
                ->lockForUpdate()
                ->value('sub_kegiatan_kunci');

            if ($kunciMaster !== null) {
                return [$kunciMaster, $kodeRekening, null];
            }
        }

        return [null, null, 'Kode Rekening tidak ditemukan dalam Sub Kegiatan tersebut, atau mata anggaran tidak aktif.'];
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
