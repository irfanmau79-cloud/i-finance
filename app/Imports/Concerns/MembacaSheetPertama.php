<?php

namespace App\Imports\Concerns;

/**
 * Batasi import hanya pada sheet PERTAMA berkas.
 *
 * Sejak setiap template/export punya sheet kedua "Petunjuk Pengisian", berkas
 * yang diunggah pengguna berisi lebih dari satu sheet. Tanpa WithMultipleSheets,
 * Maatwebsite memanggil collection() SEKALI PER SHEET pada objek yang sama,
 * sehingga `$this->rows = $rows` pada sheet terakhir menimpa data sheet
 * pertama - importer lalu membaca sheet petunjuk dan menganggap semua kolom
 * kosong.
 *
 * Mengembalikan [0 => $this] membuat pembacaan dikunci ke sheet indeks 0
 * (berdasarkan posisi, bukan nama, supaya berkas lama yang sheet-nya bernama
 * lain tetap terbaca).
 */
trait MembacaSheetPertama
{
    /** @return array<int, object> */
    public function sheets(): array
    {
        return [0 => $this];
    }
}
