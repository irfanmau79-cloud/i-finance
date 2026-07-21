<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_id', 'nomor_baris', 'hasil', 'pesan', 'identity_hash', 'tanggal_npd',
    'tahun', 'bulan', 'nomor_npd', 'jenis_input', 'jenis_kode', 'sub_kegiatan',
    'sub_kegiatan_kunci', 'program', 'kegiatan', 'kode_rekening', 'tagging_nama',
    'master_anggaran_id', 'tagging_id', 'rak_bulanan_id', 'penerima',
    'rekening_penerima', 'pegawai_id', 'vendor_id', 'penerima_manual',
    'nominal_bruto', 'uraian', 'ppn', 'pph1', 'jenis_pph1', 'pph2',
    'jenis_pph2', 'status_input', 'status_target', 'pagu', 'rak_bulan',
    'realisasi_sebelum', 'realisasi_proyeksi', 'sisa_proyeksi', 'mapping_status', 'npd_id',
])]
class NpdHistorisImportRow extends Model
{
    protected $table = 'npd_historis_import_rows';

    protected function casts(): array
    {
        return [
            'pesan' => 'array', 'tanggal_npd' => 'date', 'penerima_manual' => 'boolean',
            'nominal_bruto' => 'decimal:2', 'ppn' => 'decimal:2', 'pph1' => 'decimal:2',
            'pph2' => 'decimal:2', 'pagu' => 'decimal:2', 'rak_bulan' => 'decimal:2',
            'realisasi_sebelum' => 'decimal:2', 'realisasi_proyeksi' => 'decimal:2',
            'sisa_proyeksi' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(NpdHistorisImport::class, 'import_id');
    }

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }
}
