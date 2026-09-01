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
            'sub_kegiatan' => '6.01.01.2.01 Sub Historis', 'kode_rekening' => '5.1.02.01.01.0001 Belanja Historis',
            'tagging_id' => $tagging->id,
            'pagu' => 100_000_000, 'aktif' => true,
        ]);
        RakBulanan::create([
            'tahun' => 2026, 'bulan' => 7, 'sub_kegiatan' => $this->anggaran->sub_kegiatan_lengkap,
            'sub_kegiatan_kunci' => $this->anggaran->sub_kegiatan_kunci,
            'kode_rekening' => $this->anggaran->kode_rekening_bersih, 'target' => 50_000_000,
        ]);
    }

    /**
     * Satu baris data template. Override memakai NAMA kolom, bukan indeks
     * angka - urutan kolom sudah pernah bergeser saat kode dipisah dari
     * namanya, dan indeks angka membuat pergeseran itu tidak terdeteksi
     * (nilai diam-diam mendarat di kolom sebelahnya).
     */
    private function row(array $override = []): array
    {
        return array_values(array_replace([
            'tanggal_npd' => '2026-07-15',
            'nomor_npd' => '001/NPD/HIST/2026',
            'jenis_npd' => 'Barang/Jasa',
            'kode_sub_kegiatan' => $this->anggaran->kode_sub_kegiatan,
            'sub_kegiatan' => $this->anggaran->sub_kegiatan,
            'kode_rekening' => $this->anggaran->kode_rekening,
            'rekening' => $this->anggaran->rekening,
            'tagging' => 'Tagging Historis',
            'penerima' => 'Penerima Manual',
            'rekening_penerima' => '1234567890',
            'nominal_bruto' => 1_000_000,
            'uraian' => 'Uraian historis',
            'ppn' => 100_000,
            'pph1' => 50_000,
            'jenis_pph1' => 'PPh 21',
            'pph2' => 25_000,
            'jenis_pph2' => 'PPh 22',
            'status_npd' => '',
        ], $override));
    }

    /** Baris untuk template LAMA: kode + nama masih tergabung dalam satu sel. */
    private function rowFormatLama(array $override = []): array
    {
        return array_values(array_replace([
            'tanggal_npd' => '2026-07-15',
            'nomor_npd' => '001/NPD/HIST/2026',
            'jenis_npd' => 'Barang/Jasa',
            'sub_kegiatan' => $this->anggaran->sub_kegiatan_lengkap,
            'kode_rekening' => $this->anggaran->rekening_lengkap,
            'tagging' => 'Tagging Historis',
            'penerima' => 'Penerima Manual',
            'rekening_penerima' => '1234567890',
            'nominal_bruto' => 1_000_000,
            'uraian' => 'Uraian historis',
            'ppn' => 100_000,
            'pph1' => 50_000,
            'jenis_pph1' => 'PPh 21',
            'pph2' => 25_000,
            'jenis_pph2' => 'PPh 22',
            'status_npd' => '',
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

    /** Workbook memakai header template LAMA (16 kolom, kode + nama tergabung). */
    private function workbookFormatLama(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', NpdHistorisImportService::FORMAT_MARKER);
        $sheet->setCellValue('A2', 'Petunjuk');
        $sheet->setCellValue('A3', 'Nilai bulanan');
        $sheet->fromArray([
            'Tanggal NPD', 'Nomor NPD', 'Jenis NPD', 'Sub Kegiatan', 'Kode Rekening', 'Tagging',
            'Penerima', 'Rekening Penerima', 'Nominal Bruto', 'Uraian', 'PPN', 'PPh1',
            'Jenis PPh1', 'PPh2', 'Jenis PPh2', 'Status NPD',
        ], null, 'A4');
        $sheet->fromArray($rows, null, 'A5');
        $path = sys_get_temp_dir().'/'.uniqid('npd_hist_lama_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'npd-historis-lama.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_card_route_template_and_backend_authorization(): void
    {
        $pptk = User::factory()->create(['role' => User::ROLE_PPTK]);
        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA_PENGELUARAN]);

        $this->actingAs($this->admin)->get(route('manajemen-data.index'))->assertOk()
            ->assertSee(route('manajemen-data.import.npd-historis.create'), false);
        $this->actingAs($bendahara)->get(route('manajemen-data.index'))->assertOk()
            ->assertDontSee(route('manajemen-data.import.npd-historis.create'), false);
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.create'))->assertOk()
            ->assertSee('Unduh Template Import NPD')->assertSee('Tahun Anggaran 2026')
            // Contoh isi kolom di layar diambil dari kelas template - kalau
            // contohnya berubah di template, halaman ini ikut berubah.
            ->assertSee('Contoh Isi Kolom')
            ->assertSee('001/NPD/HIST/2026')
            ->assertSee('5.1.02.01.01.0024');

        $petunjuk = (new NpdHistorisTemplateExport)->petunjukKolom();
        $this->assertCount(count(NpdHistorisImportService::HEADERS), $petunjuk);
        $this->assertSame(NpdHistorisImportService::HEADERS, array_column($petunjuk, 0));
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
            $rows[] = $this->row(['nomor_npd' => sprintf('%03d/NPD/HIST/2026', $i + 1), 'jenis_npd' => $type, 'nominal_bruto' => ($i + 1) * 1_000_000, 'status_npd' => $i === 4 ? 'Batal' : '']);
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
            $this->row(['nomor_npd' => '101/NPD/HIST/2026', 'jenis_npd' => 'Belanja Lain', 'status_npd' => 'Draft']),
            $this->row(['tanggal_npd' => '2025-07-15', 'nomor_npd' => '102/NPD/HIST/2025']),
            $this->row(['tanggal_npd' => '2027-07-15', 'nomor_npd' => '103/NPD/HIST/2027']),
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
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row(['nominal_bruto' => 1_000_000, 'ppn' => 100_000, 'pph1' => 150_000, 'jenis_pph1' => 'PPh 21', 'pph2' => 50_000, 'jenis_pph2' => 'PPh 22'])])]);
        $import = NpdHistorisImport::firstOrFail();
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import));
        $penerima = Npd::firstOrFail()->penerima()->with('pphList')->firstOrFail();

        $this->assertSame(100_000.0, (float) $penerima->ppn);
        $this->assertSame(200_000.0, $penerima->total_pph);
        $this->assertSame(700_000.0, $penerima->netto);
        $this->assertSame('1234567890', $penerima->rekening);
    }

    public function test_preview_menghitung_realisasi_ls_dari_spm_detail_bukan_kolom_master_anggaran_id_di_spm(): void
    {
        // Regresi: SPM LS sudah direstrukturisasi jadi header (spm) + banyak baris
        // mata anggaran (spm_detail) - master_anggaran_id tidak lagi ada di tabel
        // spm. Sebelum diperbaiki, baris ini melempar QueryException 42S22
        // "Unknown column 'master_anggaran_id'" karena kode lama masih query
        // langsung ke tabel spm. Dua baris mata anggaran dipakai supaya juga
        // membuktikan tidak ada double-counting: realisasi LS untuk mata anggaran
        // NPD ini harus hanya mengambil baris miliknya sendiri (20jt), bukan
        // seluruh dokumen SPM LS (20jt + 30jt).
        $anggaranLain = MasterAnggaran::create([
            'program' => 'Program Lain', 'kegiatan' => 'Kegiatan Lain',
            'sub_kegiatan' => '6.01.01.2.02 Sub Lain', 'kode_rekening' => '5.1.02.01.01.0002 Belanja Lain',
            'pagu' => 50_000_000, 'aktif' => true,
        ]);
        Spm::buatLs([
            'nomor_dokumen' => '900/SPM-LS/2026', 'tanggal_dokumen' => '2026-07-10',
            'baris' => [
                ['master_anggaran_id' => $this->anggaran->id, 'nominal' => 20_000_000],
                ['master_anggaran_id' => $anggaranLain->id, 'nominal' => 30_000_000],
            ],
        ]);

        $response = $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row()])]);
        $import = NpdHistorisImport::firstOrFail();
        $response->assertRedirect(route('manajemen-data.import.npd-historis.preview', $import));

        $row = $import->baris()->firstOrFail();
        $this->assertSame('warning', $row->hasil);
        // pagu 100jt - dana terikat NPD aktif (0, belum ada NPD) - realisasi LS
        // mata anggaran ini saja (20jt, BUKAN 50jt) - nominal NPD yang diimpor (1jt).
        $this->assertSame(79_000_000.0, (float) $row->sisa_proyeksi);

        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.preview', $import))->assertOk();
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
        $row = $this->row(['nominal_bruto' => 100_000, 'ppn' => 80_000, 'pph1' => 30_000, 'jenis_pph1' => 'PPh 21']);
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$row, $row])]);
        $import = NpdHistorisImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_duplikat);
        $this->assertStringContainsString('melebihi Nominal Bruto', implode(' ', $import->baris()->first()->pesan));
        $this->assertSame(0, Npd::count());
    }

    /**
     * Berkas yang terlanjur diunduh dengan template LAMA (Sub Kegiatan dan
     * Kode Rekening masih menggabungkan kode + nama) tetap harus terbaca -
     * kolom Kode Sub Kegiatan dan Rekening sengaja tidak diwajibkan.
     */
    public function test_template_lama_tanpa_kolom_kode_terpisah_tetap_terbaca(): void
    {
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), [
            'file' => $this->workbookFormatLama([$this->rowFormatLama()]),
        ]);

        $import = NpdHistorisImport::firstOrFail();
        $baris = $import->baris()->firstOrFail();

        // Mata anggaran tetap ketemu meski kode dan nama datang tergabung.
        $this->assertSame($this->anggaran->id, $baris->master_anggaran_id);
        $this->assertSame($this->anggaran->sub_kegiatan_kunci, $baris->sub_kegiatan_kunci);
        $this->assertStringNotContainsString('tidak ditemukan', implode(' ', $baris->pesan ?? []));

        // 'warning' berasal dari snapshot penerima manual (sama seperti baris
        // format baru), bukan dari kegagalan pemetaan mata anggaran.
        $this->assertSame('warning', $baris->hasil);
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

    // ---------------- Tampilan halaman pemeriksaan ----------------

    /**
     * Sebelum diperbaiki, halaman ini tidak pernah menampilkan jumlah_berhasil,
     * jumlah_dilewati, maupun executed_at - padahal ketiganya sudah lama
     * disimpan dan justru itulah isi laporan yang dicari setelah menekan
     * Konfirmasi Import.
     */
    public function test_halaman_menampilkan_laporan_hasil_setelah_import_dijalankan(): void
    {
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row()])]);
        $import = NpdHistorisImport::firstOrFail();

        // Sebelum dikonfirmasi: panel hasil belum muncul, tombol konfirmasi ada.
        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.preview', $import))
            ->assertOk()
            ->assertDontSee('HASIL IMPORT')
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('Konfirmasi Import');

        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.confirm', $import));
        $import->refresh();

        $this->assertSame(NpdHistorisImport::STATUS_COMMITTED, $import->status);
        $this->assertNotNull($import->executed_at);

        $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.preview', $import))
            ->assertOk()
            ->assertSee('HASIL IMPORT')
            ->assertSee($import->jumlah_berhasil.' dokumen')
            ->assertSee($import->jumlah_dilewati.' baris')
            ->assertSee($import->executed_at->format('d-m-Y H:i'))
            ->assertSee('Sudah Diimpor')
            ->assertSee('Unduh Laporan Final')
            ->assertDontSee('Konfirmasi Import');
    }

    /** Nilai enum staging tidak boleh bocor mentah-mentah ke layar. */
    public function test_halaman_memakai_label_indonesia_bukan_nilai_enum_mentah(): void
    {
        $this->actingAs($this->admin)->post(route('manajemen-data.import.npd-historis.store'), ['file' => $this->workbook([$this->row()])]);
        $import = NpdHistorisImport::firstOrFail();

        $this->assertSame('warning', $import->baris()->firstOrFail()->hasil);

        $response = $this->actingAs($this->admin)->get(route('manajemen-data.import.npd-historis.preview', $import))->assertOk();

        $response->assertSee('Perlu Diperiksa')      // hasil: warning
            ->assertSee('Cocok penuh')               // mapping_status: exact
            ->assertSee('Barang/Jasa')               // jenis_kode: bj
            ->assertSee('Juli');                     // bulan: 7

        // Nama kolom filter memakai bahasa Indonesia, bukan nama kolom database.
        $response->assertSee('Hasil Pemeriksaan')->assertSee('Jenis NPD')->assertSee('Status Tujuan');
    }
}
