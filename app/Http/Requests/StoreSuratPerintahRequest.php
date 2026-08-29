<?php

namespace App\Http\Requests;

use App\Models\SuratPerintah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input Surat Perintah, menyamakan aturan dengan prosesInputSP() di
 * CodeSuratPerintah.gs.
 *
 * Ada dua bentuk berkas yang divalidasi berbeda:
 *
 * - "Uang Harian/Akomodasi": form manual biasa. Seluruh identitas SP wajib,
 *   Komponen Pembayaran wajib minimal satu, anggota wajib minimal satu,
 *   dan PDF SP wajib diunggah.
 *
 * - "Reimburse Transportasi": WAJIB menunjuk SP induk berjenis Uang
 *   Harian/Akomodasi. Seluruh identitas dan anggotanya DISALIN dari induk di
 *   sisi server, jadi field-field itu tidak divalidasi di sini sama sekali -
 *   apa pun yang dikirim client diabaikan. Komponen dipaksa Transport dan
 *   unggahan PDF tidak wajib.
 */
class StoreSuratPerintahRequest extends FormRequest
{
    /**
     * Enam unit kerja yang boleh dipilih pada Input Surat Perintah - sama
     * persis dengan daftar di GAS. Sengaja TIDAK memakai BidangOrganisasi:
     * daftar itu punya tujuh entri karena dipakai mengelompokkan SPJ per
     * bidang, sedangkan Surat Perintah hanya diterbitkan oleh enam unit ini.
     */
    public const UNIT_KERJA = [
        'Inspektur Pembantu I',
        'Inspektur Pembantu II',
        'Inspektur Pembantu III',
        'Inspektur Pembantu IV',
        'Inspektur Pembantu Investigasi',
        'Sekretariat',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function reimburse(): bool
    {
        return $this->input('jenis_permintaan') === SuratPerintah::JENIS_REIMBURSE;
    }

    public function rules(): array
    {
        $wajibManual = $this->reimburse() ? 'nullable' : 'required';

        return [
            'website' => ['prohibited'],
            'jenis_permintaan' => ['required', Rule::in(SuratPerintah::JENIS_PERMINTAAN)],

            // Hanya diisi (dan hanya berarti) pada Reimburse Transportasi.
            'sp_induk_id' => [
                $this->reimburse() ? 'required' : 'prohibited',
                'integer',
                Rule::exists('surat_perintah', 'id')
                    ->where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN),
                Rule::unique('surat_perintah', 'sp_induk_id'),
            ],

            'nomor_sp' => [$wajibManual, 'string', 'max:100', Rule::unique('surat_perintah', 'nomor_sp')],
            'tanggal_sp' => [$wajibManual, 'date'],
            'unit_kerja' => [$wajibManual, Rule::in(self::UNIT_KERJA)],
            'lokasi' => [$wajibManual, 'string', 'max:100'],
            'nama_pengirim' => [$wajibManual, 'string', 'max:100'],
            'tujuan_transfer' => [$wajibManual, 'string', 'max:150'],
            'irban_dibayar' => [$wajibManual, 'in:1,0'],
            'rincian_tgl_bayar' => [$wajibManual, 'string', 'max:255'],
            'keterangan' => [$wajibManual, 'string'],
            'status_sp' => ['required', 'in:Baru,Revisi'],

            // Komponen Pembayaran mengisi kolom Pengajuan. Untuk Reimburse
            // nilainya dipaksa Transport di controller, jadi tidak wajib.
            'komponen' => [$this->reimburse() ? 'nullable' : 'required', 'array'],
            'komponen.*' => ['string', Rule::in(SuratPerintah::PENGAJUAN_OPTIONS)],

            // Unggahan PDF tidak wajib untuk Reimburse (lihat prosesInputSP).
            'file_url' => [$wajibManual, 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],

            'anggota' => [$this->reimburse() ? 'nullable' : 'required', 'array', 'max:'.SuratPerintah::MAKS_ANGGOTA],
            'anggota.*.nama' => ['required', 'string', 'max:255'],
            'anggota.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'anggota.*.jabatan_sp' => ['nullable', 'string', Rule::in(SuratPerintah::JABATAN_ANGGOTA)],
            'anggota.*.manual' => ['nullable', 'boolean'],
            'anggota.*.nip' => ['nullable', 'string', 'max:50'],
            'anggota.*.golongan' => ['nullable', 'string', 'max:50'],
            'anggota.*.pangkat' => ['nullable', 'string', 'max:100'],
            'anggota.*.jabatan' => ['nullable', 'string', 'max:150'],
            'anggota.*.rekening' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('jenis_permintaan')) {
            $this->merge(['jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN]);
        }

        // Kartu anggota yang benar-benar kosong dibuang sebelum validasi -
        // pengguna sering menambah baris lalu tidak mengisinya.
        if (is_array($this->input('anggota'))) {
            $bersih = array_values(array_filter(
                $this->input('anggota'),
                fn ($item) => is_array($item) && trim((string) ($item['nama'] ?? '')) !== ''
            ));

            $this->merge(['anggota' => $bersih]);
        }
    }

    public function messages(): array
    {
        return [
            'sp_induk_id.required' => 'Untuk Reimburse Transportasi, wajib memilih SP Uang Harian/Akomodasi yang telah diinput.',
            'sp_induk_id.exists' => 'SP induk harus berjenis Uang Harian/Akomodasi.',
            'sp_induk_id.unique' => 'SP induk tersebut sudah memiliki entri Reimburse Transportasi.',
            'sp_induk_id.prohibited' => 'SP induk hanya boleh diisi untuk jenis Reimburse Transportasi.',
            'nomor_sp.unique' => 'Nomor SP ini sudah terdaftar. Gunakan nomor lain.',
            'komponen.required' => 'Komponen Pembayaran wajib dipilih minimal satu.',
            'anggota.required' => 'Anggota SP wajib diisi minimal 1 orang.',
            'anggota.*.nama.required' => 'Nama anggota wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis_permintaan' => 'Jenis Permintaan Pembayaran',
            'sp_induk_id' => 'SP induk',
            'nomor_sp' => 'Nomor SP',
            'tanggal_sp' => 'Tanggal SP',
            'unit_kerja' => 'Unit Kerja',
            'lokasi' => 'Lokasi',
            'nama_pengirim' => 'Nama Pengirim',
            'tujuan_transfer' => 'Tujuan Transfer',
            'irban_dibayar' => 'Irban Dibayar',
            'rincian_tgl_bayar' => 'Rincian Tanggal Bayar',
            'keterangan' => 'Keterangan',
            'status_sp' => 'Status SP',
            'komponen' => 'Komponen Pembayaran',
            'file_url' => 'File PDF',
            'anggota' => 'Anggota SP',
            'anggota.*.nama' => 'Nama Anggota',
            'anggota.*.jabatan_sp' => 'Jabatan Dalam Tim',
        ];
    }
}
