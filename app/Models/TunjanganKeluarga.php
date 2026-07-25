<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pegawai_id', 'catatan', 'dokumen_pendukung_path', 'dokumen_pendukung_nama', 'diperbarui_oleh'])]
class TunjanganKeluarga extends Model
{
    protected $table = 'tunjangan_keluarga';

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaKeluarga::class);
    }
}
