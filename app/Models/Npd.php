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
}
