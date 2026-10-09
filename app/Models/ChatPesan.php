<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_ruang_id', 'pengirim_id', 'pengirim_nama', 'isi'])]
class ChatPesan extends Model
{
    protected $table = 'chat_pesan';

    /** Pesan tidak pernah disunting, jadi hanya created_at yang dicatat. */
    public const UPDATED_AT = null;

    /** Batas panjang satu pesan. */
    public const MAKS_PANJANG = 2000;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(ChatRuang::class, 'chat_ruang_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }
}
