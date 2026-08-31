<?php

namespace Tests\Feature;

use App\Exports\VendorTemplateExport;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorImport;
use App\Models\VendorImportRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VendorImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['Nama', 'Rekening', 'Nomor Handphone', 'NPWP', 'Status PKP', 'Jenis Usaha', 'Aktif'];

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
    private function buatFileExcel(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADER, null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('vendor_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'vendor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRow(array $override = []): array
    {
        return array_values(array_replace([
            'nama' => 'PT Uji Sejahtera',
            'rekening' => '009-8877',
            'nomor_handphone' => '081234567890',
            'npwp' => '01.234.567.8-901.000',
            'status_pkp' => 'PKP',
            'jenis_usaha' => 'Percetakan',
            'aktif' => 'Ya',
        ], $override));
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.vendor.create'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.vendor.create'))->assertForbidden();

        $file = $this->buatFileExcel([$this->baseRow()]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.vendor.store'), ['file' => $file])->assertForbidden();
    }

    public function test_template_dapat_diunduh_dan_header_sesuai(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.vendor.template'))
            ->assertOk()->assertDownload('template-import-vendor.xlsx');

        $this->assertSame(self::HEADER, (new VendorTemplateExport)->array()[0]);
    }

    public function test_upload_menolak_file_dengan_mime_yang_tidak_didukung(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $fileTeks = UploadedFile::fake()->create('data.txt', 10, 'text/plain');

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.vendor.store'), ['file' => $fileTeks])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, VendorImport::count());
    }

    // ---------------- Preview tanpa mutasi ----------------

    public function test_preview_tidak_mengubah_data_vendor(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $import = VendorImport::firstOrFail();

        $this->assertSame(0, Vendor::count());
        $this->assertSame(VendorImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.vendor.preview', $import))->assertOk();

        $this->assertSame(0, Vendor::count());
    }

    // ---------------- Konfirmasi: baru & update ----------------

    public function test_konfirmasi_menyimpan_baris_baru_dan_update_nama_yang_sudah_ada_serta_mencatat_audit(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = Vendor::create(['nama' => 'CV Lama Jaya', 'rekening' => '111-000', 'aktif' => true]);

        $file = $this->buatFileExcel([
            $this->baseRow(), // baru
            $this->baseRow(['nama' => $existing->nama, 'rekening' => '222-999', 'status_pkp' => 'Non-PKP']), // update
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $import = VendorImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_ditolak);

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.konfirmasi', $import));
        $response->assertRedirect(route('manajemen-data.index'));

        $import->refresh();
        $this->assertSame(VendorImport::STATUS_COMMITTED, $import->status);
        $this->assertSame(2, Vendor::count());
        $this->assertSame('222-999', $existing->fresh()->rekening);
        $this->assertFalse($existing->fresh()->pkp);

        $log = AuditLog::where('aktivitas', 'Import Vendor')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Baru: 1', $log->keterangan);
        $this->assertStringContainsString('Update: 1', $log->keterangan);
    }

    public function test_konfirmasi_dua_kali_pada_batch_yang_sama_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $import = VendorImport::firstOrFail();

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.konfirmasi', $import));
        $this->assertSame(1, Vendor::count());

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        $this->assertSame(1, Vendor::count());
    }

    // ---------------- Validasi baris ----------------

    public function test_nama_kosong_ditolak_dan_nama_duplikat_dalam_file_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel([
            $this->baseRow(['nama' => '']),
            $this->baseRow(['nama' => 'Vendor A']),
            $this->baseRow(['nama' => 'Vendor A']), // duplikat dengan baris sebelumnya
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $import = VendorImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(2, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('aksi', VendorImportRow::AKSI_DITOLAK)->pluck('alasan');
        $this->assertTrue($ditolak->contains(fn ($a) => str_contains($a, 'Nama kosong')));
        $this->assertTrue($ditolak->contains(fn ($a) => str_contains($a, 'Duplikat Nama')));
    }

    /**
     * Nomor handphone vendor dipakai fitur Kirim Notifikasi di Data NPD, dan
     * hanya bisa diisi lewat import (vendor tidak punya halaman CRUD). Sel yang
     * dikosongkan sengaja TIDAK menghapus nomor yang sudah tersimpan, supaya
     * re-import berkas export lama - yang belum punya kolom ini - tidak
     * diam-diam mengosongkan nomor yang sudah dikumpulkan.
     */
    public function test_nomor_handphone_tersimpan_dan_sel_kosong_tidak_menghapus_nomor_lama(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel([$this->baseRow(['nomor_handphone' => '0812-3456-7890'])]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.konfirmasi', VendorImport::latest('id')->firstOrFail()));

        $vendor = Vendor::where('nama', 'PT Uji Sejahtera')->firstOrFail();
        $this->assertSame('0812-3456-7890', $vendor->nomor_handphone);

        // Berkas berikutnya memperbarui vendor yang sama tanpa mengisi kolom itu.
        $file = $this->buatFileExcel([$this->baseRow(['nomor_handphone' => '', 'jenis_usaha' => 'Katering'])]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.konfirmasi', VendorImport::latest('id')->firstOrFail()));

        $vendor->refresh();
        $this->assertSame('Katering', $vendor->jenis_usaha);
        $this->assertSame('0812-3456-7890', $vendor->nomor_handphone);
    }

    public function test_batalkan_staging_menghapus_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.vendor.store'), ['file' => $file]);
        $import = VendorImport::firstOrFail();

        $this->actingAs($superadmin)->delete(route('manajemen-data.import.vendor.batalkan', $import))->assertRedirect();
        $this->assertSame(0, VendorImport::count());
        $this->assertSame(0, Vendor::count());
    }
}
