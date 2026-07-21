<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterAnggaranImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // max dalam KB - 5120 KB = 5 MB. mimes memvalidasi ekstensi & MIME sekaligus.
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'tahun' => ['required', 'integer', Rule::in([(int) config('anggaran.tahun_aktif')])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('tahun')) {
            $this->merge(['tahun' => (int) config('anggaran.tahun_aktif')]);
        }
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun Anggaran',
        ];
    }
}
