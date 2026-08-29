<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterAnggaranImportRequest extends FormRequest
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

            // Nama versi pagu, mis. "DPA Murni" / "DPA Pergeseran 1".
            // Keunikan per tahun diperiksa di MasterAnggaranImport::buatDariUpload()
            // supaya pesannya seragam dengan pemeriksaan ulang saat konfirmasi.
            'versi_nama' => ['required', 'string', 'max:150'],
            'versi_keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('tahun')) {
            $this->merge(['tahun' => (int) config('anggaran.tahun_aktif')]);
        }

        if (is_string($this->input('versi_nama'))) {
            $this->merge(['versi_nama' => trim($this->input('versi_nama'))]);
        }
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun Anggaran',
            'versi_nama' => 'Nama Versi Pagu',
            'versi_keterangan' => 'Keterangan Versi',
        ];
    }
}
