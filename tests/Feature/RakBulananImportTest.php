<?php

namespace Tests\Feature;

use App\Exports\RakBulananExport;
use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\RakBulanan;
use App\Models\RakBulananImport;
use App\Models\RakBulananImportRow;
use App\Models\Tagging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RakBulananImportTest extends TestCase
{
    use RefreshDatabase;

    // Header BARU (Prompt 12A) - TANPA kolom Tagging.
    private const HEADER = ['Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Pagu', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // Header LAMA (Prompt 12) - masih punya kolom Tagging, untuk uji kompatibilitas file lama.
    private const HEADER_LAMA = ['Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

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
            'program' => 'Program Uji RAK',
            'kegiatan' => 'Kegiatan Uji RAK',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji RAK',
            'kode_rekening' => '5.1.02.05.03.5001',
            'uraian_rekening' => 'Belanja Pengujian RAK',
            'pagu' => 24_000_000,
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

        $path = sys_get_temp_dir().'/'.uniqid('rak_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $namaFile ?? 'rak.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRow(MasterAnggaran $anggaran, array $bulanOverride = []): array
    {
        $bulan = array_replace(array_fill(1, 12, 2_000_000), $bulanOverride);

        return array_values(array_merge([
            $anggaran->sub_kegiatan,
            $anggaran->kode_rekening,
            $anggaran->uraian_rekening,
            (float) $anggaran->pagu,
        ], array_values($bulan)));
    }

    private function baseRowLama(MasterAnggaran $anggaran, ?string $taggingNama, array $bulanOverride = []): array
    {
        $bulan = array_replace(array_fill(1, 12, 2_000_000), $bulanOverride);

        return array_values(array_merge([
            $anggaran->sub_kegiatan,
            $anggaran->kode_rekening,
            $anggaran->uraian_rekening,
            $taggingNama ?? '',
            (float) $anggaran->pagu,
        ], array_values($bulan)));
    }

    private function store(User $user, $file, int $tahun = 2026)
    {
        return $this->actingAs($user)->post(route('manajemen-data.import.rak-bulanan.store'), ['file' => $file, 'tahun' => $tahun]);
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import_rak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);
        $anggaran = $this->buatMasterAnggaran();

        $this->actingAs($superadmin)->get(route('manajemen-data.import.rak-bulanan.create'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.rak-bulanan.create'))->assertForbidden();

        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran)]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.rak-bulanan.store'), ['file' => $file, 'tahun' => 2026])->assertForbidden();
    }

    // ---------------- Template tanpa Tagging ----------------

    public function test_template_export_rak_bulanan_tidak_memiliki_kolom_tagging(): void
    {
        $headings = (new RakBulananExport(2026))->headings();

        $this->assertNotContains('Tagging', $headings);
        $this->assertSame(['Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Pagu'], array_slice($headings, 0, 6));
    }

    public function test_export_menggabungkan_pagu_lintas_tagging_untuk_sub_kegiatan_kode_rekening_yang_sama(): void
    {
        $t1 = Tagging::create(['nama' => 'Tim A', 'aktif' => true]);
        $t2 = Tagging::create(['nama' => 'Tim B', 'aktif' => true]);

        $this->buatMasterAnggaran(['tagging_id' => $t1->id, 'pagu' => 10_000_000]);
        $this->buatMasterAnggaran(['tagging_id' => $t2->id, 'pagu' => 15_000_000]);

        $baris = (new RakBulananExport(2026))->query()->get();
        $this->assertCount(1, $baris, 'Satu Sub Kegiatan+Kode Rekening dengan 2 tagging harus jadi SATU baris export.');

        $peta = (new RakBulananExport(2026))->map($baris->first());
        $this->assertSame(25_000_000.0, $peta[5]); // indeks 5 = kolom Pagu
    }

    // ---------------- Import baru tidak butuh Tagging ----------------

    public function test_preview_tidak_mengubah_data_rak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran)]);

        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(0, RakBulanan::count());
        $this->assertSame(RakBulananImport::STATUS_STAGED, $import->status);
        $this->assertFalse($import->ada_kolom_tagging_lama);
        $this->assertSame(12, $import->jumlah_baru);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.rak-bulanan.preview', $import))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.rak-bulanan.preview', $import))->assertOk();

        $this->assertSame(0, RakBulanan::count());
        $import->refresh();
        $this->assertSame(RakBulananImport::STATUS_STAGED, $import->status);
    }

    public function test_upload_satu_tahun_penuh_menghasilkan_dua_belas_baris_yang_benar(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $nilaiPerBulan = [];
        for ($b = 1; $b <= 12; $b++) {
            $nilaiPerBulan[$b] = $b * 100_000;
        }

        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran, $nilaiPerBulan)]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(12, $import->total_baris);
        $this->assertSame(12, $import->jumlah_baru);
        $this->assertSame(0, $import->jumlah_ditolak);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));

        $this->assertSame(12, RakBulanan::where('sub_kegiatan_kunci', MasterAnggaran::normalisasiKunci($anggaran->sub_kegiatan))
            ->where('kode_rekening', $anggaran->kode_rekening)->where('tahun', 2026)->count());

        for ($b = 1; $b <= 12; $b++) {
            $this->assertSame((float) ($b * 100_000), $anggaran->fresh()->targetRakBulan($b, 2026));
        }

        $totalSetahun = array_sum($nilaiPerBulan);
        $this->assertSame((float) $totalSetahun, $anggaran->fresh()->targetRakKumulatifSampai(12, 2026));
    }

    // ---------------- Dua Tagging berbeda, satu RAK ----------------

    public function test_dua_tagging_berbeda_pada_sub_kegiatan_kode_rekening_sama_membaca_rak_yang_sama(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $t1 = Tagging::create(['nama' => 'Tim A', 'aktif' => true]);
        $t2 = Tagging::create(['nama' => 'Tim B', 'aktif' => true]);

        $anggaranA = $this->buatMasterAnggaran(['tagging_id' => $t1->id]);
        $anggaranB = $this->buatMasterAnggaran(['tagging_id' => $t2->id]);

        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaranA, [1 => 3_000_000])]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();
        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));

        // Hanya SATU baris RAK yang tercipta untuk kombinasi sub_kegiatan+kode_rekening ini.
        $this->assertSame(12, RakBulanan::count());

        // Baik anggaranA maupun anggaranB (tagging berbeda) membaca RAK yang SAMA persis.
        $this->assertSame(3_000_000.0, $anggaranA->fresh()->targetRakBulan(1, 2026));
        $this->assertSame(3_000_000.0, $anggaranB->fresh()->targetRakBulan(1, 2026));
    }

    public function test_tagging_berbeda_pada_file_lama_dianggap_duplikat_bukan_rak_terpisah(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $t1 = Tagging::create(['nama' => 'Tim A', 'aktif' => true]);
        $t2 = Tagging::create(['nama' => 'Tim B', 'aktif' => true]);
        $this->buatMasterAnggaran(['tagging_id' => $t1->id]);
        $anggaranContoh = $this->buatMasterAnggaran(['tagging_id' => $t2->id]);

        // Dua baris file lama, Sub Kegiatan + Kode Rekening SAMA, hanya Tagging berbeda.
        $file = $this->buatFileExcel(self::HEADER_LAMA, [
            $this->baseRowLama($anggaranContoh, 'Tim A', [1 => 1_000_000]),
            $this->baseRowLama($anggaranContoh, 'Tim B', [1 => 9_000_000]),
        ]);

        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertTrue($import->ada_kolom_tagging_lama);
        // Baris pertama diterima, baris kedua ditolak sebagai DUPLIKAT (bukan RAK kedua).
        $this->assertSame(12, $import->jumlah_baru);
        $this->assertSame(12, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('nomor_baris', 3)->first();
        $this->assertSame(RakBulananImportRow::AKSI_DITOLAK, $ditolak->aksi);
        $this->assertStringContainsString('Duplikat', $ditolak->alasan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));
        $this->assertSame(12, RakBulanan::count()); // bukan 24 - tidak tergandakan oleh tagging.
    }

    public function test_file_format_lama_dengan_tagging_kosong_tetap_bisa_diimpor(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $file = $this->buatFileExcel(self::HEADER_LAMA, [$this->baseRowLama($anggaran, null, [1 => 1_000_000])]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertTrue($import->ada_kolom_tagging_lama);
        $this->assertSame(12, $import->jumlah_baru);
        $this->assertSame(0, $import->jumlah_ditolak);
    }

    // ---------------- Duplikasi dalam file (format baru) ----------------

    public function test_duplikat_sub_kegiatan_kode_rekening_dalam_file_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $file = $this->buatFileExcel(self::HEADER, [
            $this->baseRow($anggaran, [1 => 1_000_000]),
            $this->baseRow($anggaran, [1 => 5_000_000]),
        ]);

        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(12, $import->jumlah_baru);
        $this->assertSame(12, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('nomor_baris', 3)->first();
        $this->assertSame(RakBulananImportRow::AKSI_DITOLAK, $ditolak->aksi);
        $this->assertStringContainsString('Duplikat Sub Kegiatan + Kode Rekening', $ditolak->alasan);
    }

    // ---------------- Kode Rekening harus berada dalam Sub Kegiatan ----------------

    public function test_baris_ditolak_bila_kode_rekening_tidak_ada_dalam_sub_kegiatan(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $baris = $this->baseRow($anggaran);
        $baris[1] = '5.1.02.99.99.9999'; // kode rekening sengaja tidak ada di master_anggaran

        $file = $this->buatFileExcel(self::HEADER, [$baris]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(0, $import->jumlah_baru);
        $this->assertSame(12, $import->jumlah_ditolak);

        $ditolak = $import->baris()->first();
        $this->assertSame(RakBulananImportRow::AKSI_DITOLAK, $ditolak->aksi);
        $this->assertStringContainsString('tidak ditemukan dalam Sub Kegiatan', $ditolak->alasan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));
        $this->assertSame(0, RakBulanan::count());
    }

    public function test_baris_ditolak_bila_nilai_bulan_negatif(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran, [3 => -500_000])]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(11, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('bulan', 3)->firstOrFail();
        $this->assertSame(RakBulananImportRow::AKSI_DITOLAK, $ditolak->aksi);
        $this->assertStringContainsString('non-negatif', $ditolak->alasan);
    }

    // ---------------- Agregasi bulanan/kumulatif & "RAK belum tersedia" ----------------

    public function test_target_rak_belum_tersedia_tidak_pernah_jatuh_ke_pagu_dibagi_12(): void
    {
        $anggaran = $this->buatMasterAnggaran(['pagu' => 12_000_000]);

        $this->assertNull($anggaran->targetRakBulan(1, 2026));
        $this->assertNull($anggaran->targetRakKumulatifSampai(1, 2026));
    }

    public function test_agregasi_kumulatif_menjumlahkan_bulan_yang_ada_dan_menganggap_nol_bulan_yang_kosong(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        // Hanya isi Januari & Maret - Februari sengaja dikosongkan (bukan 0, benar-benar tidak diisi).
        $baris = $this->baseRow($anggaran, [1 => 1_000_000, 3 => 3_000_000]);
        foreach ([2, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $bulanKosong) {
            $baris[3 + $bulanKosong] = ''; // kolom bulan di-blank-kan
        }

        $file = $this->buatFileExcel(self::HEADER, [$baris]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(2, $import->jumlah_baru); // hanya Jan & Mar yang jadi baris staging

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));

        $anggaran->refresh();
        $this->assertSame(1_000_000.0, $anggaran->targetRakBulan(1, 2026));
        $this->assertNull($anggaran->targetRakBulan(2, 2026)); // belum tersedia utk bulan spesifik ini
        $this->assertSame(4_000_000.0, $anggaran->targetRakKumulatifSampai(3, 2026)); // 1jt + 0(feb, tapi ada data thn ini) + 3jt
        $this->assertSame(4_000_000.0, $anggaran->targetRakKumulatifSampai(12, 2026));
    }

    // ---------------- Idempotensi ----------------

    public function test_import_file_yang_sama_dua_kali_tidak_menggandakan_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $baris = [$this->baseRow($anggaran)];

        $file1 = $this->buatFileExcel(self::HEADER, $baris);
        $this->store($superadmin, $file1);
        $import1 = RakBulananImport::latest('id')->firstOrFail();
        $this->assertSame(12, $import1->jumlah_baru);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import1));
        $this->assertSame(12, RakBulanan::count());

        $file2 = $this->buatFileExcel(self::HEADER, $baris);
        $this->store($superadmin, $file2);
        $import2 = RakBulananImport::latest('id')->firstOrFail();
        $this->assertSame(0, $import2->jumlah_baru);
        $this->assertSame(12, $import2->jumlah_update);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import2));
        $this->assertSame(12, RakBulanan::count());
    }

    // ---------------- Rollback bila satu baris fatal ----------------

    public function test_konfirmasi_rollback_seluruhnya_bila_satu_baris_fatal(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran, [1 => 1_000_000, 2 => 2_000_000])]);
        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->assertSame(12, $import->jumlah_baru);

        RakBulanan::saving(function (RakBulanan $model) {
            if ($model->bulan === 2) {
                throw new \RuntimeException('Simulasi kegagalan tak terduga saat simpan.');
            }
        });

        $response = $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));
        $response->assertSessionHasErrors('import');

        $this->assertSame(0, RakBulanan::count());
        $import->refresh();
        $this->assertSame(RakBulananImport::STATUS_STAGED, $import->status);
    }

    // ---------------- Audit ----------------

    public function test_konfirmasi_mencatat_audit(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $file = $this->buatFileExcel(self::HEADER, [$this->baseRow($anggaran, [1 => 1_000_000])]);

        $this->store($superadmin, $file);
        $import = RakBulananImport::firstOrFail();

        $this->actingAs($superadmin)->post(route('manajemen-data.import.rak-bulanan.konfirmasi', $import));

        $log = AuditLog::where('aktivitas', 'Import RAK Bulanan')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Tahun: 2026', $log->keterangan);
        $this->assertStringContainsString('Baru: 12', $log->keterangan);
    }
}
