<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterDataImport implements WithMultipleSheets
{
    public RawSheetImport $masterAnggaran;

    public RawSheetImport $pegawai;

    public RawSheetImport $dataTambahan;

    public function __construct()
    {
        // Header baris gabungan (merge) 2 baris, data mulai baris ke-3.
        $this->masterAnggaran = new RawSheetImport(3);

        // Header 1 baris, data mulai baris ke-2.
        $this->pegawai = new RawSheetImport(2);

        // Header 1 baris, data mulai baris ke-2.
        $this->dataTambahan = new RawSheetImport(2);
    }

    public function sheets(): array
    {
        return [
            'Master Anggaran' => $this->masterAnggaran,
            'Nama Pegawai' => $this->pegawai,
            'Data Tambahan' => $this->dataTambahan,
        ];
    }
}
