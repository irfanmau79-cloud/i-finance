<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\SimulasiAnggaran;
use App\Models\SimulasiAnggaranRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Export satu Simulasi Pergeseran/Perubahan Anggaran tersimpan. Sumbernya
 * koleksi baris yang sudah dimuat (bukan query - simulasi cuma berisi
 * mata anggaran aktif saat dibuat, jumlahnya kecil), jadi tidak perlu
 * DataManagementExport (yang berbasis FromQuery+chunk untuk tabel besar).
 */
class SimulasiAnggaranExport implements FromCollection, PunyaPetunjukKolom, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use MenulisSheetPetunjuk;

    public function petunjukCatatan(): string
    {
        return 'Hasil satu Simulasi Pergeseran/Perubahan Anggaran yang tersimpan. Angka pada kolom Anggaran (Simulasi) bersifat RENCANA - simulasi tidak pernah mengubah pagu yang berlaku. Untuk benar-benar memberlakukan pergeseran, buat versi pagu baru lewat Import Pagu lalu aktifkan di halaman Versi Pagu. Kolom Realisasi diambil dari transaksi nyata saat berkas ini diunduh, jadi bisa berbeda bila diunduh ulang di lain waktu.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Program', '-', 'Teks', 'Program mata anggaran, sesuai kondisi saat simulasi dibuat.', '6.01 Program Penunjang Urusan Pemerintahan Daerah'],
            ['Kegiatan', '-', 'Teks', 'Kegiatan mata anggaran.', '6.01.01 Perencanaan, Penganggaran, dan Evaluasi Kinerja'],
            ['Sub Kegiatan', '-', 'Teks', 'Sub kegiatan mata anggaran.', '6.01.01.2.01 Penyusunan Dokumen Perencanaan'],
            ['Kode Rekening', '-', 'Teks', 'Kode rekening belanja.', '5.1.02.01.01.0024'],
            ['Uraian Rekening', '-', 'Teks', 'Uraian rekening tanpa kodenya.', 'Belanja Alat Tulis Kantor'],
            ['Tagging', '-', 'Teks', 'Tagging mata anggaran. Kosong berarti tanpa tagging.', 'Rutin'],
            ['Anggaran (Eksisting)', '-', 'Angka', 'Pagu yang berlaku saat simulasi dibuat.', '15000000'],
            ['Realisasi', '-', 'Angka', 'Realisasi aktual mata anggaran ini (NPD selesai + SPM LS), dihitung saat berkas diunduh.', '4000000'],
            ['Anggaran (Simulasi)', '-', 'Angka', 'Pagu usulan pada simulasi ini. Belum berlaku.', '18500000'],
            ['Selisih', '-', 'Angka', 'Anggaran (Simulasi) dikurangi Anggaran (Eksisting). Positif berarti bertambah, negatif berarti bergeser keluar.', '3500000'],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $this->tulisSheetPetunjuk($event->sheet->getDelegate(), 'Data');
        }];
    }

    public function __construct(private SimulasiAnggaran $simulasiAnggaran) {}

    public function collection(): Collection
    {
        return SimulasiAnggaranRow::lampirkanRealisasi($this->simulasiAnggaran->rows);
    }

    public function headings(): array
    {
        return ['Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Anggaran (Eksisting)', 'Realisasi', 'Anggaran (Simulasi)', 'Selisih'];
    }

    public function map($row): array
    {
        return [
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $row->tagging_nama ?? '-',
            (float) $row->pagu_eksisting,
            (float) $row->realisasi,
            (float) $row->pagu_simulasi,
            (float) $row->selisih,
        ];
    }
}
