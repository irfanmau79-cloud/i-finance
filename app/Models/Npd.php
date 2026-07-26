<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'jenis',
    'master_anggaran_id',
    'surat_perintah_id',
    'npd_referensi_id',
    'npd_induk_id',
    'keu',
    'nomor_urut',
    'bulan',
    'tahun',
    'nomor_lengkap',
    'tanggal_npd',
    'jenis_panjar',
    'mode_kd',
    'nominal',
    'terbilang',
    'status',
    'catatan',
    'detail_json',
    'sumber_data',
    'import_historis_id',
    'import_baris',
    'import_identity_hash',
    'tagging_snapshot',
    'link_pdf_npd',
    'link_pdf_lampiran',
    'dibuat_oleh',
    'spj_verified_at',
    'spj_verified_by',
])]
class Npd extends Model
{
    use SoftDeletes;

    protected $table = 'npd';

    public const JENIS_LABEL = [
        'bj' => 'Barang/Jasa',
        'pd' => 'Perjalanan Dinas',
        'tr' => 'Transport',
        'ns' => 'Narasumber',
        'kd' => 'Kontribusi Diklat',
    ];

    public const JENIS_PANJAR_LIST = ['Panjar', 'Tanpa Panjar'];

    /** Hanya berarti untuk jenis 'kd'. Port dari payload.mode di gas-lama/CodeKontribusiDiklat.gs. */
    public const MODE_KD_LIST = ['kontribusi', 'perjalanan'];

    public const STATUS_LIST = [
        'Draft NPD - PPTK',
        'Draft NPD - BPP',
        'Verifikasi - Verifikator',
        'NPD Disetujui - BPP',
        'Selesai',
        'Dibatalkan',
    ];

    public const STATUS_BADGE_CLASS = [
        'Draft NPD - PPTK' => 'st-npd',
        'Draft NPD - BPP' => 'st-npd-bpp',
        'Verifikasi - Verifikator' => 'st-verifikasi',
        'NPD Disetujui - BPP' => 'st-disetujui',
        'Selesai' => 'st-selesai',
        'Dibatalkan' => 'st-dikembalikan',
    ];

    /**
     * Alur transisi status NPD (berlaku untuk semua jenis: bj, pd, tr, ns, kd).
     * Port dari transisiNPD/verifikasiNPD di gas-lama/CodeRevisi.gs. Superadmin
     * boleh melakukan aksi apa pun; role lain hanya aksi yang tercantum di 'roles'.
     */
    public const TRANSISI = [
        'ajukan_bpp' => [
            'from' => 'Draft NPD - PPTK',
            'to' => 'Draft NPD - BPP',
            'roles' => ['pptk'],
            'label' => 'Ajukan ke BPP',
        ],
        'teruskan' => [
            'from' => 'Draft NPD - BPP',
            'to' => 'Verifikasi - Verifikator',
            'roles' => ['bpp'],
            'label' => 'Teruskan ke Verifikator',
        ],
        'verifikasi' => [
            'from' => 'Verifikasi - Verifikator',
            'to' => 'Draft NPD - BPP',
            'roles' => ['verifikator'],
            'label' => 'Verifikasi',
        ],
        'kembali_bpp' => [
            'from' => 'Verifikasi - Verifikator',
            'to' => 'Draft NPD - BPP',
            'roles' => ['verifikator'],
            'label' => 'Kembalikan ke BPP',
        ],
        'kembali_pptk' => [
            'from' => 'Draft NPD - BPP',
            'to' => 'Draft NPD - PPTK',
            'roles' => ['bpp'],
            'label' => 'Kembalikan ke PPTK',
        ],
        'setuju' => [
            'from' => 'Draft NPD - BPP',
            'to' => 'NPD Disetujui - BPP',
            'roles' => ['bpp'],
            'label' => 'Setujui Final',
        ],
        'selesai' => [
            'from' => 'NPD Disetujui - BPP',
            'to' => 'Selesai',
            'roles' => ['bpp'],
            'label' => 'Tandai Selesai',
        ],
        'batal_selesai' => [
            'from' => 'Selesai',
            'to' => 'Draft NPD - BPP',
            'roles' => ['bpp'],
            'label' => 'Batalkan Selesai',
        ],
    ];

    /** Aksi yang mewajibkan catatan/alasan diisi. */
    public const AKSI_WAJIB_CATATAN = ['kembali_bpp', 'kembali_pptk', 'batal_selesai'];

    protected function casts(): array
    {
        return [
            'tanggal_npd' => 'date',
            'nominal' => 'decimal:2',
            'detail_json' => 'array',
            'spj_verified_at' => 'datetime',
        ];
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    public function suratPerintah(): BelongsTo
    {
        return $this->belongsTo(SuratPerintah::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function spjVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spj_verified_by');
    }

    public function penerima(): HasMany
    {
        return $this->hasMany(NpdPenerima::class);
    }

    public function tim(): HasMany
    {
        return $this->hasMany(NpdTim::class);
    }

    public function narasumber(): HasMany
    {
        return $this->hasMany(NpdNarasumber::class);
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(NpdPeserta::class);
    }

    /** Untuk jenis 'kd' mode 'perjalanan': NPD Kontribusi yang dijadikan referensi (boleh null bila diisi manual). */
    public function referensi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'npd_referensi_id');
    }

    /** Untuk jenis 'kd' mode 'kontribusi': daftar NPD Perjalanan yang mereferensikan NPD ini. */
    public function turunanPerjalanan(): HasMany
    {
        return $this->hasMany(self::class, 'npd_referensi_id');
    }

    /** Untuk jenis 'tr': NPD Perjalanan Dinas induk (jenis 'pd', status Selesai saat dibuat). */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'npd_induk_id');
    }

    /** Untuk jenis 'pd': daftar NPD Transport yang menjadikan NPD ini induk. */
    public function turunanTransport(): HasMany
    {
        return $this->hasMany(self::class, 'npd_induk_id');
    }

    /**
     * True kalau NPD ini (biasanya jenis 'pd') masih punya turunan Transport
     * yang aktif (belum Dibatalkan). Dipakai untuk mencegah pembatalan induk
     * yang akan membuat turunan Transport menjadi yatim — lihat Prompt 07.
     */
    public function punyaTurunanTransportAktif(): bool
    {
        return $this->turunanTransport()->where('status', '!=', 'Dibatalkan')->exists();
    }

    public function historiStatus(): HasMany
    {
        return $this->hasMany(NpdHistoriStatus::class)->orderBy('nomor_urut');
    }

    public function arsipSpj(): HasMany
    {
        return $this->hasMany(ArsipSpj::class)->orderByDesc('ditetapkan_at');
    }

    public function arsipSpjAktif(): HasMany
    {
        return $this->hasMany(ArsipSpj::class)->where('aktif', true)->orderBy('jenis_dokumen');
    }

    public function importHistoris(): BelongsTo
    {
        return $this->belongsTo(NpdHistorisImport::class, 'import_historis_id');
    }

    /** Override manual "Tabel Detail SPJ" (Inventarisasi SPJ) - lihat App\Models\SpjDetail. */
    public function spjDetail(): HasOne
    {
        return $this->hasOne(SpjDetail::class);
    }

    public function dapatDieditOleh(User $user): bool
    {
        return $this->status === 'Draft NPD - PPTK'
            && in_array($user->role, [User::ROLE_SUPERADMIN, User::ROLE_PPTK], true);
    }

    public function dapatDihapusOleh(User $user): bool
    {
        return $user->isSuperadmin()
            || ($user->role === User::ROLE_PPTK && $this->status === 'Draft NPD - PPTK');
    }

    /**
     * Ringkasan nama penerima untuk daftar NPD (kolom "Penerima" — port dari
     * n.penerima di gas-lama/index.html renderDaftarNPD). Sumbernya beda per
     * jenis: bj/kd pakai npd_penerima langsung, pd/tr pakai anggota tim yang
     * ditandai is_penerima (fallback ke semua anggota kalau belum ada yang
     * ditandai), ns pakai npd_narasumber, kd pakai npd_peserta. Relasi harus
     * sudah di-eager-load oleh pemanggil untuk menghindari N+1.
     */
    public function ringkasanPenerima(): string
    {
        $nama = match ($this->jenis) {
            'bj' => $this->penerima->pluck('nama'),
            'pd', 'tr' => $this->tim->where('is_penerima', true)->isNotEmpty()
                ? $this->tim->where('is_penerima', true)->pluck('nama')->values()
                : $this->tim->pluck('nama'),
            'ns' => $this->narasumber->pluck('nama'),
            'kd' => $this->peserta->pluck('nama'),
            default => collect(),
        };
        $nama = $nama->filter()->values();

        if ($nama->isEmpty()) {
            return '-';
        }

        if ($nama->count() === 1) {
            return (string) $nama->first();
        }

        $sisa = $nama->count() - 1;

        return "{$nama->first()} (dan {$sisa} lainnya)";
    }

    public function catatHistoriStatus(
        ?User $user,
        string $aksi,
        ?string $statusAsal,
        string $statusTujuan,
        ?string $catatan = null,
    ): NpdHistoriStatus {
        $nomorUrut = (int) $this->historiStatus()->max('nomor_urut') + 1;

        return $this->historiStatus()->create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'status_asal' => $statusAsal,
            'status_tujuan' => $statusTujuan,
            'catatan' => $catatan,
            'nomor_urut' => $nomorUrut,
        ]);
    }

    /** Superadmin boleh melakukan aksi apa pun; role lain hanya sesuai daftar 'roles' aksi tsb. */
    public static function bolehAksi(string $aksi, string $role): bool
    {
        $rule = self::TRANSISI[$aksi] ?? null;

        return $rule !== null && ($role === User::ROLE_SUPERADMIN || in_array($role, $rule['roles'], true));
    }

    /** Daftar kunci aksi yang bisa dilakukan role tsb, sesuai status NPD saat ini. */
    public function aksiTersedia(string $role): array
    {
        return collect(self::TRANSISI)
            ->filter(fn (array $rule, string $aksi) => $rule['from'] === $this->status && self::bolehAksi($aksi, $role))
            ->keys()
            ->all();
    }

    /** Format nomor NPD resmi: "01/NPD-Keu.1.IBC/7/2026" (urut 2-digit minimal). */
    public static function buatNomorLengkap(int $nomorUrut, string $keu, int $bulan, int $tahun): string
    {
        return sprintf('%02d/NPD-Keu.%s.IBC/%d/%d', $nomorUrut, $keu, $bulan, $tahun);
    }

    /** Mirror status NPD ke surat_perintah terkait, kalau NPD ini tertaut ke satu (port _mirrorStatusKeSP). */
    public function mirrorStatusKeSuratPerintah(): void
    {
        if ($this->surat_perintah_id) {
            $this->suratPerintah()->update(['status' => $this->status]);
        }
    }

    public function lepaskanSuratPerintah(): void
    {
        if (! $this->surat_perintah_id) {
            return;
        }

        $masihDipakai = self::query()
            ->whereKeyNot($this->id)
            ->where('surat_perintah_id', $this->surat_perintah_id)
            ->where('status', '!=', 'Dibatalkan')
            ->exists();

        if (! $masihDipakai) {
            $this->suratPerintah()->update(['status' => SuratPerintah::STATUS_DITERIMA_PPTK]);
        }
    }
}
