<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggota satu Surat Perintah, menyimpan SNAPSHOT identitas sebagaimana
 * kondisinya saat SP dibuat/diedit - bukan join hidup ke master Pegawai.
 * pegawai_id hanya tautan penelusuran dan boleh kosong untuk anggota yang
 * diisi manual (pegawai di luar master). Lihat catatan lengkapnya di
 * migrasi 2026_08_28_100001_add_snapshot_to_surat_perintah_anggota.
 */
#[Fillable([
    'surat_perintah_id',
    'pegawai_id',
    'nama',
    'nip',
    'golongan',
    'pangkat',
    'jabatan',
    'rekening',
    'manual',
    'jabatan_sp',
    'urutan',
])]
class SuratPerintahAnggota extends Model
{
    protected $table = 'surat_perintah_anggota';

    protected function casts(): array
    {
        return ['manual' => 'boolean'];
    }

    public function suratPerintah(): BelongsTo
    {
        return $this->belongsTo(SuratPerintah::class);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /** "III/c / Penata" - bentuk gabungan yang dipakai di form (satu isian). */
    public function golonganPangkat(): string
    {
        return collect([$this->golongan, $this->pangkat])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->implode(' / ');
    }

    /** Bentuk array untuk mengisi ulang form dan menyalin ke NPD Perjalanan Dinas. */
    public function sebagaiInput(): array
    {
        return [
            'pegawai_id' => $this->pegawai_id,
            'nama' => (string) $this->nama,
            'nip' => (string) $this->nip,
            'golongan' => (string) $this->golongan,
            'pangkat' => (string) $this->pangkat,
            'jabatan' => (string) $this->jabatan,
            'rekening' => (string) $this->rekening,
            'jabatan_sp' => (string) $this->jabatan_sp,
            'manual' => (bool) $this->manual,
        ];
    }
}
