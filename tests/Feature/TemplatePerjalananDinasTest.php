<?php

namespace Tests\Feature;

use App\Exports\PerjalananDinasTemplateExport;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Formulir rekap Perjalanan Dinas: 3 kolom identitas + 12 bulan x 5 aspek +
 * 5 kolom Tahunan = 68 kolom (A sampai BP).
 */
class TemplatePerjalananDinasTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role): User
    {
        return User::create([
            'username' => 'u'.$role.uniqid(),
            'nama' => 'Uji '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    private function berkas(): Spreadsheet
    {
        Storage::disk('local')->makeDirectory('uji-tpl');
        Excel::store(new PerjalananDinasTemplateExport, 'uji-tpl/pd.xlsx', 'local');

        return IOFactory::load(Storage::disk('local')->path('uji-tpl/pd.xlsx'));
    }

    private function kolom(int $indeks): string
    {
        return Coordinate::stringFromColumnIndex($indeks);
    }

    public function test_susunan_kolom_sesuai_bulan_dan_aspek(): void
    {
        $export = new PerjalananDinasTemplateExport;

        $this->assertSame(68, $export->jumlahKolom());

        $sheet = $this->berkas()->getSheet(0);
        $this->assertSame('BP', $sheet->getHighestColumn());

        // Baris 1: identitas lalu 12 bulan dan Tahunan, tiap kelompok 5 kolom.
        $this->assertSame('Nama', $sheet->getCell('A1')->getValue());
        $this->assertSame('NIP', $sheet->getCell('B1')->getValue());
        $this->assertSame('Unit Kerja', $sheet->getCell('C1')->getValue());

        $kelompok = [...PerjalananDinasTemplateExport::BULAN, PerjalananDinasTemplateExport::KELOMPOK_TAHUNAN];

        foreach ($kelompok as $urutan => $nama) {
            $mulai = 4 + $urutan * 5;
            $this->assertSame($nama, $sheet->getCell($this->kolom($mulai).'1')->getValue());

            // Baris 2: lima aspek berulang di bawah tiap kelompok.
            foreach (PerjalananDinasTemplateExport::ASPEK as $urutanAspek => $aspek) {
                $this->assertSame(
                    $aspek,
                    $sheet->getCell($this->kolom($mulai + $urutanAspek).'2')->getValue()
                );
            }
        }
    }

    public function test_identitas_pegawai_aktif_sudah_terisi_dan_urut_per_unit_kerja(): void
    {
        Pegawai::create(['nama' => 'Budi Santoso', 'nip' => '198001012005011001', 'jabatan' => 'Auditor', 'bidang' => 'Irbanwil II', 'aktif' => true]);
        Pegawai::create(['nama' => 'Ani Lestari', 'nip' => '198202022006022002', 'jabatan' => 'Auditor', 'bidang' => 'Irbanwil I', 'aktif' => true]);
        Pegawai::create(['nama' => 'Citra Dewi', 'nip' => '198303032007033003', 'jabatan' => 'Auditor', 'bidang' => 'Irbanwil I', 'aktif' => true]);
        Pegawai::create(['nama' => 'Pensiun Tidak Aktif', 'nip' => '196001011980011001', 'jabatan' => 'Auditor', 'bidang' => 'Irbanwil I', 'aktif' => false]);

        $sheet = $this->berkas()->getSheet(0);

        // Pegawai non aktif tidak ikut: 3 baris data mulai baris 3.
        $this->assertSame(5, $sheet->getHighestRow());

        $this->assertSame('Ani Lestari', $sheet->getCell('A3')->getValue());
        $this->assertSame('Irbanwil I', $sheet->getCell('C3')->getValue());
        $this->assertSame('Citra Dewi', $sheet->getCell('A4')->getValue());
        $this->assertSame('Budi Santoso', $sheet->getCell('A5')->getValue());
        $this->assertSame('Irbanwil II', $sheet->getCell('C5')->getValue());

        // NIP 18 digit harus utuh sebagai teks, bukan dibulatkan jadi notasi ilmiah.
        $this->assertSame('198202022006022002', (string) $sheet->getCell('B3')->getValue());
    }

    public function test_kolom_bulanan_kosong_dan_kolom_tahunan_berisi_rumus_dua_belas_bulan(): void
    {
        Pegawai::create(['nama' => 'Budi Santoso', 'nip' => '198001012005011001', 'jabatan' => 'Auditor', 'bidang' => 'Irbanwil I', 'aktif' => true]);

        $sheet = $this->berkas()->getSheet(0);

        // Kolom bulanan dibiarkan kosong untuk diisi tangan.
        $this->assertNull($sheet->getCell('D3')->getValue());
        $this->assertNull($sheet->getCell('BG3')->getValue());

        // Tahunan mulai kolom BL. Tiap aspek menjumlah 12 sel berjarak 5 kolom.
        $this->assertSame(
            '=SUM(D3,I3,N3,S3,X3,AC3,AH3,AM3,AR3,AW3,BB3,BG3)',
            $sheet->getCell('BL3')->getValue()
        );

        // Aspek kelima (Jumlah Diterima) menjumlah kolom kelima tiap bulan.
        $this->assertSame(
            '=SUM(H3,M3,R3,W3,AB3,AG3,AL3,AQ3,AV3,BA3,BF3,BK3)',
            $sheet->getCell('BP3')->getValue()
        );
    }

    public function test_berkas_tetap_utuh_walau_belum_ada_pegawai(): void
    {
        $spreadsheet = $this->berkas();

        $this->assertSame(['Data', 'Petunjuk Pengisian'], $spreadsheet->getSheetNames());
        $this->assertSame('Tahunan', $spreadsheet->getSheet(0)->getCell('BL1')->getValue());
    }

    public function test_unduhan_template_hanya_untuk_role_manajemen_data(): void
    {
        Excel::fake();

        foreach ([User::ROLE_SUPERADMIN, User::ROLE_BENDAHARA_PENGELUARAN] as $role) {
            $this->actingAs($this->buatUser($role))
                ->get(route('manajemen-data.template.perjalanan-dinas'))
                ->assertOk();
        }

        foreach ([User::ROLE_PPTK, User::ROLE_BPP, User::ROLE_VERIFIKATOR] as $role) {
            $this->actingAs($this->buatUser($role))
                ->get(route('manajemen-data.template.perjalanan-dinas'))
                ->assertForbidden();
        }
    }

    public function test_kartu_perjalanan_dinas_menawarkan_template_tanpa_tombol_import(): void
    {
        $halaman = $this->actingAs($this->buatUser(User::ROLE_SUPERADMIN))
            ->get(route('manajemen-data.index'))->assertOk();

        $halaman->assertSee(route('manajemen-data.template.perjalanan-dinas'), false);

        // Kartu Perjalanan Dinas dan SPJ tidak lagi mengarah ke import NPD;
        // satu-satunya tautan import NPD adalah milik kartu Data NPD. Dihitung
        // lengkap dengan kutip penutup href karena URL create adalah awalan
        // dari URL template - tanpa itu tautan template ikut terhitung.
        $this->assertSame(
            1,
            substr_count($halaman->getContent(), 'href="'.route('manajemen-data.import.npd-historis.create').'"')
        );
    }
}
