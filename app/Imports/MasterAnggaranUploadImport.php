<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Baca file upload Manajemen Data > Import Pagu/Master Anggaran. Header
 * kolom mengikuti persis output MasterAnggaranExport (Prompt 09): Program,
 * Kegiatan, Sub Kegiatan, Kode Rekening, Uraian Rekening, Tagging, Pagu,
 * Aktif - supaya export bisa dipakai langsung sebagai template import.
 * WithHeadingRow men-slug header ("Kode Rekening" -> "kode_rekening") jadi
 * key array yang bisa langsung dipakai tanpa peta kolom manual.
 */
class MasterAnggaranUploadImport implements ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function __construct()
    {
        $this->rows = new Collection();
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
