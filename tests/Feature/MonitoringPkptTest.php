<?php

namespace Tests\Feature;

use App\Helpers\GuestSession;
use App\Models\Pkpt;
use App\Models\PkptImport;
use App\Models\User;
use App\Services\PkptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MonitoringPkptTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Nomor', 'Unit Kerja', 'Area Pengawasan dan Pembinaan', 'Jenis Kegiatan',
        'Tujuan dan Sasaran', 'Ruang Lingkup', 'Jumlah Tim', 'Estimasi Anggaran',
        'Realisasi', 'Rencana Pelaksanaan', 'Pelaksanaan', 'Jumlah Laporan', 'Terlaksana',
    ];

    private function buatUser(string $role, string $username = 'penguji'): User
    {
        return User::create([
            'username' => $username.'-'.str_replace('_', '', $role),
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    private function buatPkpt(array $override = []): Pkpt
    {
        return Pkpt::create(array_replace([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nomor' => '1',
            'unit_kerja' => 'Inspektur Pembantu I',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'tujuan' => 'Menilai efektivitas pelayanan',
            'ruang_lingkup' => 'Tahun berjalan',
            'jumlah_tim' => '2',
            'estimasi_anggaran' => 1_000_000,
            'realisasi' => 400_000,
            'rencana_pelaksanaan' => 'Maret',
            'pelaksanaan' => 'April',
            'jumlah_laporan' => '1',
            'terlaksana' => true,
        ], $override));
    }

    /** @param  array<int, array>  $baris */
    private function buatFileExcel(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADER, null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('pkpt_import_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'pkpt.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function barisFile(array $override = []): array
    {
        return array_values(array_replace([
            'nomor' => '1',
            'unit' => 'Irban I',
            'area' => 'Kesehatan',
            'jenis' => 'Audit Kinerja',
            'tujuan' => 'Menilai efektivitas',
            'ruang_lingkup' => 'Tahun berjalan',
            'jumlah_tim' => '2',
            'estimasi' => '1.000.000',
            'realisasi' => '400000',
            'rencana' => 'Maret',
            'pelaksanaan' => 'April',
            'jumlah_laporan' => '1',
            'terlaksana' => 'Ya',
        ], $override));
    }

    // ---------------- Agregat ----------------

    public function test_kartu_menghitung_capaian_dan_sisa_estimasi(): void
    {
        $this->buatPkpt(['nomor' => '1', 'estimasi_anggaran' => 1_000_000, 'realisasi' => 400_000, 'terlaksana' => true]);
        $this->buatPkpt(['nomor' => '2', 'estimasi_anggaran' => 3_000_000, 'realisasi' => 0, 'terlaksana' => false]);
        $this->buatPkpt(['nomor' => '3', 'estimasi_anggaran' => 0, 'realisasi' => 0, 'terlaksana' => false]);

        $kartu = app(PkptService::class)->ringkasan()['kartu'];

        $this->assertSame(3, $kartu['total_kegiatan']);
        $this->assertSame(1, $kartu['terlaksana']);
        $this->assertSame(2, $kartu['belum']);
        $this->assertSame(33.3, $kartu['persen']);
        $this->assertSame(4_000_000.0, $kartu['total_estimasi']);
        $this->assertSame(400_000.0, $kartu['total_realisasi']);
        $this->assertSame(3_600_000.0, $kartu['belum_terealisasi']);
    }

    public function test_baris_tanpa_area_dan_jenis_kegiatan_tidak_dihitung(): void
    {
        $this->buatPkpt(['nomor' => '1']);
        // Baris penyekat pada dokumen aslinya - bukan kegiatan.
        $this->buatPkpt(['nomor' => '2', 'area' => '', 'jenis_kegiatan' => '', 'estimasi_anggaran' => 9_000_000]);

        $ringkasan = app(PkptService::class)->ringkasan();

        $this->assertSame(1, $ringkasan['kartu']['total_kegiatan']);
        $this->assertSame(1_000_000.0, $ringkasan['kartu']['total_estimasi']);
    }

    public function test_urutan_unit_investigasi_selalu_paling_akhir(): void
    {
        $this->buatPkpt(['nomor' => '1', 'unit_kerja' => 'Inspektur Pembantu Investigasi']);
        $this->buatPkpt(['nomor' => '2', 'unit_kerja' => 'Inspektur Pembantu IV']);
        $this->buatPkpt(['nomor' => '3', 'unit_kerja' => 'Inspektur Pembantu I']);
        $this->buatPkpt(['nomor' => '10', 'unit_kerja' => 'Inspektur Pembantu I']);

        $ringkasan = app(PkptService::class)->ringkasan();

        $this->assertSame(
            ['Inspektur Pembantu I', 'Inspektur Pembantu I', 'Inspektur Pembantu IV', 'Inspektur Pembantu Investigasi'],
            array_column($ringkasan['rows'], 'unit')
        );
        // Nomor 3 sebelum 10: diurut sebagai angka, bukan sebagai teks.
        $this->assertSame(['3', '10'], array_slice(array_column($ringkasan['rows'], 'nomor'), 0, 2));
        $this->assertSame(
            ['Irban I', 'Irban IV', 'Investigasi'],
            array_column($ringkasan['perUnit'], 'unit_singkat')
        );
    }

    public function test_opsi_filter_periode_urut_januari_sampai_desember(): void
    {
        $this->buatPkpt(['nomor' => '1', 'rencana_pelaksanaan' => 'April']);
        $this->buatPkpt(['nomor' => '2', 'rencana_pelaksanaan' => 'Januari']);
        $this->buatPkpt(['nomor' => '3', 'rencana_pelaksanaan' => 'Desember']);
        $this->buatPkpt(['nomor' => '4', 'rencana_pelaksanaan' => 'Triwulan I']);

        $periode = app(PkptService::class)->ringkasan()['filterOpts']['periode'];

        $this->assertSame(['Januari', 'April', 'Desember', 'Triwulan I'], $periode);
    }

    public function test_data_tahun_lain_tidak_ikut_terhitung(): void
    {
        $this->buatPkpt(['nomor' => '1']);
        $this->buatPkpt(['nomor' => '2', 'tahun' => (int) config('anggaran.tahun_aktif') - 1]);

        $this->assertSame(1, app(PkptService::class)->ringkasan()['kartu']['total_kegiatan']);
    }

    // ---------------- Halaman ----------------

    public function test_halaman_menampilkan_tabel_dan_menyaring_per_unit(): void
    {
        $this->buatPkpt(['nomor' => '1', 'area' => 'Kesehatan']);
        $this->buatPkpt(['nomor' => '2', 'unit_kerja' => 'Inspektur Pembantu IV', 'area' => 'Pendidikan']);

        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->get(route('pkpt.index'))
            ->assertOk()
            ->assertSee('Monitoring PKPT')
            ->assertSee('Kesehatan')
            ->assertSee('Pendidikan');

        // "Kesehatan" tetap muncul sebagai pilihan filter, jadi yang diperiksa
        // sel tabelnya - bukan sekadar ada/tidaknya kata itu di halaman.
        $this->actingAs($superadmin)->get(route('pkpt.index', ['unit' => 'Inspektur Pembantu IV']))
            ->assertOk()
            ->assertSee('<td>Pendidikan</td>', false)
            ->assertDontSee('<td>Kesehatan</td>', false);
    }

    public function test_filter_status_memisahkan_terlaksana_dan_belum(): void
    {
        $this->buatPkpt(['nomor' => '1', 'area' => 'Kesehatan', 'terlaksana' => true]);
        $this->buatPkpt(['nomor' => '2', 'area' => 'Pendidikan', 'terlaksana' => false]);

        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->get(route('pkpt.index', ['status' => PkptService::STATUS_BELUM]))
            ->assertOk()
            ->assertSee('<td>Pendidikan</td>', false)
            ->assertDontSee('<td>Kesehatan</td>', false);
    }

    public function test_role_irban_boleh_membuka_monitoring_pkpt(): void
    {
        $this->buatPkpt();

        $this->actingAs($this->buatUser('irban2'))->get(route('pkpt.index'))->assertOk();
    }

    public function test_pengguna_layanan_tidak_boleh_membuka_monitoring_pkpt(): void
    {
        $this->buatPkpt();

        // Pengguna Layanan tidak memegang kunci menu 'pkpt' - GAS pun menutup
        // grup Analisis dan Tren untuk mereka.
        $this->withSession([GuestSession::kunciSesi() => true])
            ->get(route('pkpt.index'))
            ->assertForbidden();
    }

    // ---------------- Import ----------------

    public function test_import_menyimpan_baris_baru_setelah_dikonfirmasi(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([
            $this->barisFile(['nomor' => '1']),
            $this->barisFile(['nomor' => '2', 'unit' => 'Inspektur Pembantu Investigasi', 'terlaksana' => 'Tidak']),
        ]);

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.pkpt.store'), ['file' => $file, 'tahun' => 2026])
            ->assertRedirect();

        $import = PkptImport::sole();
        $this->assertSame(2, $import->jumlah_baru);
        $this->assertSame(0, $import->jumlah_ditolak);
        // Belum ada yang tersimpan sebelum dikonfirmasi - inti alur dry-run.
        $this->assertSame(0, Pkpt::count());

        $this->actingAs($superadmin)
            ->post(route('manajemen-data.import.pkpt.konfirmasi', $import))
            ->assertRedirect(route('manajemen-data.index'));

        $this->assertSame(2, Pkpt::count());

        // "Irban 1" dibakukan jadi "Inspektur Pembantu I".
        $pertama = Pkpt::where('nomor', '1')->sole();
        $this->assertSame('Inspektur Pembantu I', $pertama->unit_kerja);
        $this->assertSame(1_000_000.0, (float) $pertama->estimasi_anggaran);
        $this->assertTrue($pertama->terlaksana);
        $this->assertFalse(Pkpt::where('nomor', '2')->sole()->terlaksana);
    }

    public function test_import_ulang_memperbarui_baris_dengan_unit_dan_nomor_sama(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $this->buatPkpt(['nomor' => '1', 'estimasi_anggaran' => 500_000, 'terlaksana' => false]);

        $file = $this->buatFileExcel([$this->barisFile(['nomor' => '1', 'estimasi' => '2500000', 'terlaksana' => 'Ya'])]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pkpt.store'), ['file' => $file, 'tahun' => 2026]);
        $import = PkptImport::sole();

        $this->assertSame(1, $import->jumlah_update);
        $this->assertSame(0, $import->jumlah_baru);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pkpt.konfirmasi', $import));

        $this->assertSame(1, Pkpt::count());
        $this->assertSame(2_500_000.0, (float) Pkpt::sole()->estimasi_anggaran);
        $this->assertTrue(Pkpt::sole()->terlaksana);
    }

    public function test_nomor_sama_di_unit_berbeda_bukan_duplikat(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([
            $this->barisFile(['nomor' => '1', 'unit' => 'Inspektur Pembantu I']),
            $this->barisFile(['nomor' => '1', 'unit' => 'Inspektur Pembantu II']),
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pkpt.store'), ['file' => $file, 'tahun' => 2026]);

        $import = PkptImport::sole();
        $this->assertSame(2, $import->jumlah_baru);
        $this->assertSame(0, $import->jumlah_ditolak);
    }

    public function test_baris_bermasalah_ditolak_dengan_alasan(): void
    {
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);
        $file = $this->buatFileExcel([
            $this->barisFile(['nomor' => '']),
            $this->barisFile(['nomor' => '5', 'unit' => '']),
            $this->barisFile(['nomor' => '6', 'area' => '', 'jenis' => '']),
            $this->barisFile(['nomor' => '7']),
            $this->barisFile(['nomor' => '7']),
        ]);

        $this->actingAs($superadmin)->post(route('manajemen-data.import.pkpt.store'), ['file' => $file, 'tahun' => 2026]);

        $import = PkptImport::sole();
        $alasan = $import->baris()->orderBy('nomor_baris')->pluck('alasan', 'nomor_baris');

        $this->assertSame(4, $import->jumlah_ditolak);
        $this->assertSame(1, $import->jumlah_baru);
        $this->assertStringContainsString('Nomor kosong', (string) $alasan[2]);
        $this->assertStringContainsString('Unit Kerja kosong', (string) $alasan[3]);
        $this->assertStringContainsString('dua-duanya kosong', (string) $alasan[4]);
        $this->assertStringContainsString('ganda', (string) $alasan[6]);
    }

    public function test_import_hanya_untuk_superadmin_dan_bendahara(): void
    {
        $this->actingAs($this->buatUser(User::ROLE_PPTK))
            ->get(route('manajemen-data.import.pkpt.create'))
            ->assertForbidden();

        $this->actingAs($this->buatUser(User::ROLE_BENDAHARA_PENGELUARAN))
            ->get(route('manajemen-data.import.pkpt.create'))
            ->assertOk();
    }

    public function test_export_dan_template_dapat_diunduh(): void
    {
        $this->buatPkpt();
        $superadmin = $this->buatUser(User::ROLE_SUPERADMIN);

        $this->actingAs($superadmin)->get(route('manajemen-data.export', 'pkpt'))->assertOk();
        $this->actingAs($superadmin)->get(route('manajemen-data.import.pkpt.template'))->assertOk();
    }

    public function test_reset_data_pkpt_menghapus_seluruh_baris(): void
    {
        $this->buatPkpt();
        $this->buatPkpt(['nomor' => '2']);

        $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->post(route('manajemen-data.reset', 'pkpt'), ['konfirmasi' => 'HAPUS PKPT'])
            ->assertRedirect(route('manajemen-data.index'));

        $this->assertSame(0, Pkpt::count());
    }
}
