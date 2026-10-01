<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu dokumen SPJ Perjalanan Dinas yang diinput manual, bentuknya mengikuti
 * SpjDashboardService::baris() supaya bisa digabung apa adanya dengan baris
 * yang berasal dari NPD.
 *
 * Nomor dokumennya teks bebas: dokumen periode sebelum migrasi tidak selalu
 * punya padanan baris di tabel npd.
 */
#[Fillable([
    'tahun',
    'tanggal',
    'nomor_npd',
    'nomor_sp',
    'sub_kegiatan',
    'uraian',
    'bidang',
    'nominal',
    'spj_terverifikasi',
    'tanggal_verifikasi',
    'diverifikasi_oleh',
    'keterangan',
])]
class SpjPerjalananDinasManual extends Model
{
    protected $table = 'spj_perjalanan_dinas_manual';

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal' => 'date',
            'tanggal_verifikasi' => 'date',
            'nominal' => 'decimal:2',
            'spj_terverifikasi' => 'boolean',
        ];
    }

    /** Bentuk baris yang sama dengan SpjDashboardService::baris(). */
    public function sebagaiBarisDashboard(): array
    {
        return [
            'id' => 'manual-'.$this->id,
            'tanggal' => $this->tanggal,
            'nomor_npd' => $this->nomor_npd,
            'nomor_sp' => $this->nomor_sp ?: '-',
            'sub_kegiatan' => $this->sub_kegiatan ?: '-',
            'uraian' => $this->uraian ?: '-',
            'bidang' => $this->bidang,
            'nominal' => (float) $this->nominal,
            'status_spj' => $this->spj_terverifikasi ? 'terverifikasi' : 'belum',
            'verified_at' => $this->tanggal_verifikasi,
            'verified_by' => $this->diverifikasi_oleh,
            'sumber' => 'manual',
        ];
    }
}
