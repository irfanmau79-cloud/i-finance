<?php

namespace Tests\Feature;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Exports\MasterAnggaranExport;
use App\Exports\MasterAnggaranTemplateExport;
use App\Exports\NpdExport;
use App\Exports\NpdHistorisReportExport;
use App\Exports\NpdHistorisTemplateExport;
use App\Exports\PegawaiExport;
use App\Exports\PegawaiTemplateExport;
use App\Exports\PerjalananDinasExport;
use App\Exports\RakBulananExport;
use App\Exports\SimulasiAnggaranExport;
use App\Exports\SpjPerjalananDinasExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmLsTemplateExport;
use App\Exports\SpmUpGuExport;
use App\Exports\SpmUpGuTemplateExport;
use App\Exports\TunjanganKeluargaExport;
use App\Exports\TunjanganKeluargaTemplateExport;
use App\Exports\VendorExport;
use App\Exports\VendorTemplateExport;
use App\Models\NpdHistorisImport;
use App\Models\SimulasiAnggaran;
use App\Services\NpdHistorisImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as LaravelExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Setiap template dan export Excel wajib menyertakan sheet "Petunjuk
 * Pengisian" yang menjelaskan SETIAP kolom sheet data, urut dan lengkap.
 *
 * Test ini yang menjaga keduanya tetap sinkron: begitu ada kolom ditambah,
 * dihapus, atau ditukar urutannya tanpa memperbarui petunjuknya, test ini
 * gagal - bukan pengguna yang menemukannya belakangan lewat berkas yang
 * petunjuknya menyesatkan.
 */
class PetunjukPengisianExportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: PunyaPetunjukKolom, 1: array<int, string>}> */
    private function semuaExport(): array
    {
        $import = NpdHistorisImport::create([
            'nama_file' => 'uji.xlsx',
            'file_hash' => hash('sha256', 'uji'),
            'format_sumber' => 'template_v1',
            'status' => 'staged',
            'total_nominal' => 0,
            'expires_at' => now()->addHour(),
        ]);

        $simulasi = SimulasiAnggaran::create(['nama' => 'Simulasi Uji']);

        return [
            'Template Pagu' => [new MasterAnggaranTemplateExport, MasterAnggaranTemplateExport::HEADERS],
            'Export Pagu' => [new MasterAnggaranExport, (new MasterAnggaranExport)->headings()],
            'RAK Bulanan' => [new RakBulananExport(2026), (new RakBulananExport(2026))->headings()],
            'Template NPD Historis' => [new NpdHistorisTemplateExport, NpdHistorisImportService::HEADERS],
            'Export NPD' => [new NpdExport, (new NpdExport)->headings()],
            'Laporan Import NPD' => [
                new NpdHistorisReportExport($import, 'validation'),
                (new NpdHistorisReportExport($import, 'validation'))->headings(),
            ],
            'Template SPM UP/GU' => [new SpmUpGuTemplateExport, SpmUpGuTemplateExport::HEADERS],
            'Export SPM UP/GU' => [new SpmUpGuExport, (new SpmUpGuExport)->headings()],
            'Template SPM LS' => [new SpmLsTemplateExport, SpmLsTemplateExport::HEADERS],
            'Export SPM LS' => [new SpmLsExport, (new SpmLsExport)->headings()],
            'Template Pegawai' => [new PegawaiTemplateExport, PegawaiTemplateExport::HEADERS],
            'Export Pegawai' => [new PegawaiExport, (new PegawaiExport)->headings()],
            'Template Vendor' => [new VendorTemplateExport, VendorTemplateExport::HEADERS],
            'Export Vendor' => [new VendorExport, (new VendorExport)->headings()],
            'Template Tunjangan Keluarga' => [new TunjanganKeluargaTemplateExport, TunjanganKeluargaTemplateExport::HEADERS],
            'Export Tunjangan Keluarga' => [new TunjanganKeluargaExport, (new TunjanganKeluargaExport)->headings()],
            'Export Perjalanan Dinas' => [new PerjalananDinasExport, (new PerjalananDinasExport)->headings()],
            'Export SPJ Perjalanan Dinas' => [new SpjPerjalananDinasExport, (new SpjPerjalananDinasExport)->headings()],
            'Export Simulasi Anggaran' => [new SimulasiAnggaranExport($simulasi), (new SimulasiAnggaranExport($simulasi))->headings()],
        ];
    }

    public function test_setiap_export_menjelaskan_seluruh_kolomnya_urut(): void
    {
        foreach ($this->semuaExport() as $nama => [$export, $headings]) {
            $this->assertInstanceOf(PunyaPetunjukKolom::class, $export, "{$nama} harus menyertakan petunjuk pengisian.");

            $petunjuk = $export->petunjukKolom();

            $this->assertSame(
                $headings,
                array_column($petunjuk, 0),
                "Petunjuk {$nama} tidak sama dengan kolom sheet datanya - ada kolom yang belum dijelaskan, berlebih, atau urutannya bergeser."
            );

            foreach ($petunjuk as $indeks => $baris) {
                $this->assertCount(5, $baris, "Petunjuk {$nama} baris ke-{$indeks} harus berisi kolom, wajib, format, penjelasan, dan contoh.");

                $this->assertNotSame('', trim((string) $baris[3]), "Penjelasan kolom \"{$baris[0]}\" pada {$nama} kosong.");
                $this->assertNotSame('', trim((string) $baris[2]), "Format kolom \"{$baris[0]}\" pada {$nama} kosong.");
            }

            $this->assertNotSame('', trim($export->petunjukCatatan()), "Catatan pembuka {$nama} kosong.");
        }
    }

    public function test_berkas_yang_dihasilkan_punya_dua_sheet_dengan_data_di_urutan_pertama(): void
    {
        foreach ($this->semuaExport() as $nama => [$export, $headings]) {
            $basePath = tempnam(sys_get_temp_dir(), 'petunjuk_');
            $path = $basePath.'.xlsx';

            try {
                file_put_contents($path, LaravelExcel::raw($export, Excel::XLSX));
                $workbook = IOFactory::load($path);

                $this->assertSame(
                    ['Data', 'Petunjuk Pengisian'],
                    $workbook->getSheetNames(),
                    "{$nama} harus punya sheet Data lalu Petunjuk Pengisian."
                );

                // Sheet data tetap yang pertama: seluruh importer hanya
                // membaca sheet pertama, jadi urutan ini tidak boleh terbalik.
                $this->assertSame(0, $workbook->getActiveSheetIndex(), "Sheet aktif {$nama} harus sheet data.");

                $petunjuk = $workbook->getSheetByName('Petunjuk Pengisian');
                $this->assertSame('Petunjuk Pengisian', $petunjuk->getCell('A1')->getValue());
                $this->assertSame(
                    ['Kolom', 'Wajib', 'Format', 'Penjelasan', 'Contoh Isi'],
                    $petunjuk->rangeToArray('A4:E4')[0]
                );
                $this->assertSame($headings[0], $petunjuk->getCell('A5')->getValue(), "Baris pertama petunjuk {$nama} harus kolom pertama sheet data.");
                $this->assertSame(count($headings) + 4, $petunjuk->getHighestRow(), "Jumlah baris petunjuk {$nama} tidak sesuai jumlah kolomnya.");
            } finally {
                @unlink($path);
                @unlink($basePath);
            }
        }
    }
}
