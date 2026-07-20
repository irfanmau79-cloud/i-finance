<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Baca file upload Manajemen Data > Import SPM UP/GU atau LS. Header kolom
 * mengikuti persis output SpmUpGuExport/SpmLsExport (Prompt 09) - kolom
 * ekstra "Dibuat Oleh"/"Dibuat Pada" (audit, bukan input) diabaikan begitu
 * saja karena tidak pernah dibaca oleh SpmImport. Kolom LS-only (Sub
 * Kegiatan, Kode Rekening, Uraian Rekening, Tagging) cukup absen untuk
 * upload UP/GU.
 */
class SpmUploadImport implements ToCollection, WithHeadingRow
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
