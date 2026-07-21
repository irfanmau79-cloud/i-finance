<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePengajuanTunjanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'website' => ['prohibited'],
            'nama_pegawai' => ['required', 'string', 'max:150'],
            'nip' => ['nullable', 'string', 'max:30'],
            'keterangan' => ['required', 'string', 'min:10', 'max:2000'],
            'pasangan.nama' => ['nullable', 'string', 'max:150'],
            'pasangan.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'pasangan.status_tunjangan' => ['nullable', 'boolean'],
            'anak' => ['nullable', 'array', 'max:10'],
            'anak.*.nama' => ['required_with:anak.*.tanggal_lahir,anak.*.status_tunjangan', 'nullable', 'string', 'max:150'],
            'anak.*.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'anak.*.status_tunjangan' => ['nullable', 'boolean'],
            'anak.*.perpanjangan_kuliah' => ['nullable', 'boolean'],
            'anak.*.keterangan' => ['nullable', 'string', 'max:500'],
            'lampiran' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $aktif = collect($this->input('anak', []))->filter(fn ($anak) => filter_var($anak['status_tunjangan'] ?? false, FILTER_VALIDATE_BOOL))->count();
            if ($aktif > 2) {
                $validator->errors()->add('anak', 'Maksimal dua anak dapat diajukan sebagai penerima tunjangan.');
            }
        }];
    }
}
