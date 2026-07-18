<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'tanggal_sp' => 'date',
            'irban_dibayar' => 'boolean',
            'dipantau' => 'boolean',
        ];
    }
}
