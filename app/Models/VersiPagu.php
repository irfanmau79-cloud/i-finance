<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu versi dokumen pagu: "DPA Murni", "DPA Pergeseran 1", "DPA Perubahan",
 * dan seterusnya.
 *
 * Yang berversi HANYA nominal pagu - identitas mata anggaran
 * (master_anggaran) tetap satu baris supaya NPD/SPM/Pengembalian yang
 * menunjuk ke master_anggaran_id tidak pernah putus. master_anggaran.pagu
 * adalah CERMIN dari versi berstatus aktif; aktifkan() satu-satunya yang
 * boleh menulis ulang cermin itu.
 */
#[Fillable([
    'tahun',
    'nama',
    'keterangan',
    'status',
    'total_pagu',
    'jumlah_baris',
    'user_id',
    'diaktifkan_at',
    'diaktifkan_oleh_id',
])]
class VersiPagu extends Model
{
    protected $table = 'versi_pagu';

    /** Hasil import, belum berlaku di mana pun. */
    public const STATUS_DRAFT = 'draft';

    /** Pagu yang sedang dipakai seluruh aplikasi. Maksimum SATU per tahun. */
    public const STATUS_AKTIF = 'aktif';

    /** Pernah aktif, sudah digantikan versi lain. */
    public const STATUS_ARSIP = 'arsip';

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'total_pagu' => 'decimal:2',
            'jumlah_baris' => 'integer',
            'diaktifkan_at' => 'datetime',
        ];
    }

    public function detail(): HasMany
    {
        return $this->hasMany(VersiPaguDetail::class, 'versi_pagu_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diaktifkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diaktifkan_oleh_id');
    }

    public function import(): HasMany
    {
        return $this->hasMany(MasterAnggaranImport::class, 'versi_pagu_id');
    }

    public function berlaku(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }

    /** Versi yang sedang berlaku pada satu tahun anggaran, kalau ada. */
    public static function aktifTahun(int $tahun): ?self
    {
        return self::where('tahun', $tahun)->where('status', self::STATUS_AKTIF)->first();
    }

    /**
     * Mata anggaran yang akan turun di bawah batas aman kalau versi ini
     * diaktifkan sekarang: pagu versi ini lebih kecil daripada dana terikat
     * NPD + realisasi SPM LS yang sudah berjalan.
     *
     * Mata anggaran yang TIDAK dicantumkan versi ini ikut diperiksa dengan
     * pagu 0 - dokumen DPA diperlakukan utuh, jadi yang hilang berarti tidak
     * dianggarkan lagi.
     *
     * @return array<int, array{master_anggaran: MasterAnggaran, pagu_baru: float, minimum: float}>
     */
    public function konflikAktivasi(): array
    {
        $paguPerMaster = $this->detail()->pluck('pagu', 'master_anggaran_id');

        return MasterAnggaran::query()
            ->withAngkaRealisasi()
            ->get()
            ->map(function (MasterAnggaran $master) use ($paguPerMaster) {
                $paguBaru = (float) ($paguPerMaster[$master->id] ?? 0);
                $minimum = $master->paguMinimum();

                return $paguBaru < $minimum
                    ? ['master_anggaran' => $master, 'pagu_baru' => $paguBaru, 'minimum' => $minimum]
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Jadikan versi ini pagu yang berlaku. Versi aktif sebelumnya (kalau
     * ada) berpindah ke status arsip, lalu master_anggaran.pagu ditulis
     * ulang dari detail versi ini. Mata anggaran yang tidak dicantumkan
     * versi ini dipagu 0 DAN dinonaktifkan.
     *
     * Seluruhnya dalam satu transaksi: kalau satu baris gagal, tidak ada
     * pagu yang berubah sama sekali.
     *
     * @throws RuntimeException kalau versi sudah aktif, kosong, atau masih menyisakan konflik.
     */
    public function aktifkan(?int $userId = null): void
    {
        if ($this->status === self::STATUS_AKTIF) {
            throw new RuntimeException('Versi pagu ini memang sudah berstatus aktif.');
        }

        if ($this->detail()->doesntExist()) {
            throw new RuntimeException('Versi pagu ini tidak punya satu pun baris mata anggaran.');
        }

        $konflik = $this->konflikAktivasi();

        if ($konflik !== []) {
            $contoh = collect($konflik)->take(3)->map(fn (array $k) => sprintf(
                '%s / %s (pagu versi Rp %s < terpakai Rp %s)',
                $k['master_anggaran']->kode_sub_kegiatan,
                $k['master_anggaran']->kode_rekening,
                fmt_rupiah($k['pagu_baru']),
                fmt_rupiah($k['minimum'])
            ))->implode('; ');

            throw new RuntimeException(sprintf(
                'Aktivasi dibatalkan: %d mata anggaran akan berpagu lebih kecil daripada dana terikat NPD + realisasi LS yang sudah berjalan. Contoh: %s',
                count($konflik),
                $contoh
            ));
        }

        DB::transaction(function () use ($userId) {
            self::where('tahun', $this->tahun)
                ->where('status', self::STATUS_AKTIF)
                ->whereKeyNot($this->getKey())
                ->lockForUpdate()
                ->update(['status' => self::STATUS_ARSIP]);

            /** @var EloquentCollection<int, VersiPaguDetail> $detail */
            $detail = $this->detail()->get();
            $idTercakup = [];

            foreach ($detail as $baris) {
                MasterAnggaran::whereKey($baris->master_anggaran_id)->update([
                    'pagu' => $baris->pagu,
                    'aktif' => $baris->aktif,
                ]);

                $idTercakup[] = $baris->master_anggaran_id;
            }

            // Tidak tercantum di versi ini = tidak dianggarkan lagi.
            MasterAnggaran::whereNotIn('id', $idTercakup)->update(['pagu' => 0, 'aktif' => false]);

            $this->update([
                'status' => self::STATUS_AKTIF,
                'diaktifkan_at' => now(),
                'diaktifkan_oleh_id' => $userId,
            ]);
        });
    }

    /** Hitung ulang ringkasan header dari detailnya. */
    public function segarkanRingkasan(): void
    {
        $this->update([
            'total_pagu' => (float) $this->detail()->sum('pagu'),
            'jumlah_baris' => $this->detail()->count(),
        ]);
    }
}
