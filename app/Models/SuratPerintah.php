<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
])]
class SuratPerintah extends Model
{
    protected $table = 'surat_perintah';

    /** Status awal SP sendiri, sebelum tertaut NPD mana pun. */
    public const STATUS_DITERIMA_PPTK = 'Diterima PPTK';

    /** Pilihan checkbox kolom Pengajuan (Monitoring SP), disimpan sebagai teks dipisah koma. */
    public const PENGAJUAN_OPTIONS = ['Uang Harian', 'Akomodasi', 'Transport'];

    public const JABATAN_ANGGOTA = [
        'Penanggung Jawab',
        'Pengendali Teknis',
        'Ketua Tim',
        'Anggota',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sp' => 'date',
            'irban_dibayar' => 'boolean',
            'dipantau' => 'boolean',
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
}
