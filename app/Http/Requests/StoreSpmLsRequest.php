<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpmLsRequest extends FormRequest
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
                // whereDate() supaya konsisten antara MySQL (memangkas jam pada
                // kolom DATE) dan SQLite (menyimpan apa adanya) — lihat StoreSpmUpGuRequest.
                Rule::unique('spm', 'nomor_dokumen')
                    ->where(fn ($query) => $query->where('jenis_spm', 'ls')->whereDate('tanggal_dokumen', $this->input('tanggal_dokumen')))
                    ->ignore($this->route('spm')),
            ],
            'tanggal_sp2d' => ['nullable', 'date'],
            'nomor_sp2d' => ['nullable', 'string', 'max:100'],
            // Satu dokumen LS bisa mencakup beberapa mata anggaran sekaligus
            // (Prompt 22) - PPN/PPh/penerima/uraian di bawah tetap satu angka
            // untuk seluruh dokumen, hanya nominal yang dipecah per baris.
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.master_anggaran_id' => ['required', 'distinct', Rule::exists('master_anggaran', 'id')->where('aktif', true)],
            'baris.*.nominal' => ['required', 'numeric', 'min:0.01'],
            'ppn' => ['nullable', 'numeric', 'min:0'],
            'pph1' => ['nullable', 'numeric', 'min:0'],
            'jenis_pph1' => ['nullable', 'string', 'max:50'],
            'pph2' => ['nullable', 'numeric', 'min:0'],
            'jenis_pph2' => ['nullable', 'string', 'max:50'],
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
            'baris' => 'Baris Mata Anggaran',
            'baris.*.master_anggaran_id' => 'Mata Anggaran',
            'baris.*.nominal' => 'Nominal',
            'uraian' => 'Uraian',
        ];
    }
}
