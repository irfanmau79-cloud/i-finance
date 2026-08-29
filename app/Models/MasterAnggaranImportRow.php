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
    'kode_program',
    'program',
    'kode_kegiatan',
    'kegiatan',
    'kode_sub_kegiatan',
    'sub_kegiatan',
    'kode_rekening',
    'rekening',
    'tagging_nama',
    'aktif',
    'pagu_baru',
    'pagu_lama',
    'master_anggaran_id',
])]
class MasterAnggaranImportRow extends Model
{
    protected $table = 'master_anggaran_import_rows';

    public const AKSI_BARU = 'baru';

    public const AKSI_UPDATE = 'update';

    public const AKSI_DITOLAK = 'ditolak';

    /**
     * Mata anggaran yang ada di pagu berlaku tapi TIDAK dicantumkan pada
     * file versi ini. Bukan baris file - disintesis saat staging dan diberi
     * nomor_baris 0. File DPA diperlakukan sebagai dokumen utuh, jadi yang
     * hilang berarti pagunya menjadi 0 dan mata anggarannya dinonaktifkan.
     */
    public const AKSI_DINOLKAN = 'dinolkan';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'pagu_baru' => 'decimal:2',
            'pagu_lama' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaranImport::class, 'import_id');
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    /** Baris yang akan benar-benar ditulis ke master_anggaran saat konfirmasi. */
    public function akanDisimpan(): bool
    {
        return in_array($this->aksi, [self::AKSI_BARU, self::AKSI_UPDATE, self::AKSI_DINOLKAN], true);
    }
}
