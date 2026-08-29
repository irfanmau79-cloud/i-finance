<?php

namespace App\Http\Requests;

use App\Models\Npd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdKontribusiDiklatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(Npd::MODE_KD_LIST)],
            'npd_referensi_id' => ['nullable', 'integer', 'exists:npd,id'],
            'master_anggaran_id' => ['required', Rule::exists('master_anggaran', 'id')->where('aktif', true)],
            'jenis_panjar' => ['required', Rule::in(Npd::JENIS_PANJAR_LIST)],
            'tanggal_npd' => ['required', 'date'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            // Selalu tahun anggaran berjalan - isiannya sudah dihapus dari
            // formulir, tetapi tetap ditegakkan di sini.
            'tahun' => ['required', 'integer', 'in:'.config('anggaran.tahun_aktif')],

            'nama_pelatihan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'penerima_index' => ['required', 'integer', 'min:0'],
            'keterangan_lampiran' => ['nullable', 'string'],
            'ppn' => ['nullable', 'numeric', 'min:0'],
            'pph_jenis' => ['nullable', 'string', 'max:50'],
            'pph_nilai' => ['nullable', 'numeric', 'min:0'],
            'biaya_lain' => ['nullable', 'numeric', 'min:0'],

            'peserta' => ['required', 'array', 'min:1'],
            'peserta.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'peserta.*.nama' => ['required', 'string', 'max:255'],
            'peserta.*.pangkat' => ['nullable', 'string', 'max:100'],
            'peserta.*.nip' => ['nullable', 'string', 'max:50'],
            'peserta.*.rekening' => ['nullable', 'string', 'max:100'],
            'peserta.*.volume_kontribusi' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_kontribusi' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.volume_mooc' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_mooc' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.hari_uh' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_uh' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.volume_akomodasi' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_akomodasi' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.hari_saku' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_saku' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.transport' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'mode' => 'Mode NPD',
            'npd_referensi_id' => 'Referensi NPD Kontribusi',
            'master_anggaran_id' => 'Sumber Dana',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'nama_pelatihan' => 'Nama Pelatihan',
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_selesai' => 'Tanggal Selesai',
            'penerima_index' => 'Penerima Dana',
            'peserta.*.nama' => 'Nama Peserta',
        ];
    }
}
