<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Header saja - kolomnya sama persis dengan PkptExport/PkptUploadImport. */
class PkptTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Program Kerja Pengawasan Tahunan (PKPT) yang dipantau di menu Analisis dan Tren > Monitoring PKPT, sekaligus menjadi sumber daftar kegiatan pada modul Estimasi Kebutuhan Kegiatan Pengawasan. Satu baris = satu kegiatan pengawasan. Baris dikenali dari gabungan Tahun (dipilih di formulir import) + Unit Kerja + Nomor: kombinasi yang sudah ada akan DIPERBARUI, yang belum ada dibuat baru.';

    public const PETUNJUK = [
        ['Nomor', 'Ya', 'Teks', 'Nomor kegiatan pada dokumen PKPT. Boleh bukan angka murni (mis. "1-IRB1"). Hanya perlu unik di dalam satu Unit Kerja - nomor yang sama di unit berbeda tidak dianggap ganda.', '1'],
        ['Unit Kerja', 'Ya', 'Teks', 'Unit pelaksana. Ejaan dibakukan otomatis selama angka unitnya ditulis ROMAWI: "Irban I", "IRBAN III", dan "Inspektur Pembantu I" sama-sama tersimpan sebagai "Inspektur Pembantu I"; "Investigasi" jadi "Inspektur Pembantu Investigasi". Ejaan yang tidak dikenali disimpan apa adanya dan unitnya ditaruh di urutan terakhir.', 'Inspektur Pembantu I'],
        ['Area Pengawasan dan Pembinaan', 'Ya*', 'Teks', 'Area pengawasan sesuai dokumen PKPT. *Wajib bersama Jenis Kegiatan: baris yang kedua kolomnya kosong dianggap baris penyekat dan ditolak.', 'Kesehatan'],
        ['Jenis Kegiatan', 'Ya*', 'Teks', 'Jenis kegiatan pengawasan. *Lihat catatan Area di atas.', 'Audit Kinerja'],
        ['Tujuan dan Sasaran', 'Tidak', 'Teks', 'Tujuan atau sasaran kegiatan. Tampil saat baris di tabel Monitoring PKPT diklik.', 'Menilai efektivitas pelayanan'],
        ['Ruang Lingkup', 'Tidak', 'Teks', 'Ruang lingkup kegiatan. Tampil bersama Tujuan saat baris diklik.', 'Tahun Anggaran berjalan'],
        ['Jumlah Tim', 'Tidak', 'Teks', 'Banyaknya tim yang diturunkan. Ditulis apa adanya.', '2'],
        ['Estimasi Anggaran', 'Tidak', 'Angka', 'Estimasi anggaran kegiatan. Boleh diketik "1.250.000" atau 1250000; sel kosong dihitung 0.', '1250000'],
        ['Realisasi', 'Tidak', 'Angka', 'Realisasi anggaran kegiatan. Selisihnya dengan Estimasi menjadi kartu "Estimasi Anggaran PKPT Belum Terealisasi".', '900000'],
        ['Rencana Pelaksanaan', 'Tidak', 'Teks', 'Periode rencana pelaksanaan. Menjadi isi filter Periode, yang diurutkan Januari s.d. Desember - jadi sebut nama bulannya.', 'Maret'],
        ['Pelaksanaan', 'Tidak', 'Teks', 'Periode pelaksanaan yang benar-benar terjadi.', 'April'],
        ['Jumlah Laporan', 'Tidak', 'Teks', 'Banyaknya laporan hasil pengawasan.', '1'],
        ['Terlaksana', 'Tidak', 'Ya / Tidak', 'Status pelaksanaan. Diisi Ya/TRUE/1 untuk terlaksana; selain itu dianggap belum. Kolom inilah yang menghitung persentase capaian per unit.', 'Ya'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = [
        'Nomor', 'Unit Kerja', 'Area Pengawasan dan Pembinaan', 'Jenis Kegiatan',
        'Tujuan dan Sasaran', 'Ruang Lingkup', 'Jumlah Tim', 'Estimasi Anggaran',
        'Realisasi', 'Rencana Pelaksanaan', 'Pelaksanaan', 'Jumlah Laporan', 'Terlaksana',
    ];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'M';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
