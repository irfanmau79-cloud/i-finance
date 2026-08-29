<?php

namespace App\Http\Requests;

use App\Models\Npd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'npd_induk_id' => ['required', 'integer', 'exists:npd,id'],
            'jenis_panjar' => ['required', Rule::in(Npd::JENIS_PANJAR_LIST)],
            'tanggal_npd' => ['required', 'date'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            // Selalu tahun anggaran berjalan - isiannya sudah dihapus dari
            // formulir, tetapi tetap ditegakkan di sini.
            'tahun' => ['required', 'integer', 'in:'.config('anggaran.tahun_aktif')],
            'penerima_index' => ['required', 'integer', 'min:0'],
            'keterangan_lampiran' => ['nullable', 'string'],

            // Identitas anggota (nama/jabatan/nip/rekening/pegawai_id) TIDAK divalidasi/diterima
            // di sini — selalu disalin ulang dari anggota NPD induk berdasarkan urutan, supaya
            // Transport benar-benar snapshot induk, bukan input bebas.
            'tim' => ['required', 'array', 'min:1'],
            'tim.*.bbm_liter' => ['nullable', 'numeric', 'min:0'],
            'tim.*.bbm_tarif' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tol' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tiket' => ['nullable', 'numeric', 'min:0'],
            'tim.*.representatif' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'npd_induk_id' => 'NPD Perjalanan Dinas Induk',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'penerima_index' => 'Penerima Dana',
        ];
    }
}
