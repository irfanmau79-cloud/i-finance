<?php

namespace App\Http\Requests;

use App\Models\SpjDetail;
use App\Support\BidangOrganisasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpjDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'nomor_sp' => ['nullable', 'string', 'max:100'],
            'nominal' => ['nullable', 'numeric', 'min:0'],
            'koordinator' => ['nullable', 'string', 'max:255'],
            'bidang' => ['nullable', Rule::in(BidangOrganisasi::SPJ)],
            'uraian' => ['nullable', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:100', Rule::exists('bantex_spj', 'nama')->where('aktif', true)],
            'status' => ['required', Rule::in([SpjDetail::STATUS_LENGKAP, SpjDetail::STATUS_BELUM_LENGKAP])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bulan' => 'Bulan',
            'nomor_sp' => 'Nomor Surat Perintah',
            'nominal' => 'Nominal',
            'koordinator' => 'Koordinator',
            'bidang' => 'Bidang',
            'uraian' => 'Uraian',
            'lokasi' => 'Lokasi',
            'status' => 'Status',
            'catatan' => 'Catatan',
        ];
    }
}
