<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'jenis',
    'master_anggaran_id',
    'surat_perintah_id',
    'keu',
    'nomor_urut',
    'bulan',
    'tahun',
    'nomor_lengkap',
    'tanggal_npd',
    'jenis_panjar',
    'nominal',
    'terbilang',
    'status',
    'catatan',
    'detail_json',
    'link_pdf_npd',
    'link_pdf_lampiran',
    'dibuat_oleh',
])]
class Npd extends Model
{
    protected $table = 'npd';

    public const JENIS_LABEL = [
        'bj' => 'Barang/Jasa',
        'pd' => 'Perjalanan Dinas',
        'tr' => 'Transport',
        'ns' => 'Narasumber',
        'kd' => 'Kegiatan Dalam',
    ];

    public const JENIS_PANJAR_LIST = ['Panjar', 'Tanpa Panjar'];

    public const STATUS_LIST = [
        'Draft NPD - PPTK',
        'Draft NPD - BPP',
        'Verifikasi - Verifikator',
        'Dikembalikan',
        'NPD Disetujui - BPP',
        'Selesai',
    ];

    public const STATUS_BADGE_CLASS = [
        'Draft NPD - PPTK' => 'st-npd',
        'Draft NPD - BPP' => 'st-npd-bpp',
        'Verifikasi - Verifikator' => 'st-verifikasi',
        'Dikembalikan' => 'st-dikembalikan',
        'NPD Disetujui - BPP' => 'st-disetujui',
        'Selesai' => 'st-selesai',
    ];

    /**
     * Alur transisi status NPD (berlaku untuk semua jenis: bj, pd, tr, ns, kd).
     * Port dari transisiNPD/verifikasiNPD di gas-lama/CodeRevisi.gs. Bendahara
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

    public function penerima(): HasMany
    {
        return $this->hasMany(NpdPenerima::class);
    }

    public function tim(): HasMany
    {
        return $this->hasMany(NpdTim::class);
    }

    /** Role bendahara boleh melakukan aksi apa pun; role lain hanya sesuai daftar 'roles' aksi tsb. */
    public static function bolehAksi(string $aksi, string $role): bool
    {
        $rule = self::TRANSISI[$aksi] ?? null;

        return $rule !== null && ($role === 'bendahara' || in_array($role, $rule['roles'], true));
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
}
