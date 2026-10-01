<?php

namespace App\Models;

use App\Imports\RincianUploadImport;
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
 * Import parsial rincian Perjalanan Dinas manual, pola preview/dry-run.
 *
 * Baris dikenali dari NIP + Bulan + Tahun. Yang sudah ada DIPERBARUI, bukan
 * ditolak - itulah inti "import parsial": memperbaiki beberapa baris tidak
 * lagi menuntut hapus-semua-lalu-muat-ulang.
 *
 * Tahun diambil dari formulir import, bukan dari kolom berkas, supaya satu
 * berkas tidak bisa setengah menulis ke tahun yang salah.
 *
 * NIP yang TIDAK ADA di Data Pegawai ditolak dengan alasan jelas - tidak
 * dibuatkan pegawai baru. Dashboard mengelompokkan orang lewat NIP; membuat
 * pegawai dadakan dari berkas akan melahirkan orang kembar yang baru
 * ketahuan setelah angkanya terlanjur salah.
 */
#[Fillable([
    'user_id', 'nama_file', 'status', 'tahun',
    'total_baris', 'jumlah_baru', 'jumlah_update', 'jumlah_ditolak',
    'expires_at', 'committed_at',
])]
class PerjalananDinasManualImport extends Model
{
    use StagingKedaluwarsa;

    protected $table = 'perjalanan_dinas_manual_imports';

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
        return $this->hasMany(PerjalananDinasManualImportRow::class, 'import_id');
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

                if ($hasil['aksi'] !== PerjalananDinasManualImportRow::AKSI_DITOLAK) {
                    $kunci = $hasil['isi']['nip'].'|'.$hasil['isi']['bulan'];

                    if (isset($terlihat[$kunci])) {
                        $hasil['aksi'] = PerjalananDinasManualImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Orang & bulan yang sama sudah ada di baris {$terlihat[$kunci]} pada berkas ini.";
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
            $ditolakTambahan = 0;

            $barisTerkena = $this->baris()
                ->whereIn('aksi', [PerjalananDinasManualImportRow::AKSI_BARU, PerjalananDinasManualImportRow::AKSI_UPDATE])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                $isi = $baris->isi ?? [];
                $pegawai = Pegawai::where('nip', $isi['nip'] ?? '')->first();

                if ($pegawai === null) {
                    $baris->update([
                        'aksi' => PerjalananDinasManualImportRow::AKSI_DITOLAK,
                        'alasan' => 'NIP tidak ada di Data Pegawai.',
                    ]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $model = PerjalananDinasManual::updateOrCreate(
                        ['pegawai_id' => $pegawai->id, 'bulan' => $isi['bulan'], 'tahun' => $this->tahun],
                        [
                            'hari' => $isi['hari'],
                            'uang_harian' => $isi['uang_harian'],
                            'akomodasi' => $isi['akomodasi'],
                            'transport' => $isi['transport'],
                            'representatif' => $isi['representatif'],
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
                'jumlah_ditolak' => $this->jumlah_ditolak + $ditolakTambahan,
            ]);

            return ['baru' => $baru, 'update' => $update, 'ditolak_tambahan' => $ditolakTambahan];
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

        $nip = preg_replace('/\D/', '', (string) ($row['nip'] ?? '')) ?? '';
        $bulan = (int) AngkaBerkas::dari($row['bulan'] ?? null);

        $isi = [
            'nip' => $nip,
            'bulan' => $bulan,
            'nama_berkas' => trim((string) ($row['nama_pegawai'] ?? $row['nama'] ?? '')),
            'hari' => AngkaBerkas::dari($row['jumlah_hari'] ?? $row['hari'] ?? null),
            'uang_harian' => AngkaBerkas::dari($row['uang_harian'] ?? null),
            'akomodasi' => AngkaBerkas::dari($row['akomodasi'] ?? null),
            'transport' => AngkaBerkas::dari($row['transport'] ?? null),
            'representatif' => AngkaBerkas::dari($row['representatif'] ?? null),
            'keterangan' => trim((string) ($row['keterangan'] ?? '')) ?: null,
        ];

        $tolak = fn (string $alasan) => ['aksi' => PerjalananDinasManualImportRow::AKSI_DITOLAK, 'alasan' => $alasan, 'isi' => $isi];

        if ($nip === '') {
            return $tolak('NIP kosong.');
        }

        if ($bulan < 1 || $bulan > 12) {
            return $tolak('Bulan harus diisi angka 1 sampai 12.');
        }

        $pegawai = Pegawai::where('nip', $nip)->first();

        if ($pegawai === null) {
            return $tolak('NIP '.$nip.' tidak ada di Data Pegawai. Tambahkan pegawainya lebih dulu, lalu ulangi import.');
        }

        $isi['pegawai_nama'] = $pegawai->nama;

        $ada = PerjalananDinasManual::where('pegawai_id', $pegawai->id)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->exists();

        return [
            'aksi' => $ada ? PerjalananDinasManualImportRow::AKSI_UPDATE : PerjalananDinasManualImportRow::AKSI_BARU,
            'alasan' => null,
            'isi' => $isi,
        ];
    }
}
