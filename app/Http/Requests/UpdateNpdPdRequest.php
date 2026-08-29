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
        // SP tetap WAJIB saat mengedit. Yang dilonggarkan hanya syarat
        // kelayakannya: SP yang sudah tertaut ke NPD ini tetap boleh dipakai
        // walau penanda sumber NPD-nya sudah dimatikan belakangan - kalau
        // tidak, NPD lama jadi tidak bisa disunting sama sekali.
        $rules['surat_perintah_id'] = [
            'required',
            Rule::exists('surat_perintah', 'id')->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
                    ->where('sumber_npd', true)
                    ->where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN))
                ->orWhere('id', $npd?->surat_perintah_id)),
        ];

        return $rules;
    }
}
