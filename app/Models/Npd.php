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
