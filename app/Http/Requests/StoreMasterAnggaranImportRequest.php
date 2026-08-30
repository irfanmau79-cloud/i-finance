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

            // Tahapan pagu, mis. "DPA Murni" / "DPA Pergeseran 1".
            // Keunikan per tahun diperiksa di MasterAnggaranImport::buatDariUpload()
            // supaya pesannya seragam dengan pemeriksaan ulang saat konfirmasi.
            'versi_nama' => ['required', 'string', 'max:150'],

            // Satu nomor untuk satu dokumen DPA. Boleh dikosongkan saat
            // nomornya belum terbit; bisa dilengkapi belakangan di halaman
            // Tahapan Pagu tanpa perlu impor ulang.
            'versi_nomor_dpa' => ['nullable', 'string', 'max:100'],
            'versi_keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('tahun')) {
            $this->merge(['tahun' => (int) config('anggaran.tahun_aktif')]);
        }

        foreach (['versi_nama', 'versi_nomor_dpa'] as $isian) {
            if (is_string($this->input($isian))) {
                $this->merge([$isian => trim($this->input($isian))]);
            }
        }
    }

    public function attributes(): array
    {
        return [
            'file' => 'File Excel',
            'tahun' => 'Tahun Anggaran',
            'versi_nama' => 'Tahapan Pagu',
            'versi_nomor_dpa' => 'Nomor DPA',
            'versi_keterangan' => 'Keterangan Tahapan',
        ];
    }
}
