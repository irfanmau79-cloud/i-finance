<?php

namespace Tests\Feature;

use App\Exports\MasterAnggaranExport;
use App\Exports\NpdExport;
use App\Exports\PegawaiExport;
use App\Exports\PerjalananDinasExport;
use App\Exports\SpjPerjalananDinasExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmUpGuExport;
use App\Exports\TunjanganKeluargaExport;
use App\Exports\VendorExport;
use App\Models\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Spm;
use App\Models\Tagging;
use App\Models\TunjanganKeluarga;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ManajemenDataExportTest extends TestCase
{
    use RefreshDatabase;

    /** Semua kunci export yang terdaftar di route/controller. */
    private const JENIS = ['master-anggaran', 'rak-bulanan', 'npd', 'perjalanan-dinas', 'spj-perjalanan-dinas', 'spm-up-gu', 'spm-ls', 'pegawai', 'vendor', 'tunjangan-keluarga'];

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatMasterAnggaran(string $kodeRekening = '5.1.02.05.01.9001'): MasterAnggaran
    {
        $tagging = Tagging::create(['nama' => 'Tagging Uji '.$kodeRekening, 'aktif' => true]);

        return MasterAnggaran::create([
            'program' => 'Program Uji Manajemen Data',
            'kegiatan' => 'Kegiatan Uji Manajemen Data',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Manajemen Data',
            'kode_rekening' => $kodeRekening,
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
            $this->actingAs($user)->get(route('manajemen-data.export', 'master-anggaran'))->assertOk();
        }

        foreach ([$pptk, $bpp, $verifikator] as $user) {
            $this->actingAs($user)->get(route('manajemen-data.index'))->assertForbidden();
            $this->actingAs($user)->get(route('manajemen-data.export', 'master-anggaran'))->assertForbidden();
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
        $this->assertSame('Tagging Uji 5.1.02.05.01.9001', $mapped[6]);
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

    public function test_export_perjalanan_dinas_satu_baris_per_anggota_tim(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaran = $this->buatMasterAnggaran();
        $pegawai = Pegawai::create(['nama' => 'Pejalan Uji', 'nip' => 'PD-001', 'jabatan' => 'Auditor', 'bidang' => 'Irban I', 'aktif' => true]);

        $npd = Npd::create([
            'jenis' => 'pd', 'master_anggaran_id' => $anggaran->id, 'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'nomor_lengkap' => '010/NPD-PD/2026', 'tanggal_npd' => '2026-07-15', 'nominal' => 1_200_000,
            'terbilang' => 'satu juta dua ratus ribu rupiah', 'status' => 'Selesai', 'detail_json' => ['uraian_sp' => 'Perjalanan uji'],
        ]);
        $tim = $npd->tim()->create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama, 'jabatan' => $pegawai->jabatan,
            'bidang_snapshot' => $pegawai->bidang, 'is_penerima' => true, 'tol' => 50_000, 'tiket' => 0, 'representatif' => 0,
        ]);
        $tim->paket()->create(['cluster' => 'A', 'wilayah' => 'Kota Cimahi', 'lama_hari' => 2, 'tarif_uh' => 100_000, 'malam' => 1, 'tarif_akom' => 300_000]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'perjalanan-dinas'))->assertOk();

        $export = new PerjalananDinasExport;
        $this->assertSame(
            ['Tanggal NPD', 'Nomor NPD', 'Sub Kegiatan', 'Bidang', 'Nama', 'Jabatan', 'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Diterima'],
            $export->headings()
        );
        $this->assertSame(1, $export->jumlahBaris());

        $mapped = $export->map($export->query()->first());
        $this->assertSame('2026-07-15', $mapped[0]);
        $this->assertSame('010/NPD-PD/2026', $mapped[1]);
        $this->assertSame('Inspektur Pembantu I', $mapped[3]);
        $this->assertSame('Pejalan Uji', $mapped[4]);
        $this->assertSame(2.0, $mapped[6]);
        $this->assertSame(200_000.0, $mapped[7]);
        $this->assertSame(300_000.0, $mapped[8]);
    }

    public function test_export_spj_perjalanan_dinas_hanya_kode_rekening_perjalanan_dinas(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $anggaranPd = $this->buatMasterAnggaran(kodeRekening: '5.1.02.04.001.00001');
        $anggaranLain = $this->buatMasterAnggaran(kodeRekening: '5.1.02.05.01.9999');

        $npdPd = Npd::create([
            'jenis' => 'pd', 'master_anggaran_id' => $anggaranPd->id, 'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'nomor_lengkap' => '011/NPD-PD/2026', 'tanggal_npd' => '2026-07-16', 'nominal' => 900_000,
            'terbilang' => 'sembilan ratus ribu rupiah', 'status' => 'Selesai', 'detail_json' => ['uraian_sp' => 'SPJ uji'],
        ]);
        Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaranLain->id, 'keu' => '1', 'bulan' => 7, 'tahun' => 2026,
            'tanggal_npd' => '2026-07-16', 'nominal' => 500_000, 'terbilang' => 'lima ratus ribu rupiah', 'status' => 'Selesai',
        ]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'spj-perjalanan-dinas'))->assertOk();

        $export = new SpjPerjalananDinasExport;
        $this->assertSame(1, $export->jumlahBaris());
        $mapped = $export->map($export->query()->first());
        $this->assertSame('011/NPD-PD/2026', $mapped[1]);
        $this->assertSame(900_000.0, $mapped[6]);
        $this->assertSame('Belum', $mapped[7]);
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

    public function test_export_pegawai_dan_vendor_header_dan_jumlah_baris(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        Pegawai::create(['nama' => 'Budi Santoso', 'nip' => '123456789012345678', 'jabatan' => 'Staf', 'bidang' => 'Umum', 'golongan' => 'III', 'pangkat' => 'Penata', 'rekening' => '001-2233', 'aktif' => true]);
        Vendor::create(['nama' => 'PT Uji Sejahtera', 'rekening' => '009-8877', 'npwp' => '01.234.567.8-901.000', 'pkp' => true, 'jenis_usaha' => 'Percetakan', 'aktif' => true]);

        Excel::fake();
        foreach (['pegawai', 'vendor'] as $jenis) {
            $this->actingAs($superadmin)->get(route('manajemen-data.export', $jenis))->assertOk();
        }

        $pegawaiExport = new PegawaiExport;
        $this->assertSame(['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Aktif'], $pegawaiExport->headings());
        $this->assertSame(1, $pegawaiExport->jumlahBaris());

        $vendorExport = new VendorExport;
        $this->assertSame(['Nama', 'Rekening', 'NPWP', 'Status PKP', 'Jenis Usaha', 'Aktif'], $vendorExport->headings());
        $this->assertSame(1, $vendorExport->jumlahBaris());
        $mappedVendor = $vendorExport->map($vendorExport->query()->first());
        $this->assertSame('PKP', $mappedVendor[3]);
        $this->assertSame('Percetakan', $mappedVendor[4]);
    }

    public function test_export_tunjangan_keluarga_mencakup_semua_pegawai_aktif_termasuk_yang_belum_isi_data(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $sudahIsi = Pegawai::create(['nama' => 'Sudah Isi', 'nip' => '555', 'jabatan' => 'Staf', 'bidang' => 'Umum', 'aktif' => true]);
        Pegawai::create(['nama' => 'Belum Isi', 'nip' => '666', 'jabatan' => 'Staf', 'bidang' => 'Umum', 'aktif' => true]);
        Pegawai::create(['nama' => 'Nonaktif', 'nip' => '777', 'jabatan' => 'Staf', 'bidang' => 'Umum', 'aktif' => false]);

        $tk = TunjanganKeluarga::create(['pegawai_id' => $sudahIsi->id]);
        $tk->anggota()->create(['hubungan' => 'pasangan', 'nama' => 'Pasangan Uji', 'tanggal_lahir' => '1990-01-01', 'status_tunjangan' => true]);
        $tk->anggota()->create(['hubungan' => 'anak', 'nama' => 'Anak Uji', 'tanggal_lahir' => '2015-05-05', 'status_tunjangan' => true, 'keterangan' => 'Sekolah']);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'tunjangan-keluarga'))->assertOk();

        $export = new TunjanganKeluargaExport;
        $this->assertSame(2, $export->jumlahBaris(), 'Hanya pegawai aktif (bukan yang nonaktif) yang masuk export.');

        $rows = $export->query()->get();
        $mappedIsi = $export->map($rows->firstWhere('nip', '555'));
        $this->assertSame('Pasangan Uji', $mappedIsi[2]);
        $this->assertSame('Anak Uji', $mappedIsi[5]);
        $this->assertSame('Sekolah', $mappedIsi[8]);

        $mappedKosong = $export->map($rows->firstWhere('nip', '666'));
        $this->assertNull($mappedKosong[2]);
        $this->assertNull($mappedKosong[5]);
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
            new TunjanganKeluargaExport,
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
        Vendor::create(['nama' => 'Vendor Audit 1', 'aktif' => true]);
        Vendor::create(['nama' => 'Vendor Audit 2', 'aktif' => true]);

        Excel::fake();
        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'vendor'))->assertOk();

        $log = AuditLog::where('aktivitas', 'Export Data')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($superadmin->username, $log->username);
        $this->assertStringContainsString('Vendor', $log->keterangan);
        $this->assertStringContainsString('Baris: 2', $log->keterangan);
    }
}
