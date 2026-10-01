<?php

namespace Tests\Feature;

use App\Models\RekapPotensiImport;
use App\Models\RekapPotensiImportRow;
use App\Models\RekapPotensiPengembalian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Rekap Potensi Pengembalian (adopsi GAS #95 & #96).
 *
 * Yang dijaga: Sisa SELALU hasil hitung potensi - setoran (tidak pernah
 * dibaca dari berkas), urutannya menaruh tunggakan terbesar di atas, dan
 * NIP 18 digit tidak boleh tertukar karena dibandingkan sebagai angka.
 */
class RekapPotensiPengembalianTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_SUPERADMIN): User
    {
        return User::create([
            'username' => 'rekap-'.$role.'-'.uniqid(),
            'nama' => 'Petugas '.$role,
            'password' => Hash::make('rahasia123'),
            'role' => $role,
            'aktif' => true,
        ]);
    }

    private function rekap(string $nip, string $nama, float $potensi, float $setoran, ?string $jabatan = null): RekapPotensiPengembalian
    {
        return RekapPotensiPengembalian::create([
            'nip' => $nip,
            'nama' => $nama,
            'jabatan' => $jabatan,
            'potensi' => $potensi,
            'setoran' => $setoran,
        ]);
    }

    /** @param array<int, array<int, mixed>> $baris */
    private function berkas(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            [['NIP', 'Nama Pegawai', 'Jabatan', 'Potensi Kelebihan Pembayaran', 'Setoran', 'Keterangan'], ...$baris],
            null,
            'A1'
        );

        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'rekap.xlsx', null, null, true);
    }

    // ---------------- Perhitungan & urutan ----------------

    public function test_sisa_selalu_dihitung_bukan_disimpan(): void
    {
        $baris = $this->rekap('198001012000011001', 'Budi', 1_000_000, 250_000);

        $this->assertSame(750_000.0, $baris->sisa());
        $this->assertTrue($baris->adaPengembalian());

        // Tidak ada kolom sisa di tabelnya - itu yang menjamin ia tidak
        // mungkin bertentangan dengan potensi & setoran.
        $this->assertArrayNotHasKey('sisa', $baris->getAttributes());

        $baris->update(['setoran' => 1_000_000]);
        $this->assertSame(0.0, $baris->fresh()->sisa());
        $this->assertFalse($baris->fresh()->adaPengembalian());
    }

    public function test_urutan_baku_sisa_terbesar_lalu_nip_menaik(): void
    {
        $this->rekap('198001012000011005', 'Lunas Lima', 500_000, 500_000);
        $this->rekap('198001012000011002', 'Lunas Dua', 300_000, 300_000);
        $besar = $this->rekap('199001012015011009', 'Tunggakan Besar', 2_000_000, 0);
        $sedang = $this->rekap('199001012015011008', 'Tunggakan Sedang', 900_000, 100_000);

        $urut = RekapPotensiPengembalian::urutanBaku()->pluck('nama')->all();

        $this->assertSame(
            [$besar->nama, $sedang->nama, 'Lunas Dua', 'Lunas Lima'],
            $urut,
            'Tunggakan terbesar harus di atas; yang sisanya sama diurutkan NIP menaik.'
        );
    }

    public function test_nip_panjang_tidak_tertukar_karena_dibandingkan_sebagai_angka(): void
    {
        // Dua NIP 18 digit yang hanya beda di digit TERAKHIR. Kalau
        // dibandingkan sebagai bilangan, keduanya melebihi presisi aman dan
        // bisa terbaca sama.
        $this->rekap('199001012015011118', 'NIP Berakhir 118', 0, 0);
        $this->rekap('199001012015011117', 'NIP Berakhir 117', 0, 0);

        $this->assertSame(
            ['NIP Berakhir 117', 'NIP Berakhir 118'],
            RekapPotensiPengembalian::urutanBaku()->pluck('nama')->all()
        );
    }

    // ---------------- Halaman rekap ----------------

    public function test_halaman_menampilkan_ringkasan_dan_saringan(): void
    {
        $this->rekap('198001012000011001', 'Masih Nunggak', 1_000_000, 250_000, 'Auditor');
        $this->rekap('198001012000011002', 'Sudah Lunas', 400_000, 400_000, 'Pengawas');

        $user = $this->user();

        $this->actingAs($user)->get(route('gaji-tunjangan.rekap-potensi'))
            ->assertOk()
            ->assertSee('Masih Nunggak')
            ->assertSee('Sudah Lunas')
            ->assertViewHas('totalPotensi', 1_400_000.0)
            ->assertViewHas('totalSetoran', 650_000.0)
            ->assertViewHas('totalSisa', 750_000.0)
            ->assertViewHas('jumlahBelumLunas', 1);

        $this->actingAs($user)->get(route('gaji-tunjangan.rekap-potensi', ['saring' => 'ada']))
            ->assertOk()
            ->assertSee('Masih Nunggak')
            ->assertDontSee('Sudah Lunas');

        $this->actingAs($user)->get(route('gaji-tunjangan.rekap-potensi', ['saring' => 'tidak']))
            ->assertOk()
            ->assertSee('Sudah Lunas')
            ->assertDontSee('Masih Nunggak');

        $this->actingAs($user)->get(route('gaji-tunjangan.rekap-potensi', ['cari' => 'Pengawas']))
            ->assertOk()
            ->assertSee('Sudah Lunas')
            ->assertDontSee('Masih Nunggak');
    }

    public function test_ringkasan_dihitung_atas_seluruh_data_bukan_halaman_yang_tampil(): void
    {
        // 12 baris, paginasi 10 - totalnya tetap harus mencakup semuanya.
        for ($i = 1; $i <= 12; $i++) {
            $this->rekap('19800101200001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'Pegawai '.$i, 100_000, 0);
        }

        $this->actingAs($this->user())->get(route('gaji-tunjangan.rekap-potensi'))
            ->assertOk()
            ->assertViewHas('totalPotensi', 1_200_000.0)
            ->assertViewHas('jumlahBelumLunas', 12)
            ->assertViewHas('rekap', fn ($rekap) => $rekap->count() === 10);
    }

    public function test_role_di_luar_daftar_ditolak(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_BENDAHARA_PENGELUARAN, 'pengawas'] as $role) {
            $this->actingAs($this->user($role))->get(route('gaji-tunjangan.rekap-potensi'))->assertOk();
        }

        foreach (['pptk', 'bpp', 'verifikator'] as $role) {
            $this->actingAs($this->user($role))->get(route('gaji-tunjangan.rekap-potensi'))->assertForbidden();
        }
    }

    // ---------------- Import preview/dry-run ----------------

    public function test_import_tidak_menyentuh_data_sampai_dikonfirmasi(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.store'), [
            'file' => $this->berkas([
                ['198001012000011001', 'Budi Santoso', 'Auditor', 1000000, 250000, 'Angsuran 1'],
            ]),
        ])->assertRedirect();

        $import = RekapPotensiImport::sole();

        $this->assertSame(RekapPotensiImport::STATUS_STAGED, $import->status);
        $this->assertSame(1, $import->jumlah_baru);
        // Belum ada apa pun yang tersimpan ke tabel sebenarnya.
        $this->assertSame(0, RekapPotensiPengembalian::count());

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.konfirmasi', $import))
            ->assertRedirect(route('manajemen-data.index'));

        $baris = RekapPotensiPengembalian::sole();

        $this->assertSame('Budi Santoso', $baris->nama);
        $this->assertSame(750_000.0, $baris->sisa());
    }

    public function test_setoran_melebihi_potensi_ditolak(): void
    {
        // Sisanya akan negatif - itu bukan rekap yang bisa dibaca.
        $this->actingAs($this->user())->post(route('manajemen-data.import.rekap-potensi.store'), [
            'file' => $this->berkas([
                ['198001012000011001', 'Budi Santoso', 'Auditor', 100000, 250000, ''],
            ]),
        ])->assertRedirect();

        $import = RekapPotensiImport::sole();

        $this->assertSame(1, $import->jumlah_ditolak);
        $this->assertSame(RekapPotensiImportRow::AKSI_DITOLAK, $import->baris()->sole()->aksi);
        $this->assertStringContainsString('melebihi Potensi', $import->baris()->sole()->alasan);
    }

    public function test_nip_ganda_dalam_satu_berkas_ditolak(): void
    {
        $this->actingAs($this->user())->post(route('manajemen-data.import.rekap-potensi.store'), [
            'file' => $this->berkas([
                ['198001012000011001', 'Budi', 'Auditor', 100000, 0, ''],
                ['1980 0101 2000 01 1001', 'Budi Lagi', 'Auditor', 200000, 0, ''],
            ]),
        ])->assertRedirect();

        $import = RekapPotensiImport::sole();

        // NIP dibandingkan setelah tanda baca & spasinya dibuang, jadi dua
        // penulisan berbeda untuk orang yang sama tetap ketahuan.
        $this->assertSame(1, $import->jumlah_baru);
        $this->assertSame(1, $import->jumlah_ditolak);
    }

    public function test_nip_yang_sudah_ada_diperbarui_bukan_digandakan(): void
    {
        $this->rekap('198001012000011001', 'Nama Lama', 500_000, 0);

        $user = $this->user();

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.store'), [
            'file' => $this->berkas([
                ['198001012000011001', 'Nama Baru', 'Auditor', 1000000, 400000, 'Angsuran 2'],
            ]),
        ])->assertRedirect();

        $import = RekapPotensiImport::sole();
        $this->assertSame(1, $import->jumlah_update);

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.konfirmasi', $import));

        $this->assertSame(1, RekapPotensiPengembalian::count());
        $baris = RekapPotensiPengembalian::sole();
        $this->assertSame('Nama Baru', $baris->nama);
        $this->assertSame(600_000.0, $baris->sisa());
    }

    public function test_angka_bertipe_teks_dibaca_sesuai_format_indonesia(): void
    {
        // Sel yang BENAR-BENAR teks - mis. hasil salin-tempel dari dokumen.
        // Sel bertipe angka tidak melewati jalur ini; PhpSpreadsheet sudah
        // menyerahkannya sebagai float.
        $metode = new \ReflectionMethod(RekapPotensiImport::class, 'angka');
        $metode->setAccessible(true);
        $angka = fn ($nilai) => $metode->invoke(null, $nilai);

        // Titik sebagai pemisah RIBUAN - inilah yang paling mudah salah:
        // is_numeric() menerima "250.000" dan membacanya 250.
        $this->assertSame(250_000.0, $angka('250.000'));
        $this->assertSame(1_250_000.0, $angka('1.250.000'));
        $this->assertSame(1_250_000.5, $angka('1.250.000,50'));
        $this->assertSame(1_250_000.5, $angka('Rp 1.250.000,50'));

        // Titik sebagai pemisah DESIMAL tetap dihormati.
        $this->assertSame(250.5, $angka('250.5'));

        // Angka asli dari Excel lewat apa adanya; sel kosong jadi 0.
        $this->assertSame(1_250_000.5, $angka(1250000.5));
        $this->assertSame(0.0, $angka(''));
        $this->assertSame(0.0, $angka(null));
    }

    public function test_nilai_numerik_dari_berkas_tersimpan_utuh(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.store'), [
            'file' => $this->berkas([
                ['198001012000011001', 'Budi', 'Auditor', 1250000.5, 250000, ''],
            ]),
        ])->assertRedirect();

        $this->actingAs($user)->post(route('manajemen-data.import.rekap-potensi.konfirmasi', RekapPotensiImport::sole()));

        $baris = RekapPotensiPengembalian::sole();

        $this->assertSame(1_250_000.5, (float) $baris->potensi);
        $this->assertSame(250_000.0, (float) $baris->setoran);
        $this->assertSame(1_000_000.5, $baris->sisa());
    }

    public function test_export_menyertakan_sisa_walau_tidak_disimpan(): void
    {
        $this->rekap('198001012000011001', 'Budi', 1_000_000, 250_000, 'Auditor');

        $export = new \App\Exports\RekapPotensiExport;

        $this->assertContains('Sisa Pengembalian', $export->headings());
        $this->assertSame(750_000.0, $export->map(RekapPotensiPengembalian::sole())[6]);

        Excel::fake();
        $this->actingAs($this->user())->get(route('manajemen-data.export', 'rekap-potensi'))->assertOk();
    }
}
