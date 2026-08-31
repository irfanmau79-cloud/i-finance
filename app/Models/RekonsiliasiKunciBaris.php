<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status Tunjangan Keluarga satu pegawai pada saat periode dikunci.
 * Identitas pegawainya ikut disalin supaya log tetap terbaca walau
 * pegawainya kelak dihapus dari Data Pegawai.
 */
#[Fillable([
    'kunci_id', 'pegawai_id', 'nama', 'nip',
    'status_tk', 'jumlah_pasangan', 'jumlah_anak',
    'catatan_suntingan', 'disunting_oleh', 'disunting_at',
])]
class RekonsiliasiKunciBaris extends Model
{
    protected $table = 'rekonsiliasi_kunci_baris';

    protected function casts(): array
    {
        return [
            'jumlah_pasangan' => 'integer',
            'jumlah_anak' => 'integer',
            'disunting_at' => 'datetime',
        ];
    }

    public function kunci(): BelongsTo
    {
        return $this->belongsTo(RekonsiliasiKunci::class, 'kunci_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function disuntingOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disunting_oleh');
    }
}
