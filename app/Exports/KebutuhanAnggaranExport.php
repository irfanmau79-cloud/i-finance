<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\KebutuhanAnggaran;
use App\Support\BidangOrganisasi;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Estimasi Kebutuhan Kegiatan Pengawasan.
 *
 * Satu baris = satu RINCIAN, bukan satu kegiatan: kolom kegiatan diulang di
 * tiap barisnya. Bentuk ini yang berguna saat menyusun pagu - rincian per
 * jenis anggota bisa langsung dijumlah dengan pivot, sedangkan bentuk satu
 * baris per kegiatan menyembunyikan angka penyusunnya. Kolom Transport hanya
 * diisi pada rincian pertama tiap kegiatan supaya tidak terhitung berkali-kali.
 *
 * Tidak ada pasangan import-nya: data ini lahir dari formulir Irban, yang
 * mengunci unit kerja ke akun pengisinya.
 */
class KebutuhanAnggaranExport implements CountsRows, FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents, WithHeadings
{
    use Exportable;
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Rekap Estimasi Kebutuhan Kegiatan Pengawasan yang diinput tiap Inspektur Pembantu di menu Analisis dan Tren. Satu baris = satu rincian (per jenis anggota); kolom kegiatan diulang di tiap barisnya. Kolom Estimasi Transport hanya terisi pada rincian pertama tiap kegiatan karena transport dihitung sekali untuk seluruh kegiatan. Berkas ini tidak untuk diunggah kembali - datanya diinput lewat formulir.';

    public const PETUNJUK = [
        ['Unit Kerja', '-', 'Teks', 'Inspektur Pembantu pengusul. Terkunci ke akun pengisi formulir.', 'Inspektur Pembantu I'],
        ['Tanggal Mulai / Tanggal Selesai', '-', 'Tanggal', 'Rentang pelaksanaan kegiatan yang direncanakan.', '2026-03-02'],
        ['Dalam PKPT', '-', 'Ya / Tidak', 'Tidak = kegiatan diusulkan di luar PKPT, penjelasannya ada di kolom Keterangan.', 'Ya'],
        ['Nomor PKPT', '-', 'Teks', 'Nomor kegiatan pada PKPT, hanya untuk kegiatan yang ada di PKPT.', '3'],
        ['Area / Jenis Kegiatan', '-', 'Teks', 'Identitas kegiatan; ikut terisi otomatis saat kegiatan PKPT dipilih.', 'Kesehatan'],
        ['Keterangan', '-', 'Teks', 'Penjelasan kegiatan di luar PKPT.', 'Pendampingan khusus'],
        ['Rincian ke', '-', 'Angka', 'Nomor urut rincian di dalam kegiatannya.', '1'],
        ['Jenis Anggota', '-', 'Teks', 'Kelompok tarif anggota tim.', 'Eselon III / Golongan IV'],
        ['Jumlah Orang', '-', 'Angka', 'Banyaknya orang pada rincian tsb.', '3'],
        ['Hari / Tarif / Jumlah UH Dalam Kota', '-', 'Angka', 'Jumlah UH Dalam Kota = Hari x Tarif.', '2'],
        ['Hari / Tarif / Jumlah UH Luar Kota', '-', 'Angka', 'Jumlah UH Luar Kota = Hari x Tarif.', '3'],
        ['Malam / Tarif / Total Akomodasi', '-', 'Angka', 'Total Akomodasi = Malam x Tarif. Tarif akomodasi boleh di luar daftar standar.', '2'],
        ['Estimasi Kebutuhan Rincian', '-', 'Angka', 'Jumlah UH Dalam Kota + Jumlah UH Luar Kota + Total Akomodasi.', '1750000'],
        ['Estimasi Transport Kegiatan', '-', 'Angka', 'BBM/TOL/Tiket, dihitung sekali per kegiatan - hanya terisi di rincian pertama.', '500000'],
        ['Total Estimasi Kegiatan', '-', 'Angka', 'Seluruh rincian kegiatan + transportnya. Diulang di tiap baris kegiatan yang sama.', '2250000'],
    ];

    /** @var array<int, KebutuhanAnggaran>|null */
    private ?array $kegiatan = null;

    public function __construct(private readonly ?int $tahun = null) {}

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public function headings(): array
    {
        return [
            'Unit Kerja', 'Tanggal Mulai', 'Tanggal Selesai', 'Dalam PKPT', 'Nomor PKPT',
            'Area Pengawasan dan Pembinaan', 'Jenis Kegiatan', 'Keterangan',
            'Rincian ke', 'Jenis Anggota', 'Jumlah Orang',
            'Hari Dalam Kota', 'Tarif UH Dalam Kota', 'Jumlah UH Dalam Kota',
            'Hari Luar Kota', 'Tarif UH Luar Kota', 'Jumlah UH Luar Kota',
            'Jumlah Malam', 'Tarif Akomodasi', 'Total Akomodasi',
            'Estimasi Kebutuhan Rincian', 'Estimasi Transport Kegiatan', 'Total Estimasi Kegiatan',
            'Diinput Oleh', 'Waktu Input',
        ];
    }

    public function jumlahBaris(): int
    {
        return array_sum(array_map(fn (KebutuhanAnggaran $k) => max(1, $k->rincian->count()), $this->kegiatan()));
    }

    public function array(): array
    {
        $baris = [];

        foreach ($this->kegiatan() as $k) {
            foreach ($k->rincian as $i => $d) {
                $baris[] = [
                    $k->unit_kerja,
                    $k->tanggal_mulai?->format('Y-m-d'),
                    $k->tanggal_selesai?->format('Y-m-d'),
                    $k->dalam_pkpt ? 'Ya' : 'Tidak',
                    $k->nomor_pkpt,
                    $k->area,
                    $k->jenis_kegiatan,
                    $k->keterangan,
                    $d->urutan,
                    $d->jenis_anggota,
                    $d->jumlah_orang,
                    $d->hari_dalam,
                    (float) $d->tarif_uh_dalam,
                    (float) $d->jumlah_uh_dalam,
                    $d->hari_luar,
                    (float) $d->tarif_uh_luar,
                    (float) $d->jumlah_uh_luar,
                    $d->jumlah_malam,
                    (float) $d->tarif_akomodasi,
                    (float) $d->total_akomodasi,
                    (float) $d->estimasi_kebutuhan,
                    // Transport hanya di baris pertama - lihat catatan kelas.
                    $i === 0 ? (float) $k->total_transport : null,
                    (float) $k->total_estimasi,
                    $k->user?->nama,
                    $k->created_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $baris;
    }

    /** @return array<int, KebutuhanAnggaran> */
    private function kegiatan(): array
    {
        return $this->kegiatan ??= KebutuhanAnggaran::query()
            ->tahun($this->tahun)
            ->with(['rincian', 'user'])
            ->get()
            ->sortBy(fn (KebutuhanAnggaran $k) => [BidangOrganisasi::urutanPkpt($k->unit_kerja), $k->created_at?->timestamp ?? 0])
            ->values()
            ->all();
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'Y';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
