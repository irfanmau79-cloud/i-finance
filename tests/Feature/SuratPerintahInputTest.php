<?php

namespace Tests\Feature;

use App\Http\Requests\StoreSuratPerintahRequest;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Input Surat Perintah — aturan diselaraskan dengan prosesInputSP() dan
 * _spNormalisasiAnggota() di gas-lama/CodeSuratPerintah.gs.
 */
class SuratPerintahInputTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'uji-'.$role,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function pegawai(string $nama, array $override = []): Pegawai
    {
        return Pegawai::create(array_replace([
            'nama' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'golongan' => 'III/c',
            'pangkat' => 'Penata',
            'jabatan' => 'Auditor Ahli Muda',
            'bidang' => 'Inspektur Pembantu I',
            'rekening' => '100200300',
            'aktif' => true,
        ], $override));
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_replace([
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'nomor_sp' => '087/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim Uji',
            'tujuan_transfer' => 'Koordinator Uji',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'komponen' => ['Uang Harian', 'Akomodasi'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ], $override);
    }

    // ---------------- Komponen Pembayaran ----------------

    /**
     * Unit Kerja pada Input SP hanya enam - sama dengan GAS. "Subbagian Tata
     * Usaha" sempat ikut terdaftar padahal itu milik pengelompokan bidang
     * SPJ, bukan unit penerbit Surat Perintah.
     */
    public function test_unit_kerja_hanya_enam_dan_sama_dengan_gas(): void
    {
        $this->assertSame([
            'Inspektur Pembantu I',
            'Inspektur Pembantu II',
            'Inspektur Pembantu III',
            'Inspektur Pembantu IV',
            'Inspektur Pembantu Investigasi',
            'Sekretariat',
        ], StoreSuratPerintahRequest::UNIT_KERJA);

        $this->assertNotContains('Subbagian Tata Usaha', StoreSuratPerintahRequest::UNIT_KERJA);
    }

    public function test_komponen_pembayaran_mengisi_kolom_pengajuan_dan_wajib_dipilih(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');

        $anggota = [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']];

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'komponen' => [],
            'anggota' => $anggota,
        ]))->assertSessionHasErrors('komponen');

        $this->assertSame(0, SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'komponen' => ['Uang Harian', 'Transport'],
            'anggota' => $anggota,
        ]))->assertRedirect(route('surat-perintah.index'));

        $this->assertSame('Uang Harian, Transport', SuratPerintah::sole()->pengajuan);
    }

    // ---------------- Anggota ----------------

    public function test_anggota_wajib_minimal_satu_orang(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user('pptk'))
            ->post(route('surat-perintah.store'), $this->payload(['anggota' => []]))
            ->assertSessionHasErrors('anggota');

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_nama_di_luar_master_hanya_diterima_lewat_isi_manual(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');

        // Tanpa flag manual: ditolak, sama seperti GAS.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['nama' => 'Orang Luar Instansi', 'jabatan_sp' => 'Anggota']],
        ]))->assertSessionHasErrors('anggota');

        $this->assertSame(0, SuratPerintah::count());

        // Dengan Isi Manual: diterima beserta identitas ketikan pengguna.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [[
                'nama' => 'Orang Luar Instansi',
                'manual' => '1',
                'nip' => '198001012005011003',
                'golongan' => 'IV/a',
                'pangkat' => 'Pembina',
                'jabatan' => 'Auditor Madya',
                'rekening' => '900800700',
                'jabatan_sp' => 'Anggota',
            ]],
        ]))->assertRedirect(route('surat-perintah.index'));

        $anggota = SuratPerintah::sole()->anggota->sole();
        $this->assertNull($anggota->pegawai_id, 'Anggota manual tidak boleh dikaitkan ke master Pegawai.');
        $this->assertTrue($anggota->manual);
        $this->assertSame('Orang Luar Instansi', $anggota->nama);
        $this->assertSame('Auditor Madya', $anggota->jabatan);
        $this->assertSame('IV/a', $anggota->golongan);
    }

    public function test_nama_anggota_ganda_dan_jabatan_tim_tidak_sah_ditolak(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [
                ['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim'],
                ['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Anggota'],
            ],
        ]))->assertSessionHasErrors('anggota');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Komandan']],
        ]))->assertSessionHasErrors('anggota.0.jabatan_sp');

        $this->assertSame(0, SuratPerintah::count());
    }

    public function test_jabatan_dalam_tim_bersifat_opsional_dan_mengenal_wakil_penanggungjawab(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $satu = $this->pegawai('Tanpa Jabatan Tim');
        $dua = $this->pegawai('Wakil Penanggung');

        // Daftar jabatan mengikuti SP_JABATAN_TIM di GAS, termasuk ejaannya.
        $this->assertSame([
            'Penanggungjawab',
            'Wakil Penanggungjawab',
            'Pengendali Teknis',
            'Ketua Tim',
            'Anggota',
        ], SuratPerintah::JABATAN_ANGGOTA);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [
                ['pegawai_id' => $satu->id, 'nama' => $satu->nama],
                ['pegawai_id' => $dua->id, 'nama' => $dua->nama, 'jabatan_sp' => 'Wakil Penanggungjawab'],
            ],
        ]))->assertRedirect(route('surat-perintah.index'));

        $anggota = SuratPerintah::sole()->anggota;
        $this->assertNull($anggota[0]->jabatan_sp);
        $this->assertSame('Wakil Penanggungjawab', $anggota[1]->jabatan_sp);
    }

    public function test_snapshot_anggota_tidak_ikut_berubah_saat_master_pegawai_diperbarui(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso', ['jabatan' => 'Auditor Ahli Pertama']);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]));

        $orang->update(['jabatan' => 'Auditor Ahli Madya', 'nip' => '111111111111111111']);

        $anggota = SuratPerintah::sole()->anggota->sole();
        $this->assertSame('Auditor Ahli Pertama', $anggota->jabatan, 'Dokumen historis tidak boleh ikut berubah.');
        $this->assertNotSame('111111111111111111', $anggota->nip);
    }

    public function test_nomor_sp_ganda_ditolak(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $orang = $this->pegawai('Budi Santoso');
        $anggota = [['pegawai_id' => $orang->id, 'nama' => $orang->nama]];

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload(['anggota' => $anggota]));
        $this->assertSame(1, SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload(['anggota' => $anggota]))
            ->assertSessionHasErrors('nomor_sp');

        $this->assertSame(1, SuratPerintah::count());
    }

    // ---------------- Reimburse Transportasi ----------------

    private function buatIndukUangHarian(User $pptk): SuratPerintah
    {
        $orang = $this->pegawai('Ketua Induk');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]))->assertRedirect(route('surat-perintah.index'));

        return SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN)->sole();
    }

    public function test_reimburse_menyalin_data_dan_anggota_dari_induk_serta_memakai_suffix_nomor(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatIndukUangHarian($pptk);

        // PDF sengaja tidak diunggah: untuk Reimburse memang tidak wajib.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertRedirect(route('surat-perintah.index'));

        $reimburse = SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->sole();

        $this->assertSame($induk->nomor_sp.' (Reimburse)', $reimburse->nomor_sp);
        $this->assertSame($induk->id, $reimburse->sp_induk_id);
        $this->assertSame($induk->lokasi, $reimburse->lokasi);
        $this->assertSame($induk->keterangan, $reimburse->keterangan);
        $this->assertSame($induk->unit_kerja, $reimburse->unit_kerja);
        $this->assertSame('Transport', $reimburse->pengajuan, 'Komponen Reimburse dipaksa Transport.');
        $this->assertNull($reimburse->file_url);
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $reimburse->status);

        // Anggota disalin apa adanya dari induk.
        $this->assertCount(1, $reimburse->anggota);
        $this->assertSame('Ketua Induk', $reimburse->anggota->sole()->nama);
        $this->assertSame('Ketua Tim', $reimburse->anggota->sole()->jabatan_sp);
    }

    public function test_reimburse_wajib_menunjuk_induk_dan_hanya_boleh_satu_per_induk(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatIndukUangHarian($pptk);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'status_sp' => 'Baru',
        ])->assertSessionHasErrors('sp_induk_id');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertRedirect(route('surat-perintah.index'));

        // Percobaan kedua pada induk yang sama ditolak.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertSessionHasErrors('sp_induk_id');

        $this->assertSame(1, SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->count());
    }

    public function test_sp_reimburse_tidak_bisa_dijadikan_induk_reimburse_lain(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatIndukUangHarian($pptk);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ]);

        $reimburse = SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->sole();

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $reimburse->id,
            'status_sp' => 'Baru',
        ])->assertSessionHasErrors('sp_induk_id');

        $this->assertSame(1, SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->count());
    }

    public function test_induk_tanpa_anggota_tidak_muncul_sebagai_pilihan_reimburse(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatIndukUangHarian($pptk);

        $this->actingAs($pptk)->get(route('surat-perintah.create'))->assertOk()->assertSee($induk->nomor_sp);

        $induk->anggota()->delete();

        $this->actingAs($pptk)->get(route('surat-perintah.create'))->assertOk();
        $this->assertCount(0, SuratPerintah::calonIndukReimburse());
    }

    // ---------------- Form publik ----------------

    public function test_form_publik_menerima_input_tanpa_login(): void
    {
        Storage::fake('local');
        $orang = $this->pegawai('Budi Santoso');

        $this->post(route('sp.input.store'), $this->payload([
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ]))->assertRedirect(route('surat-perintah.monitoring'));

        $sp = SuratPerintah::sole();
        $this->assertTrue($sp->dipantau);
        $this->assertTrue($sp->sumber_npd, 'SP baru otomatis menjadi sumber data NPD.');
        $this->assertSame(SuratPerintah::JENIS_UANG_HARIAN, $sp->jenis_permintaan);
    }
}
