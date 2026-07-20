<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\UniqueUsername;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->role === User::ROLE_SUPERADMIN
            && $target instanceof User
            && $this->user()->id !== $target->id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => User::normalisasiUsername($this->input('username')),
        ]);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'username' => User::aturanUsername(new UniqueUsername($target->id)),
            'current_password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'current_password.current_password' => 'Password superadmin saat ini tidak benar.',
        ];
    }
}
