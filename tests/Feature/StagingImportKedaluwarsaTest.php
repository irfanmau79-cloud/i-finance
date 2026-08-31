<?php

namespace Tests\Feature;

use App\Models\MasterAnggaranImport;
use App\Models\PegawaiImport;
use App\Models\RakBulananImport;
use App\Models\SpmImport;
use App\Models\User;
use App\Models\VendorImport;
use App\Models\VersiPagu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Masa berlaku staging import (pola preview/dry-run).
 *
 * Latar belakang: di server produksi (shared hosting cPanel, MariaDB dengan
 * `explicit_defaults_for_timestamp = OFF`) kolom `expires_at` mendapat
 * `ON UPDATE CURRENT_TIMESTAMP` secara implisit, sehingga UPDATE penghitung
 * di akhir buatDariUpload() menimpanya dan setiap konfirmasi import ditolak
 * "Sesi staging sudah kedaluwarsa" padahal berkas baru saja diunggah. Di
 * MySQL 8 lokal hal itu tidak terjadi, jadi bug-nya tidak pernah tertangkap.
 *
 * Test di sini mengunci kedua lapis perbaikannya: perhitungan masa berlaku
 * yang tidak lagi bergantung pada kolom expires_at, dan bentuk kolomnya yang
 * sudah dinormalkan lewat migrasi.
 */
class StagingImportKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Tahun', 'Kode Program', 'Program', 'Kode Kegiatan', 'Kegiatan',
        'Kode Sub Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Rekening',
        'Tagging', 'Pagu', 'Aktif/Non Aktif',
    ];

    private function superadmin(): User
    {
        return User::create([
            'username' => 'superadmin-staging',
            'nama' => 'Penguji Staging',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'test-only-password',
        ]);
    }

    private function unggah(User $user, string $versiNama = 'DPA Murni'): MasterAnggaranImport
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADER, null, 'A1');
        $sheet->fromArray([[
            2026, '6.01', 'Program Uji', '6.01.01', 'Kegiatan Uji',
            '6.01.01.2.01', 'Sub Kegiatan Uji', '5.1.02.05.01.7001',
            'Belanja Uji', '', 10_000_000, 'Aktif',
        ]], null, 'A2');

        $path = sys_get_temp_dir().'/'.uniqid('staging_', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($user)->post(route('manajemen-data.import.master-anggaran.store'), [
            'file' => new UploadedFile($path, 'master-anggaran.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'versi_nama' => $versiNama,
        ]);

        return MasterAnggaranImport::latest('id')->firstOrFail();
    }

    // ---------------- Regresi bug produksi ----------------

    /**
     * Inti bug produksi: MariaDB menimpa expires_at dengan jam saat UPDATE
     * penghitung dijalankan, sehingga jendela staging runtuh jadi nol detik.
     * Disimulasikan dengan menulis langsung ke kolomnya lewat query builder
     * (tanpa lewat model, persis seperti yang dilakukan server DB).
     *
     * Batch tetap harus dianggap hidup dan konfirmasinya harus berhasil.
     */
    public function test_konfirmasi_tetap_jalan_walau_kolom_expires_at_ditimpa_server_db(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        DB::table('master_anggaran_imports')->where('id', $import->id)
            ->update(['expires_at' => now()->subSeconds(1)]);

        $this->assertFalse($import->fresh()->kedaluwarsa());

        $this->actingAs($user)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();

        $this->actingAs($user)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertRedirect(route('versi-pagu.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(MasterAnggaranImport::STATUS_COMMITTED, $import->fresh()->status);
        $this->assertSame(1, VersiPagu::where('nama', 'DPA Murni')->count());
    }

    /** Kasus zona waktu DB berbeda: expires_at mundur berjam-jam, batch tetap hidup. */
    public function test_konfirmasi_tetap_jalan_walau_expires_at_mundur_berjam_jam(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        DB::table('master_anggaran_imports')->where('id', $import->id)
            ->update(['expires_at' => now()->subHours(7)]);

        $this->assertFalse($import->fresh()->kedaluwarsa());

        $this->actingAs($user)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasNoErrors();
    }

    /** expires_at kosong sama sekali tidak boleh menghanguskan pekerjaan user. */
    public function test_expires_at_null_tidak_membuat_batch_dianggap_kedaluwarsa(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        DB::table('master_anggaran_imports')->where('id', $import->id)->update(['expires_at' => null]);

        $this->assertFalse($import->fresh()->kedaluwarsa());
        $this->actingAs($user)->get(route('manajemen-data.import.master-anggaran.preview', $import))->assertOk();
    }

    // ---------------- Masa berlaku tetap ditegakkan ----------------

    public function test_batch_masih_hidup_tepat_sebelum_masa_berlaku_habis(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        $this->travel(MasterAnggaranImport::menitKedaluwarsa() - 1)->minutes();

        $this->assertFalse($import->fresh()->kedaluwarsa());
        $this->actingAs($user)
            ->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasNoErrors();
    }

    public function test_batch_kedaluwarsa_setelah_masa_berlaku_habis(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        $this->travel(MasterAnggaranImport::menitKedaluwarsa() + 1)->minutes();

        $this->assertTrue($import->fresh()->kedaluwarsa());

        // Preview memulangkan user ke form upload dengan pesan yang jelas.
        $this->actingAs($user)->get(route('manajemen-data.import.master-anggaran.preview', $import))
            ->assertRedirect(route('manajemen-data.import.master-anggaran.create'))
            ->assertSessionHasErrors('file');

        // Konfirmasi ditolak dan TIDAK boleh membuat versi pagu apa pun.
        $this->actingAs($user)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import))
            ->assertSessionHasErrors('import');

        $this->assertSame(MasterAnggaranImport::STATUS_STAGED, $import->fresh()->status);
        $this->assertSame(0, VersiPagu::count());
    }

    public function test_masa_berlaku_mengikuti_konfigurasi(): void
    {
        config()->set('anggaran.menit_staging_import', 5);

        $user = $this->superadmin();
        $import = $this->unggah($user);

        $this->travel(4)->minutes();
        $this->assertFalse($import->fresh()->kedaluwarsa());

        $this->travel(2)->minutes();
        $this->assertTrue($import->fresh()->kedaluwarsa());
    }

    // ---------------- Pembersihan batch basi ----------------

    public function test_bersihkan_kedaluwarsa_hanya_membuang_batch_yang_sudah_mati(): void
    {
        $user = $this->superadmin();

        $lama = $this->unggah($user, 'DPA Murni');
        $baru = $this->unggah($user, 'DPA Pergeseran 1');

        // Ditua-kan SETELAH kedua batch dibuat: store() sendiri sudah
        // memanggil bersihkanKedaluwarsa(), jadi kalau ditua-kan lebih dulu
        // batch lama keburu terhapus dan yang diuji di sini bukan lagi
        // pembersihannya.
        DB::table('master_anggaran_imports')->where('id', $lama->id)
            ->update(['created_at' => now()->subMinutes(MasterAnggaranImport::menitKedaluwarsa() + 5)]);

        $this->assertSame(1, MasterAnggaranImport::bersihkanKedaluwarsa());
        $this->assertNull(MasterAnggaranImport::find($lama->id));
        $this->assertNotNull(MasterAnggaranImport::find($baru->id));

        // Baris staging batch yang dibuang ikut hilang (cascade), baris batch
        // yang masih hidup tetap utuh.
        $this->assertSame(0, DB::table('master_anggaran_import_rows')->where('import_id', $lama->id)->count());
        $this->assertGreaterThan(0, DB::table('master_anggaran_import_rows')->where('import_id', $baru->id)->count());
    }

    public function test_batch_committed_tidak_pernah_dianggap_kedaluwarsa(): void
    {
        $user = $this->superadmin();
        $import = $this->unggah($user);

        $this->actingAs($user)->post(route('manajemen-data.import.master-anggaran.konfirmasi', $import));

        $this->travel(MasterAnggaranImport::menitKedaluwarsa() + 60)->minutes();

        $import = $import->fresh();
        $this->assertSame(MasterAnggaranImport::STATUS_COMMITTED, $import->status);
        $this->assertFalse($import->kedaluwarsa());
        $this->assertSame(0, MasterAnggaranImport::bersihkanKedaluwarsa());
    }

    // ---------------- Bentuk kolom (regresi migrasi) ----------------

    /**
     * Penjaga struktural: kalau ada migrasi baru yang mendeklarasikan lagi
     * `$table->timestamp('expires_at')` NOT NULL sebagai kolom TIMESTAMP
     * pertama, MariaDB akan memasang ON UPDATE CURRENT_TIMESTAMP lagi dan bug
     * produksinya kembali. Kolom itu wajib NULL-able di semua tabel staging.
     */
    public function test_kolom_expires_at_nullable_di_seluruh_tabel_staging(): void
    {
        $tabel = [
            'master_anggaran_imports', 'spm_imports', 'rak_bulanan_imports',
            'npd_historis_imports', 'pegawai_imports', 'vendor_imports',
        ];

        foreach ($tabel as $nama) {
            $kolom = collect(Schema::getColumns($nama))->firstWhere('name', 'expires_at');

            $this->assertNotNull($kolom, "Kolom expires_at tidak ada di {$nama}.");
            $this->assertTrue(
                $kolom['nullable'],
                "Kolom expires_at di {$nama} masih NOT NULL - MariaDB akan memasang ON UPDATE CURRENT_TIMESTAMP dan menghanguskan staging."
            );
        }
    }

    /** Semua jenis import berbagi satu sumber masa berlaku. */
    public function test_seluruh_model_import_memakai_masa_berlaku_yang_sama(): void
    {
        config()->set('anggaran.menit_staging_import', 45);

        foreach ([MasterAnggaranImport::class, SpmImport::class, RakBulananImport::class, PegawaiImport::class, VendorImport::class] as $model) {
            $this->assertSame(45, $model::menitKedaluwarsa(), $model.' memakai masa berlaku sendiri.');
        }
    }
}
