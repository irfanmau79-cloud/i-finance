<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu kali notifikasi WhatsApp pencairan NPD dibuka petugas.
 * Nomor dan isi pesan disimpan apa adanya (bukan referensi ke pegawai),
 * supaya riwayat tetap menggambarkan apa yang benar-benar dikirim meski
 * template atau nomor handphone pegawai berubah kemudian.
 */
#[Fillable([
    'npd_id',
    'user_id',
    'kanal',
    'tujuan_nama',
    'tujuan_nomor',
    'pesan',
])]
class NpdNotifikasi extends Model
{
    protected $table = 'npd_notifikasi';

    /** Teks disiapkan aplikasi, tombol Kirim ditekan petugas di WhatsApp-nya sendiri. */
    public const KANAL_DEEP_LINK = 'deep_link';

    public function npd(): BelongsTo
    {
        return $this->belongsTo(Npd::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
