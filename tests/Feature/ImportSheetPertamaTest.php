<?php

namespace Tests\Feature;

use App\Exports\MasterAnggaranTemplateExport;
use App\Exports\PegawaiTemplateExport;
use App\Exports\TunjanganKeluargaTemplateExport;
use App\Imports\MasterAnggaranUploadImport;
use App\Imports\PegawaiUploadImport;
use App\Imports\RawSheetImport;
use App\Models\MasterAnggaranImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Setiap template/export sekarang punya sheet kedua "Petunjuk Pengisian".
 * Tanpa penguncian ke sheet pertama, Maatwebsite memanggil collection()
 * sekali per sheet pada objek importer yang SAMA sehingga sheet petunjuk
 * menimpa sheet data - seluruh kolom lalu terbaca kosong dan import ditolak
 * dengan alasan "Kode Sub Kegiatan atau Kode Rekening kosong".
 *
 * Test ini memakai berkas asli hasil export (bukan fixture buatan tangan)
 * supaya ikut menjaga bila sheet petunjuk kelak berpindah atau bertambah.
 */
class ImportSheetPertamaTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<int, array<int, string>>  $barisData */
    private function berkasDariExport(object $export, string $nama, array $barisData): string
    {
        Storage::disk('local')->makeDirectory('uji-import');
        Excel::store($export, "uji-import/{$nama}", 'local');
        $path = Storage::disk('local')->path("uji-import/{$nama}");

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        foreach ($barisData as $indeks => $isi) {
            foreach (array_values($isi) as $kolom => $nilai) {
                $sheet->setCellValue([$kolom + 1, $indeks + 2], $nilai);
            }
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $path;
    }

    public function test_template_pagu_punya_sheet_petunjuk_terpisah(): void
    {
        $path = $this->berkasDariExport(new MasterAnggaranTemplateExport, 'pagu-sheet.xlsx', []);
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(['Data', 'Petunjuk Pengisian'], $spreadsheet->getSheetNames());
    }

    public function test_upload_pagu_membaca_sheet_data_bukan_sheet_petunjuk(): void
    {
        $path = $this->berkasDariExport(new MasterAnggaranTemplateExport, 'pagu.xlsx', [
            ['2026', '6.01', 'Program Penunjang', '6.01.01', 'Kegiatan A', '6.01.01.2.01',
                'Sub Kegiatan A', '5.1.02.01.01.0024', 'Belanja ATK', 'Rutin', '15000000', 'Aktif'],
        ]);

        $reader = new MasterAnggaranUploadImport;
        Excel::import($reader, $path);

        $baris = $reader->rows
            ->map(fn ($row) => $row instanceof Collection ? $row->all() : (array) $row)
            ->first(fn (array $row) => trim((string) ($row['kode_rekening'] ?? '')) !== '');

        $this->assertNotNull($baris, 'Importer membaca sheet petunjuk, bukan sheet data.');
        $this->assertSame('6.01.01.2.01', $baris['kode_sub_kegiatan']);
        $this->assertSame('5.1.02.01.01.0024', $baris['kode_rekening']);
    }

    public function test_import_pagu_dari_berkas_template_asli_tidak_ditolak_kosong(): void
    {
        $path = $this->berkasDariExport(new MasterAnggaranTemplateExport, 'pagu-e2e.xlsx', [
            ['', '6.01', 'Program Penunjang', '6.01.01', 'Kegiatan A', '6.01.01.2.01',
                'Sub Kegiatan A', '5.1.02.01.01.0024', 'Belanja ATK', 'Rutin', '15000000', 'Aktif'],
            ['', '6.01', 'Program Penunjang', '6.01.01', 'Kegiatan A', '6.01.01.2.01',
                'Sub Kegiatan A', '5.1.02.01.01.0025', 'Belanja Cetak', '', '9000000', 'Non Aktif'],
        ]);

        $import = MasterAnggaranImport::buatDariUpload(
            new UploadedFile($path, 'pagu-e2e.xlsx', null, null, true),
            (int) config('anggaran.tahun_aktif'),
            'DPA Uji Sheet',
            null,
            null,
            null
        );

        $this->assertSame(2, $import->total_baris);
        $this->assertSame(0, $import->jumlah_ditolak);
        $this->assertSame(2, $import->jumlah_baru);

        $baris = $import->baris()->orderBy('nomor_baris')->get();
        $this->assertSame('6.01.01.2.01', $baris[0]->kode_sub_kegiatan);
        $this->assertSame('5.1.02.01.01.0024', $baris[0]->kode_rekening);
        $this->assertSame('15000000.00', (string) $baris[0]->pagu_baru);
    }

    /** "Aktif/Non Aktif" ter-slug jadi "aktifnon_aktif" - garis miringnya hilang tanpa pemisah. */
    public function test_kolom_non_aktif_pada_template_asli_tetap_menonaktifkan_baris(): void
    {
        $path = $this->berkasDariExport(new MasterAnggaranTemplateExport, 'pagu-aktif.xlsx', [
            ['', '6.01', 'Program', '6.01.01', 'Kegiatan', '6.01.01.2.01',
                'Sub Kegiatan', '5.1.02.01.01.0026', 'Belanja Modal', '', '1000000', 'Non Aktif'],
        ]);

        $import = MasterAnggaranImport::buatDariUpload(
            new UploadedFile($path, 'pagu-aktif.xlsx', null, null, true),
            (int) config('anggaran.tahun_aktif'),
            'DPA Uji Non Aktif',
            null,
            null,
            null
        );

        $this->assertFalse((bool) $import->baris()->first()->aktif);
    }

    public function test_upload_pegawai_membaca_sheet_data(): void
    {
        $path = $this->berkasDariExport(new PegawaiTemplateExport, 'pegawai.xlsx', [
            ['Budi Santoso', '198001012005011001', 'Auditor', 'Irbanwil I', 'III/c', 'Penata', '1234567890', 'Aktif'],
        ]);

        $reader = new PegawaiUploadImport;
        Excel::import($reader, $path);

        $baris = $reader->rows
            ->map(fn ($row) => $row instanceof Collection ? $row->all() : (array) $row)
            ->first(fn (array $row) => trim((string) ($row['nip'] ?? '')) !== '');

        $this->assertNotNull($baris, 'Importer pegawai membaca sheet petunjuk, bukan sheet data.');
        $this->assertSame('Budi Santoso', $baris['nama']);
    }

    public function test_raw_sheet_import_berhenti_pada_sheet_pertama(): void
    {
        $path = $this->berkasDariExport(new TunjanganKeluargaTemplateExport, 'tk.xlsx', [
            ['Budi Santoso', '198001012005011001'],
        ]);

        $reader = new RawSheetImport(1);
        Excel::import($reader, $path);

        $this->assertSame('Nama Pegawai', trim((string) $reader->rows->first()[0]));
    }

    /** Peran kedua RawSheetImport - sub-importer per nama sheet - tidak boleh ikut terkunci. */
    public function test_raw_sheet_import_tetap_bisa_dipakai_per_sheet_bernama(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Lain')->setCellValue('A1', 'bukan ini');
        $kedua = $spreadsheet->createSheet();
        $kedua->setTitle('Nama Pegawai');
        $kedua->setCellValue('A1', 'Nama');
        $kedua->setCellValue('A2', 'Budi Santoso');

        Storage::disk('local')->makeDirectory('uji-import');
        $path = Storage::disk('local')->path('uji-import/multi.xlsx');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $reader = new RawSheetImport(2);
        $induk = new class($reader) implements WithMultipleSheets
        {
            public function __construct(private RawSheetImport $pegawai) {}

            /** @return array<string, object> */
            public function sheets(): array
            {
                return ['Nama Pegawai' => $this->pegawai];
            }
        };

        Excel::import($induk, $path);

        $this->assertSame('Budi Santoso', trim((string) $reader->rows->first()[0]));
    }
}
