<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\MasterAnggaran;
use App\Models\RakBulanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Template RAK Bulanan: satu baris per KOMBINASI Sub Kegiatan + Kode
 * Rekening AKTIF (BUKAN per Tagging - lihat Prompt 12A), kolom
 * Januari..Desember berisi target BULANAN (bukan kumulatif - lihat
 * dokumentasi di migration create_rak_bulanan_table). Sel bulan yang belum
 * punya RAK dibiarkan KOSONG (null) - tidak pernah diisi pagu/12 - supaya
 * kekosongan tetap terlihat jelas sebagai "RAK belum tersedia" saat file
 * ini dibuka maupun saat diupload kembali sebagai template import.
 *
 * Kode dan nama berada di kolom terpisah, mengikuti template Pagu/Master
 * Anggaran. Kolom "Total RAK" adalah RUMUS =SUM(Januari:Desember) yang ikut
 * berubah saat sel bulan diketik, jadi berperan sebagai penjumlah otomatis -
 * bukan angka yang perlu diisi manual. Importer mengabaikan kolom ini.
 *
 * Header berada di BARIS 1 - tidak ada lagi baris marker/instruksi di
 * atasnya. Sebagai gantinya, format resmi dikenali importer dari tanda
 * tangan headernya (kolom "Kode Sub Kegiatan" + "Total RAK"); lihat
 * RakBulananImport::normalisasiWorkbook(). FORMAT_MARKER dipertahankan
 * HANYA untuk membaca berkas yang terlanjur diunduh dengan format lama.
 *
 * TIDAK ADA kolom Tagging. Satu Sub Kegiatan + Kode Rekening bisa punya
 * banyak baris master_anggaran (satu per Tagging, untuk dana terikat/
 * realisasi per NPD) - export ini menggabungkannya jadi SATU baris supaya
 * total RAK tahunan tidak tergandakan.
 */
class RakBulananExport extends DataManagementExport implements PunyaPetunjukKolom
{
    /**
     * Marker versi lama. TIDAK lagi ditulis ke berkas baru - hanya dipakai
     * importer supaya berkas yang sudah terlanjur diunduh tetap terbaca.
     */
    public const FORMAT_MARKER = 'IFINANCE_RAK_BULANAN_MONTHLY_V2';

    private const BULAN_LABEL = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Header di baris 1, jadi data mulai baris 2. */
    private const BARIS_DATA_PERTAMA = 2;

    /** 10 kolom identitas + 12 bulan = kolom terakhir V. */
    private const KOLOM_TERAKHIR = 'V';

    private const KOLOM_TOTAL = 'J';

    private const KOLOM_BULAN_PERTAMA = 'K';

    /** Kolom kode: harus teks supaya "5.1.02.01.01.0024" tidak dibaca sebagai angka/tanggal. */
    private const KOLOM_KODE = ['B', 'D', 'F', 'H'];

    /** @var array<int, int> id master_anggaran wakil (satu per kombinasi Sub Kegiatan+Kode Rekening) */
    private array $idWakil;

    /** @var Collection<string, Collection> RAK bulan tahun ini, key = "sub_kegiatan_kunci|kode_rekening" */
    private Collection $rakByKunci;

    public function __construct(private readonly int $tahun)
    {
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        if ($tahun !== $tahunAktif) {
            throw new InvalidArgumentException("Template RAK Bulanan hanya tersedia untuk Tahun Anggaran {$tahunAktif}.");
        }

        $this->idWakil = MasterAnggaran::query()
            ->selectRaw('MIN(id) as id_wakil')
            ->where('aktif', true)
            ->groupBy('sub_kegiatan_kunci', 'kode_rekening_bersih')
            ->pluck('id_wakil')
            ->all();

        $this->rakByKunci = RakBulanan::where('tahun', $tahun)
            ->get()
            ->groupBy(fn ($rak) => $rak->sub_kegiatan_kunci.'|'.$rak->kode_rekening);
    }

    public function query(): Builder
    {
        return MasterAnggaran::query()
            ->whereIn('id', $this->idWakil)
            ->orderBy('kode_sub_kegiatan')
            ->orderBy('kode_rekening_bersih');
    }

    public function headings(): array
    {
        return array_merge(
            [
                'Tahun', 'Kode Program', 'Program', 'Kode Kegiatan', 'Kegiatan',
                'Kode Sub Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Rekening', 'Total RAK',
            ],
            array_values(self::BULAN_LABEL)
        );
    }

    protected function siapkanSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $akhir = self::KOLOM_TERAKHIR;
        $total = self::KOLOM_TOTAL;
        $mulai = self::BARIS_DATA_PERTAMA;

        $sheet->getStyle("A1:{$akhir}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$akhir}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$akhir}1");

        $barisTerakhir = max($sheet->getHighestRow(), $mulai - 1);

        if ($barisTerakhir < $mulai) {
            return; // tidak ada mata anggaran aktif - hanya header
        }

        foreach (self::KOLOM_KODE as $kolom) {
            $sheet->getStyle("{$kolom}{$mulai}:{$kolom}{$barisTerakhir}")
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Total RAK menjumlah sendiri kolom bulan pada barisnya, jadi hasil
        // pembagian ke 12 bulan langsung terlihat sambil diketik.
        for ($baris = $mulai; $baris <= $barisTerakhir; $baris++) {
            $sheet->setCellValue(
                $total.$baris,
                '=SUM('.self::KOLOM_BULAN_PERTAMA.$baris.':'.$akhir.$baris.')'
            );
        }

        $sheet->getStyle("{$total}{$mulai}:{$akhir}{$barisTerakhir}")
            ->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("{$total}1:{$total}{$barisTerakhir}")->getFont()->setBold(true);
    }

    public function petunjukCatatan(): string
    {
        return 'Satu baris mewakili satu kombinasi Sub Kegiatan + Kode Rekening. Nilai Januari-Desember adalah alokasi BULANAN, bukan kumulatif: isi berapa yang direncanakan cair pada bulan itu saja. Bulan yang belum direncanakan biarkan KOSONG - jangan diisi nol, karena kosong berarti "RAK belum tersedia" sedangkan nol berarti "memang tidak dianggarkan bulan itu". Baris yang Sub Kegiatan + Kode Rekening-nya tidak cocok dengan mata anggaran aktif akan ditolak, bukan membuat mata anggaran baru. RAK tidak dibedakan per Tagging.';
    }

    public function petunjukKolom(): array
    {
        $petunjuk = [
            ['Tahun', 'Ya', 'Angka 4 digit', 'Tahun anggaran baris ini. Harus sama dengan tahun yang dipilih saat upload.', (string) $this->tahun],
            ['Kode Program', 'Tidak', 'Teks', 'Kode program. Hanya referensi, tidak dipakai mencocokkan.', '6.01'],
            ['Program', 'Tidak', 'Teks', 'Nama program tanpa kodenya. Hanya referensi.', 'Program Penunjang Urusan Pemerintahan Daerah'],
            ['Kode Kegiatan', 'Tidak', 'Teks', 'Kode kegiatan. Hanya referensi.', '6.01.01'],
            ['Kegiatan', 'Tidak', 'Teks', 'Nama kegiatan tanpa kodenya. Hanya referensi.', 'Perencanaan, Penganggaran, dan Evaluasi Kinerja'],
            ['Kode Sub Kegiatan', 'Ya', 'Teks', 'Kode sub kegiatan. Bersama Kode Rekening dipakai mencari mata anggaran yang sudah ada dan aktif.', '6.01.01.2.01'],
            ['Sub Kegiatan', 'Ya', 'Teks', 'Nama sub kegiatan tanpa kodenya.', 'Penyusunan Dokumen Perencanaan Perangkat Daerah'],
            ['Kode Rekening', 'Ya', 'Teks', 'Kode rekening belanja. Jangan digabung dengan uraiannya.', '5.1.02.01.01.0024'],
            ['Rekening', 'Tidak', 'Teks', 'Uraian rekening tanpa kodenya. Hanya referensi.', 'Belanja Alat Tulis Kantor'],
            ['Total RAK', 'Tidak', 'Rumus otomatis', 'Berisi rumus penjumlahan dua belas kolom bulan. Biarkan apa adanya - terisi sendiri saat kolom bulan diketik dan tidak dibaca saat import.', '=SUM(K2:V2)'],
        ];

        foreach (self::BULAN_LABEL as $nomor => $label) {
            $petunjuk[] = [
                $label,
                'Tidak',
                'Angka, tanpa Rp',
                sprintf('Rencana penarikan dana bulan %s. Kosongkan bila bulan ini memang belum direncanakan. Nilai negatif ditolak.', $label),
                $nomor === 1 ? '2500000' : '',
            ];
        }

        return $petunjuk;
    }

    public function map($row): array
    {
        $kunci = $row->sub_kegiatan_kunci.'|'.$row->kode_rekening_bersih;
        $perBulan = ($this->rakByKunci->get($kunci) ?? collect())->keyBy('bulan');

        $kolomBulan = [];

        foreach (array_keys(self::BULAN_LABEL) as $bulan) {
            $kolomBulan[] = $perBulan->has($bulan) ? (float) $perBulan[$bulan]->target : null;
        }

        return array_merge([
            $this->tahun,
            $row->kode_program,
            $row->program,
            $row->kode_kegiatan,
            $row->kegiatan,
            $row->kode_sub_kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->rekening,
            // Diisi ulang sebagai rumus di registerEvents(): nomor barisnya
            // baru diketahui setelah seluruh data ditulis.
            null,
        ], $kolomBulan);
    }
}
