<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\SimulasiRealisasi;
use App\Models\SimulasiRealisasiRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Export satu Simulasi Realisasi tersimpan: satu baris per mata anggaran,
 * dengan seluruh rencana bernamanya diringkas pada kolom terakhir. Bentuk ini
 * dipilih supaya berkasnya tetap bisa di-pivot per mata anggaran, sementara
 * nama tiap rencana tidak hilang.
 */
class SimulasiRealisasiExport implements FromCollection, PunyaPetunjukKolom, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use MenulisSheetPetunjuk;

    public function __construct(private SimulasiRealisasi $simulasiRealisasi) {}

    public function petunjukCatatan(): string
    {
        return 'Hasil satu Simulasi Realisasi yang tersimpan. Kolom Proyeksi bersifat PERKIRAAN - belanja yang belum terjadi dan belum tercatat di mana pun. Realisasi (Estimasi) = Realisasi + Proyeksi, yaitu perkiraan capaian sampai akhir tahun bila seluruh rencana terlaksana. Kolom Realisasi diambil dari transaksi nyata (NPD selesai + SPM LS, dikurangi pengembalian disetujui) saat berkas ini diunduh, jadi bisa berbeda bila diunduh ulang di lain waktu. Simulasi tidak pernah mengubah data anggaran maupun transaksi.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Program', '-', 'Teks', 'Program mata anggaran, sesuai kondisi saat simulasi dibuat.', '6.01 Program Penunjang Urusan Pemerintahan Daerah'],
            ['Kegiatan', '-', 'Teks', 'Kegiatan mata anggaran.', '6.01.01 Perencanaan, Penganggaran, dan Evaluasi Kinerja'],
            ['Sub Kegiatan', '-', 'Teks', 'Sub kegiatan mata anggaran.', '6.01.01.2.01 Penyusunan Dokumen Perencanaan'],
            ['Kode Rekening', '-', 'Teks', 'Kode rekening belanja.', '5.1.02.01.01.0024'],
            ['Uraian Rekening', '-', 'Teks', 'Uraian rekening tanpa kodenya.', 'Belanja Alat Tulis Kantor'],
            ['Tagging', '-', 'Teks', 'Tagging mata anggaran. Kosong berarti tanpa tagging.', 'On Call'],
            ['Pagu', '-', 'Angka', 'Pagu yang berlaku saat simulasi dibuat.', '15000000'],
            ['Realisasi', '-', 'Angka', 'Belanja yang SUDAH terjadi, dihitung saat berkas diunduh.', '4000000'],
            ['Sisa Anggaran', '-', 'Angka', 'Pagu dikurangi Realisasi: sisa menurut keadaan hari ini.', '11000000'],
            ['Proyeksi', '-', 'Angka', 'Jumlah seluruh rencana belanja pada mata anggaran ini. Belum terjadi.', '1500000'],
            ['Realisasi (Estimasi)', '-', 'Angka', 'Realisasi + Proyeksi: perkiraan capaian akhir tahun.', '5500000'],
            ['Sisa Anggaran (Estimasi)', '-', 'Angka', 'Pagu dikurangi Realisasi (Estimasi). Negatif berarti diperkirakan melebihi pagu.', '9500000'],
            ['Rincian Rencana', '-', 'Teks', 'Daftar rencana bernama beserta nominalnya, dipisah titik koma.', 'Perjalanan dinas ke Cirebon (1.000.000); Rapat koordinasi (500.000)'],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $this->tulisSheetPetunjuk($event->sheet->getDelegate(), 'Data');
        }];
    }

    public function collection(): Collection
    {
        return SimulasiRealisasiRow::lampirkanRealisasi(
            $this->simulasiRealisasi->rows()->with('items')->get()
        );
    }

    public function headings(): array
    {
        return [
            'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging',
            'Pagu', 'Realisasi', 'Sisa Anggaran', 'Proyeksi', 'Realisasi (Estimasi)', 'Sisa Anggaran (Estimasi)', 'Rincian Rencana',
        ];
    }

    public function map($row): array
    {
        $rincian = $row->items
            ->map(fn ($item) => $item->nama.' ('.number_format((float) $item->nominal, 2, ',', '.').')')
            ->implode('; ');

        return [
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $row->tagging_nama ?? '-',
            (float) $row->pagu,
            (float) $row->realisasi,
            (float) $row->sisa_anggaran,
            (float) $row->proyeksi_total,
            (float) $row->realisasi_estimasi,
            (float) $row->sisa_estimasi,
            $rincian,
        ];
    }
}
