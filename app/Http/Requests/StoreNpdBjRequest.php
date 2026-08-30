<?php

namespace App\Http\Requests;

use App\Models\Npd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdBjRequest extends FormRequest
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

            // Hanya untuk kolom "SISA ANGGARAN" di PDF NPD - lihat
            // Npd::sisaAnggaranCetak(). Dikosongkan berarti memakai angka
            // sistem. Saat isian ini dikunci kembali, nilainya diabaikan di
            // controller, bukan ditolak, supaya formulir lama tidak gagal.
            'sisa_anggaran_manual' => ['nullable', 'numeric', 'min:0'],

            'penerima' => ['required', 'array', 'min:1'],
            'penerima.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'penerima.*.vendor_id' => ['nullable', 'integer', 'exists:vendor,id'],
            'penerima.*.nama' => ['required', 'string', 'max:255'],
            'penerima.*.rekening' => ['nullable', 'string', 'max:100'],
            'penerima.*.bruto' => ['required', 'numeric', 'min:0'],
            'penerima.*.ppn' => ['nullable', 'numeric', 'min:0'],
            'penerima.*.biaya_ku_rtgs' => ['nullable', 'numeric', 'min:0'],
            'penerima.*.pph_list' => ['nullable', 'array'],
            'penerima.*.pph_list.*.jenis' => ['nullable', 'string', 'max:50'],
            'penerima.*.pph_list.*.nilai' => ['nullable', 'numeric', 'min:0'],
            'penerima.*.keterangan' => ['required', 'string'],
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
            'sisa_anggaran_manual' => 'Sisa Anggaran (cetak PDF)',
            'penerima.*.nama' => 'Nama Penerima',
            'penerima.*.bruto' => 'Bruto',
            'penerima.*.ppn' => 'PPN',
            'penerima.*.biaya_ku_rtgs' => 'Biaya KU/RTGS',
            'penerima.*.keterangan' => 'Keterangan',
        ];
    }
}
