<?php

namespace App\Http\Requests;

use App\Services\SpjBerkasService;
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

            // Hanya untuk kolom "SISA ANGGARAN" di PDF NPD - lihat
            // Npd::sisaAnggaranCetak(). Dikosongkan berarti memakai angka
            // sistem. Saat isian ini dikunci kembali, nilainya diabaikan di
            // controller, bukan ditolak, supaya formulir lama tidak gagal.
            'sisa_anggaran_manual' => ['nullable', 'numeric', 'min:0'],
            'penerima_index' => ['required', 'integer', 'min:0'],
            'keterangan_lampiran' => ['nullable', 'string'],
            // Penanda dari formulir: 'otomatis' berarti isian di atas hanya
            // pratinjau dan TIDAK disimpan - uraiannya dirangkai ulang saat
            // mencetak, mengikuti data terakhir. Formulir/permintaan lama yang
            // tidak mengirim penanda ini dianggap 'manual', supaya teks yang
            // sudah dikirim tetap tersimpan seperti dulu.
            'keterangan_mode' => ['nullable', Rule::in(['otomatis', 'manual'])],

            // Identitas anggota (nama/jabatan/nip/rekening/pegawai_id) TIDAK divalidasi/diterima
            // di sini — selalu disalin ulang dari anggota NPD induk berdasarkan urutan, supaya
            // Transport benar-benar snapshot induk, bukan input bebas.
            'tim' => ['required', 'array', 'min:1'],
            // Total Nominal BBM: yang diketik sekarang. Liter tidak lagi diketik -
            // dihitung dari nominal dibagi tarif, jadi tarifnya wajib ada.
            'tim.*.bbm_nominal' => ['nullable', 'numeric', 'min:0', function (string $atribut, mixed $nilai, \Closure $gagal) {
                $tarif = $this->input(str_replace('bbm_nominal', 'bbm_tarif', $atribut));

                if ((float) $nilai > 0 && (float) $tarif <= 0) {
                    $gagal('Tarif BBM per liter wajib diisi bila Total Nominal BBM diisi.');
                }
            }],
            // Liter hanya diterima untuk permintaan cara lama (tanpa bbm_nominal).
            'tim.*.bbm_liter' => ['nullable', 'numeric', 'min:0'],
            'tim.*.bbm_tarif' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tol' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tiket' => ['nullable', 'numeric', 'min:0'],
            'tim.*.representatif' => ['nullable', 'numeric', 'min:0'],
            // Unggah SPJ - OPSIONAL, lihat App\Services\SpjBerkasService.
            // Aturannya diambil dari service supaya kelima jenis NPD dan
            // pintu unggah di Inventarisasi SPJ tidak bisa berbeda-beda.
            ...SpjBerkasService::aturan(),
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
            'sisa_anggaran_manual' => 'Sisa Anggaran (cetak PDF)',
            'penerima_index' => 'Penerima Dana',
            ...SpjBerkasService::labelIsian(),
        ];
    }
}
