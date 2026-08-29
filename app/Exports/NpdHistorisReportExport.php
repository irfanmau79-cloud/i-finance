<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\NpdHistorisImport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class NpdHistorisReportExport implements FromCollection, PunyaPetunjukKolom, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use MenulisSheetPetunjuk;

    public function petunjukCatatan(): string
    {
        return 'Laporan hasil Import NPD Historis. Mode "validation" berisi hasil pemeriksaan SEBELUM disimpan, mode "final" berisi hasil setelah dikonfirmasi. Berkas ini murni laporan - tidak bisa diunggah kembali sebagai data. Pakai kolom Hasil dan Pesan untuk menelusuri baris yang perlu diperbaiki di berkas sumber, lalu ulangi import.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Batch', '-', 'Teks/Angka', 'Nomor batch import berkas ini.', '12'],
            ['Baris Sumber', '-', 'Teks/Angka', 'Nomor baris pada berkas Excel yang diunggah.', '5'],
            ['Hasil', '-', 'Teks/Angka', 'valid, warning, error, atau duplicate. Hanya valid dan warning yang tersimpan saat dikonfirmasi.', 'warning'],
            ['Pesan', '-', 'Teks/Angka', 'Seluruh catatan validasi baris ini, dipisahkan tanda |.', 'Penerima tidak ada di master; snapshot dipakai.'],
            ['Tanggal', '-', 'Teks/Angka', 'Tanggal NPD hasil pembacaan.', '2026-07-15'],
            ['Tahun', '-', 'Teks/Angka', 'Tahun anggaran, diturunkan dari Tanggal NPD.', '2026'],
            ['Bulan', '-', 'Teks/Angka', 'Bulan realisasi, diturunkan dari Tanggal NPD.', '7'],
            ['Nomor NPD', '-', 'Teks/Angka', 'Nomor dokumen dari berkas.', '001/NPD/HIST/2026'],
            ['Jenis Input', '-', 'Teks/Angka', 'Jenis NPD sebagaimana ditulis di berkas.', 'Barang/Jasa'],
            ['Jenis Kode', '-', 'Teks/Angka', 'Kode jenis hasil pemetaan sistem.', 'bj'],
            ['Program', '-', 'Teks/Angka', 'Program mata anggaran hasil pemetaan.', '6.01 Program Penunjang Urusan'],
            ['Kegiatan', '-', 'Teks/Angka', 'Kegiatan mata anggaran hasil pemetaan.', '6.01.01 Perencanaan dan Evaluasi'],
            ['Sub Kegiatan', '-', 'Teks/Angka', 'Sub kegiatan sebagaimana dibaca dari berkas.', '6.01.01.2.01 Penyusunan Dokumen'],
            ['Kode Rekening', '-', 'Teks/Angka', 'Kode rekening sebagaimana dibaca dari berkas.', '5.1.02.01.01.0024'],
            ['Tagging', '-', 'Teks/Angka', 'Tagging dari berkas. Kosong berarti tanpa tagging.', 'Rutin'],
            ['Penerima', '-', 'Teks/Angka', 'Nama penerima dari berkas.', 'CV Sumber Rejeki'],
            ['Penerima Manual', '-', 'Teks/Angka', 'Ya bila penerima tidak ditemukan di master dan dipakai sebagai snapshot.', 'Ya'],
            ['Nominal Bruto', '-', 'Teks/Angka', 'Nilai bruto dokumen.', '1000000'],
            ['PPN', '-', 'Teks/Angka', 'PPN yang termasuk di dalam bruto.', '100000'],
            ['PPh1', '-', 'Teks/Angka', 'Nominal potongan PPh pertama.', '50000'],
            ['Jenis PPh1', '-', 'Teks/Angka', 'Jenis potongan PPh pertama.', 'PPh 21'],
            ['PPh2', '-', 'Teks/Angka', 'Nominal potongan PPh kedua.', '25000'],
            ['Jenis PPh2', '-', 'Teks/Angka', 'Jenis potongan PPh kedua.', 'PPh 22'],
            ['Status', '-', 'Teks/Angka', 'Status akhir yang akan disimpan untuk NPD ini.', 'Selesai'],
            ['Pagu', '-', 'Teks/Angka', 'Pagu mata anggaran tujuan saat validasi dijalankan.', '15000000'],
            ['RAK Bulan', '-', 'Teks/Angka', 'Target RAK bulan tersebut. Kosong bila RAK belum tersedia.', '2500000'],
            ['Realisasi Sebelum', '-', 'Teks/Angka', 'Realisasi mata anggaran sebelum baris ini diperhitungkan.', '4000000'],
            ['Realisasi Proyeksi', '-', 'Teks/Angka', 'Realisasi setelah baris ini ikut dihitung.', '5000000'],
            ['Sisa Proyeksi', '-', 'Teks/Angka', 'Sisa tersedia setelah baris ini ikut dihitung. Negatif berarti melampaui pagu.', '10000000'],
            ['Mapping', '-', 'Teks/Angka', 'Status pemetaan ke mata anggaran: berhasil, ambigu, atau gagal.', 'exact'],
            ['NPD ID', '-', 'Teks/Angka', 'ID NPD yang terbentuk. Hanya terisi pada laporan final setelah konfirmasi.', '318'],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $this->tulisSheetPetunjuk($event->sheet->getDelegate(), 'Data');
        }];
    }

    public function __construct(private readonly NpdHistorisImport $import, private readonly string $mode) {}

    public function collection()
    {
        return $this->import->baris()->orderBy('nomor_baris')->get();
    }

    public function headings(): array
    {
        return ['Batch', 'Baris Sumber', 'Hasil', 'Pesan', 'Tanggal', 'Tahun', 'Bulan', 'Nomor NPD', 'Jenis Input', 'Jenis Kode', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Tagging', 'Penerima', 'Penerima Manual', 'Nominal Bruto', 'PPN', 'PPh1', 'Jenis PPh1', 'PPh2', 'Jenis PPh2', 'Status', 'Pagu', 'RAK Bulan', 'Realisasi Sebelum', 'Realisasi Proyeksi', 'Sisa Proyeksi', 'Mapping', 'NPD ID'];
    }

    public function map($row): array
    {
        return [$this->import->id, $row->nomor_baris, $row->hasil, implode(' | ', $row->pesan ?? []), optional($row->tanggal_npd)->format('Y-m-d'), $row->tahun, $row->bulan, $row->nomor_npd, $row->jenis_input, $row->jenis_kode, $row->program, $row->kegiatan, $row->sub_kegiatan, $row->kode_rekening, $row->tagging_nama, $row->penerima, $row->penerima_manual ? 'Ya' : 'Tidak', (float) $row->nominal_bruto, (float) $row->ppn, (float) $row->pph1, $row->jenis_pph1, (float) $row->pph2, $row->jenis_pph2, $row->status_target, (float) $row->pagu, (float) $row->rak_bulan, (float) $row->realisasi_sebelum, (float) $row->realisasi_proyeksi, (float) $row->sisa_proyeksi, $row->mapping_status, $this->mode === 'final' ? $row->npd_id : null];
    }
}
