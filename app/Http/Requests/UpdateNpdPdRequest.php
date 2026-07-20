<?php

namespace App\Http\Requests;

use App\Models\SuratPerintah;
use Illuminate\Validation\Rule;

class UpdateNpdPdRequest extends StoreNpdPdRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $npd = $this->route('npd');
        $rules['surat_perintah_id'] = [
            'nullable',
            Rule::exists('surat_perintah', 'id')->where(fn ($query) => $query
                ->where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
                ->orWhere('id', $npd?->surat_perintah_id)),
        ];

        return $rules;
    }
}
