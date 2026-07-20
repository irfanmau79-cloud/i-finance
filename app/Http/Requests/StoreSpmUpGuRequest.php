<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpmUpGuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_dokumen' => ['required', 'date'],
            'nomor_dokumen' => [
                'required', 'string', 'max:100',
                // whereDate(), bukan where() biasa — kolom 'date' Eloquent selalu
                // diserialisasi sebagai "Y-m-d H:i:s" (lihat getDateFormat()); MySQL
                // memangkas jam pada kolom DATE sungguhan saat disimpan, tapi SQLite
                // (dipakai test suite) menyimpan apa adanya termasuk "00:00:00".
                // whereDate() membandingkan hanya bagian tanggal, konsisten di kedua driver.
                Rule::unique('spm', 'nomor_dokumen')
                    ->where(fn ($query) => $query->where('jenis_spm', 'up_gu')->whereDate('tanggal_dokumen', $this->input('tanggal_dokumen')))
                    ->ignore($this->route('spm')),
            ],
            'tanggal_sp2d' => ['nullable', 'date'],
            'nomor_sp2d' => ['nullable', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0.01'],
            'penerima' => ['nullable', 'string', 'max:255'],
            'uraian' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tanggal_dokumen' => 'Tanggal SPM',
            'nomor_dokumen' => 'Nomor SPM',
            'tanggal_sp2d' => 'Tanggal SP2D',
            'nomor_sp2d' => 'Nomor SP2D',
            'nominal' => 'Nominal',
            'uraian' => 'Uraian',
        ];
    }
}
