<?php

namespace App\Imports;

use App\Imports\Concerns\MembacaSheetPertama;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Baca file upload Manajemen Data > Import Pagu/Master Anggaran. Header
 * kolom mengikuti persis output MasterAnggaranExport (lihat
 * MasterAnggaranImport::KOLOM): Tahun, Kode Program, Program, Kode Kegiatan,
 * Kegiatan, Kode Sub Kegiatan, Sub Kegiatan, Kode Rekening, Rekening,
 * Tagging, Pagu, Aktif/Non Aktif - supaya export bisa dipakai langsung
 * sebagai template import.
 *
 * WithHeadingRow men-slug header jadi key array ("Kode Sub Kegiatan" ->
 * "kode_sub_kegiatan"; garis miring pada "Aktif/Non Aktif" dibuang tanpa
 * pemisah sehingga menjadi "aktifnon_aktif"), jadi tidak
 * perlu peta kolom manual. Pemetaan ke bentuk yang dipakai aturan bisnis
 * (termasuk toleransi file format lama yang masih menggabungkan kode dan
 * nama dalam satu sel) ada di MasterAnggaranImport::petakanKolom().
 */
class MasterAnggaranUploadImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    use MembacaSheetPertama;

    public Collection $rows;

    public function __construct()
    {
        $this->rows = new Collection;
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
