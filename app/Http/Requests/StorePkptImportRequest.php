<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePkptImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            // Tahun tidak ada di berkasnya - PKPT disusun per tahun anggaran
            // dan dokumen aslinya tidak mencantumkan tahun di tiap baris.
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun Anggaran',
        ];
    }
}
