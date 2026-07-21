<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tunjangan_keluarga_id', 'hubungan', 'nama', 'tanggal_lahir', 'status_tunjangan', 'perpanjangan_kuliah', 'keterangan'])]
class AnggotaKeluarga extends Model
{
    protected $table = 'anggota_keluarga';

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date', 'status_tunjangan' => 'boolean', 'perpanjangan_kuliah' => 'boolean'];
    }

    public function tunjanganKeluarga(): BelongsTo
    {
        return $this->belongsTo(TunjanganKeluarga::class);
    }
}
