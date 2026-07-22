<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\MasterAnggaranImport;
use App\Models\MasterAnggaranImportRow;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterAnggaranImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu', 'Aktif'];

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    /** @param  array<int, array>  $baris */
    private function buatFileExcel(array $baris, ?string $namaFile = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADER, null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('ma_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $namaFile ?? 'master-anggaran.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRow(array $override = []): array
    {
        return array_values(array_replace([
            'program' => 'Program Uji Import',
            'kegiatan' => 'Kegiatan Uji Import',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Import',
            'kode_rekening' => '5.1.02.05.01.7001',
            'uraian_rekening' => 'Belanja Pengujian Import',
            'tagging' => '',
            'pagu' => 10_000_000,
            'aktif' => 'Ya',
        ], $override));
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.create'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.master-anggaran.create'))->assertForbidden();

        $file = $this->buatFileExcel([$this->baseRow()]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file])->assertForbidden();
    }

    public function test_upload_menolak_file_dengan_mime_yang_tidak_didukung(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $fileTeks = UploadedFile::fake()->create('data.txt', 10, 'text/plain');

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $fileTeks])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MasterAnggaranImport::count());
    }

    public function test_upload_menolak_file_yang_melebihi_batas_jumlah_baris(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $banyakBaris = [];
        for ($i = 0; $i < MasterAnggaranImport::MAKS_BARIS + 1; $i++) {
            $banyakBaris[] = $this->baseRow(['kode_rekening' => '5.1.02.05.01.'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }
        $file = $this->buatFileExcel($banyakBaris);

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MasterAnggaranImport::count());
    }

    // ---------------- Preview tanpa mutasi ----------------

    public function test_preview_tidak_mengubah_data_master_anggaran(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->assertSame(0, MasterAnggaran::count());
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        // Lihat preview berkali-kali - murni baca, tidak boleh mengubah apa pun.
        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();

        $this->assertSame(0, MasterAnggaran::count());
        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);
    }

    public function test_tahun_anggaran_2026_diterima_dan_ditampilkan_pada_preview(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $header = array_merge(['Tahun Anggaran'], self::HEADER);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray([array_merge([2026], $this->baseRow())], null, 'A2');
        $path = sys_get_temp_dir().'/'.uniqid('ma_tahun_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'master-anggaran-2026.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file, 'tahun' => 2026]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->assertSame(0, MasterAnggaran::count());
        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.preview', $import))
            ->assertOk()->assertSee('Tahun Anggaran 2026');
    }

    public function test_request_atau_file_master_anggaran_tahun_lain_ditolak_tanpa_mutasi(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        foreach ([2025, 2027] as $tahun) {
            $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
                'file' => $this->buatFileExcel([$this->baseRow()]),
                'tahun' => $tahun,
            ])->assertSessionHasErrors('tahun');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_merge(['Tahun Anggaran'], self::HEADER), null, 'A1');
        $sheet->fromArray([array_merge([2027], $this->baseRow())], null, 'A2');
        $path = sys_get_temp_dir().'/'.uniqid('ma_tahun_salah_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => new UploadedFile($path, 'master-anggaran-2027.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'tahun' => 2026,
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, MasterAnggaranImport::count());
        $this->assertSame(0, MasterAnggaran::count());
    }

    // ---------------- Konfirmasi ----------------

    public function test_konfirmasi_menyimpan_baris_baru_dan_update_serta_mencatat_audit(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = MasterAnggaran::create([
            'program' => 'Program Lama',
            'kegiatan' => 'Kegiatan Lama',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Update',
            'kode_rekening' => '5.1.02.05.01.8001',
            'uraian_rekening' => 'Belanja Lama',
            'pagu' => 5_000_000,
            'aktif' => true,
        ]);

        $file = $this->buatFileExcel([
            $this->baseRow(), // baru
            $this->baseRow([ // update baris existing
                'sub_kegiatan' => $existing->sub_kegiatan,
                'kode_rekening' => $existing->kode_rekening,
                'pagu' => 7_500_000,
            ]),
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_ditolak);

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));
        $response->assertRedirect(route('manajemen-data.index'));

        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_COMMITTED, $import->status);
        $this->assertNotNull($import->committed_at);
        $this->assertSame(2, MasterAnggaran::count());
        $this->assertSame(7_500_000.0, (float) $existing->fresh()->pagu);

        $log = AuditLog::where('aktivitas', 'Import Master Anggaran')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Baru: 1', $log->keterangan);
        $this->assertStringContainsString('Update: 1', $log->keterangan);
        $this->assertStringContainsString('Ditolak: 0', $log->keterangan);
    }

    public function test_konfirmasi_dua_kali_pada_batch_yang_sama_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));
        $this->assertSame(1, MasterAnggaran::count());

        // Konfirmasi kedua pada batch yang sama tidak boleh menggandakan data.
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        $this->assertSame(1, MasterAnggaran::count());
    }

    // ---------------- Rollback bila satu baris fatal ----------------

    public function test_konfirmasi_rollback_seluruhnya_bila_satu_baris_fatal(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel([
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7101']), // akan sukses jika berdiri sendiri
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7102']), // dipaksa gagal fatal
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->assertSame(2, $import->jumlah_baru);

        // Suntikkan kegagalan tak terduga lewat event model (mewakili error
        // infra/DB nyata seperti koneksi putus atau constraint eksternal) -
        // murni untuk membuktikan mekanisme rollback transaksi di
        // konfirmasi(), tanpa mengubah kode produksi maupun bergantung pada
        // detail penegakan constraint yang berbeda antar driver DB.
        MasterAnggaran::saving(function (MasterAnggaran $model) {
            if ($model->kode_rekening === '5.1.02.05.01.7102') {
                throw new \RuntimeException('Simulasi kegagalan tak terduga saat simpan.');
            }
        });

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));
        $response->assertSessionHasErrors('import');

        // Seluruh batch batal - baris pertama yang seharusnya sukses pun tidak tersimpan.
        $this->assertSame(0, MasterAnggaran::count());
        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
    }

    // ---------------- Idempotensi ----------------

    public function test_import_file_yang_sama_dua_kali_tidak_menggandakan_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $baris = [$this->baseRow(['tagging' => 'Tagging Idempoten'])];

        // Putaran pertama.
        $file1 = $this->buatFileExcel($baris);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file1]);
        $import1 = MasterAnggaranImport::latest('id')->firstOrFail();
        $this->assertSame(1, $import1->jumlah_baru);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import1));

        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, Tagging::count());

        // Putaran kedua - file identik.
        $file2 = $this->buatFileExcel($baris);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file2]);
        $import2 = MasterAnggaranImport::latest('id')->firstOrFail();

        $this->assertSame(0, $import2->jumlah_baru);
        $this->assertSame(1, $import2->jumlah_update);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import2));

        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, Tagging::count());
    }

    // ---------------- Penolakan pagu terlalu kecil ----------------

    public function test_baris_ditolak_bila_pagu_baru_lebih_kecil_dari_dana_terikat_dan_realisasi_ls(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = MasterAnggaran::create([
            'program' => 'Program Uji Pagu Kecil',
            'kegiatan' => 'Kegiatan Uji Pagu Kecil',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Pagu Kecil',
            'kode_rekening' => '5.1.02.05.01.9101',
            'uraian_rekening' => 'Belanja Pengujian Pagu Kecil',
            'pagu' => 20_000_000,
            'aktif' => true,
        ]);

        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $existing->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 6_000_000,
            'terbilang' => 'enam juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);

        Spm::buatLs([
            'tanggal_dokumen' => '2026-07-10',
            'nomor_dokumen' => '001/SPM-LS/2026',
            'baris' => [['master_anggaran_id' => $existing->id, 'nominal' => 5_000_000]],
            'penerima' => 'Vendor Uji',
            'uraian' => 'Pembayaran LS',
        ]);

        // Dana terikat NPD (6jt) + realisasi LS (5jt) = minimum 11jt. Ajukan pagu 8jt - harus ditolak.
        $file = $this->buatFileExcel([
            $this->baseRow([
                'sub_kegiatan' => $existing->sub_kegiatan,
                'kode_rekening' => $existing->kode_rekening,
                'pagu' => 8_000_000,
            ]),
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $file]);
        $import = MasterAnggaranImport::firstOrFail();

        $this->assertSame(0, $import->jumlah_baru);
        $this->assertSame(0, $import->jumlah_update);
        $this->assertSame(1, $import->jumlah_ditolak);

        $baris = $import->baris()->firstOrFail();
        $this->assertSame(MasterAnggaranImportRow::AKSI_DITOLAK, $baris->aksi);
        $this->assertStringContainsString('lebih kecil dari dana terikat', $baris->alasan);

        // Konfirmasi tidak boleh mengubah pagu existing karena baris ditolak.
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));
        $this->assertSame(20_000_000.0, (float) $existing->fresh()->pagu);
    }
}
