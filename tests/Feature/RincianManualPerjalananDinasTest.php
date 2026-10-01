<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdTim;
use App\Models\Pegawai;
use App\Models\PerjalananDinasManual;
use App\Models\PerjalananDinasManualImport;
use App\Models\SpjPerjalananDinasManual;
use App\Models\SpjPerjalananDinasManualImport;
use App\Models\User;
use App\Services\PerjalananDinasDashboardService;
use App\Services\SpjDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Rincian manual Perjalanan Dinas & SPJ Perjalanan Dinas.
 *
 * Latarnya: aplikasi mulai dipakai di pertengahan tahun. NPD sebelum migrasi
 * sudah masuk lewat Import NPD Historis dan nilainya real, tetapi tanpa
 * rincian anggota tim - padahal itu yang dibaca Dashboard Perjalanan Dinas.
 *
 * Dua jaminan yang paling dijaga di sini:
 *  1. Baris manual TIDAK PERNAH menyentuh perhitungan anggaran.
 *  2. Baris manual dan baris dari NPD jatuh ke ORANG YANG SAMA di dashboard,
 *     bukan terpecah dua.
 */
class RincianManualPerjalananDinasTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    private function superadmin(): User
    {
        return User::create([
            'username' => 'rincian-admin-'.uniqid(),
            'nama' => 'Superadmin Uji',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia',
        ]);
    }

    private function pegawai(string $nama, string $nip, string $bidang = 'Inspektur Pembantu I'): Pegawai
    {
        return Pegawai::create([
            'nama' => $nama,
            'nip' => $nip,
            'jabatan' => 'Auditor Ahli Muda',
            'bidang' => $bidang,
            'pangkat' => 'Penata',
            'aktif' => true,
        ]);
    }

    private function anggaran(string $kodeRekening = '5.1.02.04.001.00001'): MasterAnggaran
    {
        $this->urut++;

        return MasterAnggaran::create([
            'program' => 'Program Uji Rincian',
            'kegiatan' => 'Kegiatan Uji Rincian',
            'sub_kegiatan' => '6.01.01.2.0'.$this->urut.' Sub Kegiatan Uji Rincian',
            'kode_rekening' => $kodeRekening,
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    /** @param array<int, array<int, mixed>> $baris */
    private function berkas(array $header, array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$header, ...$baris], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'rincian').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'rincian.xlsx', null, null, true);
    }

    // ---------------- Jaminan utama ----------------

    public function test_rincian_manual_tidak_menyentuh_perhitungan_anggaran(): void
    {
        // Jaminan paling penting: realisasi dan sisa tersedia tetap murni
        // dari tabel npd. Kalau baris manual ikut terhitung, angka pagu yang
        // sekarang benar akan bergeser diam-diam.
        $anggaran = $this->anggaran();
        $pegawai = $this->pegawai('Budi Santoso', '198001012000011001');

        $sisaSebelum = $anggaran->sisaTersedia();
        $terikatSebelum = $anggaran->danaTerikatNpd();

        PerjalananDinasManual::create([
            'pegawai_id' => $pegawai->id,
            'bulan' => 3,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'uang_harian' => 5_000_000,
            'akomodasi' => 2_000_000,
        ]);

        $this->assertSame($sisaSebelum, $anggaran->fresh()->sisaTersedia());
        $this->assertSame($terikatSebelum, $anggaran->fresh()->danaTerikatNpd());
    }

    public function test_baris_manual_dan_baris_npd_jatuh_ke_orang_yang_sama(): void
    {
        $tahun = (int) config('anggaran.tahun_aktif');
        $pegawai = $this->pegawai('Budi Santoso', '198001012000011001');
        $anggaran = $this->anggaran();

        // Baris dari NPD: bulan Juli.
        $npd = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => $tahun,
            'tanggal_npd' => $tahun.'-07-10',
            'nominal' => 860_000,
            'terbilang' => '-',
            'status' => 'Selesai',
        ]);
        $tim = NpdTim::create([
            'npd_id' => $npd->id,
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'bidang_snapshot' => $pegawai->bidang,
            'is_penerima' => true,
        ]);
        $tim->paket()->create([
            'cluster' => 'D', 'wilayah' => 'Kota Cirebon',
            'lama_hari' => 2, 'tarif_uh' => 430_000, 'malam' => 0, 'tarif_akom' => 0,
        ]);

        // Baris manual: bulan Maret, periode sebelum migrasi.
        PerjalananDinasManual::create([
            'pegawai_id' => $pegawai->id,
            'bulan' => 3,
            'tahun' => $tahun,
            'hari' => 3,
            'uang_harian' => 1_290_000,
            'akomodasi' => 500_000,
        ]);

        $data = app(PerjalananDinasDashboardService::class)->data(['metrik' => 'terima'], $tahun);

        // Satu baris pegawai saja - bukan dua - dan nilainya gabungan NPD
        // (Juli) dengan rincian manual (Maret).
        $anggota = collect($data['rekap']['rows'])->flatMap(fn (array $b) => $b['anggota'])
            ->where('nip', $pegawai->nip)->values();

        $this->assertCount(1, $anggota, 'Pegawai harus muncul satu kali, bukan terpecah dua.');
        $this->assertSame(2_650_000.0, (float) $anggota[0]['terima'], 'Totalnya gabungan NPD + rincian manual.');
        $this->assertSame(1, $data['rekap']['total']['pegawai']);
        $this->assertSame(2_650_000.0, (float) $data['rekap']['total']['terima']);
    }

    public function test_dokumen_spj_manual_tergabung_dan_bisa_disaring_sumbernya(): void
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        SpjPerjalananDinasManual::create([
            'tahun' => $tahun,
            'tanggal' => $tahun.'-03-14',
            'nomor_npd' => '12/NPD-LAMA/III/'.$tahun,
            'bidang' => 'Inspektur Pembantu I',
            'nominal' => 4_500_000,
            'spj_terverifikasi' => true,
        ]);

        $hasil = app(SpjDashboardService::class)->ringkasan([], $tahun);

        $this->assertSame(1, $hasil['total']);
        $this->assertSame(1, $hasil['terverifikasi']);
        $this->assertSame('manual', $hasil['rows'][0]['sumber']);

        // Disaring ke sumber NPD, baris manual itu hilang.
        $this->assertSame(0, app(SpjDashboardService::class)->ringkasan(['sumber' => 'npd'], $tahun)['total']);
        $this->assertSame(1, app(SpjDashboardService::class)->ringkasan(['sumber' => 'manual'], $tahun)['total']);
    }

    // ---------------- Import parsial ----------------

    public function test_import_memperbarui_baris_yang_sudah_ada_bukan_menggandakan(): void
    {
        // Inti kebutuhannya: memperbaiki beberapa baris tidak lagi menuntut
        // hapus-semua-lalu-muat-ulang.
        $tahun = (int) config('anggaran.tahun_aktif');
        $pegawai = $this->pegawai('Budi Santoso', '198001012000011001');
        $admin = $this->superadmin();

        $header = ['NIP', 'Nama Pegawai', 'Bulan', 'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Keterangan'];

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.store', 'perjalanan-dinas'), [
            'tahun' => $tahun,
            'file' => $this->berkas($header, [[$pegawai->nip, $pegawai->nama, 3, 2, 860000, 0, 0, 0, 'Rekap Maret']]),
        ])->assertRedirect();

        $import = PerjalananDinasManualImport::sole();
        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(0, PerjalananDinasManual::count(), 'Belum tersimpan sebelum dikonfirmasi.');

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.konfirmasi', ['perjalanan-dinas', $import->id]));
        $this->assertSame(860_000.0, (float) PerjalananDinasManual::sole()->uang_harian);

        // Berkas kedua memperbaiki angkanya - bukan menambah baris kedua.
        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.store', 'perjalanan-dinas'), [
            'tahun' => $tahun,
            'file' => $this->berkas($header, [[$pegawai->nip, $pegawai->nama, 3, 2, 900000, 0, 0, 0, 'Koreksi']]),
        ]);

        $kedua = PerjalananDinasManualImport::latest('id')->first();
        $this->assertSame(1, $kedua->jumlah_update);

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.konfirmasi', ['perjalanan-dinas', $kedua->id]));

        $this->assertSame(1, PerjalananDinasManual::count());
        $this->assertSame(900_000.0, (float) PerjalananDinasManual::sole()->uang_harian);
    }

    public function test_nip_yang_tidak_ada_di_data_pegawai_ditolak(): void
    {
        // Aturan yang diminta: jangan buat pegawai dadakan dari berkas -
        // dashboard mengelompokkan orang lewat NIP, dan orang kembar baru
        // ketahuan setelah angkanya terlanjur salah.
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.store', 'perjalanan-dinas'), [
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'file' => $this->berkas(
                ['NIP', 'Nama Pegawai', 'Bulan', 'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Keterangan'],
                [['199900001111222233', 'Orang Asing', 3, 1, 500000, 0, 0, 0, '']]
            ),
        ])->assertRedirect();

        $import = PerjalananDinasManualImport::sole();
        $baris = $import->baris()->sole();

        $this->assertSame(1, $import->jumlah_ditolak);
        $this->assertSame('ditolak', $baris->aksi);
        $this->assertStringContainsString('tidak ada di Data Pegawai', $baris->alasan);

        // Tidak ada pegawai baru yang dibuat diam-diam.
        $this->assertSame(0, Pegawai::count());
    }

    public function test_bidang_di_luar_daftar_ditolak_pada_import_spj(): void
    {
        // Bidang asing akan membuat barisnya jatuh ke luar pengelompokan
        // dashboard dan seolah hilang - lebih baik ditolak di depan.
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.store', 'spj-perjalanan-dinas'), [
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'file' => $this->berkas(
                ['Nomor Dokumen', 'Tanggal', 'Nomor SP', 'Sub Kegiatan', 'Uraian', 'Bidang', 'Nominal', 'Status SPJ', 'Tanggal Verifikasi', 'Diverifikasi Oleh', 'Keterangan'],
                [['12/NPD/III/2026', '2026-03-14', '', '', 'Reviu', 'Bidang Antah Berantah', 4500000, '', '', '', '']]
            ),
        ])->assertRedirect();

        $import = SpjPerjalananDinasManualImport::sole();

        $this->assertSame(1, $import->jumlah_ditolak);
        $this->assertStringContainsString('tidak dikenali', $import->baris()->sole()->alasan);
        $this->assertSame(0, SpjPerjalananDinasManual::count());
    }

    public function test_tahun_diambil_dari_formulir_bukan_berkas(): void
    {
        $pegawai = $this->pegawai('Budi Santoso', '198001012000011001');
        $admin = $this->superadmin();

        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.store', 'perjalanan-dinas'), [
            'tahun' => 2025,
            'file' => $this->berkas(
                ['NIP', 'Nama Pegawai', 'Bulan', 'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Keterangan'],
                [[$pegawai->nip, $pegawai->nama, 5, 1, 300000, 0, 0, 0, '']]
            ),
        ]);

        $import = PerjalananDinasManualImport::sole();
        $this->actingAs($admin)->post(route('manajemen-data.import.rincian.konfirmasi', ['perjalanan-dinas', $import->id]));

        $this->assertSame(2025, PerjalananDinasManual::sole()->tahun);
    }

    // ---------------- Input manual & pintu masuk ----------------

    public function test_satu_orang_hanya_boleh_punya_satu_baris_per_bulan(): void
    {
        $pegawai = $this->pegawai('Budi Santoso', '198001012000011001');
        $tahun = (int) config('anggaran.tahun_aktif');
        $admin = $this->superadmin();

        $isian = [
            'pegawai_id' => $pegawai->id,
            'bulan' => 4,
            'tahun' => $tahun,
            'uang_harian' => 500_000,
        ];

        $this->actingAs($admin)->post(route('manajemen-data.rincian.perjalanan-dinas.store'), $isian)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('manajemen-data.rincian.perjalanan-dinas.store'), $isian)
            ->assertSessionHasErrors(['tahun']);

        $this->assertSame(1, PerjalananDinasManual::count());
    }

    public function test_judul_kartu_manajemen_data_menuju_halaman_rincian(): void
    {
        $this->actingAs($this->superadmin())->get(route('manajemen-data.index'))
            ->assertOk()
            ->assertSee(route('manajemen-data.rincian.perjalanan-dinas'), false)
            ->assertSee(route('manajemen-data.rincian.spj-perjalanan-dinas'), false);
    }

    public function test_daftar_bidang_pada_template_sama_dengan_yang_divalidasi(): void
    {
        // Daftarnya ditulis harfiah di template (konstanta PHP tidak boleh
        // memanggil implode), jadi perlu dijaga agar tidak menyimpang.
        $petunjukBidang = collect(\App\Exports\SpjPerjalananDinasManualTemplateExport::PETUNJUK)
            ->firstWhere(0, 'Bidang')[3];

        $this->assertStringContainsString(
            implode(', ', \App\Support\BidangOrganisasi::PENGAWASAN),
            $petunjukBidang
        );
    }
}
