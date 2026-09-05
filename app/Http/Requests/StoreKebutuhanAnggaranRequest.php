<?php

namespace App\Http\Requests;

use App\Support\BidangOrganisasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Isian formulir Estimasi Kebutuhan Kegiatan Pengawasan.
 *
 * Unit kerja SENGAJA tidak ada di sini: unitnya ditentukan role penyimpan,
 * bukan isian - lihat KebutuhanController::store. Angka turunan (jumlah UH,
 * total akomodasi, total estimasi) juga tidak diterima dari klien; semuanya
 * dihitung ulang di KebutuhanAnggaranService.
 */
class StoreKebutuhanAnggaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BidangOrganisasi::unitRole($this->user()?->role) !== null;
    }

    public function rules(): array
    {
        return [
            'kegiatan' => ['required', 'array', 'min:1', 'max:50'],
            'kegiatan.*.luar_pkpt' => ['nullable', 'boolean'],
            'kegiatan.*.nomor_pkpt' => ['nullable', 'string', 'max:50'],
            'kegiatan.*.area' => ['nullable', 'string', 'max:255'],
            'kegiatan.*.jenis_kegiatan' => ['nullable', 'string', 'max:255'],
            'kegiatan.*.keterangan' => ['nullable', 'string', 'max:2000'],
            'kegiatan.*.tanggal_mulai' => ['required', 'date'],
            'kegiatan.*.tanggal_selesai' => ['required', 'date', 'after_or_equal:kegiatan.*.tanggal_mulai'],
            'kegiatan.*.total_transport' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],

            'kegiatan.*.rincian' => ['required', 'array', 'min:1', 'max:50'],
            'kegiatan.*.rincian.*.jenis_anggota' => ['required', 'string', Rule::in(config('kebutuhan.jenis_anggota'))],
            'kegiatan.*.rincian.*.jumlah_orang' => ['required', 'integer', 'min:1', 'max:999'],
            'kegiatan.*.rincian.*.hari_dalam' => ['nullable', 'integer', 'min:0', 'max:366'],
            'kegiatan.*.rincian.*.hari_luar' => ['nullable', 'integer', 'min:0', 'max:366'],
            'kegiatan.*.rincian.*.jumlah_malam' => ['nullable', 'integer', 'min:0', 'max:366'],
            // Tarif uang harian adalah standar biaya - hanya nilai dari daftar
            // yang diterima, termasuk 0 untuk komponen yang tidak dipakai.
            'kegiatan.*.rincian.*.tarif_uh_dalam' => ['nullable', 'numeric', Rule::in([0, ...config('kebutuhan.tarif_uh_dalam')])],
            'kegiatan.*.rincian.*.tarif_uh_luar' => ['nullable', 'numeric', Rule::in([0, ...config('kebutuhan.tarif_uh_luar')])],
            // Akomodasi boleh di luar daftar: menginap dengan tarif lain memang terjadi.
            'kegiatan.*.rincian.*.tarif_akomodasi' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('kegiatan', []) as $i => $kegiatan) {
                $nomor = $i + 1;
                $luarPkpt = (bool) ($kegiatan['luar_pkpt'] ?? false);

                if ($luarPkpt && trim((string) ($kegiatan['keterangan'] ?? '')) === '') {
                    $validator->errors()->add(
                        "kegiatan.{$i}.keterangan",
                        "Kegiatan {$nomor}: isi Keterangan Kegiatan karena kegiatan ini di luar PKPT."
                    );
                }

                if (! $luarPkpt
                    && trim((string) ($kegiatan['nomor_pkpt'] ?? '')) === ''
                    && trim((string) ($kegiatan['area'] ?? '')) === '') {
                    $validator->errors()->add(
                        "kegiatan.{$i}.nomor_pkpt",
                        "Kegiatan {$nomor}: pilih kegiatan PKPT, atau centang bahwa kegiatan ini tidak ada dalam PKPT."
                    );
                }

                // Satu rincian yang seluruh komponennya nol tidak menambah apa
                // pun ke usulan - biasanya baris yang lupa diisi, bukan maksud.
                foreach (array_values($kegiatan['rincian'] ?? []) as $r => $rincian) {
                    $adaIsi = (int) ($rincian['hari_dalam'] ?? 0) > 0
                        || (int) ($rincian['hari_luar'] ?? 0) > 0
                        || (int) ($rincian['jumlah_malam'] ?? 0) > 0;

                    if (! $adaIsi) {
                        $validator->errors()->add(
                            "kegiatan.{$i}.rincian.{$r}.hari_dalam",
                            "Kegiatan {$nomor} rincian ".($r + 1).': isi jumlah hari atau jumlah malamnya - rincian bernilai nol tidak bisa disimpan.'
                        );
                    }
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'kegiatan' => 'Kegiatan',
            'kegiatan.*.tanggal_mulai' => 'Tanggal Mulai',
            'kegiatan.*.tanggal_selesai' => 'Tanggal Selesai',
            'kegiatan.*.rincian' => 'Rincian Estimasi Anggaran',
            'kegiatan.*.rincian.*.jenis_anggota' => 'Jenis Anggota',
            'kegiatan.*.rincian.*.jumlah_orang' => 'Jumlah Orang',
        ];
    }
}
