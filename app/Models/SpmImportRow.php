<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_id',
    'nomor_baris',
    'aksi',
    'alasan',
    'nomor_dokumen',
    'tanggal_dokumen',
    'nomor_sp2d',
    'tanggal_sp2d',
    'sub_kegiatan',
    'kode_rekening',
    'tagging_nama',
    'master_anggaran_id',
    'nominal',
    'ppn',
    'pph1',
    'jenis_pph1',
    'pph2',
    'jenis_pph2',
    'penerima',
    'penerima_pegawai_id',
    'penerima_vendor_id',
    'bank_tujuan',
    'nomor_rekening',
    'uraian',
    'sisa_sebelum',
    'sisa_sesudah',
    'spm_id',
])]
class SpmImportRow extends Model
{
    protected $table = 'spm_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    protected function casts(): array
    {
        return [
            'tanggal_dokumen' => 'date',
            'tanggal_sp2d' => 'date',
            'nominal' => 'decimal:2',
            'ppn' => 'decimal:2',
            'pph1' => 'decimal:2',
            'pph2' => 'decimal:2',
            'sisa_sebelum' => 'decimal:2',
            'sisa_sesudah' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(SpmImport::class, 'import_id');
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    public function spm(): BelongsTo
    {
        return $this->belongsTo(Spm::class);
    }

    /** Hasil pencocokan nama penerima ke master saat file di-parse. */
    public function penerimaPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'penerima_pegawai_id');
    }

    public function penerimaVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'penerima_vendor_id');
    }
}
