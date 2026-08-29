<?php

namespace Tests\Feature;

use App\Exports\TunjanganKeluargaExport;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\TunjanganKeluargaImportService;
use App\Services\TunjanganKeluargaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

/**
 * Modul Data Kepegawaian: sub menu Data Pegawai sebagai daftar induk, dan
 * Data Tunjangan Keluarga yang hanya memuat pegawai berhak (PNS & PPPK
 * Penuh Waktu) beserta alur export - isi - import timpa.
 */
class DataKepegawaianTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'kepeg-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function pegawai(string $nama, string $nip, string $status = Pegawai::STATUS_PNS): Pegawai
    {
        return Pegawai::create([
            'nama' => $nama,
            'nip' => $nip,
            'jabatan' => 'Auditor Ahli Muda',
            'golongan' => 'III/c',
            'pangkat' => 'Penata',
            'bidang' => 'Inspektur Pembantu I',
            'periode_kgb' => 'April 2026',
            'status_kepegawaian' => $status,
            'aktif' => true,
        ]);
    }

    // ---------------- Sub menu Data Pegawai ----------------

    public function test_halaman_data_pegawai_menampilkan_seluruh_kolom_kepegawaian(): void
    {
        $this->pegawai('Budi Santoso', '199001012010011001');

        $this->actingAs($this->user('superadmin'))->get(route('tunjangan.pegawai.index'))
            ->assertOk()
            ->assertSee('Data Pegawai')
            ->assertSee('Pangkat/Golongan')
            ->assertSee('Unit Kerja')
            ->assertSee('Periode KGB')
            ->assertSee('Status Kepegawaian')
            ->assertSee('Budi Santoso')
            ->assertSee('III/c / Penata')
            ->assertSee('April 2026')
            ->assertSee(Pegawai::STATUS_PNS);
    }

    public function test_hanya_superadmin_yang_boleh_membuka_data_pegawai(): void
    {
        foreach (['pptk', 'bendahara_pengeluaran', 'bpp'] as $role) {
            $this->actingAs($this->user($role))->get(route('tunjangan.pegawai.index'))->assertForbidden();
        }

        $this->actingAs($this->user('superadmin'))->get(route('tunjangan.pegawai.index'))->assertOk();
    }

    public function test_tambah_dan_edit_pegawai_menyimpan_periode_kgb_dan_status_kepegawaian(): void
    {
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->post(route('tunjangan.pegawai.store'), [
            'nama' => 'Siti Aminah',
            'nip' => '199203032015032002',
            'jabatan' => 'Auditor Ahli Pertama',
            'golongan' => 'III/a',
            'pangkat' => 'Penata Muda',
            'bidang' => 'Sekretariat',
            'periode_kgb' => 'Oktober 2027',
            'status_kepegawaian' => Pegawai::STATUS_PPPK_PENUH,
        ])->assertRedirect(route('tunjangan.pegawai.index'));

        $pegawai = Pegawai::where('nip', '199203032015032002')->sole();
        $this->assertSame('Oktober 2027', $pegawai->periode_kgb);
        $this->assertSame(Pegawai::STATUS_PPPK_PENUH, $pegawai->status_kepegawaian);

        $this->actingAs($admin)->put(route('tunjangan.pegawai.update', $pegawai), [
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'bidang' => $pegawai->bidang,
            'periode_kgb' => 'April 2028',
            'status_kepegawaian' => Pegawai::STATUS_PPPK_PARUH,
        ])->assertRedirect(route('tunjangan.pegawai.index'));

        $pegawai->refresh();
        $this->assertSame('April 2028', $pegawai->periode_kgb);
        $this->assertSame(Pegawai::STATUS_PPPK_PARUH, $pegawai->status_kepegawaian);
    }

    public function test_status_kepegawaian_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->user('superadmin'))->post(route('tunjangan.pegawai.store'), [
            'nama' => 'Salah Status',
            'nip' => '111111111111111111',
            'jabatan' => 'Auditor',
            'bidang' => 'Sekretariat',
            'status_kepegawaian' => 'Honorer',
        ])->assertSessionHasErrors('status_kepegawaian');

        $this->assertSame(0, Pegawai::count());
    }

    // ---------------- Penyaringan Data Tunjangan Keluarga ----------------

    public function test_data_tunjangan_keluarga_hanya_memuat_pns_dan_pppk_penuh_waktu(): void
    {
        $pns = $this->pegawai('Pegawai PNS', '1', Pegawai::STATUS_PNS);
        $penuh = $this->pegawai('Pegawai PPPK Penuh', '2', Pegawai::STATUS_PPPK_PENUH);
        $paruh = $this->pegawai('Pegawai PPPK Paruh', '3', Pegawai::STATUS_PPPK_PARUH);

        $this->actingAs($this->user('superadmin'))->get(route('tunjangan.data.index'))
            ->assertOk()
            ->assertSee($pns->nama)
            ->assertSee($penuh->nama)
            ->assertDontSee($paruh->nama);
    }

    public function test_export_tunjangan_keluarga_mengikuti_daftar_yang_sama(): void
    {
        $this->pegawai('Pegawai PNS', '1', Pegawai::STATUS_PNS);
        $this->pegawai('Pegawai PPPK Penuh', '2', Pegawai::STATUS_PPPK_PENUH);
        $this->pegawai('Pegawai PPPK Paruh', '3', Pegawai::STATUS_PPPK_PARUH);
        $this->pegawai('Pegawai Non Aktif', '4')->update(['aktif' => false]);

        $export = new TunjanganKeluargaExport;
        $nama = $export->query()->pluck('nama');

        $this->assertCount(2, $nama);
        $this->assertTrue($nama->contains('Pegawai PNS'));
        $this->assertTrue($nama->contains('Pegawai PPPK Penuh'));
        $this->assertFalse($nama->contains('Pegawai PPPK Paruh'));
        $this->assertFalse($nama->contains('Pegawai Non Aktif'));
    }

    // ---------------- Status Tunjangan ----------------

    public function test_status_tunjangan_mengikuti_pasangan_dan_anak_yang_berhak(): void
    {
        $service = app(TunjanganKeluargaService::class);
        $pegawai = $this->pegawai('Budi', '9');

        $this->assertSame('TK/0', $service->statusTunjangan($pegawai->tunjanganKeluarga));

        $keluarga = $service->simpanKeluarga($pegawai, [
            'pasangan' => ['nama' => 'Istri Budi', 'status_tunjangan' => true],
            'anak' => [
                ['nama' => 'Anak Kecil', 'tanggal_lahir' => now()->subYears(5)->toDateString(), 'status_tunjangan' => true],
                // Lewat 25 tahun: tidak berhak walau ditandai aktif.
                ['nama' => 'Anak Dewasa', 'tanggal_lahir' => now()->subYears(27)->toDateString(), 'status_tunjangan' => true],
            ],
        ]);

        $this->assertSame('K/1', $service->statusTunjangan($keluarga->fresh('anggota')));
    }

    public function test_hapus_data_mengosongkan_keluarga_tanpa_menghapus_pegawai(): void
    {
        $admin = $this->user('superadmin');
        $pegawai = $this->pegawai('Budi', '9');

        app(TunjanganKeluargaService::class)->simpanKeluarga($pegawai, [
            'pasangan' => ['nama' => 'Istri Budi', 'status_tunjangan' => true],
            'anak' => [],
        ]);

        $this->assertDatabaseHas('tunjangan_keluarga', ['pegawai_id' => $pegawai->id]);

        $this->actingAs($admin)->delete(route('tunjangan.data.hapus', $pegawai))->assertRedirect();

        $this->assertDatabaseMissing('tunjangan_keluarga', ['pegawai_id' => $pegawai->id]);
        $this->assertDatabaseMissing('anggota_keluarga', ['nama' => 'Istri Budi']);
        $this->assertNotNull($pegawai->fresh(), 'Baris pegawainya tidak boleh ikut terhapus.');
    }

    // ---------------- Penjagaan import: jumlah & NIP harus sama ----------------

    /** @param array<int, array<int, mixed>> $baris */
    private function berkas(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nama Pegawai', 'NIP', 'Nama Pasangan', 'Tanggal Lahir Pasangan', 'Status Pasangan'], null, 'A1');
        $sheet->fromArray($baris, null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('tk_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'tk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_ditolak_bila_jumlah_pegawai_tidak_sama(): void
    {
        $this->pegawai('Satu', '11');
        $this->pegawai('Dua', '22');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jumlah pegawai tidak sama');

        app(TunjanganKeluargaImportService::class)->preview(
            $this->berkas([['Satu', '11', 'Istri Satu', '1990-01-01', 'Aktif']]),
            $this->user('superadmin')->id
        );
    }

    public function test_import_ditolak_bila_ada_nip_yang_tidak_dikenal(): void
    {
        $this->pegawai('Satu', '11');
        $this->pegawai('Dua', '22');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak sama dengan Data Tunjangan Keluarga');

        app(TunjanganKeluargaImportService::class)->preview(
            $this->berkas([
                ['Satu', '11', '', '', ''],
                ['Asing', '99', '', '', ''],
            ]),
            $this->user('superadmin')->id
        );
    }

    public function test_import_ditolak_bila_ada_nip_ganda(): void
    {
        $this->pegawai('Satu', '11');
        $this->pegawai('Dua', '22');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NIP ganda');

        app(TunjanganKeluargaImportService::class)->preview(
            $this->berkas([
                ['Satu', '11', '', '', ''],
                ['Satu lagi', '11', '', '', ''],
            ]),
            $this->user('superadmin')->id
        );
    }

    public function test_import_diterima_bila_jumlah_dan_nip_sama_persis(): void
    {
        $this->pegawai('Satu', '11');
        $this->pegawai('Dua', '22');

        // NIP dengan spasi tetap dianggap sama - pembandingannya hanya digit.
        $import = app(TunjanganKeluargaImportService::class)->preview(
            $this->berkas([
                ['Satu', '1 1', 'Istri Satu', '1990-01-01', 'Aktif'],
                ['Dua', '22', '', '', ''],
            ]),
            $this->user('superadmin')->id
        );

        $this->assertSame(2, $import->total_baris);
        $this->assertSame(0, $import->baris_invalid);
    }

    public function test_pppk_paruh_waktu_tidak_ikut_dihitung_saat_import(): void
    {
        $this->pegawai('Satu', '11');
        $this->pegawai('Paruh', '33', Pegawai::STATUS_PPPK_PARUH);

        // Berkas hanya berisi pegawai berhak; PPPK Paruh Waktu memang tidak
        // pernah ikut diexport, jadi jumlahnya tetap dianggap cocok.
        $import = app(TunjanganKeluargaImportService::class)->preview(
            $this->berkas([['Satu', '11', '', '', '']]),
            $this->user('superadmin')->id
        );

        $this->assertSame(1, $import->total_baris);
    }
}
