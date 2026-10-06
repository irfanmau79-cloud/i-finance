<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Satu pesan broadcast superadmin - lihat App\Services\LoncengService. */
#[Fillable(['pesan', 'dibuat_oleh'])]
class Siaran extends Model
{
    protected $table = 'siaran';

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** Akun yang sudah membuka siaran ini. */
    public function pembaca(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'siaran_dibaca')->withPivot('dibaca_at');
    }
}
