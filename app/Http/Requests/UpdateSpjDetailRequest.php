<?php

namespace App\Http\Requests;

use App\Models\BantexSpj;
use App\Models\SpjDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yang boleh diubah Pengelola SPJ dari Tabel Rincian SPJ hanya tiga: Lokasi
 * Penyimpanan, Status SPJ, dan Catatan. Kolom lain (Bulan, Nomor SP, Nominal,
 * Koordinator, Bidang, Uraian) sekarang MURNI hasil hitung dari data NPD dan
 * ditampilkan baca-saja, jadi tidak lagi diterima dari formulir.
 */
class UpdateSpjDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Lokasi disimpan sebagai label bernomor ("07 - PDTT Irban II"),
            // jadi dicocokkan ke daftar label bantex aktif, bukan ke kolom nama.
            'lokasi' => ['nullable', 'string', 'max:100', Rule::in(
                BantexSpj::query()->where('aktif', true)->get()->map->label()->all()
            )],
            'status' => ['required', Rule::in(array_keys(SpjDetail::STATUS))],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lokasi' => 'Lokasi Penyimpanan',
            'status' => 'Status SPJ',
            'catatan' => 'Catatan',
        ];
    }
}
