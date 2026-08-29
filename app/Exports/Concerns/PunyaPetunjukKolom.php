<?php

namespace App\Exports\Concerns;

/**
 * Export/template yang menyertakan sheet "Petunjuk Pengisian" berisi
 * penjelasan tiap kolom beserta contoh isinya.
 *
 * Sheet petunjuk ditulis oleh MenulisSheetPetunjuk sebagai worksheet KEDUA
 * pada workbook yang sama, jadi berkas tetap satu file dan sheet data tetap
 * di urutan pertama - importer hanya membaca sheet pertama, sehingga sheet
 * petunjuk tidak pernah ikut terbaca sebagai data.
 */
interface PunyaPetunjukKolom
{
    /**
     * Satu baris per kolom pada sheet data, URUT sesuai headings().
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     *                                                                                  kolom, wajib, format, penjelasan, contoh
     */
    public function petunjukKolom(): array;

    /** Kalimat pembuka di atas tabel petunjuk. Boleh berisi beberapa kalimat. */
    public function petunjukCatatan(): string;
}
