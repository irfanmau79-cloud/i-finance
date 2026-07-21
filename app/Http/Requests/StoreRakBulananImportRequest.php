<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tahun' => ['required', 'integer', Rule::in([(int) config('anggaran.tahun_aktif')])],
            'format_legacy' => ['nullable', 'in:legacy_laravel_monthly_v1,legacy_gas_cumulative_v1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun',
        ];
    }

    public function messages(): array
    {
        return [
            'tahun.in' => 'Import RAK Bulanan hanya menerima Tahun Anggaran '.config('anggaran.tahun_aktif').'.',
        ];
    }
}
