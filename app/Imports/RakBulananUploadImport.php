<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Baca file upload Manajemen Data > Import RAK Bulanan. Header mengikuti
 * persis output RakBulananExport (Prompt 12): Sub Kegiatan, Kode Rekening,
 * Uraian Rekening, Tagging, Pagu, lalu Januari..Desember. Format LEBAR
 * (satu baris per mata anggaran) - RakBulananImport yang meng-explode-nya
 * menjadi baris staging per bulan. Kolom Uraian Rekening dan Pagu murni
 * informasi referensi, tidak dibaca untuk pencocokan/validasi.
 */
class RakBulananUploadImport implements ToCollection, WithHeadingRow
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
