<?php

namespace Tests\Feature;

use App\Exports\PegawaiTemplateExport;
use App\Models\AuditLog;
use App\Models\Pegawai;
use App\Models\PegawaiImport;
use App\Models\PegawaiImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Nomor Handphone', 'Aktif'];

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

        $path = sys_get_temp_dir().'/'.uniqid('pegawai_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'pegawai.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRow(array $override = []): array
    {
        return array_values(array_replace([
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'jabatan' => 'Auditor Muda',
            'bidang' => 'Pengawasan',
            'golongan' => 'III/c',
            'pangkat' => 'Penata',
            'rekening' => '001-2233-4455',
            'nomor_handphone' => '081234567890',
            'aktif' => 'Ya',
        ], $override));
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.pegawai.create'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.pegawai.create'))->assertForbidden();

        $file = $this->buatFileExcel([$this->baseRow()]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file])->assertForbidden();
    }

    public function test_template_dapat_diunduh_dan_header_sesuai(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.pegawai.template'))
            ->assertOk()->assertDownload('template-import-pegawai.xlsx');

        $this->assertSame(self::HEADER, (new PegawaiTemplateExport)->array()[0]);
    }

    public function test_upload_menolak_file_dengan_mime_yang_tidak_didukung(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $fileTeks = UploadedFile::fake()->create('data.txt', 10, 'text/plain');

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.pegawai.store'), ['file' => $fileTeks])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, PegawaiImport::count());
    }

    // ---------------- Preview tanpa mutasi ----------------

    public function test_preview_tidak_mengubah_data_pegawai(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $import = PegawaiImport::firstOrFail();

        $this->assertSame(0, Pegawai::count());
        $this->assertSame(PegawaiImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.pegawai.preview', $import))->assertOk();

        $this->assertSame(0, Pegawai::count());
        $import->refresh();
        $this->assertSame(PegawaiImport::STATUS_STAGED, $import->status);
    }

    // ---------------- Konfirmasi: baru & update ----------------

    public function test_konfirmasi_menyimpan_baris_baru_dan_update_nip_yang_sudah_ada_serta_mencatat_audit(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = Pegawai::create([
            'nama' => 'Nama Lama', 'nip' => '199001012015011002', 'jabatan' => 'Staf Lama',
            'bidang' => 'Umum', 'aktif' => true,
        ]);

        $file = $this->buatFileExcel([
            $this->baseRow(), // baru
            $this->baseRow(['nip' => $existing->nip, 'nama' => 'Nama Baru', 'rekening' => '999-8888']), // update
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $import = PegawaiImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_ditolak);

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.konfirmasi', $import));
        $response->assertRedirect(route('manajemen-data.index'));

        $import->refresh();
        $this->assertSame(PegawaiImport::STATUS_COMMITTED, $import->status);
        $this->assertSame(2, Pegawai::count());
        $this->assertSame('Nama Baru', $existing->fresh()->nama);
        $this->assertSame('999-8888', $existing->fresh()->rekening);

        $log = AuditLog::where('aktivitas', 'Import Pegawai')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Baru: 1', $log->keterangan);
        $this->assertStringContainsString('Update: 1', $log->keterangan);
    }

    public function test_konfirmasi_dua_kali_pada_batch_yang_sama_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $import = PegawaiImport::firstOrFail();

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.konfirmasi', $import));
        $this->assertSame(1, Pegawai::count());

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        $this->assertSame(1, Pegawai::count());
    }

    // ---------------- Validasi baris ----------------

    public function test_baris_wajib_kosong_ditolak_dan_nip_duplikat_dalam_file_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel([
            $this->baseRow(['nama' => '']), // wajib kosong -> ditolak
            $this->baseRow(['nip' => '111']),
            $this->baseRow(['nip' => '111', 'nama' => 'Duplikat NIP']), // duplikat dengan baris sebelumnya
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $import = PegawaiImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(2, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('aksi', PegawaiImportRow::AKSI_DITOLAK)->pluck('alasan');
        $this->assertTrue($ditolak->contains(fn ($a) => str_contains($a, 'wajib diisi')));
        $this->assertTrue($ditolak->contains(fn ($a) => str_contains($a, 'Duplikat NIP')));
    }

    /**
     * Sama seperti pada import Vendor: sel Nomor Handphone yang dikosongkan
     * TIDAK menghapus nomor yang sudah tersimpan, supaya re-import berkas
     * export lama tidak mengosongkan data yang sudah dikumpulkan untuk fitur
     * Kirim Notifikasi di Data NPD.
     */
    public function test_nomor_handphone_tersimpan_dan_sel_kosong_tidak_menghapus_nomor_lama(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel([$this->baseRow(['nomor_handphone' => '0812-3456-7890'])]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.konfirmasi', PegawaiImport::latest('id')->firstOrFail()));

        $pegawai = Pegawai::where('nip', '198501012010011001')->firstOrFail();
        $this->assertSame('0812-3456-7890', $pegawai->nomor_handphone);

        $file = $this->buatFileExcel([$this->baseRow(['nomor_handphone' => '', 'jabatan' => 'Auditor Madya'])]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.konfirmasi', PegawaiImport::latest('id')->firstOrFail()));

        $pegawai->refresh();
        $this->assertSame('Auditor Madya', $pegawai->jabatan);
        $this->assertSame('0812-3456-7890', $pegawai->nomor_handphone);
    }

    public function test_batalkan_staging_menghapus_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pegawai.store'), ['file' => $file]);
        $import = PegawaiImport::firstOrFail();

        $this->actingAs($superadmin)->delete(route('manajemen-data.import.pegawai.batalkan', $import))->assertRedirect();
        $this->assertSame(0, PegawaiImport::count());
        $this->assertSame(0, Pegawai::count());
    }
}
