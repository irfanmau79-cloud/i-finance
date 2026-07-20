<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
        ];
    }
}
