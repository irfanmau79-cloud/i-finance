<?php

namespace Tests\Feature;

use App\Exports\KpaSheetExport;
use App\Exports\MasterAnggaranExport;
use App\Exports\NpdExport;
use App\Exports\PegawaiExport;
use App\Exports\PejabatExport;
use App\Exports\PejabatOpdSheetExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmUpGuExport;
use App\Exports\TaggingExport;
use App\Exports\VendorExport;
use App\Models\AuditLog;
use App\Models\Kpa;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ManajemenDataExportTest extends TestCase
{
    use RefreshDatabase;

    /** Semua kunci export yang terdaftar di route/controller. */
    private const JENIS = ['master-anggaran', 'npd', 'spm-up-gu', 'spm-ls', 'pegawai', 'vendor', 'tagging', 'pejabat'];

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatMasterAnggaran(): MasterAnggaran
    {
        $tagging = Tagging::create(['nama' => 'Tagging Uji', 'aktif' => true]);

        return MasterAnggaran::create([
            'program' => 'Program Uji Manajemen Data',
            'kegiatan' => 'Kegiatan Uji Manajemen Data',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Manajemen Data',
            'kode_rekening' => '5.1.02.05.01.9001',
            'uraian_rekening' => 'Belanja Pengujian Manajemen Data',
            'tagging_id' => $tagging->id,
            'pagu' => 25_000_000,
            'aktif' => true,
        ]);
    }

    // ---------------- Akses ----------------

    public function test_hanya_superadmin_dan_bendahara_pengeluaran_dapat_mengakses_manajemen_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $bendahara = $this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN);
        $pptk = $this->buatUser(User::ROLE_PPTK);
        $bpp = $this->buatUser(User::ROLE_BPP);
        $verifikator = $this->buatUser(User::ROLE_VERIFIKATOR);

        Excel::fake();

        foreach ([$superadmin, $bendahara] as $user) {
            $this->actingAs($user)->get(route('manajemen-data.index'))->assertOk();
            $this->actingAs($user)->get(route('manajemen-data.export', 'tagging'))->assertOk();
        }

        foreach ([$pptk, $bpp, $verifikator] as $user) {
            $this->actingAs($user)->get(route('manajemen-data.index'))->assertForbidden();
            $this->actingAs($user)->get(route('manajemen-data.export', 'tagging'))->assertForbidden();
        }
    }

    public function test_jenis_export_yang_tidak_dikenal_menghasilkan_404(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->get('/manajemen-data/export/tidak-ada')->assertNotFound();
    }

    // ---------------- Header & jumlah baris per jenis ----------------

    public function test_export_master_anggaran_header_dan_isi_benar(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'master-anggaran'))->assertOk();

        $export = new MasterAnggaranExport;
        $this->assertSame(
            ['Tahun Anggaran', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu', 'Aktif'],
            $export->headings()
        );
        $this->assertSame(1, $export->jumlahBaris());

        $row = $export->query()->first();
        $mapped = $export->map($row);
        $this->assertSame(2026, $mapped[0]);
        $this->assertSame($anggaran->sub_kegiatan, $mapped[3]);
        $this->assertSame(25_000_000.0, $mapped[7]);
        $this->assertSame('Tagging Uji', $mapped[6]);
        $this->assertSame('Ya', $mapped[8]);
    }

    public function test_export_npd_header_dan_isi_benar(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $dibuatOleh = $this->buatUser(User::ROLE_PPTK, 'pembuat');

        $npd = Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'nomor_lengkap' => '01/NPD-Keu.1.IBC/7/2026',
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_500_000,
            'terbilang' => 'satu juta lima ratus ribu rupiah',
            'status' => 'Draft NPD - PPTK',
            'dibuat_oleh' => $dibuatOleh->id,
        ]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'npd'))->assertOk();

        $export = new NpdExport;
        $this->assertSame(
            ['Nomor NPD', 'Jenis', 'Tanggal NPD', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening',
                'Tagging', 'Jenis Panjar', 'Nominal', 'Terbilang', 'Penerima', 'Status', 'Catatan',
                'Dibuat Oleh', 'Dibuat Pada'],
            $export->headings()
        );
        $this->assertSame(1, $export->jumlahBaris());

        $row = $export->query()->first();
        $mapped = $export->map($row);
        $this->assertSame($npd->nomor_lengkap, $mapped[0]);
        $this->assertSame('Barang/Jasa', $mapped[1]);
        $this->assertSame('2026-07-20', $mapped[2]);
        $this->assertSame(1500000.0, $mapped[8]);
        $this->assertSame($dibuatOleh->username, $mapped[13]);
    }

    public function test_export_spm_up_gu_dan_ls_header_dan_isi_benar(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();

        $upGu = Spm::buatUpGu([
            'tanggal_dokumen' => '2026-07-01',
            'nomor_dokumen' => '001/SPM-UP/2026',
            'nominal' => 3_000_000,
            'penerima' => 'BPP Uji',
            'uraian' => 'Pengisian UP',
        ]);

        $ls = Spm::buatLs([
            'tanggal_dokumen' => '2026-07-05',
            'nomor_dokumen' => '002/SPM-LS/2026',
            'baris' => [['master_anggaran_id' => $anggaran->id, 'nominal' => 2_000_000]],
            'penerima' => 'Vendor Uji',
            'uraian' => 'Pembayaran LS',
        ]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'spm-up-gu'))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'spm-ls'))->assertOk();

        $upGuExport = new SpmUpGuExport;
        $this->assertSame(1, $upGuExport->jumlahBaris());
        $mappedUpGu = $upGuExport->map($upGuExport->query()->first());
        $this->assertSame($upGu->nomor_dokumen, $mappedUpGu[0]);
        $this->assertSame(3000000.0, $mappedUpGu[4]);

        $lsExport = new SpmLsExport;
        $this->assertSame(
            ['Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D',
                'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging',
                'Nominal', 'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2',
                'Penerima', 'Uraian', 'Dibuat Oleh', 'Dibuat Pada'],
            $lsExport->headings()
        );
        $this->assertSame(1, $lsExport->jumlahBaris());
        $mappedLs = $lsExport->map($lsExport->query()->first());
        $this->assertSame($ls->nomor_dokumen, $mappedLs[0]);
        $this->assertSame($anggaran->sub_kegiatan, $mappedLs[4]);
        $this->assertSame(2000000.0, $mappedLs[8]);
    }

    public function test_export_pegawai_vendor_dan_tagging_header_dan_jumlah_baris(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        Pegawai::create(['nama' => 'Budi Santoso', 'nip' => '123456789012345678', 'jabatan' => 'Staf', 'bidang' => 'Umum', 'golongan' => 'III', 'pangkat' => 'Penata', 'rekening' => '001-2233', 'aktif' => true]);
        Vendor::create(['nama' => 'PT Uji Sejahtera', 'rekening' => '009-8877', 'aktif' => true]);
        Tagging::create(['nama' => 'DAK Fisik', 'aktif' => true]);

        Excel::fake();
        foreach (['pegawai', 'vendor', 'tagging'] as $jenis) {
            $this->actingAs($superadmin)->get(route('manajemen-data.export', $jenis))->assertOk();
        }

        $pegawaiExport = new PegawaiExport;
        $this->assertSame(['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Aktif'], $pegawaiExport->headings());
        $this->assertSame(1, $pegawaiExport->jumlahBaris());

        $vendorExport = new VendorExport;
        $this->assertSame(['Nama', 'Rekening', 'Aktif'], $vendorExport->headings());
        $this->assertSame(1, $vendorExport->jumlahBaris());

        $taggingExport = new TaggingExport;
        $this->assertSame(['Nama', 'Aktif'], $taggingExport->headings());
        $this->assertSame(1, $taggingExport->jumlahBaris());
    }

    public function test_export_pejabat_gabungan_dua_sheet_dengan_jumlah_baris_benar(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $pa = Pegawai::create(['nama' => 'PA Uji', 'nip' => '111', 'jabatan' => 'Inspektur', 'bidang' => 'Umum', 'aktif' => true]);
        $bendahara = Pegawai::create(['nama' => 'Bendahara Uji', 'nip' => '222', 'jabatan' => 'Bendahara', 'bidang' => 'Umum', 'aktif' => true]);
        PejabatOpd::create(['pa_pegawai_id' => $pa->id, 'bendahara_pengeluaran_pegawai_id' => $bendahara->id, 'aktif' => true]);

        $kpaPegawai = Pegawai::create(['nama' => 'KPA Uji', 'nip' => '333', 'jabatan' => 'KPA', 'bidang' => 'Umum', 'aktif' => true]);
        $bppPegawai = Pegawai::create(['nama' => 'BPP Uji', 'nip' => '444', 'jabatan' => 'BPP', 'bidang' => 'Umum', 'aktif' => true]);
        Kpa::create(['kpa_pegawai_id' => $kpaPegawai->id, 'bpp_pegawai_id' => $bppPegawai->id, 'nama_jabatan' => 'Keu 1', 'aktif' => true]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'pejabat'))->assertOk();

        $export = new PejabatExport;
        $this->assertSame(2, $export->jumlahBaris());

        $sheets = $export->sheets();
        $this->assertArrayHasKey('Pejabat OPD', $sheets);
        $this->assertArrayHasKey('KPA & BPP', $sheets);
        $this->assertInstanceOf(PejabatOpdSheetExport::class, $sheets['Pejabat OPD']);
        $this->assertInstanceOf(KpaSheetExport::class, $sheets['KPA & BPP']);
        $this->assertSame(['Pengguna Anggaran (PA)', 'NIP PA', 'Bendahara Pengeluaran', 'NIP Bendahara Pengeluaran', 'Aktif'], $sheets['Pejabat OPD']->headings());
        $this->assertSame(['Nama Jabatan (KEU)', 'KPA', 'NIP KPA', 'BPP', 'NIP BPP', 'Aktif'], $sheets['KPA & BPP']->headings());
    }

    // ---------------- Tidak ada field rahasia ----------------

    public function test_tidak_ada_export_yang_memuat_field_rahasia(): void
    {
        $forbidden = ['password', 'token', 'session', 'remember'];

        $exports = [
            new MasterAnggaranExport,
            new NpdExport,
            new SpmUpGuExport,
            new SpmLsExport,
            new PegawaiExport,
            new VendorExport,
            new TaggingExport,
            new PejabatOpdSheetExport,
            new KpaSheetExport,
        ];

        foreach ($exports as $export) {
            foreach ($export->headings() as $heading) {
                foreach ($forbidden as $word) {
                    $this->assertStringNotContainsStringIgnoringCase($word, $heading, get_class($export).' header "'.$heading.'" mengandung kata terlarang "'.$word.'"');
                }
            }
        }
    }

    public function test_export_npd_dan_spm_tidak_membocorkan_hash_password_pembuat(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $dibuatOleh = $this->buatUser(User::ROLE_PPTK, 'pembuat-rahasia');

        Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Draft NPD - PPTK',
            'dibuat_oleh' => $dibuatOleh->id,
        ]);

        $export = new NpdExport;
        $row = $export->query()->first();
        $mapped = $export->map($row);

        $this->assertNotContains($dibuatOleh->password, $mapped);
        $this->assertContains($dibuatOleh->username, $mapped);
    }

    // ---------------- Audit log ----------------

    public function test_export_mencatat_audit_log_jenis_waktu_user_dan_jumlah_baris(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        Tagging::create(['nama' => 'Tagging Audit', 'aktif' => true]);
        Tagging::create(['nama' => 'Tagging Audit 2', 'aktif' => true]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'tagging'))->assertOk();

        $log = AuditLog::where('aktivitas', 'Export Data')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($superadmin->username, $log->username);
        $this->assertStringContainsString('Tagging', $log->keterangan);
        $this->assertStringContainsString('Baris: 2', $log->keterangan);
    }
}
