<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu berkas SPJ (PDF atau hasil pindaian JPG) milik satu NPD.
 *
 * Lihat migrasi create_spj_berkas_table untuk alasan tabelnya berdiri
 * sendiri, dan App\Services\SpjBerkasService untuk seluruh penyimpanan,
 * penghapusan, dan pengubahannya jadi halaman PDF.
 */
#[Fillable(['npd_id', 'path', 'nama_asli', 'mime', 'ukuran', 'urutan', 'diunggah_oleh'])]
class SpjBerkas extends Model
{
    protected $table = 'spj_berkas';

    protected function casts(): array
    {
        return ['ukuran' => 'integer', 'urutan' => 'integer'];
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function diunggahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function pdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function gambar(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Ukuran berkas dalam satuan yang enak dibaca manusia (KB/MB). */
    public function ukuranTerbaca(): string
    {
        $bytes = (int) $this->ukuran;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.').' KB';
    }
}
