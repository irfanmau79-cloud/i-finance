<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\Spm;
use App\Models\SpmImport;
use App\Models\SpmImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpmImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER_UP_GU = ['Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D', 'Nominal', 'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2', 'Penerima', 'Uraian'];

    private const HEADER_LS = ['Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Nominal', 'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2', 'Penerima', 'Uraian'];

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatMasterAnggaran(array $override = []): MasterAnggaran
    {
        return MasterAnggaran::create(array_replace([
            'program' => 'Program Uji Import SPM',
            'kegiatan' => 'Kegiatan Uji Import SPM',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Import SPM',
            'kode_rekening' => '5.1.02.05.02.6001',
            'uraian_rekening' => 'Belanja Pengujian Import SPM',
            'pagu' => 10_000_000,
            'aktif' => true,
        ], $override));
    }

    /** @param  array<int, array>  $baris */
    private function buatFileExcel(array $header, array $baris, ?string $namaFile = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('spm_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $namaFile ?? 'spm.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRowUpGu(array $override = []): array
    {
        return array_values(array_replace([
            'nomor_dokumen' => '001/SPM-UP/2026',
            'tanggal_dokumen' => '2026-07-01',
            'nomor_sp2d' => 'SP2D-001',
            'tanggal_sp2d' => '2026-07-02',
            'nominal' => 3_000_000,
            'ppn' => 0,
            'pph1' => 0,
            'jenis_pph1' => '',
            'pph2' => 0,
            'jenis_pph2' => '',
            'penerima' => 'BPP Uji',
            'uraian' => 'Pengisian UP',
        ], $override));
    }

    private function baseRowLs(MasterAnggaran $anggaran, array $override = []): array
    {
        return array_values(array_replace([
            'nomor_dokumen' => '001/SPM-LS/2026',
            'tanggal_dokumen' => '2026-07-01',
            'nomor_sp2d' => '',
            'tanggal_sp2d' => '',
            'sub_kegiatan' => $anggaran->sub_kegiatan,
            'kode_rekening' => $anggaran->kode_rekening,
            'uraian_rekening' => $anggaran->uraian_rekening,
            'tagging' => '',
            'nominal' => 4_000_000,
            'ppn' => 0,
            'pph1' => 0,
            'jenis_pph1' => '',
            'pph2' => 0,
            'jenis_pph2' => '',
            'penerima' => 'Vendor Uji',
            'uraian' => 'Pembayaran LS',
        ], $override));
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import_spm(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.spm.create', 'spm-up-gu'))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.spm.create', 'spm-ls'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.spm.create', 'spm-up-gu'))->assertForbidden();

        $file = $this->buatFileExcel(self::HEADER_UP_GU, [$this->baseRowUpGu()]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.spm.store', 'spm-up-gu'), ['file' => $file])->assertForbidden();
    }

    public function test_jenis_url_yang_tidak_dikenal_tidak_bisa_diakses(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        // 'tidak-ada' gagal whereIn di rute GET create, tapi URI yang sama
        // masih cocok dengan rute DELETE batalkan/{import} (tidak dibatasi
        // whereIn) - jadi router mengembalikan 405 (URI dikenali, method
        // tidak cocok), bukan 404.
        $this->actingAs($superadmin)->get('/manajemen-data/import/spm/tidak-ada')->assertStatus(405);
    }

    // ---------------- Preview tanpa mutasi ----------------

    public function test_preview_tidak_mengubah_data_spm_maupun_master_anggaran(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $file = $this->buatFileExcel(self::HEADER_LS, [$this->baseRowLs($anggaran)]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-ls'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $this->assertSame(0, Spm::count());
        $this->assertSame(SpmImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.spm.preview', $import))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.spm.preview', $import))->assertOk();

        $this->assertSame(0, Spm::count());
        $this->assertSame(10_000_000.0, (float) $anggaran->fresh()->pagu);
        $import->refresh();
        $this->assertSame(SpmImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        $baris = $import->baris()->firstOrFail();
        $this->assertSame(10_000_000.0, (float) $baris->sisa_sebelum);
        $this->assertSame(6_000_000.0, (float) $baris->sisa_sesudah);
    }

    // ---------------- Duplikasi dalam file ----------------

    public function test_duplikat_nomor_dokumen_dan_tanggal_dalam_file_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel(self::HEADER_UP_GU, [
            $this->baseRowUpGu(),
            $this->baseRowUpGu(['nominal' => 5_000_000]), // dokumen sama persis
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-up-gu'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('aksi', SpmImportRow::AKSI_DITOLAK)->firstOrFail();
        $this->assertStringContainsString('Duplikat Nomor Dokumen', $ditolak->alasan);
    }

    // ---------------- Mata anggaran tidak ditemukan (LS) ----------------

    public function test_baris_ls_ditolak_bila_mata_anggaran_tidak_ditemukan(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran(); // dibuat tapi tidak dipakai - kode rekening beda sengaja

        $file = $this->buatFileExcel(self::HEADER_LS, [
            $this->baseRowLs($anggaran, ['kode_rekening' => '5.1.02.99.99.9999', 'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Tidak Ada']),
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-ls'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $this->assertSame(0, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_ditolak);

        $baris = $import->baris()->firstOrFail();
        $this->assertSame(SpmImportRow::AKSI_DITOLAK, $baris->aksi);
        $this->assertStringContainsString('Mata anggaran tidak ditemukan', $baris->alasan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import));
        $this->assertSame(0, Spm::count());
    }

    // ---------------- Pagu terlampaui (agregat per batch) ----------------

    public function test_total_ls_per_mata_anggaran_dalam_batch_tidak_boleh_melebihi_sisa_tersedia(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran(['pagu' => 10_000_000]); // sisa tersedia awal = 10jt

        $file = $this->buatFileExcel(self::HEADER_LS, [
            $this->baseRowLs($anggaran, ['nomor_dokumen' => '001/SPM-LS/2026', 'nominal' => 6_000_000]), // muat (sisa jadi 4jt)
            $this->baseRowLs($anggaran, ['nomor_dokumen' => '002/SPM-LS/2026', 'nominal' => 6_000_000]), // tidak muat (butuh 6jt, sisa cuma 4jt)
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-ls'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_ditolak);

        $diterima = $import->baris()->where('nomor_dokumen', '001/SPM-LS/2026')->firstOrFail();
        $ditolak = $import->baris()->where('nomor_dokumen', '002/SPM-LS/2026')->firstOrFail();

        $this->assertSame(SpmImportRow::AKSI_BARU, $diterima->aksi);
        $this->assertSame(SpmImportRow::AKSI_DITOLAK, $ditolak->aksi);
        $this->assertStringContainsString('Melebihi sisa tersedia', $ditolak->alasan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import));

        $this->assertSame(1, Spm::count());
        $this->assertSame(4_000_000.0, $anggaran->fresh()->sisaTersedia());
    }

    // ---------------- Rollback bila satu baris fatal ----------------

    public function test_konfirmasi_rollback_seluruhnya_bila_satu_baris_fatal(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $file = $this->buatFileExcel(self::HEADER_UP_GU, [
            $this->baseRowUpGu(['nomor_dokumen' => '001/SPM-UP/2026']), // akan sukses jika berdiri sendiri
            $this->baseRowUpGu(['nomor_dokumen' => '002/SPM-UP/2026']), // dipaksa gagal fatal
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-up-gu'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $this->assertSame(2, $import->jumlah_baru);

        // Suntikkan kegagalan tak terduga lewat event model (mewakili error
        // infra/DB nyata) - murni untuk membuktikan mekanisme rollback
        // transaksi di konfirmasi(), portabel lintas driver DB (lihat catatan
        // yang sama di MasterAnggaranImportTest).
        Spm::saving(function (Spm $model) {
            if ($model->nomor_dokumen === '002/SPM-UP/2026') {
                throw new \RuntimeException('Simulasi kegagalan tak terduga saat simpan.');
            }
        });

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import));
        $response->assertSessionHasErrors('import');

        $this->assertSame(0, Spm::count());
        $import->refresh();
        $this->assertSame(SpmImport::STATUS_STAGED, $import->status);
    }

    // ---------------- Idempotensi ----------------

    public function test_import_file_up_gu_yang_sama_dua_kali_tidak_menggandakan_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $baris = [$this->baseRowUpGu()];

        $file1 = $this->buatFileExcel(self::HEADER_UP_GU, $baris);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-up-gu'), ['file' => $file1]);
        $import1 = SpmImport::latest('id')->firstOrFail();
        $this->assertSame(1, $import1->jumlah_baru);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import1));
        $this->assertSame(1, Spm::count());

        $file2 = $this->buatFileExcel(self::HEADER_UP_GU, $baris);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-up-gu'), ['file' => $file2]);
        $import2 = SpmImport::latest('id')->firstOrFail();

        $this->assertSame(0, $import2->jumlah_baru);
        $this->assertSame(1, $import2->jumlah_update);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import2));

        $this->assertSame(1, Spm::count());
    }

    // ---------------- Konfirmasi & audit ----------------

    public function test_konfirmasi_menyimpan_dan_mencatat_audit_per_jenis(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $file = $this->buatFileExcel(self::HEADER_LS, [$this->baseRowLs($anggaran, ['nominal' => 2_000_000])]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.store', 'spm-ls'), ['file' => $file]);
        $import = SpmImport::firstOrFail();

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.spm.konfirmasi', $import));
        $response->assertRedirect(route('manajemen-data.index'));

        $import->refresh();
        $this->assertSame(SpmImport::STATUS_COMMITTED, $import->status);
        $this->assertSame(1, Spm::count());
        $spm = Spm::firstOrFail();
        $this->assertSame('ls', $spm->jenis_spm);
        $this->assertSame($anggaran->id, $spm->master_anggaran_id);

        $log = AuditLog::where('aktivitas', 'Import SPM')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('LS', $log->keterangan);
        $this->assertStringContainsString('Baru: 1', $log->keterangan);
    }
}
