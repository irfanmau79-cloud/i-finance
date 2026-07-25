<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePengajuanTunjanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "Dapat Tunjangan?"/"Perpanjangan Kuliah?" adalah <select> (selalu
     * terkirim, defaultnya "Tidak"/0) — bukan checkbox yang absen kalau
     * tidak dicentang. Tanpa ini, kartu anak yang sengaja dikosongkan
     * (mis. Anak Ke-2 saat hanya Anak Ke-1 diisi) tetap dianggap "ada
     * isinya" oleh required_with di rules() karena field status selalu
     * bernilai "0", bukan benar-benar kosong.
     */
    protected function prepareForValidation(): void
    {
        $anak = collect($this->input('anak', []))
            ->filter(fn ($a) => filled($a['nama'] ?? null) || filled($a['tanggal_lahir'] ?? null) || filled($a['keterangan'] ?? null))
            ->values()->all();

        $this->merge(['anak' => $anak]);
    }

    public function rules(): array
    {
        return [
            'website' => ['prohibited'],
            'nama_pegawai' => ['required', 'string', 'max:150'],
            'nip' => ['nullable', 'string', 'max:30'],
            'keterangan' => ['required', 'string', 'min:10', 'max:2000'],
            'pasangan.nama' => ['nullable', 'string', 'max:150'],
            'pasangan.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'pasangan.status_tunjangan' => ['nullable', 'boolean'],
            'anak' => ['nullable', 'array', 'max:10'],
            'anak.*.nama' => ['required_with:anak.*.tanggal_lahir,anak.*.status_tunjangan', 'nullable', 'string', 'max:150'],
            'anak.*.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'anak.*.status_tunjangan' => ['nullable', 'boolean'],
            'anak.*.perpanjangan_kuliah' => ['nullable', 'boolean'],
            'anak.*.keterangan' => ['nullable', 'string', 'max:500'],
            'lampiran' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $aktif = collect($this->input('anak', []))->filter(fn ($anak) => filter_var($anak['status_tunjangan'] ?? false, FILTER_VALIDATE_BOOL))->count();
            if ($aktif > 2) {
                $validator->errors()->add('anak', 'Maksimal dua anak dapat diajukan sebagai penerima tunjangan.');
            }
        }];
    }
}
