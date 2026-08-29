<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'nomor_sp',
    'tanggal_sp',
    'unit_kerja',
    'lokasi',
    'nama_pengirim',
    'tujuan_transfer',
    'irban_dibayar',
    'rincian_tgl_bayar',
    'keterangan',
    'file_url',
    'status_sp',
    'status',
    'pengajuan',
    'catatan',
    'dipantau',
    'jenis_permintaan',
    'sp_induk_id',
    'sumber_npd',
])]
class SuratPerintah extends Model
{
    protected $table = 'surat_perintah';

    /** Status awal SP sendiri, sebelum tertaut NPD mana pun. */
    public const STATUS_DITERIMA_PPTK = 'Diterima PPTK';

    /** Pilihan checkbox kolom Pengajuan (Monitoring SP), disimpan sebagai teks dipisah koma. */
    public const PENGAJUAN_OPTIONS = ['Uang Harian', 'Akomodasi', 'Transport'];

    /** Jenis Permintaan Pembayaran (kolom P sheet Monitoring SP di GAS). */
    public const JENIS_UANG_HARIAN = 'Uang Harian/Akomodasi';

    public const JENIS_REIMBURSE = 'Reimburse Transportasi';

    public const JENIS_PERMINTAAN = [self::JENIS_UANG_HARIAN, self::JENIS_REIMBURSE];

    /** Suffix nomor SP Reimburse, mengikuti penomoran GAS: "{induk} (Reimburse)". */
    public const SUFFIX_REIMBURSE = ' (Reimburse)';

    /** Sama persis dengan SP_JABATAN_TIM di CodeSuratPerintah.gs, termasuk ejaannya. */
    public const JABATAN_ANGGOTA = [
        'Penanggungjawab',
        'Wakil Penanggungjawab',
        'Pengendali Teknis',
        'Ketua Tim',
        'Anggota',
    ];

    /** Batas jumlah anggota per SP, mengikuti GAS. */
    public const MAKS_ANGGOTA = 100;

    protected function casts(): array
    {
        return [
            'tanggal_sp' => 'date',
            'irban_dibayar' => 'boolean',
            'dipantau' => 'boolean',
            'sumber_npd' => 'boolean',
        ];
    }

    public function fileDisk(): string
    {
        return str_starts_with((string) $this->file_url, 'private:') ? 'local' : 'public';
    }

    public function filePath(): string
    {
        return str_starts_with((string) $this->file_url, 'private:')
            ? substr((string) $this->file_url, strlen('private:'))
            : (string) $this->file_url;
    }

    public function fileTersedia(): bool
    {
        return filled($this->file_url) && Storage::disk($this->fileDisk())->exists($this->filePath());
    }

    /** Kolom pengajuan (teks "Uang Harian, Transport") sebagai array untuk checkbox. */
    public function pengajuanArray(): array
    {
        return array_filter(array_map('trim', explode(',', (string) $this->pengajuan)));
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(SuratPerintahAnggota::class)->orderBy('urutan');
    }

    /** SP induk berjenis Uang Harian/Akomodasi, hanya terisi pada SP Reimburse. */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sp_induk_id');
    }

    /** Entri Reimburse Transportasi milik SP ini (maksimal satu). */
    public function reimburse(): HasOne
    {
        return $this->hasOne(self::class, 'sp_induk_id');
    }

    public function npd(): HasMany
    {
        return $this->hasMany(Npd::class);
    }

    public function isReimburse(): bool
    {
        return $this->jenis_permintaan === self::JENIS_REIMBURSE;
    }

    /**
     * SP yang boleh dipakai sebagai sumber data pembuatan NPD: masih
     * berstatus awal DAN flag Sumber NPD menyala. Port dari getSPTerinput()
     * di CodeSuratPerintah.gs.
     */
    public function scopeSumberNpdAktif(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('status', self::STATUS_DITERIMA_PPTK)->where('sumber_npd', true);
    }

    /**
     * SP yang boleh dipilih pada Pembuatan NPD Perjalanan Dinas: sumber NPD
     * aktif DAN berjenis Uang Harian/Akomodasi. SP Reimburse Transportasi
     * sengaja tidak ikut - di GAS ia khusus dipakai pada alur NPD Transport
     * (lihat penyaringan jenis di muatOrderanSP(), gas-lama/index.html).
     */
    public function scopeSumberNpdPerjalanan(EloquentBuilder $query): EloquentBuilder
    {
        return $query->sumberNpdAktif()->where('jenis_permintaan', self::JENIS_UANG_HARIAN);
    }

    /** SP yang tampil di halaman Monitoring SP. */
    public function scopeDipantau(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('dipantau', true);
    }

    /**
     * SP induk yang masih boleh dibuatkan entri Reimburse Transportasi:
     * berjenis Uang Harian/Akomodasi, flag Sumber NPD menyala, punya
     * anggota, dan belum punya entri Reimburse. Port dari
     * daftarSPUntukReimburse().
     */
    public static function calonIndukReimburse(): EloquentCollection
    {
        return self::query()
            ->where('jenis_permintaan', self::JENIS_UANG_HARIAN)
            ->where('sumber_npd', true)
            ->whereDoesntHave('reimburse')
            ->whereHas('anggota')
            ->with('anggota')
            ->orderByDesc('id')
            ->get();
    }
}
