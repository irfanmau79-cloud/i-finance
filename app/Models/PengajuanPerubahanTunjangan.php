<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pegawai_id', 'nama_pegawai', 'nip', 'payload', 'keterangan', 'status', 'ip_address', 'diajukan_at', 'diproses_oleh', 'diproses_at', 'catatan_proses'])]
class PengajuanPerubahanTunjangan extends Model
{
    protected $table = 'pengajuan_perubahan_tunjangan';

    /** Role yang boleh Approve/Tolak pengajuan. Dipakai rute DAN tampilan Monitoring. */
    public const ROLE_PEMROSES = ['superadmin', 'kepegawaian'];

    /** Role yang boleh membuka lampiran pengajuan. */
    public const ROLE_LAMPIRAN = ['superadmin', 'kepegawaian', 'bendahara_pengeluaran'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'diajukan_at' => 'datetime', 'diproses_at' => 'datetime'];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(LampiranTunjangan::class, 'pengajuan_id');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
