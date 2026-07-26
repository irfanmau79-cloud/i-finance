<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($this->route('pegawai'))],
            'jabatan' => ['required', 'string', 'max:255'],
            'bidang' => ['required', 'string', 'max:100'],
            'golongan' => ['nullable', 'string', 'max:20'],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'rekening' => ['nullable', 'string', 'max:100'],
            'nomor_handphone' => ['nullable', 'string', 'max:30'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'Nama',
            'nip' => 'NIP',
            'jabatan' => 'Jabatan',
            'bidang' => 'Bidang',
            'golongan' => 'Golongan',
            'pangkat' => 'Pangkat',
            'rekening' => 'Rekening',
            'nomor_handphone' => 'Nomor Handphone',
            'aktif' => 'Aktif',
        ];
    }
}
