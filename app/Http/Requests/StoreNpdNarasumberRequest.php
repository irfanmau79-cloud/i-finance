<?php

namespace App\Http\Requests;

use App\Models\Npd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdNarasumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_anggaran_id' => ['required', Rule::exists('master_anggaran', 'id')->where('aktif', true)],
            'jenis_panjar' => ['required', Rule::in(Npd::JENIS_PANJAR_LIST)],
            'tanggal_npd' => ['required', 'date'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            // Selalu tahun anggaran berjalan - isiannya sudah dihapus dari
            // formulir, tetapi tetap ditegakkan di sini.
            'tahun' => ['required', 'integer', 'in:'.config('anggaran.tahun_aktif')],

            'uraian_kegiatan' => ['required', 'string'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],

            'narasumber' => ['required', 'array', 'min:1'],
            'narasumber.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'narasumber.*.vendor_id' => ['nullable', 'integer', 'exists:vendor,id'],
            'narasumber.*.nama' => ['required', 'string', 'max:255'],
            'narasumber.*.jabatan' => ['nullable', 'string', 'max:255'],
            'narasumber.*.rekening' => ['nullable', 'string', 'max:100'],
            'narasumber.*.jumlah_jp' => ['required', 'integer', 'min:0'],
            'narasumber.*.tarif_jp' => ['required', 'numeric', 'min:0'],
            'narasumber.*.transport' => ['nullable', 'numeric', 'min:0'],
            'narasumber.*.pph21' => ['nullable', 'numeric', 'min:0'],
            'narasumber.*.uraian' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'master_anggaran_id' => 'Sumber Dana',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'uraian_kegiatan' => 'Uraian Kegiatan',
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_selesai' => 'Tanggal Selesai',
            'narasumber.*.nama' => 'Nama Narasumber',
            'narasumber.*.jumlah_jp' => 'Jumlah JP',
            'narasumber.*.tarif_jp' => 'Tarif per JP',
        ];
    }
}
