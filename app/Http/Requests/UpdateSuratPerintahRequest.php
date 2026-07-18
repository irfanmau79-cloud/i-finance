<?php

namespace App\Http\Requests;

class UpdateSuratPerintahRequest extends StoreSuratPerintahRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'file_url' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }
}
