<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueUsername implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dipakai = User::query()
            ->when($this->ignoreUserId, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->whereRaw('LOWER(username) = ?', [mb_strtolower((string) $value)])
            ->exists();

        if ($dipakai) {
            $fail('Username sudah digunakan.');
        }
    }
}
