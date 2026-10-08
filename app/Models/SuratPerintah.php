<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'nomor_sp',
    'duplikat_ke',
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
    'jenis_pembayaran',
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

    /**
     * JENIS LAMA - tidak lagi bisa diinput (keputusan Irfan, Oktober 2026).
     * Entri ini khusus melayani NPD Transport, yang pembuatannya sudah
     * dihapus: transport kini dibayar lewat NPD Perjalanan Dinas dengan
     * mencentang komponen Transport pada SP Uang Harian/Akomodasi. Konstanta
     * dan relasinya dipertahankan untuk entri Reimburse yang sudah ada.
     */
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
            'duplikat_ke' => 'integer',
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

    /**
     * Jenis Pembayaran: satu SP boleh menanggung dua-duanya sekaligus, jadi
     * disimpan sebagai daftar bergabung koma seperti kolom `pengajuan`.
     */
    public const JENIS_PEMBAYARAN_OPTIONS = ['Dalam Daerah/Luar Daerah', 'Dalam Kota'];

    /** @return array<int, string> */
    public function jenisPembayaranArray(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->jenis_pembayaran))));
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
     * Duplikat SP: baris SP tersendiri dengan nomor_sp yang SAMA dengan
     * aslinya, dibuat dari halaman Data SP supaya satu Surat Perintah bisa
     * dijadikan acuan beberapa NPD Perjalanan Dinas (satu baris SP hanya
     * bisa ditaut ke satu NPD). duplikat_ke: 0 = asli, n = duplikat ke-n.
     */
    public function isDuplikat(): bool
    {
        return (int) $this->duplikat_ke > 0;
    }

    /**
     * Nomor SP dengan keterangan "(Duplikat-n)" di ujungnya. HANYA untuk
     * halaman Data SP, tempat duplikat harus bisa dibedakan dari aslinya. Di
     * Monitoring SP, NPD, dan dokumen cetak pakai nomor_sp apa adanya.
     */
    public function nomorBerlabel(): string
    {
        return $this->isDuplikat()
            ? $this->nomor_sp.' (Duplikat-'.$this->duplikat_ke.')'
            : (string) $this->nomor_sp;
    }

    /**
     * Hanya SP Uang Harian/Akomodasi yang bisa diduplikat. SP Reimburse
     * Transportasi menumpang SP induknya dan dibatasi satu per induk.
     */
    public function dapatDiduplikat(): bool
    {
        return ! $this->isReimburse();
    }

    /** SP asli beserta seluruh duplikatnya: semua baris bernomor sama. */
    public function scopeSenomor(EloquentBuilder $query, string $nomorSp): EloquentBuilder
    {
        return $query->where('nomor_sp', $nomorSp);
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
}
