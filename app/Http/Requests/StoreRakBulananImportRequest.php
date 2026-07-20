<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRakBulananImportRequest extends FormRequest
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
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun',
        ];
    }
}
