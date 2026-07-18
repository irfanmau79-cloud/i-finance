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

    /** Status awal SP sendiri, sebelum tertaut NPD mana pun. */
    public const STATUS_DITERIMA_PPTK = 'Diterima PPTK';

    /** Pilihan checkbox kolom Pengajuan (Monitoring SP), disimpan sebagai teks dipisah koma. */
    public const PENGAJUAN_OPTIONS = ['Uang Harian', 'Akomodasi', 'Transport'];

    protected function casts(): array
    {
        return [
            'tanggal_sp' => 'date',
            'irban_dibayar' => 'boolean',
            'dipantau' => 'boolean',
        ];
    }

    /** Kolom pengajuan (teks "Uang Harian, Transport") sebagai array untuk checkbox. */
    public function pengajuanArray(): array
    {
        return array_filter(array_map('trim', explode(',', (string) $this->pengajuan)));
    }
}
