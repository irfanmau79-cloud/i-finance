<?php

namespace App\Http\Requests;

use App\Models\Npd;
use App\Models\SuratPerintah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdPdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_anggaran_id' => ['required', Rule::exists('master_anggaran', 'id')->where('aktif', true)],
            // NPD Perjalanan Dinas WAJIB berangkat dari Surat Perintah - tidak
            // boleh lagi dibuat lepas. SP-nya pun harus yang benar-benar layak
            // jadi sumber: berjenis Uang Harian/Akomodasi, masih berstatus
            // Diterima PPTK, dan penandanya sebagai sumber NPD masih menyala.
            'surat_perintah_id' => ['required', Rule::exists('surat_perintah', 'id')
                ->where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
                ->where('sumber_npd', true)
                ->where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN)],
            'jenis_panjar' => ['required', Rule::in(Npd::JENIS_PANJAR_LIST)],
            'tanggal_npd' => ['required', 'date'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            // Selalu tahun anggaran berjalan - isiannya sudah dihapus dari
            // formulir, tetapi tetap ditegakkan di sini.
            'tahun' => ['required', 'integer', 'in:'.config('anggaran.tahun_aktif')],

            // 'nomor_sp' dan 'tanggal_sp' sengaja TIDAK divalidasi di sini:
            // keduanya diambil langsung dari Surat Perintah yang dipilih
            // (lihat NpdPdController), supaya tidak bisa dikarang lewat form.
            'uraian_sp' => ['required', 'string'],
            'berangkat_dari' => ['required', 'string', 'max:150'],
            'tujuan' => ['required', 'string', 'max:150'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_pulang' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'keterangan_lampiran' => ['nullable', 'string'],

            'penerima_index' => ['required', 'integer', 'min:0'],

            'tim' => ['required', 'array', 'min:1'],
            'tim.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'tim.*.nama' => ['required', 'string', 'max:255'],
            'tim.*.jabatan' => ['nullable', 'string', 'max:255'],
            'tim.*.nip' => ['nullable', 'string', 'max:30'],
            'tim.*.rekening' => ['nullable', 'string', 'max:100'],
            'tim.*.bbm_liter' => ['nullable', 'numeric', 'min:0'],
            'tim.*.bbm_tarif' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tol' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tiket' => ['nullable', 'numeric', 'min:0'],
            'tim.*.representatif' => ['nullable', 'numeric', 'min:0'],

            'tim.*.paket' => ['required', 'array', 'min:1'],
            'tim.*.paket.*.cluster' => ['required', Rule::in(['A', 'B', 'C', 'D', 'LP'])],
            'tim.*.paket.*.wilayah' => ['required', 'string', 'max:100'],
            'tim.*.paket.*.lama_hari' => ['required', 'integer', 'min:0'],
            'tim.*.paket.*.tarif_uh' => ['required', 'numeric', 'min:0'],
            'tim.*.paket.*.malam' => ['nullable', 'integer', 'min:0'],
            'tim.*.paket.*.tarif_akom' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'master_anggaran_id' => 'Sumber Dana',
            'surat_perintah_id' => 'Surat Perintah',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'uraian_sp' => 'Uraian/Maksud Perjalanan',
            'berangkat_dari' => 'Berangkat Dari',
            'tujuan' => 'Tujuan',
            'tanggal_berangkat' => 'Tanggal Berangkat',
            'tanggal_pulang' => 'Tanggal Pulang',
            'penerima_index' => 'Penerima Dana',
            'tim.*.nama' => 'Nama Anggota',
            'tim.*.paket' => 'Paket Tujuan',
            'tim.*.paket.*.cluster' => 'Cluster',
            'tim.*.paket.*.wilayah' => 'Wilayah Tujuan',
            'tim.*.paket.*.lama_hari' => 'Lama Hari',
            'tim.*.paket.*.tarif_uh' => 'Tarif Uang Harian',
        ];
    }
}
