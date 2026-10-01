<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\RekapPotensiPengembalian;
use Illuminate\Database\Eloquent\Builder;

class RekapPotensiExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return RekapPotensiTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return RekapPotensiTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return RekapPotensiPengembalian::query()->urutanBaku();
    }

    /**
     * Sisa Pengembalian IKUT diekspor walau tidak disimpan - pembaca berkas
     * membutuhkannya. Saat berkas yang sama diimpor balik, kolom itu
     * diabaikan dan dihitung ulang, jadi tidak mungkin menyimpang.
     */
    public function headings(): array
    {
        return [...RekapPotensiTemplateExport::HEADERS, 'Sisa Pengembalian'];
    }

    public function map($row): array
    {
        return [
            $row->nip,
            $row->nama,
            $row->jabatan,
            (float) $row->potensi,
            (float) $row->setoran,
            $row->keterangan,
            $row->sisa(),
        ];
    }
}
