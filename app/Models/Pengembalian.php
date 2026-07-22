<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pengurang terhadap REALISASI AKTUAL (istilah kantor: "Realisasi SPJ3")
 * suatu NPD berstatus Selesai atau SPM LS - BUKAN pengubah data SP2D/NPD/SPM
 * mentah, yang tetap tercatat apa adanya sebagai bukti kas keluar historis.
 * Append-only: dibaca ulang setiap kali sisa_tersedia dihitung, lihat
 * MasterAnggaran::pengembalianDisetujuiNpd()/pengembalianDisetujuiLs().
 */
#[Fillable([
    'dokumen_tipe',
    'dokumen_id',
    'tanggal_pengembalian',
    'dokumen_pendukung',
    'keterangan',
    'status',
    'dibuat_oleh',
    'disetujui_oleh',
    'disetujui_at',
])]
class Pengembalian extends Model
{
    protected $table = 'pengembalian';

    public const TIPE_NPD = 'npd';

    public const TIPE_SPM_LS = 'spm_ls';

    public const TIPE_LIST = [self::TIPE_NPD, self::TIPE_SPM_LS];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DISETUJUI = 'disetujui';

    protected function casts(): array
    {
        return [
            'tanggal_pengembalian' => 'date',
            'disetujui_at' => 'datetime',
        ];
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PengembalianDetail::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** Dokumen sumber (NPD atau SPM) - dua tabel berbeda tergantung dokumen_tipe, jadi bukan relasi Eloquent biasa. */
    public function dokumen(): Npd|Spm|null
    {
        return match ($this->dokumen_tipe) {
            self::TIPE_NPD => Npd::find($this->dokumen_id),
            self::TIPE_SPM_LS => Spm::find($this->dokumen_id),
            default => null,
        };
    }

    public function totalNominal(): float
    {
        return (float) ($this->relationLoaded('detail') ? $this->detail->sum('nominal') : $this->detail()->sum('nominal'));
    }

    /**
     * Nominal asli per master_anggaran_id dari dokumen sumber (NPD Selesai
     * atau SPM LS). NPD selalu satu baris (satu master_anggaran_id per
     * dokumen); SPM LS bisa lebih dari satu (satu per baris spm_detail).
     *
     * @return array<int, float>
     */
    public static function nominalAsliPerMataAnggaran(string $dokumenTipe, int $dokumenId): array
    {
        if ($dokumenTipe === self::TIPE_NPD) {
            $npd = Npd::where('status', 'Selesai')->find($dokumenId);

            return $npd ? [(int) $npd->master_anggaran_id => (float) $npd->nominal] : [];
        }

        if ($dokumenTipe === self::TIPE_SPM_LS) {
            $spm = Spm::where('jenis_spm', 'ls')->find($dokumenId);

            return $spm
                ? $spm->detail()->get()->mapWithKeys(fn (SpmDetail $d) => [(int) $d->master_anggaran_id => (float) $d->nominal])->all()
                : [];
        }

        return [];
    }

    /**
     * Total pengembalian DISETUJUI sebelumnya dari dokumen ini, per
     * master_anggaran_id. Draft tidak dihitung (belum final).
     *
     * @return array<int, float>
     */
    public static function sudahDikembalikanPerMataAnggaran(string $dokumenTipe, int $dokumenId, ?int $kecualikanId = null): array
    {
        return self::query()
            ->where('dokumen_tipe', $dokumenTipe)
            ->where('dokumen_id', $dokumenId)
            ->where('status', self::STATUS_DISETUJUI)
            ->when($kecualikanId, fn ($q) => $q->whereKeyNot($kecualikanId))
            ->with('detail')
            ->get()
            ->flatMap(fn (self $p) => $p->detail)
            ->groupBy('master_anggaran_id')
            ->map(fn ($rows) => (float) $rows->sum('nominal'))
            ->all();
    }

    /**
     * Buat draft pengembalian. Baris kosong/nol boleh dilewatkan (dikirim
     * dengan nominal 0 atau tidak sama sekali). Divalidasi di sini (satu
     * pintu, dipakai dari controller) sebelum apa pun disimpan.
     *
     * @param  array{dokumen_tipe: string, dokumen_id: int, tanggal_pengembalian: string, dokumen_pendukung?: ?string, keterangan?: ?string, baris: array<int, array{master_anggaran_id?: mixed, nominal?: mixed}>}  $data
     *
     * @throws RuntimeException
     */
    public static function buatDraft(array $data, int $dibuatOleh): self
    {
        return DB::transaction(function () use ($data, $dibuatOleh) {
            $baris = self::validasiBaris($data['dokumen_tipe'], (int) $data['dokumen_id'], $data['baris'] ?? []);

            $pengembalian = self::create([
                'dokumen_tipe' => $data['dokumen_tipe'],
                'dokumen_id' => $data['dokumen_id'],
                'tanggal_pengembalian' => $data['tanggal_pengembalian'],
                'dokumen_pendukung' => $data['dokumen_pendukung'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'status' => self::STATUS_DRAFT,
                'dibuat_oleh' => $dibuatOleh,
            ]);

            foreach ($baris as $item) {
                $pengembalian->detail()->create($item);
            }

            return $pengembalian;
        });
    }

    /**
     * Setujui draft (hanya dari status draft, wajib dokumen_pendukung
     * sudah terisi). Divalidasi ULANG terhadap dokumen sumber di sini
     * (defense-in-depth terhadap race antar draft yang dibuat bersamaan
     * dari dokumen yang sama sebelum salah satunya disetujui).
     *
     * @throws RuntimeException
     */
    public function setujui(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($locked->status !== self::STATUS_DRAFT) {
                throw new RuntimeException('Pengembalian ini sudah diproses sebelumnya.');
            }

            if (blank($locked->dokumen_pendukung)) {
                throw new RuntimeException('Dokumen pendukung wajib diunggah sebelum pengembalian dapat disetujui.');
            }

            $baris = $locked->detail()->get()->map(fn (PengembalianDetail $d) => [
                'master_anggaran_id' => (int) $d->master_anggaran_id,
                'nominal' => (float) $d->nominal,
            ])->all();

            self::validasiBaris($locked->dokumen_tipe, (int) $locked->dokumen_id, $baris, $locked->id);

            $locked->update([
                'status' => self::STATUS_DISETUJUI,
                'disetujui_oleh' => $user->id,
                'disetujui_at' => now(),
            ]);

            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Validasi tiap baris: mata anggaran tidak duplikat, nominal > 0, tidak
     * melebihi nominal ASLI dokumen sumber pada mata anggaran itu, dan
     * (kumulatif dengan pengembalian DISETUJUI sebelumnya dari dokumen yang
     * sama) tidak melebihi nominal asli tsb. Baris dengan nominal <= 0
     * diabaikan (boleh dikosongkan).
     *
     * @param  array<int, array{master_anggaran_id?: mixed, nominal?: mixed}>  $baris
     * @return array<int, array{master_anggaran_id: int, nominal: float}>
     *
     * @throws RuntimeException
     */
    private static function validasiBaris(string $dokumenTipe, int $dokumenId, array $baris, ?int $kecualikanId = null): array
    {
        if (! in_array($dokumenTipe, self::TIPE_LIST, true)) {
            throw new RuntimeException('Tipe dokumen sumber tidak dikenal.');
        }

        $nominalAsli = self::nominalAsliPerMataAnggaran($dokumenTipe, $dokumenId);

        if (empty($nominalAsli)) {
            throw new RuntimeException('Dokumen sumber tidak ditemukan atau tidak memenuhi syarat (NPD harus berstatus Selesai, SPM harus berjenis LS).');
        }

        $sudahDikembalikan = self::sudahDikembalikanPerMataAnggaran($dokumenTipe, $dokumenId, $kecualikanId);

        $dipakai = [];
        $hasil = [];

        foreach ($baris as $item) {
            $nominal = (float) ($item['nominal'] ?? 0);

            if ($nominal <= 0) {
                continue;
            }

            $masterAnggaranId = (int) ($item['master_anggaran_id'] ?? 0);

            if (in_array($masterAnggaranId, $dipakai, true)) {
                throw new RuntimeException('Mata anggaran tidak boleh dipilih lebih dari satu kali dalam satu pengembalian.');
            }
            $dipakai[] = $masterAnggaranId;

            $asli = $nominalAsli[$masterAnggaranId] ?? null;

            if ($asli === null) {
                throw new RuntimeException('Mata anggaran yang dipilih tidak ditemukan pada dokumen sumber.');
            }

            if ($nominal > $asli) {
                throw new RuntimeException(sprintf(
                    'Nominal pengembalian %s melebihi nominal tercatat pada dokumen sumber (%s) untuk mata anggaran itu.',
                    fmt_rupiah($nominal),
                    fmt_rupiah($asli)
                ));
            }

            $sudahKumulatif = (float) ($sudahDikembalikan[$masterAnggaranId] ?? 0);

            if ($sudahKumulatif + $nominal > $asli) {
                throw new RuntimeException(sprintf(
                    'Total pengembalian (termasuk %s yang sudah disetujui sebelumnya dari dokumen yang sama) akan melebihi nominal dokumen sumber (%s). Sisa yang masih bisa dikembalikan: %s.',
                    fmt_rupiah($sudahKumulatif),
                    fmt_rupiah($asli),
                    fmt_rupiah(max(0, $asli - $sudahKumulatif))
                ));
            }

            $hasil[] = ['master_anggaran_id' => $masterAnggaranId, 'nominal' => $nominal];
        }

        if (empty($hasil)) {
            throw new RuntimeException('Pengembalian wajib memiliki minimal 1 baris dengan nominal lebih dari 0.');
        }

        return $hasil;
    }
}
