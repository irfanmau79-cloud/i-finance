<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'nama', 'nip', 'pegawai_id', 'role', 'aktif', 'password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_BENDAHARA_PENGELUARAN = 'bendahara_pengeluaran';

    public const ROLE_PPTK = 'pptk';

    public const ROLE_BPP = 'bpp';

    public const ROLE_VERIFIKATOR = 'verifikator';

    public const ROLE_OPTIONS = [
        self::ROLE_SUPERADMIN,
        self::ROLE_BENDAHARA_PENGELUARAN,
        self::ROLE_PPTK,
        self::ROLE_BPP,
        self::ROLE_VERIFIKATOR,
        'inspektur',
        'sekretaris',
        'kasubbag',
        'inspektur_pembantu',
        'perencanaan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'aktif' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public static function normalisasiUsername(mixed $username): string
    {
        return mb_strtolower(trim((string) $username));
    }

    /** @return array<int, string|ValidationRule> */
    public static function aturanUsername(ValidationRule $uniqueRule): array
    {
        return ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/', $uniqueRule];
    }
}
