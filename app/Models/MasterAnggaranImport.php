<?php

namespace App\Models;

use App\Imports\MasterAnggaranUploadImport;
use App\Models\Concerns\StagingKedaluwarsa;
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
 * Batch import Pagu/Master Anggaran dengan alur preview/dry-run: upload
 * di-parse ke master_anggaran_import_rows (staging, punya expires_at) tanpa
 * menyentuh master_anggaran sama sekali, lalu user menekan Konfirmasi Simpan
 * untuk memicu konfirmasi().
 *
 * Sejak pagu berversi, konfirmasi() TIDAK langsung mengubah pagu yang
 * berlaku. Yang dihasilkannya:
 *
 *   1. baris identitas di master_anggaran (mata anggaran baru dibuat dengan
 *      pagu 0 dan non-aktif), dan
 *   2. satu VersiPagu berstatus draft berisi nominal pagu per mata anggaran.
 *
 * Pagu baru berlaku setelah VersiPagu::aktifkan() dipanggil dari halaman
 * Versi Pagu. Pemeriksaan "pagu tidak boleh lebih kecil daripada dana
 * terikat NPD + realisasi LS" karena itu ditegakkan di titik AKTIVASI
 * (lihat VersiPagu::konflikAktivasi) - di sanalah pagu betul-betul berubah.
 * Saat import, pelanggaran yang sama hanya ditandai sebagai peringatan
 * supaya dokumen versinya tetap utuh, bukan dipreteli sebagian.
 */
#[Fillable([
    'user_id',
    'nama_file',
    'tahun',
    'versi_nama',
    'versi_nomor_dpa',
    'versi_keterangan',
    'versi_pagu_id',
    'status',
    'total_baris',
    'jumlah_baru',
    'jumlah_update',
    'jumlah_ditolak',
    'jumlah_dinolkan',
    'expires_at',
    'committed_at',
])]
class MasterAnggaranImport extends Model
{
    use StagingKedaluwarsa;

    protected $table = 'master_anggaran_imports';

    public const STATUS_STAGED = 'staged';

    public const STATUS_COMMITTED = 'committed';

    /** Batas baris data per file - file lebih besar dari ini ditolak seluruhnya, bukan dipotong. */
    public const MAKS_BARIS = 5000;

    /**
     * Urutan kolom template. Maatwebsite men-slug baris header jadi key
     * array, sehingga "Kode Sub Kegiatan" terbaca "kode_sub_kegiatan".
     * Perhatikan "Aktif/Non Aktif": garis miring DIBUANG tanpa pemisah,
     * sehingga key-nya "aktifnon_aktif" - bukan "aktif_non_aktif".
     */
    public const KOLOM = [
        'Tahun',
        'Kode Program',
        'Program',
        'Kode Kegiatan',
        'Kegiatan',
        'Kode Sub Kegiatan',
        'Sub Kegiatan',
        'Kode Rekening',
        'Rekening',
        'Tagging',
        'Pagu',
        'Aktif/Non Aktif',
    ];

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
        return $this->hasMany(MasterAnggaranImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versi(): BelongsTo
    {
        return $this->belongsTo(VersiPagu::class, 'versi_pagu_id');
    }

    /**
     * Parse file yang diupload menjadi baris staging. TIDAK menyentuh
     * master_anggaran maupun versi_pagu sama sekali - murni membaca &
     * mengevaluasi.
     *
     * @throws ValidationException kalau file kosong, melebihi batas baris, tahunnya salah, atau nama versi bentrok.
     */
    public static function buatDariUpload(
        UploadedFile $file,
        int $tahun,
        string $versiNama,
        ?string $versiNomorDpa,
        ?string $versiKeterangan,
        ?int $userId
    ): self {
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        if ($tahun !== $tahunAktif) {
            throw ValidationException::withMessages([
                'tahun' => "Import Master Anggaran hanya menerima Tahun Anggaran {$tahunAktif}.",
            ]);
        }

        $versiNama = trim($versiNama);
        if ($versiNama === '') {
            throw ValidationException::withMessages([
                'versi_nama' => 'Tahapan pagu wajib diisi, misalnya DPA Murni atau DPA Pergeseran 1.',
            ]);
        }

        if (VersiPagu::where('tahun', $tahun)->where('nama', $versiNama)->exists()) {
            throw ValidationException::withMessages([
                'versi_nama' => sprintf('Tahapan pagu %s untuk Tahun Anggaran %d sudah ada. Pakai nama lain.', $versiNama, $tahun),
            ]);
        }

        $sheet = new MasterAnggaranUploadImport;
        Excel::import($sheet, $file);

        // ToCollection menyerahkan tiap baris sebagai Collection; disamakan
        // jadi array supaya array_key_exists() di ambilKolom() bisa dipakai.
        $baris = $sheet->rows
            ->map(fn ($row) => $row instanceof Collection ? $row->all() : (array) $row)
            ->filter(fn (array $row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty());

        if ($baris->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data pada sheet pertama.']);
        }

        if ($baris->count() > self::MAKS_BARIS) {
            throw ValidationException::withMessages([
                'file' => sprintf('File berisi %d baris data, melebihi batas maksimum %d baris per import.', $baris->count(), self::MAKS_BARIS),
            ]);
        }

        $tahunFileTidakValid = $baris->first(function ($row) use ($tahunAktif) {
            $nilai = trim((string) self::ambilKolom($row, ['tahun', 'tahun_anggaran']));

            return $nilai !== '' && (! ctype_digit($nilai) || (int) $nilai !== $tahunAktif);
        });

        if ($tahunFileTidakValid !== null) {
            $nilai = trim((string) self::ambilKolom($tahunFileTidakValid, ['tahun', 'tahun_anggaran']));
            throw ValidationException::withMessages([
                'file' => "File Master Anggaran secara eksplisit bertanda Tahun Anggaran {$nilai}; hanya Tahun Anggaran {$tahunAktif} yang diterima.",
            ]);
        }

        $versiNomorDpa = trim((string) $versiNomorDpa) ?: null;

        return DB::transaction(function () use ($file, $userId, $baris, $tahun, $versiNama, $versiNomorDpa, $versiKeterangan) {
            $import = self::create([
                'user_id' => $userId,
                'nama_file' => $file->getClientOriginalName(),
                'tahun' => $tahun,
                'versi_nama' => $versiNama,
                'versi_nomor_dpa' => $versiNomorDpa,
                'versi_keterangan' => $versiKeterangan,
                'status' => self::STATUS_STAGED,
                'total_baris' => $baris->count(),
                'expires_at' => now()->addMinutes(self::menitKedaluwarsa()),
            ]);

            $kunciTerlihat = [];
            $idTercakup = [];
            $baru = 0;
            $update = 0;
            $ditolak = 0;

            foreach ($baris as $indeksAsli => $row) {
                $nomorBaris = $indeksAsli + 2; // baris 1 = header

                $hasil = self::evaluasiBaris(self::petakanKolom($row));

                if ($hasil['aksi'] !== MasterAnggaranImportRow::AKSI_DITOLAK) {
                    $kunci = mb_strtolower($hasil['kode_sub_kegiatan']).'|'
                        .mb_strtolower($hasil['kode_rekening']).'|'
                        .mb_strtolower((string) $hasil['tagging_nama']);

                    if (isset($kunciTerlihat[$kunci])) {
                        $hasil['aksi'] = MasterAnggaranImportRow::AKSI_DITOLAK;
                        $hasil['alasan'] = "Duplikat kombinasi Kode Sub Kegiatan + Kode Rekening + Tagging dengan baris {$kunciTerlihat[$kunci]} pada file ini.";
                    } else {
                        $kunciTerlihat[$kunci] = $nomorBaris;
                    }
                }

                match ($hasil['aksi']) {
                    MasterAnggaranImportRow::AKSI_BARU => $baru++,
                    MasterAnggaranImportRow::AKSI_UPDATE => $update++,
                    default => $ditolak++,
                };

                if ($hasil['master_anggaran_id'] !== null && $hasil['aksi'] !== MasterAnggaranImportRow::AKSI_DITOLAK) {
                    $idTercakup[] = $hasil['master_anggaran_id'];
                }

                $import->baris()->create($hasil + ['nomor_baris' => $nomorBaris]);
            }

            $dinolkan = $import->sintesisBarisDinolkan($idTercakup);

            $import->update([
                'jumlah_baru' => $baru,
                'jumlah_update' => $update,
                'jumlah_ditolak' => $ditolak,
                'jumlah_dinolkan' => $dinolkan,
            ]);

            return $import;
        });
    }

    /**
     * Mata anggaran yang sudah ada di master_anggaran tapi TIDAK dicantumkan
     * file ini dicatat sebagai baris 'dinolkan' (nomor_baris 0). File DPA
     * adalah dokumen utuh: yang tidak tercantum berarti tidak dianggarkan
     * lagi pada versi ini, jadi pagunya 0 dan mata anggarannya non-aktif.
     *
     * @param  array<int, int>  $idTercakup
     */
    private function sintesisBarisDinolkan(array $idTercakup): int
    {
        $hilang = MasterAnggaran::whereNotIn('id', $idTercakup === [] ? [0] : $idTercakup)
            ->with('tagging:id,nama')
            ->get();

        foreach ($hilang as $master) {
            $this->baris()->create([
                'nomor_baris' => 0,
                'aksi' => MasterAnggaranImportRow::AKSI_DINOLKAN,
                'alasan' => 'Tidak dicantumkan pada file versi ini - pagu menjadi 0 dan mata anggaran dinonaktifkan saat versi diaktifkan.',
                'kode_program' => $master->kode_program,
                'program' => $master->program,
                'kode_kegiatan' => $master->kode_kegiatan,
                'kegiatan' => $master->kegiatan,
                'kode_sub_kegiatan' => $master->kode_sub_kegiatan,
                'sub_kegiatan' => $master->sub_kegiatan,
                'kode_rekening' => $master->kode_rekening,
                'rekening' => $master->rekening,
                'tagging_nama' => $master->tagging?->nama,
                'aktif' => false,
                'pagu_baru' => 0,
                'pagu_lama' => (float) $master->pagu,
                'master_anggaran_id' => $master->id,
            ]);
        }

        return $hilang->count();
    }

    /**
     * Tulis hasil staging: baris identitas ke master_anggaran, lalu satu
     * VersiPagu berstatus DRAFT berisi nominal pagunya. Pagu yang berlaku
     * belum berubah sampai versi tersebut diaktifkan.
     *
     * Kalau satu baris gagal disimpan karena error tak terduga, SELURUH
     * transaction di-rollback - tidak ada penyimpanan sebagian.
     *
     * @throws RuntimeException kalau batch sudah diproses, kedaluwarsa, nama versi keburu dipakai, atau ada baris gagal disimpan fatal.
     */
    public function konfirmasi(): array
    {
        if ($this->status !== self::STATUS_STAGED) {
            throw new RuntimeException('Import ini sudah diproses sebelumnya.');
        }

        if ($this->kedaluwarsa()) {
            throw new RuntimeException('Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.');
        }

        if (VersiPagu::where('tahun', $this->tahun)->where('nama', $this->versi_nama)->exists()) {
            throw new RuntimeException(sprintf(
                'Tahapan pagu %s untuk Tahun Anggaran %d keburu dibuat proses lain. Ulangi import dengan nama tahapan berbeda.',
                $this->versi_nama,
                $this->tahun
            ));
        }

        return DB::transaction(function () {
            $versi = VersiPagu::create([
                'tahun' => $this->tahun,
                'nama' => $this->versi_nama,
                'nomor_dpa' => $this->versi_nomor_dpa,
                'keterangan' => $this->versi_keterangan,
                'status' => VersiPagu::STATUS_DRAFT,
                'user_id' => $this->user_id,
            ]);

            $baruAkhir = 0;
            $updateAkhir = 0;
            $ditolakTambahan = 0;

            $barisTerkena = $this->baris()
                ->whereIn('aksi', [
                    MasterAnggaranImportRow::AKSI_BARU,
                    MasterAnggaranImportRow::AKSI_UPDATE,
                    MasterAnggaranImportRow::AKSI_DINOLKAN,
                ])
                ->orderBy('nomor_baris')
                ->lockForUpdate()
                ->get();

            foreach ($barisTerkena as $baris) {
                if ($baris->aksi === MasterAnggaranImportRow::AKSI_DINOLKAN) {
                    // Identitasnya sudah ada; cukup catat nominal 0 di versi ini.
                    if ($baris->master_anggaran_id !== null) {
                        VersiPaguDetail::updateOrCreate(
                            ['versi_pagu_id' => $versi->id, 'master_anggaran_id' => $baris->master_anggaran_id],
                            ['pagu' => 0, 'aktif' => false]
                        );
                    }

                    continue;
                }

                $hasil = self::evaluasiBaris([
                    'kode_program' => $baris->kode_program,
                    'program' => $baris->program,
                    'kode_kegiatan' => $baris->kode_kegiatan,
                    'kegiatan' => $baris->kegiatan,
                    'kode_sub_kegiatan' => $baris->kode_sub_kegiatan,
                    'sub_kegiatan' => $baris->sub_kegiatan,
                    'kode_rekening' => $baris->kode_rekening,
                    'rekening' => $baris->rekening,
                    'tagging' => $baris->tagging_nama,
                    'pagu' => (float) $baris->pagu_baru,
                    'aktif' => $baris->aktif ? 'Aktif' : 'Non Aktif',
                ]);

                if ($hasil['aksi'] === MasterAnggaranImportRow::AKSI_DITOLAK) {
                    $baris->update(['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => $hasil['alasan']]);
                    $ditolakTambahan++;

                    continue;
                }

                try {
                    $taggingId = $hasil['tagging_nama'] !== null
                        ? Tagging::firstOrCreate(['nama' => $hasil['tagging_nama']])->id
                        : null;

                    // Identitas mata anggaran. pagu & aktif SENGAJA tidak
                    // ditulis di sini - keduanya milik versi, dan baru
                    // menyentuh master_anggaran lewat VersiPagu::aktifkan().
                    $model = MasterAnggaran::firstOrNew([
                        'kode_sub_kegiatan' => $hasil['kode_sub_kegiatan'],
                        'kode_rekening' => $hasil['kode_rekening'],
                        'tagging_id' => $taggingId,
                    ]);

                    $baruDibuat = ! $model->exists;

                    $model->fill([
                        'kode_program' => $hasil['kode_program'],
                        'program' => $hasil['program'],
                        'kode_kegiatan' => $hasil['kode_kegiatan'],
                        'kegiatan' => $hasil['kegiatan'],
                        'sub_kegiatan' => $hasil['sub_kegiatan'],
                        'rekening' => $hasil['rekening'],
                    ]);

                    if ($baruDibuat) {
                        // Mata anggaran yang belum pernah ada mulai dari nol
                        // dan non-aktif sampai versinya diaktifkan.
                        $model->pagu = 0;
                        $model->aktif = false;
                    }

                    $model->save();

                    VersiPaguDetail::updateOrCreate(
                        ['versi_pagu_id' => $versi->id, 'master_anggaran_id' => $model->id],
                        ['pagu' => $hasil['pagu_baru'], 'aktif' => $hasil['aktif']]
                    );
                } catch (Throwable $e) {
                    // Sengaja dilempar ulang di DALAM transaction supaya seluruh
                    // batch (termasuk baris lain yang sudah disimpan) rollback.
                    throw new RuntimeException("Baris {$baris->nomor_baris}: gagal disimpan - {$e->getMessage()}");
                }

                if ($baruDibuat) {
                    $baruAkhir++;
                } else {
                    $updateAkhir++;
                }

                $baris->update(['master_anggaran_id' => $model->id, 'pagu_lama' => $hasil['pagu_lama']]);
            }

            $versi->segarkanRingkasan();

            $this->update([
                'status' => self::STATUS_COMMITTED,
                'committed_at' => now(),
                'versi_pagu_id' => $versi->id,
                'jumlah_baru' => $baruAkhir,
                'jumlah_update' => $updateAkhir,
                'jumlah_ditolak' => $this->jumlah_ditolak + $ditolakTambahan,
            ]);

            return [
                'versi' => $versi,
                'baru' => $baruAkhir,
                'update' => $updateAkhir,
                'dinolkan' => $this->jumlah_dinolkan,
                'ditolak_tambahan' => $ditolakTambahan,
            ];
        });
    }

    /**
     * Ambil nilai kolom pertama yang ada di antara beberapa kemungkinan nama
     * slug - supaya file dengan header versi lama tetap terbaca.
     *
     * @param  array<int|string, mixed>  $row
     * @param  array<int, string>  $kandidat
     */
    private static function ambilKolom(array $row, array $kandidat): mixed
    {
        foreach ($kandidat as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * Petakan satu baris Excel ke bentuk yang dimengerti evaluasiBaris().
     *
     * Template sekarang memisahkan kode dan nama. File format LAMA (kode +
     * nama tergabung dalam satu sel) tetap diterima: kalau kolom kode tidak
     * ada, nilainya dipecah dari kolom namanya pada spasi pertama.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function petakanKolom(array $row): array
    {
        $pisah = function (mixed $kode, mixed $gabungan): array {
            $kode = trim((string) $kode);
            $gabungan = trim((string) $gabungan);

            if ($kode !== '') {
                return [$kode, $gabungan];
            }

            return MasterAnggaran::pisahKodeUraian($gabungan);
        };

        [$kodeProgram, $program] = $pisah(self::ambilKolom($row, ['kode_program']), $row['program'] ?? null);
        [$kodeKegiatan, $kegiatan] = $pisah(self::ambilKolom($row, ['kode_kegiatan']), $row['kegiatan'] ?? null);
        [$kodeSub, $subKegiatan] = $pisah(self::ambilKolom($row, ['kode_sub_kegiatan']), $row['sub_kegiatan'] ?? null);
        [$kodeRekening, $rekening] = $pisah(
            self::ambilKolom($row, ['kode_rekening']),
            self::ambilKolom($row, ['rekening', 'uraian_rekening'])
        );

        // File lama: satu sel "Kode Rekening" berisi kode + uraian sekaligus
        // dan kolom Rekening tidak ada sama sekali.
        if ($rekening === '' && str_contains($kodeRekening, ' ')) {
            [$kodeRekening, $rekening] = MasterAnggaran::pisahKodeUraian($kodeRekening);
        }

        return [
            'kode_program' => $kodeProgram,
            'program' => $program,
            'kode_kegiatan' => $kodeKegiatan,
            'kegiatan' => $kegiatan,
            'kode_sub_kegiatan' => $kodeSub,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'rekening' => $rekening,
            'tagging' => $row['tagging'] ?? null,
            'pagu' => $row['pagu'] ?? null,
            // Str::slug() MEMBUANG garis miring tanpa menyisipkan pemisah, jadi
            // "Aktif/Non Aktif" menjadi "aktifnon_aktif". Varian ber-underscore
            // tetap didaftar untuk berkas yang headernya ditulis ulang manual.
            'aktif' => self::ambilKolom($row, ['aktifnon_aktif', 'aktif_non_aktif', 'aktif']),
        ];
    }

    /**
     * Evaluasi satu baris data mentah terhadap kondisi master_anggaran SAAT
     * INI. Dipakai baik saat parse awal (preview) maupun saat konfirmasi
     * (re-validasi) - satu-satunya sumber aturan bisnis supaya keduanya tidak
     * bisa berbeda hasil.
     *
     * @param  array<string, mixed>  $mentah
     * @return array<string, mixed>
     */
    private static function evaluasiBaris(array $mentah): array
    {
        $ambil = fn (string $key): string => trim((string) ($mentah[$key] ?? ''));

        $kodeProgram = $ambil('kode_program');
        $program = $ambil('program');
        $kodeKegiatan = $ambil('kode_kegiatan');
        $kegiatan = $ambil('kegiatan');
        $kodeSubKegiatan = $ambil('kode_sub_kegiatan');
        $subKegiatan = $ambil('sub_kegiatan');
        $kodeRekening = $ambil('kode_rekening');
        $rekening = $ambil('rekening');

        $taggingNama = $ambil('tagging');
        $taggingNama = $taggingNama === '' ? null : $taggingNama;

        // Hanya "Non Aktif"/"Tidak" yang menonaktifkan; sel kosong tetap aktif.
        $nilaiAktif = mb_strtolower($ambil('aktif'));
        $aktif = ! in_array($nilaiAktif, ['non aktif', 'nonaktif', 'non-aktif', 'tidak', 'no', 'n'], true);

        $dasar = [
            'kode_program' => $kodeProgram !== '' ? $kodeProgram : null,
            'program' => $program,
            'kode_kegiatan' => $kodeKegiatan !== '' ? $kodeKegiatan : null,
            'kegiatan' => $kegiatan,
            'kode_sub_kegiatan' => $kodeSubKegiatan,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'rekening' => $rekening !== '' ? $rekening : null,
            'tagging_nama' => $taggingNama,
            'aktif' => $aktif,
            'pagu_baru' => null,
            'pagu_lama' => null,
            'master_anggaran_id' => null,
        ];

        if ($kodeSubKegiatan === '' || $kodeRekening === '') {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Kode Sub Kegiatan atau Kode Rekening kosong.'];
        }

        if (mb_strlen($kodeSubKegiatan) > 50) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Kode Sub Kegiatan melebihi 50 karakter.'];
        }

        if (mb_strlen($kodeRekening) > 50) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Kode Rekening melebihi 50 karakter.'];
        }

        if (mb_strlen($program) > 255 || mb_strlen($kegiatan) > 255 || mb_strlen($subKegiatan) > 255 || mb_strlen($rekening) > 255) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Salah satu kolom nama (Program/Kegiatan/Sub Kegiatan/Rekening) melebihi 255 karakter.'];
        }

        $pagu = self::normalisasiAngka($mentah['pagu'] ?? null);
        $dasar['pagu_baru'] = $pagu;

        if ($pagu === null) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Kolom Pagu harus berisi angka nominal saja, tanpa Rp, huruf, atau simbol lain.'];
        }

        if ($pagu < 0) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_DITOLAK, 'alasan' => 'Pagu tidak boleh bernilai negatif.'];
        }

        $taggingId = $taggingNama !== null ? Tagging::where('nama', $taggingNama)->value('id') : null;

        $existing = MasterAnggaran::where('kode_sub_kegiatan', $kodeSubKegiatan)
            ->where('kode_rekening', $kodeRekening)
            ->where('tagging_id', $taggingId)
            ->lockForUpdate()
            ->first();

        if (! $existing) {
            return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_BARU, 'alasan' => null];
        }

        $dasar['master_anggaran_id'] = $existing->id;
        $dasar['pagu_lama'] = (float) $existing->pagu;

        $minimum = $existing->paguMinimum();

        // Peringatan, BUKAN penolakan: versi masih draft sehingga pagu yang
        // berlaku belum berubah. Aktivasi versi inilah yang memblokir kalau
        // kondisi ini masih bertahan (VersiPagu::konflikAktivasi()).
        $peringatan = $pagu < $minimum
            ? sprintf(
                'Peringatan: pagu versi ini (Rp %s) lebih kecil dari dana terikat NPD + realisasi LS saat ini (Rp %s). Versi tidak akan bisa diaktifkan selama kondisi ini bertahan.',
                fmt_rupiah($pagu),
                fmt_rupiah($minimum)
            )
            : null;

        return $dasar + ['aksi' => MasterAnggaranImportRow::AKSI_UPDATE, 'alasan' => $peringatan];
    }

    /**
     * Kolom Pagu harus berisi NOMINAL KEUANGAN SAJA. Angka mentah dari Excel
     * diterima apa adanya; teks hasil ketik manual masih ditoleransi selama
     * isinya cuma digit dengan pemisah ribuan titik dan desimal koma
     * ("1.500.000,50"). Apa pun yang mengandung huruf atau simbol mata uang
     * ("Rp 1.500.000", "1,5jt") ditolak, bukan diam-diam dipaksa jadi angka.
     */
    private static function normalisasiAngka(mixed $nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        if (! is_string($nilai)) {
            return null;
        }

        $bersih = trim($nilai);

        if ($bersih === '' || preg_match('/^-?[\d.,]+$/', $bersih) !== 1) {
            return null;
        }

        // Titik = pemisah ribuan, koma = desimal (konvensi Indonesia).
        $bersih = str_replace('.', '', $bersih);
        $bersih = str_replace(',', '.', $bersih);

        return is_numeric($bersih) ? (float) $bersih : null;
    }
}
