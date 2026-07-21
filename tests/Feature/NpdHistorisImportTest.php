<?php

namespace Tests\Feature;

use App\Exports\NpdHistorisTemplateExport;
use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdHistorisImport;
use App\Models\NpdHistorisImportRow;
use App\Models\NpdHistoriStatus;
use App\Models\RakBulanan;
use App\Models\Spm;
use App\Models\SuratPerintah;
use App\Models\Tagging;
use App\Models\User;
use App\Services\NpdHistorisImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as LaravelExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class NpdHistorisImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MasterAnggaran $anggaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $tagging = Tagging::create(['nama' => 'Tagging Historis', 'aktif' => true]);
        $this->anggaran = MasterAnggaran::create([
            'program' => 'Program Historis', 'kegiatan' => 'Kegiatan Historis',
            'sub_kegiatan' => '6.01.01.2.01 Sub Historis', 'kode_rekening' => '5.1.02.01.01.0001',
            'uraian_rekening' => 'Belanja Historis', 'tagging_id' => $tagging->id,
            'pagu' => 100_000_000, 'aktif' => true,
        ]);
        RakBulanan::create([
            'tahun' => 2026, 'bulan' => 7, 'sub_kegiatan' => $this->anggaran->sub_kegiatan,
            'sub_kegiatan_kunci' => $this->anggaran->sub_kegiatan_kunci,
            'kode_rekening' => $this->anggaran->kode_rekening, 'target' => 50_000_000,
        ]);
    }

    private function row(array $override = []): array
    {
        return array_values(array_replace([
            '2026-07-15', '001/NPD/HIST/2026', 'Barang/Jasa', $this->anggaran->sub_kegiatan,
            $this->anggaran->kode_rekening, 'Tagging Historis', 'Penerima Manual', '1234567890',
            1_000_000, 'Uraian historis', 100_000, 50_000, 'PPh 21', 25_000, 'PPh 22', '',
        ], $override));
    }

    private function workbook(array $rows, ?string $path = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', NpdHistorisImportService::FORMAT_MARKER);
        $sheet->setCellValue('A2', 'Petunjuk');
        $sheet->setCellValue('A3', 'Nilai bulanan');
        $sheet->fromArray(NpdHistorisImportService::HEADERS, null, 'A4');
        $sheet->fromArray($rows, null, 'A5');
        $path ??= sys_get_temp_dir().'/'.uniqid('npd_hist_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'npd-historis.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_card_route_template_and_backend_authorization(): void
    {
        $pptk = User::factory()->create(['role' => User::ROLE_PPTK]);
        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA_PENGELUARAN]);

        $this->actingAs($this->admin)->get(route('manajemen-data.index'))->assertOk()->assertSee('Import NPD Historis');
        $this->actingAs($bendahara)->get(route('manajemen-data.index'))->assertOk()->assertDontSee('Import NPD Historis');
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.create'))->assertOk()
            ->assertSee('Unduh Template Import NPD')->assertSee('Tahun Anggaran 2026');
        $this->actingAs($pptk)->get(route('manajemen-data.import.npd-historis.create'))->assertForbidden();
        $this->actingAs($bendahara)->get(route('manajemen-data.import.npd-historis.template'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.template'))->assertOk()->assertDownload('template-import-npd-historis-v1.xlsx');

        $template = (new NpdHistorisTemplateExport)->array();
        $this->assertSame(NpdHistorisImportService::FORMAT_MARKER, $template[0][0]);
        $this->assertSame(NpdHistorisImportService::HEADERS, $template[3]);
        $this->assertStringContainsString('TAHUN ANGGARAN 2026', $template[1][0]);

        $bytes = LaravelExcel::raw(new NpdHistorisTemplateExport, Excel::XLSX);
        $basePath = tempnam(sys_get_temp_dir(), 'npd_template_');
        $path = $basePath.'.xlsx';

        try {
            file_put_contents($path, $bytes);
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame(NpdHistorisImportService::FORMAT_MARKER, $sheet->getCell('A1')->getValue());
            $this->assertSame('Tanggal NPD', $sheet->getCell('A4')->getValue());
            $this->assertSame('A5', $sheet->getFreezePane());
        } finally {
            @unlink($path);
            @unlink($basePath);
        }
    }

    public function test_preview_is_dry_run_and_shows_mapping_manual_recipient_and_totals(): void
    {
        $response = $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row()])]);
        $import = NpdHistorisImport::firstOrFail();
        $response->assertRedirect(route('manajemen-data.import.npd-historis.preview', $import));

        $this->assertSame(0, Npd::count());
        $row = $import->baris()->firstOrFail();
        $this->assertSame('warning', $row->hasil);
        $this->assertTrue($row->penerima_manual);
        $this->assertSame(2026, $row->tahun);
        $this->assertSame(7, $row->bulan);
        $this->assertSame('bj', $row->jenis_kode);
        $this->assertSame('exact', $row->mapping_status);
        $this->assertSame(50_000_000.0, (float) $row->rak_bulan);
        $this->assertSame(1_000_000.0, (float) $import->total_nominal);

        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.preview', $import))
            ->assertOk()->assertSee('Snapshot manual')->assertSee('Program Historis');
        $this->assertSame(0, Npd::count());
    }

    public function test_all_five_types_map_blank_status_defaults_finished_and_batal_has_no_realization(): void
    {
        $types = ['Barang/Jasa', 'perjalanan-dinas', 'TRANSPORT', 'Narasumber', 'Kontribusi   Diklat'];
        $rows = [];
        foreach ($types as $i => $type) {
            $rows[] = $this->row([1 => sprintf('%03d/NPD/HIST/2026', $i + 1), 2 => $type, 8 => ($i + 1) * 1_000_000, 15 => $i === 4 ? 'Batal' : '']);
        }
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook($rows)]);
        $import = NpdHistorisImport::firstOrFail();

        $this->assertSame(['bj', 'kd', 'ns', 'pd', 'tr'], $import->baris()->pluck('jenis_kode')->sort()->values()->all());
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import))->assertRedirect();

        $this->assertSame(5, Npd::count());
        $this->assertSame(4, Npd::where('status', 'Selesai')->count());
        $this->assertSame(1, Npd::where('status', 'Dibatalkan')->count());
        $this->assertSame(10_000_000.0, $this->anggaran->fresh()->realisasiNpd());
        $this->assertSame(5, NpdHistoriStatus::where('aksi', 'Import Data Lama')->count());
        $this->assertSame(0, SuratPerintah::count());
        $this->assertSame(0, Spm::count());
        $this->assertNotNull(AuditLog::where('aktivitas', 'Import NPD Historis')->first());
    }

    public function test_unknown_type_wrong_status_and_npd_year_2025_atau_2027_are_rejected_without_mapping(): void
    {
        $rows = [
            $this->row([1 => '101/NPD/HIST/2026', 2 => 'Belanja Lain', 15 => 'Draft']),
            $this->row([0 => '2025-07-15', 1 => '102/NPD/HIST/2025']),
            $this->row([0 => '2027-07-15', 1 => '103/NPD/HIST/2027']),
        ];
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook($rows)]);
        $import = NpdHistorisImport::firstOrFail();

        $this->assertSame(3, $import->jumlah_error);
        $this->assertStringContainsString('tidak dikenal', implode(' ', $import->baris()->where('nomor_baris', 5)->value('pesan')));
        $this->assertStringContainsString('Status hanya', implode(' ', $import->baris()->where('nomor_baris', 5)->value('pesan')));
        foreach ([6 => 2025, 7 => 2027] as $nomorBaris => $tahun) {
            $row = $import->baris()->where('nomor_baris', $nomorBaris)->firstOrFail();
            $this->assertStringContainsString("Tahun Anggaran {$tahun}", implode(' ', $row->pesan));
            $this->assertSame('tahun_ditolak', $row->mapping_status);
            $this->assertNull($row->master_anggaran_id);
            $this->assertNull($row->rak_bulanan_id);
        }
        $this->assertSame(0, Npd::count());
        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, RakBulanan::count());
    }

    public function test_tax_rules_snapshot_and_net_follow_existing_lampiran_rule(): void
    {
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row([8 => 1_000_000, 10 => 100_000, 11 => 150_000, 12 => 'PPh 21', 13 => 50_000, 14 => 'PPh 22'])])]);
        $import = NpdHistorisImport::firstOrFail();
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import));
        $penerima = Npd::firstOrFail()->penerima()->with('pphList')->firstOrFail();

        $this->assertSame(100_000.0, (float) $penerima->ppn);
        $this->assertSame(200_000.0, $penerima->total_pph);
        $this->assertSame(700_000.0, $penerima->netto);
        $this->assertSame('1234567890', $penerima->rekening);
    }

    public function test_duplicate_file_and_database_document_are_idempotent(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('npd_hist_same_', true).'.xlsx';
        $file = $this->workbook([$this->row()], $path);
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $file]);
        $import = NpdHistorisImport::firstOrFail();
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import));

        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), [
            'file' => new UploadedFile($path, 'npd-historis.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertSame(1, NpdHistorisImport::count());
        $this->assertSame(1, Npd::count());
        $this->assertSame(1, NpdHistorisImportRow::count());
    }

    public function test_duplicate_inside_file_and_impossible_tax_total_are_reported(): void
    {
        $row = $this->row([8 => 100_000, 10 => 80_000, 11 => 30_000, 12 => 'PPh 21']);
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$row, $row])]);
        $import = NpdHistorisImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_duplikat);
        $this->assertStringContainsString('melebihi Nominal Bruto', implode(' ', $import->baris()->first()->pesan));
        $this->assertSame(0, Npd::count());
    }

    public function test_validation_and_final_reports_are_downloadable(): void
    {
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row()])]);
        $import = NpdHistorisImport::firstOrFail();
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.report', [$import, 'validation']))->assertOk()->assertDownload();
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.report', [$import, 'final']))->assertStatus(409);
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import));
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.report', [$import, 'final']))->assertOk()->assertDownload();
    }
}
