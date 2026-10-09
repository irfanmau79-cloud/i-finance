<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu ruang chat: percakapan pribadi dua akun, atau ruang sebuah role.
 * Lihat migrasi create_chat_tables untuk bentuk datanya.
 */
#[Fillable(['jenis', 'role', 'user_a_id', 'user_b_id', 'pesan_terakhir_id', 'pesan_terakhir_at'])]
class ChatRuang extends Model
{
    protected $table = 'chat_ruang';

    public const JENIS_PRIBADI = 'pribadi';

    public const JENIS_ROLE = 'role';

    protected function casts(): array
    {
        return ['pesan_terakhir_at' => 'datetime'];
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(ChatPesan::class);
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function isRole(): bool
    {
        return $this->jenis === self::JENIS_ROLE;
    }

    /**
     * Siapa yang boleh membuka ruang ini.
     *
     * Ruang role terbuka bagi SEMUA akun - begitulah seluruh pemegang sebuah
     * role disapa sekaligus. Ruang pribadi hanya untuk dua pesertanya;
     * superadmin pun tidak bisa membaca percakapan pribadi orang lain.
     */
    public function bolehDibuka(User $user): bool
    {
        if ($this->isRole()) {
            return true;
        }

        return in_array((int) $user->id, [(int) $this->user_a_id, (int) $this->user_b_id], true);
    }

    /** Lawan bicara pada ruang pribadi, dilihat dari sisi $user. */
    public function lawan(User $user): ?User
    {
        if ($this->isRole()) {
            return null;
        }

        return (int) $this->user_a_id === (int) $user->id ? $this->userB : $this->userA;
    }

    /** Nama ruang sebagaimana tampil bagi $user. */
    public function namaUntuk(User $user): string
    {
        if ($this->isRole()) {
            return 'Ruang '.(config('akses.role_label')[$this->role] ?? $this->role);
        }

        return (string) ($this->lawan($user)?->nama ?? 'Akun dihapus');
    }
}
