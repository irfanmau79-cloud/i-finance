<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuratPerintahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_sp' => ['required', 'string', 'max:100'],
            'tanggal_sp' => ['required', 'date'],
            'unit_kerja' => ['required', 'in:Inspektur Pembantu I,Inspektur Pembantu II,Inspektur Pembantu III,Inspektur Pembantu IV,Inspektur Pembantu Investigasi,Sekretariat,Subbagian Tata Usaha'],
            'lokasi' => ['required', 'string', 'max:100'],
            'nama_pengirim' => ['required', 'string', 'max:100'],
            'tujuan_transfer' => ['required', 'string', 'max:150'],
            'irban_dibayar' => ['required', 'in:1,0'],
            'rincian_tgl_bayar' => ['required', 'string', 'max:255'],
            'keterangan' => ['required', 'string'],
            'status_sp' => ['required', 'in:Baru,Revisi'],
            'file_url' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
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
            'file_url' => 'File PDF',
        ];
    }
}
