<?php

namespace App\Http\Requests;

use App\Models\Pengembalian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengembalianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dokumen_tipe' => ['required', Rule::in(Pengembalian::TIPE_LIST)],
            'dokumen_id' => ['required', 'integer', 'min:1'],
            'tanggal_pengembalian' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            // Baris kosong (nominal 0/kosong) diperbolehkan - diabaikan oleh
            // Pengembalian::buatDraft(). Minimal 1 baris valid ditegakkan di model.
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.master_anggaran_id' => ['required', 'integer', 'distinct', 'exists:master_anggaran,id'],
            'baris.*.nominal' => ['nullable', 'numeric', 'min:0'],
            'dokumen_pendukung' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'dokumen_tipe' => 'Jenis Dokumen',
            'dokumen_id' => 'Dokumen Sumber',
            'tanggal_pengembalian' => 'Tanggal Pengembalian',
            'baris' => 'Baris Mata Anggaran',
            'baris.*.master_anggaran_id' => 'Mata Anggaran',
            'baris.*.nominal' => 'Nominal',
            'dokumen_pendukung' => 'Dokumen Pendukung',
        ];
    }
}
