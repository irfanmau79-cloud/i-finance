<?php

namespace App\Http\Requests;

use App\Services\InventarisasiSpjService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArsipSpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => ['required', Rule::in(InventarisasiSpjService::JENIS_DOKUMEN)],
            'lokasi' => ['required', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
