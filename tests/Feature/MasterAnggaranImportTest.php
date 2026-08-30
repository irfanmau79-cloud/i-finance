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
use App\Models\VersiPagu;
use App\Models\VersiPaguDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterAnggaranImportTest extends TestCase
{
    use RefreshDatabase;

    /** Header template: kode dan uraian terpisah, 12 kolom. */
    private const HEADER = [
        'Tahun',
        'Kode Program',
        'Program',
        'Kode Kegiatan',
        'Kegiatan',
        'Kode Sub Kegiatan',
        'Sub Kegiatan',
        'Kode Rekening',
        'Rekening',
        'Tagging',
        'Pagu',
        'Aktif/Non Aktif',
    ];

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
    private function buatFileExcel(array $baris, ?string $namaFile = null, ?array $header = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header ?? self::HEADER, null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('ma_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $namaFile ?? 'master-anggaran.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function baseRow(array $override = []): array
    {
        return array_values(array_replace([
            'tahun' => 2026,
            'kode_program' => '6.01',
            'program' => 'Program Uji Import',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji Import',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Uji Import',
            'kode_rekening' => '5.1.02.05.01.7001',
            'rekening' => 'Belanja Pengujian Import',
            'tagging' => '',
            'pagu' => 10_000_000,
            'aktif' => 'Aktif',
        ], $override));
    }

    /** @param  array<int, array>  $baris */
    private function unggah(User $user, array $baris, string $versiNama = 'DPA Murni', array $extra = []): MasterAnggaranImport
    {
        $this->actingAs($user)->post(route('manajemen-data.import.master-anggaran.store'), array_replace([
            'file' => $this->buatFileExcel($baris),
            'versi_nama' => $versiNama,
        ], $extra));

        return MasterAnggaranImport::latest('id')->firstOrFail();
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_import(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.create'))->assertOk();
        $this->actingAs($pptk)->get(route('manajemen-data.import.master-anggaran.create'))->assertForbidden();

        $file = $this->buatFileExcel([$this->baseRow()]);
        $this->actingAs($pptk)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $file,
            'versi_nama' => 'DPA Murni',
        ])->assertForbidden();
    }

    public function test_template_dapat_diunduh(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $pptk = $this->buatUser(User::ROLE_PPTK);

        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.template'))
            ->assertOk()->assertDownload('template-import-pagu-master-anggaran.xlsx');
        $this->actingAs($pptk)->get(route('manajemen-data.import.master-anggaran.template'))->assertForbidden();
    }

    public function test_upload_menolak_file_dengan_mime_yang_tidak_didukung(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $fileTeks = UploadedFile::fake()->create('data.txt', 10, 'text/plain');

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.store'), ['file' => $fileTeks, 'versi_nama' => 'DPA Murni'])
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

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $this->buatFileExcel($banyakBaris),
            'versi_nama' => 'DPA Murni',
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, MasterAnggaranImport::count());
    }

    // ---------------- Versi pagu wajib & unik ----------------

    public function test_upload_tanpa_nama_versi_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $this->buatFileExcel([$this->baseRow()]),
        ])->assertSessionHasErrors('versi_nama');

        $this->assertSame(0, MasterAnggaranImport::count());
    }

    public function test_nama_versi_tidak_boleh_bentrok_dalam_satu_tahun(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [$this->baseRow()], 'DPA Murni');
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $this->buatFileExcel([$this->baseRow()]),
            'versi_nama' => 'DPA Murni',
        ])->assertSessionHasErrors('versi_nama');

        $this->assertSame(1, VersiPagu::count());
    }

    // ---------------- Preview tanpa mutasi ----------------

    public function test_preview_tidak_mengubah_data_master_anggaran(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $import = $this->unggah($superadmin, [$this->baseRow()]);

        $this->assertSame(0, MasterAnggaran::count());
        $this->assertSame(0, VersiPagu::count());
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);

        // Lihat preview berkali-kali - murni baca, tidak boleh mengubah apa pun.
        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();

        $this->assertSame(0, MasterAnggaran::count());
        $this->assertSame(0, VersiPagu::count());
        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);
    }

    public function test_tahun_anggaran_2026_diterima_dan_ditampilkan_pada_preview(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $import = $this->unggah($superadmin, [$this->baseRow(['tahun' => 2026])], 'DPA Murni', ['tahun' => 2026]);

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
                'versi_nama' => 'DPA Murni',
                'tahun' => $tahun,
            ])->assertSessionHasErrors('tahun');
        }

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $this->buatFileExcel([$this->baseRow(['tahun' => 2027])], 'master-anggaran-2027.xlsx'),
            'versi_nama' => 'DPA Murni',
            'tahun' => 2026,
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, MasterAnggaranImport::count());
        $this->assertSame(0, MasterAnggaran::count());
    }

    // ---------------- Format kolom ----------------

    public function test_kode_dan_uraian_disimpan_pada_kolom_terpisah(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [$this->baseRow()]);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));

        $master = MasterAnggaran::firstOrFail();

        $this->assertSame('6.01', $master->kode_program);
        $this->assertSame('Program Uji Import', $master->program);
        $this->assertSame('6.01.01', $master->kode_kegiatan);
        $this->assertSame('Kegiatan Uji Import', $master->kegiatan);
        $this->assertSame('6.01.01.2.01', $master->kode_sub_kegiatan);
        $this->assertSame('Sub Kegiatan Uji Import', $master->sub_kegiatan);
        $this->assertSame('5.1.02.05.01.7001', $master->kode_rekening);
        $this->assertSame('Belanja Pengujian Import', $master->rekening);

        // Label gabungan tetap tersedia untuk tampilan & kunci pencocokan
        // lintas modul (RAK Bulanan, Pelimpahan).
        $this->assertSame('6.01.01.2.01 Sub Kegiatan Uji Import', $master->sub_kegiatan_lengkap);
        $this->assertSame('5.1.02.05.01.7001 Belanja Pengujian Import', $master->rekening_lengkap);
        $this->assertSame('6.01.01.2.01 sub kegiatan uji import', $master->sub_kegiatan_kunci);
        $this->assertSame('5.1.02.05.01.7001', $master->kode_rekening_bersih);
    }

    public function test_file_format_lama_dengan_kode_dan_nama_tergabung_tetap_terbaca(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $headerLama = ['Tahun Anggaran', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Tagging', 'Pagu', 'Aktif'];
        $barisLama = [
            2026,
            '6.01 Program Lama',
            '6.01.01 Kegiatan Lama',
            '6.01.01.2.09 Sub Kegiatan Lama',
            '5.1.02.05.01.7009 Belanja Format Lama',
            '',
            12_000_000,
            'Ya',
        ];

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => $this->buatFileExcel([$barisLama], 'format-lama.xlsx', $headerLama),
            'versi_nama' => 'DPA Murni',
        ]);

        $import = MasterAnggaranImport::latest('id')->firstOrFail();
        $this->assertSame(1, $import->jumlah_baru);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));

        $master = MasterAnggaran::firstOrFail();
        $this->assertSame('6.01.01.2.09', $master->kode_sub_kegiatan);
        $this->assertSame('Sub Kegiatan Lama', $master->sub_kegiatan);
        $this->assertSame('5.1.02.05.01.7009', $master->kode_rekening);
        $this->assertSame('Belanja Format Lama', $master->rekening);
    }

    public function test_pagu_hanya_menerima_angka_nominal(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7001', 'pagu' => 'Rp 10.000.000']),
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7002', 'pagu' => '1,5jt']),
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7003', 'pagu' => '12.500.000,50']),
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7004', 'pagu' => -1]),
        ]);

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(3, $import->jumlah_ditolak);

        $ditolak = $import->baris()->where('aksi', MasterAnggaranImportRow::AKSI_DITOLAK)->orderBy('nomor_baris')->get();
        $this->assertStringContainsString('angka nominal saja', $ditolak[0]->alasan);
        $this->assertStringContainsString('angka nominal saja', $ditolak[1]->alasan);
        $this->assertStringContainsString('negatif', $ditolak[2]->alasan);

        // Format ribuan Indonesia tetap diterima sebagai angka.
        $diterima = $import->baris()->where('aksi', MasterAnggaranImportRow::AKSI_BARU)->firstOrFail();
        $this->assertSame(12_500_000.5, (float) $diterima->pagu_baru);
    }

    // ---------------- Konfirmasi menghasilkan versi draft ----------------

    public function test_nomor_dpa_dari_formulir_ikut_tersimpan_ke_tahapan_pagu(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [$this->baseRow()], 'DPA Murni', [
            'versi_nomor_dpa' => '  027/DPA/2026  ',
        ]);

        // Masih di staging: nomornya menempel pada berkasnya dulu, tahapannya
        // belum dibuat sampai dikonfirmasi.
        $this->assertSame('027/DPA/2026', $import->versi_nomor_dpa);
        $this->assertSame(0, VersiPagu::count());

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertRedirect(route('versi-pagu.index'));

        $this->assertSame('027/DPA/2026', VersiPagu::sole()->nomor_dpa);
    }

    public function test_nomor_dpa_boleh_dikosongkan_saat_impor(): void
    {
        // Nomor DPA kerap terbit belakangan; impor tidak boleh terhalang.
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [$this->baseRow()], 'DPA Murni');

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertRedirect(route('versi-pagu.index'));

        $this->assertNull(VersiPagu::sole()->nomor_dpa);
    }

    public function test_konfirmasi_membuat_versi_draft_tanpa_mengubah_pagu_berlaku(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Lama',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Lama',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Update',
            'kode_rekening' => '5.1.02.05.01.8001',
            'rekening' => 'Belanja Lama',
            'pagu' => 5_000_000,
            'aktif' => true,
        ]);

        $import = $this->unggah($superadmin, [
            $this->baseRow(), // baru
            $this->baseRow([  // update baris existing
                'kode_sub_kegiatan' => $existing->kode_sub_kegiatan,
                'sub_kegiatan' => $existing->sub_kegiatan,
                'kode_rekening' => $existing->kode_rekening,
                'rekening' => $existing->rekening,
                'pagu' => 7_500_000,
            ]),
        ], 'DPA Pergeseran 1');

        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_ditolak);
        $this->assertSame(0, $import->jumlah_dinolkan);

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertRedirect(route('versi-pagu.index'));

        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_COMMITTED, $import->status);
        $this->assertNotNull($import->committed_at);

        $versi = VersiPagu::firstOrFail();
        $this->assertSame(VersiPagu::STATUS_DRAFT, $versi->status);
        $this->assertSame('DPA Pergeseran 1', $versi->nama);
        $this->assertSame(2, $versi->jumlah_baris);
        $this->assertSame(17_500_000.0, (float) $versi->total_pagu);
        $this->assertSame($versi->id, $import->versi_pagu_id);

        // Identitas mata anggaran sudah ada, TAPI pagu berlaku belum berubah.
        $this->assertSame(2, MasterAnggaran::count());
        $this->assertSame(5_000_000.0, (float) $existing->fresh()->pagu);

        $baruDibuat = MasterAnggaran::where('kode_rekening', '5.1.02.05.01.7001')->firstOrFail();
        $this->assertSame(0.0, (float) $baruDibuat->pagu);
        $this->assertFalse($baruDibuat->aktif);

        $log = AuditLog::where('aktivitas', 'Import Master Anggaran')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Tahapan: DPA Pergeseran 1 (draft)', $log->keterangan);
        $this->assertStringContainsString('Baru: 1', $log->keterangan);
        $this->assertStringContainsString('Update: 1', $log->keterangan);
    }

    public function test_konfirmasi_dua_kali_pada_batch_yang_sama_ditolak(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $import = $this->unggah($superadmin, [$this->baseRow()]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));
        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, VersiPagu::count());

        // Konfirmasi kedua pada batch yang sama tidak boleh menggandakan data.
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, VersiPagu::count());
    }

    public function test_konfirmasi_rollback_seluruhnya_bila_satu_baris_fatal(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import = $this->unggah($superadmin, [
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7101']), // akan sukses jika berdiri sendiri
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7102']), // dipaksa gagal fatal
        ]);

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

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        // Seluruh batch batal - baris pertama yang seharusnya sukses pun tidak
        // tersimpan, dan versi pagunya ikut hilang.
        $this->assertSame(0, MasterAnggaran::count());
        $this->assertSame(0, VersiPagu::count());
        $this->assertSame(0, VersiPaguDetail::count());

        $import->refresh();
        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->status);
    }

    public function test_import_file_yang_sama_dua_kali_tidak_menggandakan_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $baris = [$this->baseRow(['tagging' => 'Tagging Idempoten'])];

        // Putaran pertama.
        $import1 = $this->unggah($superadmin, $baris, 'DPA Murni');
        $this->assertSame(1, $import1->jumlah_baru);
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import1));

        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, Tagging::count());

        // Putaran kedua - isi file identik, hanya nama versinya berbeda.
        $import2 = $this->unggah($superadmin, $baris, 'DPA Pergeseran 1');
        $this->assertSame(0, $import2->jumlah_baru);
        $this->assertSame(1, $import2->jumlah_update);
        $this->assertSame(0, $import2->jumlah_dinolkan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import2));

        $this->assertSame(1, MasterAnggaran::count());
        $this->assertSame(1, Tagging::count());
        $this->assertSame(2, VersiPagu::count());
    }

    // ---------------- Baris yang hilang dari file ----------------

    public function test_mata_anggaran_yang_tidak_dicantumkan_ditandai_dinolkan(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $import1 = $this->unggah($superadmin, [
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7001']),
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7002']),
        ], 'DPA Murni');
        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import1));
        $this->assertSame(2, MasterAnggaran::count());

        // Versi berikutnya hanya memuat satu dari dua mata anggaran.
        $import2 = $this->unggah($superadmin, [
            $this->baseRow(['kode_rekening' => '5.1.02.05.01.7001']),
        ], 'DPA Pergeseran 1');

        $this->assertSame(1, $import2->jumlah_update);
        $this->assertSame(1, $import2->jumlah_dinolkan);

        $dinolkan = $import2->baris()->where('aksi', MasterAnggaranImportRow::AKSI_DINOLKAN)->firstOrFail();
        $this->assertSame('5.1.02.05.01.7002', $dinolkan->kode_rekening);
        $this->assertSame(0, (int) $dinolkan->pagu_baru);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import2));

        $versi2 = VersiPagu::where('nama', 'DPA Pergeseran 1')->firstOrFail();
        $this->assertSame(2, $versi2->jumlah_baris);

        $detailNol = $versi2->detail()->where('master_anggaran_id', $dinolkan->master_anggaran_id)->firstOrFail();
        $this->assertSame(0.0, (float) $detailNol->pagu);
        $this->assertFalse($detailNol->aktif);
    }

    // ---------------- Pagu lebih kecil dari yang sudah terpakai ----------------

    public function test_pagu_lebih_kecil_dari_dana_terikat_ditandai_dan_memblokir_aktivasi(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $existing = MasterAnggaran::create([
            'kode_program' => '6.01',
            'program' => 'Program Uji Pagu Kecil',
            'kode_kegiatan' => '6.01.01',
            'kegiatan' => 'Kegiatan Uji Pagu Kecil',
            'kode_sub_kegiatan' => '6.01.01.2.01',
            'sub_kegiatan' => 'Sub Kegiatan Pagu Kecil',
            'kode_rekening' => '5.1.02.05.01.9101',
            'rekening' => 'Belanja Pengujian Pagu Kecil',
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

        // Dana terikat NPD (6jt) + realisasi LS (5jt) = minimum 11jt. Ajukan pagu 8jt.
        $import = $this->unggah($superadmin, [
            $this->baseRow([
                'kode_sub_kegiatan' => $existing->kode_sub_kegiatan,
                'sub_kegiatan' => $existing->sub_kegiatan,
                'kode_rekening' => $existing->kode_rekening,
                'rekening' => $existing->rekening,
                'pagu' => 8_000_000,
            ]),
        ], 'DPA Pergeseran 1');

        // Baris tetap masuk versi (dokumen DPA harus utuh) tapi diberi peringatan.
        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_ditolak);

        $baris = $import->baris()->where('nomor_baris', '>', 0)->firstOrFail();
        $this->assertSame(MasterAnggaranImportRow::AKSI_UPDATE, $baris->aksi);
        $this->assertStringContainsString('lebih kecil dari dana terikat', $baris->alasan);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));

        // Konfirmasi tidak mengubah pagu berlaku.
        $this->assertSame(20_000_000.0, (float) $existing->fresh()->pagu);

        // Aktivasi diblokir selama kondisinya bertahan.
        $versi = VersiPagu::where('nama', 'DPA Pergeseran 1')->firstOrFail();
        $this->actingAs($superadmin)->post(route('versi-pagu.aktifkan', $versi))
            ->assertSessionHasErrors('aktivasi');

        $this->assertSame(VersiPagu::STATUS_DRAFT, $versi->fresh()->status);
        $this->assertSame(20_000_000.0, (float) $existing->fresh()->pagu);
    }
}
