<?php

namespace App\Models;

use App\Imports\SpmUploadImport;
use App\Models\Concerns\StagingKedaluwarsa;
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
 * Sejak Prompt 22, satu SPM LS bisa mencakup beberapa mata anggaran - format
 * file mengikuti saran di spesifikasi: SATU BARIS FILE per kombinasi SPM +
 * mata anggaran, dengan kolom header (Nomor/Tanggal Dokumen, SP2D, PPN, PPh,
 * Penerima, Uraian) DIULANG persis sama di setiap baris milik SPM yang sama.
 * Baris-baris itu dikelompokkan lewat kunci (nomor_dokumen, tanggal_dokumen)
 * saat commit - lihat konfirmasi(). Ini dipilih dibanding format
 * "satu-baris-per-SPM-dengan-kolom-mata-anggaran-berulang" karena jumlah
 * mata anggaran per SPM tidak tetap, sehingga tidak bisa direpresentasikan
 * sebagai kolom tetap di spreadsheet - baris datar per kombinasi jauh lebih
 * sederhana untuk dibaca/ditulis Excel dan konsisten dengan cara pengguna
 * sudah terbiasa mengisi baris demi baris.
 *
 * LS wajib cocok ke master_anggaran aktif (tidak pernah membuat baris baru
 * di master_anggaran) dan tunduk pada batas anggaran AGREGAT per batch:
 * beberapa baris LS pada file yang sama bisa menyasar mata anggaran yang
 * sama, jadi validasi per-baris saja tidak cukup - lihat terapkanBatasAnggaran().
 *
 * Satu dokumen SPM LS disimpan SEKALIGUS atau TIDAK SAMA SEKALI
 * (all-or-nothing per dokumen, sama seperti Spm::buatLs()/updateLs() untuk
 * input manual): kalau salah satu baris pada dokumen yang sama ditolak (mata
 * anggaran tidak ditemukan, melebihi sisa tersedia, duplikat mata anggaran,
 * atau kolom header antar baris tidak konsisten), SELURUH baris pada
 * dokumen itu ikut ditolak - lihat terapkanAturanGrupDokumen().
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
    use StagingKedaluwarsa;

    protected $table = 'spm_imports';

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
        return $this->hasMany(SpmImportRow::class, 'import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @throws ValidationException kalau file kosong atau melebihi batas jumlah baris.
     */
    public static function buatDariUpload(UploadedFile $file, string $jenisSpm, ?int $userId): self
    {
        if (! in_array($jenisSpm, Spm::JENIS_LIST, true)) {
            throw new InvalidArgumentException("Jenis SPM tidak dikenal: {$jenisSpm}");
        }

        $sheet = new SpmUploadImport;
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
                'expires_at' => now()->addMinutes(self::menitKedaluwarsa()),
            ]);

            $hasilBaris = self::evaluasiSemuaBaris($baris, $jenisSpm);

            foreach ($hasilBaris as $nomorBaris => $hasil) {
                $import->baris()->create(self::kolomBaris($nomorBaris, $hasil));
            }

            $import->update(self::hitungRingkasan($hasilBaris, $jenisSpm));

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
                    'bank_tujuan' => $baris->bank_tujuan,
                    'nomor_rekening' => $baris->nomor_rekening,
                    'uraian' => $baris->uraian,
                ];

                $hasil = $this->jenis_spm === 'ls' ? self::evaluasiLs($mentah) : self::evaluasiUpGu($mentah);
                $hasil['_baris'] = $baris;
                $hasilBaris[$baris->nomor_baris] = $hasil;
            }

            if ($this->jenis_spm === 'ls') {
                $hasilBaris = self::terapkanBatasAnggaran($hasilBaris);
                $hasilBaris = self::terapkanAturanGrupDokumen($hasilBaris);
            }

            [$baruAkhir, $updateAkhir, $ditolakTambahan] = $this->jenis_spm === 'ls'
                ? self::commitBarisLs($hasilBaris)
                : self::commitBarisUpGu($hasilBaris);

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
                // Template UP/GU memakai "Nomor/Tanggal SPM"; template LS dan
                // berkas lama memakai "Nomor/Tanggal Dokumen". Keduanya
                // menunjuk kolom yang sama.
                'nomor_dokumen' => $row['nomor_spm'] ?? $row['nomor_dokumen'] ?? null,
                'tanggal_dokumen' => $row['tanggal_spm'] ?? $row['tanggal_dokumen'] ?? null,
                'nomor_sp2d' => $row['nomor_sp2d'] ?? null,
                'tanggal_sp2d' => $row['tanggal_sp2d'] ?? null,
                // Template LS memisahkan Kode Sub Kegiatan dari namanya;
                // digabung kembali jadi label utuh supaya baris staging dan
                // pencocokan ke master_anggaran tetap sama seperti sebelumnya,
                // dan berkas lama (kode + nama dalam satu sel) tetap terbaca.
                'sub_kegiatan' => MasterAnggaran::gabungKodeUraian(
                    trim((string) ($row['kode_sub_kegiatan'] ?? '')),
                    trim((string) ($row['sub_kegiatan'] ?? ''))
                ),
                'kode_rekening' => $row['kode_rekening'] ?? null,
                'tagging' => $row['tagging'] ?? null,
                'nominal' => $row['nominal'] ?? null,
                'ppn' => $row['ppn'] ?? null,
                // Kolom nominal PPh kini bernama "Nominal PPh 1/2"; nama lama
                // "PPh 1/2" tetap diterima.
                'pph_1' => $row['nominal_pph_1'] ?? $row['pph_1'] ?? null,
                'jenis_pph_1' => $row['jenis_pph_1'] ?? null,
                'pph_2' => $row['nominal_pph_2'] ?? $row['pph_2'] ?? null,
                'jenis_pph_2' => $row['jenis_pph_2'] ?? null,
                'penerima' => $row['penerima'] ?? null,
                'bank_tujuan' => $row['bank_tujuan'] ?? null,
                'nomor_rekening' => $row['nomor_rekening'] ?? null,
                'uraian' => $row['uraian'] ?? null,
            ];

            $hasil = $jenisSpm === 'ls' ? self::evaluasiLs($mentah) : self::evaluasiUpGu($mentah);

            // UP/GU: satu baris file SELALU satu dokumen, jadi nomor+tanggal
            // dokumen yang sama pada baris lain adalah duplikat murni. LS
            // SENGAJA dikecualikan - baris dengan nomor+tanggal dokumen yang
            // sama adalah cara file merepresentasikan beberapa mata anggaran
            // dalam SATU dokumen yang sama (lihat dok kelas ini); duplikat
            // mata anggaran DALAM dokumen yang sama dicek terpisah di
            // terapkanAturanGrupDokumen().
            if ($jenisSpm !== 'ls' && $hasil['aksi'] !== SpmImportRow::AKSI_DITOLAK) {
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
            $hasilBaris = self::terapkanAturanGrupDokumen($hasilBaris);
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
            'penerima_pegawai_id' => null,
            'penerima_vendor_id' => null,
            'bank_tujuan' => trim((string) ($mentah['bank_tujuan'] ?? '')) ?: null,
            // Dibaca sebagai teks: nol di depan nomor rekening tidak boleh hilang.
            'nomor_rekening' => trim((string) ($mentah['nomor_rekening'] ?? '')) ?: null,
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

        // Penerima dan Uraian WAJIB untuk LS: dokumen pencairan ke pihak
        // ketiga tanpa nama penerima atau tanpa keperluannya tidak bisa
        // dipertanggungjawabkan. UP/GU tidak tunduk aturan ini - di sana
        // penerimanya selalu Bendahara Pengeluaran (BP) dan sudah jelas
        // dari jenis dokumennya.
        if ($dasar['penerima'] === null) {
            return self::dasarDitolak($dasar, 'Penerima wajib diisi untuk SPM LS.');
        }

        if ($dasar['uraian'] === null) {
            return self::dasarDitolak($dasar, 'Uraian wajib diisi untuk SPM LS.');
        }

        $subKegiatan = trim((string) ($mentah['sub_kegiatan'] ?? ''));
        // File sumber kadang berisi kode+uraian gabungan di kolom Kode
        // Rekening - ambil bagian kode saja untuk matching & penyimpanan
        // (kolom staging kode_rekening tetap varchar pendek).
        $kodeRekening = MasterAnggaran::pisahKodeUraian(trim((string) ($mentah['kode_rekening'] ?? '')))[0];
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

        // Dicocokkan lewat sub_kegiatan_kunci (kode + nama, dinormalisasi)
        // karena sejak kode dipisah ke kolom sendiri, kolom sub_kegiatan
        // hanya berisi NAMA - sementara file SPM tetap menuliskan keduanya
        // dalam satu sel. Lihat MasterAnggaran::booted().
        $masterAnggaran = MasterAnggaran::where('sub_kegiatan_kunci', MasterAnggaran::normalisasiKunci($subKegiatan))
            ->where('kode_rekening_bersih', $kodeRekening)
            ->where('tagging_id', $taggingId)
            ->where('aktif', true)
            ->lockForUpdate()
            ->first();

        if (! $masterAnggaran) {
            // Sebab paling sering: kolom Tagging dikosongkan padahal mata
            // anggarannya bertagging. Disebut terpisah supaya pengisi file tahu
            // persis apa yang kurang, bukan sekadar "tidak ditemukan".
            if ($taggingNama === null) {
                $taggingTersedia = MasterAnggaran::where('sub_kegiatan_kunci', MasterAnggaran::normalisasiKunci($subKegiatan))
                    ->where('kode_rekening_bersih', $kodeRekening)
                    ->whereNotNull('tagging_id')
                    ->where('aktif', true)
                    ->with('tagging')
                    ->get()
                    ->pluck('tagging.nama')
                    ->filter()
                    ->unique()
                    ->values();

                if ($taggingTersedia->isNotEmpty()) {
                    return self::dasarDitolak($dasar, 'Kolom Tagging wajib diisi untuk mata anggaran ini. Tagging yang tersedia: '.$taggingTersedia->implode(', ').'.');
                }
            }

            return self::dasarDitolak($dasar, 'Mata anggaran tidak ditemukan atau tidak aktif untuk kombinasi Sub Kegiatan + Kode Rekening + Tagging tersebut.');
        }

        $existing = Spm::where('jenis_spm', 'ls')
            ->where('nomor_dokumen', $dasar['nomor_dokumen'])
            ->whereDate('tanggal_dokumen', $dasar['tanggal_dokumen'])
            ->lockForUpdate()
            ->first();

        // "lama" hanya berarti kalau dokumen ini sudah punya baris spm_detail
        // pada mata anggaran YANG SAMA sebelumnya - kalau baris dipindah ke
        // mata anggaran lain, nominal lama tetap mengurangi mata anggaran
        // LAMA di realisasiLs() sampai baris ini benar-benar di-commit
        // (spm_detail lama baru dihapus saat commit), jadi tidak dikreditkan
        // di sini.
        $nominalLama = $existing
            ? (float) (SpmDetail::where('spm_id', $existing->id)->where('master_anggaran_id', $masterAnggaran->id)->value('nominal') ?? 0)
            : 0.0;

        return array_replace($dasar, self::cocokkanPenerima($dasar), [
            'aksi' => $existing ? SpmImportRow::AKSI_UPDATE : SpmImportRow::AKSI_BARU,
            'alasan' => null,
            'spm_id' => $existing?->id,
            'master_anggaran_id' => $masterAnggaran->id,
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'tagging_nama' => $taggingNama,
            'nominal_lama' => $nominalLama,
            'sisa_sebelum' => null,
            'sisa_sesudah' => null,
        ]);
    }

    /**
     * Tautkan nama penerima ke Data Pegawai/Vendor bila namanya SAMA PERSIS
     * (tanpa membedakan huruf besar-kecil) dengan tepat satu data aktif.
     *
     * Sengaja tidak menebak-nebak: nama yang cocok ke lebih dari satu data,
     * atau tidak cocok sama sekali, dibiarkan sebagai teks saja - tautan yang
     * salah lebih berbahaya daripada tidak ada tautan sama sekali. Nomor
     * rekening dari master hanya dipakai bila kolomnya memang dikosongkan di
     * file; yang tertulis di file selalu menang.
     *
     * @param  array<string, mixed>  $dasar
     * @return array<string, mixed> kolom penerima yang berubah (kosong bila tidak ada yang cocok)
     */
    private static function cocokkanPenerima(array $dasar): array
    {
        $nama = $dasar['penerima'];

        if ($nama === null) {
            return [];
        }

        $cocok = fn (string $kelas) => $kelas::query()
            ->where('aktif', true)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
            ->get(['id', 'rekening']);

        $pegawai = $cocok(Pegawai::class);
        $vendor = $pegawai->count() === 1 ? collect() : $cocok(Vendor::class);

        $terpilih = match (true) {
            $pegawai->count() === 1 => ['penerima_pegawai_id' => $pegawai->first()->id, 'rekening' => $pegawai->first()->rekening],
            $vendor->count() === 1 => ['penerima_vendor_id' => $vendor->first()->id, 'rekening' => $vendor->first()->rekening],
            default => null,
        };

        if ($terpilih === null) {
            return [];
        }

        $rekening = trim((string) $terpilih['rekening']) ?: null;
        unset($terpilih['rekening']);

        return $terpilih + ['nomor_rekening' => $dasar['nomor_rekening'] ?? $rekening];
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

    /**
     * Satu dokumen SPM LS = kumpulan baris file dengan (nomor_dokumen,
     * tanggal_dokumen) yang sama. Diterapkan SETELAH evaluasi per-baris dan
     * batas anggaran agregat: mengecek mata anggaran tidak duplikat DAN
     * kolom header (SP2D, PPN, PPh, Penerima, Uraian) konsisten antar baris
     * pada dokumen yang sama, lalu menegakkan all-or-nothing PER DOKUMEN -
     * kalau satu baris pada dokumen itu ditolak (alasan apa pun, termasuk
     * dari terapkanBatasAnggaran()), seluruh baris dokumen itu ikut ditolak.
     * Dokumen lain pada batch yang sama tidak terpengaruh. Sama seperti
     * Spm::buatLs()/updateLs() untuk input manual: satu SPM adalah satu
     * dokumen fisik, tidak boleh tersimpan sebagian.
     *
     * @param  array<int, array>  $hasilBaris
     * @return array<int, array>
     */
    private static function terapkanAturanGrupDokumen(array $hasilBaris): array
    {
        $grup = [];

        foreach ($hasilBaris as $nomorBaris => $hasil) {
            $kunci = mb_strtolower((string) $hasil['nomor_dokumen']).'|'.$hasil['tanggal_dokumen'];
            $grup[$kunci][] = $nomorBaris;
        }

        foreach ($grup as $kunci => $nomorBarisList) {
            if (count($nomorBarisList) < 2) {
                continue;
            }

            $alasanGrup = null;
            $matadipakai = [];

            foreach ($nomorBarisList as $n) {
                $hasil = $hasilBaris[$n];
                if ($hasil['aksi'] === SpmImportRow::AKSI_DITOLAK || $hasil['master_anggaran_id'] === null) {
                    continue;
                }
                if (in_array($hasil['master_anggaran_id'], $matadipakai, true)) {
                    $alasanGrup = "Mata anggaran dipilih lebih dari satu kali dalam dokumen {$hasil['nomor_dokumen']} ({$hasil['tanggal_dokumen']}).";
                    break;
                }
                $matadipakai[] = $hasil['master_anggaran_id'];
            }

            if ($alasanGrup === null) {
                $headerPertama = null;
                foreach ($nomorBarisList as $n) {
                    $hasil = $hasilBaris[$n];
                    if ($hasil['aksi'] === SpmImportRow::AKSI_DITOLAK) {
                        continue;
                    }
                    $header = [
                        $hasil['tanggal_sp2d'], $hasil['nomor_sp2d'], $hasil['ppn'],
                        $hasil['pph1'], $hasil['jenis_pph1'], $hasil['pph2'], $hasil['jenis_pph2'],
                        $hasil['penerima'], $hasil['bank_tujuan'], $hasil['nomor_rekening'],
                        $hasil['uraian'],
                    ];
                    if ($headerPertama === null) {
                        $headerPertama = $header;
                    } elseif ($header !== $headerPertama) {
                        $alasanGrup = "Kolom SP2D/PPN/PPh/Penerima/Bank/Uraian berbeda antar baris untuk dokumen {$hasil['nomor_dokumen']} ({$hasil['tanggal_dokumen']}) - harus sama persis di semua baris dokumen yang sama.";
                        break;
                    }
                }
            }

            if ($alasanGrup === null) {
                $barisDitolak = collect($nomorBarisList)->first(fn ($n) => $hasilBaris[$n]['aksi'] === SpmImportRow::AKSI_DITOLAK);
                if ($barisDitolak !== null) {
                    $dok = $hasilBaris[$barisDitolak];
                    $alasanGrup = "Ditolak karena salah satu baris lain pada dokumen {$dok['nomor_dokumen']} ({$dok['tanggal_dokumen']}) ditolak - satu dokumen SPM LS disimpan sekaligus atau tidak sama sekali.";
                }
            }

            if ($alasanGrup !== null) {
                foreach ($nomorBarisList as $n) {
                    if ($hasilBaris[$n]['aksi'] !== SpmImportRow::AKSI_DITOLAK) {
                        $hasilBaris[$n]['aksi'] = SpmImportRow::AKSI_DITOLAK;
                        $hasilBaris[$n]['alasan'] = $alasanGrup;
                    }
                }
            }
        }

        return $hasilBaris;
    }

    /** @return array{0: int, 1: int, 2: int} [baruAkhir, updateAkhir, ditolakTambahan] */
    private static function commitBarisUpGu(array $hasilBaris): array
    {
        $baru = 0;
        $update = 0;
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

            // Hanya kolom yang benar-benar ada di template UP/GU. PPN/PPh dan
            // Penerima SENGAJA tidak ditulis: berkas UP/GU tidak lagi
            // membawanya, jadi menulisnya berarti menimpa nilai yang sudah
            // diisi lewat form SPM dengan nol/null.
            $data = [
                'tanggal_dokumen' => $hasil['tanggal_dokumen'],
                'nomor_dokumen' => $hasil['nomor_dokumen'],
                'tanggal_sp2d' => $hasil['tanggal_sp2d'],
                'nomor_sp2d' => $hasil['nomor_sp2d'],
                'nominal' => $hasil['nominal'],
                'uraian' => $hasil['uraian'],
            ];

            try {
                if ($hasil['spm_id'] !== null) {
                    $spm = Spm::where('id', $hasil['spm_id'])->lockForUpdate()->firstOrFail();
                    $spm->update($data);
                } else {
                    // Kolom pajak NOT NULL tanpa default - dokumen baru mulai
                    // dari nol.
                    $spm = Spm::create($data + [
                        'jenis_spm' => 'up_gu',
                        'ppn' => 0,
                        'pph1' => 0,
                        'pph2' => 0,
                    ]);
                }
            } catch (Throwable $e) {
                // Dilempar ulang di DALAM transaction supaya seluruh batch
                // (termasuk baris lain yang sudah disimpan) rollback.
                throw new RuntimeException("Baris {$barisModel->nomor_baris}: gagal disimpan - {$e->getMessage()}");
            }

            $hasil['spm_id'] !== null ? $update++ : $baru++;

            $barisModel->update([
                'spm_id' => $spm->id,
                'sisa_sebelum' => $hasil['sisa_sebelum'],
                'sisa_sesudah' => $hasil['sisa_sesudah'],
            ]);
        }

        return [$baru, $update, $ditolakTambahan];
    }

    /**
     * Satu grup (nomor_dokumen, tanggal_dokumen) -> satu Spm header + N
     * SpmDetail (satu per baris file dalam grup itu, sudah lolos
     * terapkanAturanGrupDokumen() sehingga tiap grup yang sampai di sini
     * seluruh baris diterimanya konsisten - baik semua diterima atau
     * (ditangani terpisah di bawah) semua ditolak). Baris detail lama
     * diganti seluruhnya saat update, sama seperti Spm::updateLs().
     *
     * @return array{0: int, 1: int, 2: int} [baruAkhir, updateAkhir, ditolakTambahan]
     */
    private static function commitBarisLs(array $hasilBaris): array
    {
        $baru = 0;
        $update = 0;
        $ditolakTambahan = 0;

        $grup = collect($hasilBaris)
            ->groupBy(fn (array $hasil) => mb_strtolower((string) $hasil['nomor_dokumen']).'|'.$hasil['tanggal_dokumen']);

        foreach ($grup as $anggotaGrup) {
            foreach ($anggotaGrup as $hasil) {
                if ($hasil['aksi'] === SpmImportRow::AKSI_DITOLAK) {
                    $hasil['_baris']->update([
                        'aksi' => SpmImportRow::AKSI_DITOLAK,
                        'alasan' => $hasil['alasan'],
                        'sisa_sebelum' => $hasil['sisa_sebelum'],
                        'sisa_sesudah' => $hasil['sisa_sesudah'],
                    ]);
                    $ditolakTambahan++;
                }
            }

            $diterima = $anggotaGrup->reject(fn (array $hasil) => $hasil['aksi'] === SpmImportRow::AKSI_DITOLAK);

            if ($diterima->isEmpty()) {
                continue;
            }

            $pertama = $diterima->first();
            $data = [
                'tanggal_dokumen' => $pertama['tanggal_dokumen'],
                'nomor_dokumen' => $pertama['nomor_dokumen'],
                'tanggal_sp2d' => $pertama['tanggal_sp2d'],
                'nomor_sp2d' => $pertama['nomor_sp2d'],
                'ppn' => $pertama['ppn'],
                'pph1' => $pertama['pph1'],
                'jenis_pph1' => $pertama['jenis_pph1'],
                'pph2' => $pertama['pph2'],
                'jenis_pph2' => $pertama['jenis_pph2'],
                'penerima' => $pertama['penerima'],
                'penerima_pegawai_id' => $pertama['penerima_pegawai_id'],
                'penerima_vendor_id' => $pertama['penerima_vendor_id'],
                'bank_tujuan' => $pertama['bank_tujuan'],
                'nomor_rekening' => $pertama['nomor_rekening'],
                'uraian' => $pertama['uraian'],
            ];

            try {
                if ($pertama['spm_id'] !== null) {
                    $spm = Spm::where('id', $pertama['spm_id'])->lockForUpdate()->firstOrFail();
                    $spm->update($data + ['jenis_spm' => 'ls']);
                    $spm->detail()->delete();
                } else {
                    $spm = Spm::create($data + ['jenis_spm' => 'ls']);
                }

                foreach ($diterima as $hasil) {
                    $spm->detail()->create([
                        'master_anggaran_id' => $hasil['master_anggaran_id'],
                        'nominal' => $hasil['nominal'],
                    ]);
                }
            } catch (Throwable $e) {
                // Dilempar ulang di DALAM transaction supaya seluruh batch
                // (termasuk dokumen lain yang sudah disimpan) rollback.
                throw new RuntimeException("Baris {$pertama['_baris']->nomor_baris}: gagal disimpan - {$e->getMessage()}");
            }

            $pertama['spm_id'] !== null ? $update++ : $baru++;

            foreach ($diterima as $hasil) {
                $hasil['_baris']->update([
                    'spm_id' => $spm->id,
                    'sisa_sebelum' => $hasil['sisa_sebelum'],
                    'sisa_sesudah' => $hasil['sisa_sesudah'],
                ]);
            }
        }

        return [$baru, $update, $ditolakTambahan];
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
            'penerima_pegawai_id' => $hasil['penerima_pegawai_id'],
            'penerima_vendor_id' => $hasil['penerima_vendor_id'],
            'bank_tujuan' => $hasil['bank_tujuan'],
            'nomor_rekening' => $hasil['nomor_rekening'],
            'uraian' => $hasil['uraian'],
            'sisa_sebelum' => $hasil['sisa_sebelum'],
            'sisa_sesudah' => $hasil['sisa_sesudah'],
            'spm_id' => $hasil['spm_id'],
        ];
    }

    /**
     * jumlah_baru/jumlah_update dihitung PER DOKUMEN untuk LS (satu dokumen
     * = satu SPM, apa pun jumlah baris file-nya) supaya angka yang
     * ditampilkan tetap berarti "berapa SPM dibuat/diperbarui" seperti
     * sebelum Prompt 22 - bukan "berapa baris file". jumlah_ditolak tetap
     * dihitung per BARIS FILE karena tiap baris ditolak ditampilkan
     * terpisah (dengan alasannya sendiri) di halaman preview. Untuk UP/GU
     * satu baris selalu satu dokumen, jadi kedua cara hitung identik.
     */
    private static function hitungRingkasan(array $hasilBaris, string $jenisSpm): array
    {
        if ($jenisSpm !== 'ls') {
            return self::hitungRingkasanPerBaris($hasilBaris);
        }

        $baru = 0;
        $update = 0;
        $ditolak = 0;

        collect($hasilBaris)
            ->groupBy(fn (array $hasil) => mb_strtolower((string) $hasil['nomor_dokumen']).'|'.$hasil['tanggal_dokumen'])
            ->each(function (Collection $anggotaGrup) use (&$baru, &$update, &$ditolak) {
                $ditolak += $anggotaGrup->filter(fn (array $hasil) => $hasil['aksi'] === SpmImportRow::AKSI_DITOLAK)->count();
                $diterima = $anggotaGrup->reject(fn (array $hasil) => $hasil['aksi'] === SpmImportRow::AKSI_DITOLAK);

                if ($diterima->isEmpty()) {
                    return;
                }

                $diterima->first()['spm_id'] !== null ? $update++ : $baru++;
            });

        return ['jumlah_baru' => $baru, 'jumlah_update' => $update, 'jumlah_ditolak' => $ditolak];
    }

    private static function hitungRingkasanPerBaris(array $hasilBaris): array
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
