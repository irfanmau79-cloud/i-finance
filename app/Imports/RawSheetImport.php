<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Pembaca sheet mentah. Dipakai dua peran: berdiri sendiri lewat
 * Excel::import(), dan sebagai sub-importer per-nama-sheet di
 * MasterDataImport. Karena peran kedua itu kelas ini TIDAK boleh mendeklarasi
 * sheets() sendiri (lihat Concerns\MembacaSheetPertama) - pembatasan ke sheet
 * pertama ditegakkan lewat penjaga di collection().
 */
class RawSheetImport implements ToCollection, WithStartRow
{
    public Collection $rows;

    /**
     * Sejak template punya sheet kedua "Petunjuk Pengisian", Maatwebsite
     * memanggil collection() sekali per sheet pada objek yang sama. Tanpa
     * penjaga ini sheet terakhir menimpa sheet pertama dan importer membaca
     * lembar petunjuk, bukan datanya.
     */
    private bool $sudahTerisi = false;

    public function __construct(private readonly int $startRowNumber)
    {
        $this->rows = new Collection;
    }

    public function startRow(): int
    {
        return $this->startRowNumber;
    }

    public function collection(Collection $rows): void
    {
        if ($this->sudahTerisi) {
            return;
        }

        $this->sudahTerisi = true;
        $this->rows = $rows;
    }
}
