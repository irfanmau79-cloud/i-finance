<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Edit Surat Perintah + penyaringan SP pada Pembuatan NPD.
 * Port dari editSP() dan penyaringan jenis di muatOrderanSP() (gas-lama).
 */
class SuratPerintahEditTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'edit-'.$role,
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
            'bidang' => 'Sekretariat',
            'rekening' => '100200300',
            'aktif' => true,
        ], $override));
    }

    private function buatSp(User $pptk, array $override = []): SuratPerintah
    {
        $orang = $this->pegawai('Ketua Awal');

        $this->actingAs($pptk)->post(route('surat-perintah.store'), array_replace([
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'nomor_sp' => '087/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Koordinator',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'komponen' => ['Uang Harian'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ], $override))->assertRedirect(route('surat-perintah.index'));

        return SuratPerintah::latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $override */
    private function payloadEdit(SuratPerintah $sp, array $override = []): array
    {
        return array_replace([
            'nomor_sp' => $sp->nomor_sp,
            'tanggal_sp' => $sp->tanggal_sp->format('Y-m-d'),
            'unit_kerja' => $sp->unit_kerja,
            'lokasi' => $sp->lokasi,
            'nama_pengirim' => $sp->nama_pengirim,
            'tujuan_transfer' => $sp->tujuan_transfer,
            'irban_dibayar' => $sp->irban_dibayar ? '1' : '0',
            'rincian_tgl_bayar' => $sp->rincian_tgl_bayar,
            'keterangan' => $sp->keterangan,
            'status_sp' => $sp->status_sp,
            'komponen' => $sp->pengajuanArray(),
            'anggota' => $sp->anggota->map(fn ($a) => $a->sebagaiInput())->all(),
        ], $override);
    }

    public function test_halaman_edit_terbuka_dan_menampilkan_anggota_tersimpan(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $sp = $this->buatSp($pptk);

        $this->actingAs($pptk)->get(route('surat-perintah.edit', $sp))
            ->assertOk()
            ->assertSee('Ketua Awal')
            ->assertSee('Anggota Surat Perintah')
            // Jenis permintaan tidak bisa diubah setelah SP dibuat.
            ->assertSee('Jenis permintaan tidak bisa diubah setelah SP dibuat.');
    }

    public function test_edit_menambah_dan_menghapus_anggota_serta_mempertahankan_snapshot(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $sp = $this->buatSp($pptk);
        $baru = $this->pegawai('Anggota Tambahan');

        $anggotaLama = $sp->anggota->sole()->sebagaiInput();

        $this->actingAs($pptk)->put(route('surat-perintah.update', $sp), $this->payloadEdit($sp, [
            'keterangan' => 'Reviu LKPD (revisi)',
            'anggota' => [
                $anggotaLama,
                ['pegawai_id' => $baru->id, 'nama' => $baru->nama, 'jabatan_sp' => 'Anggota'],
            ],
        ]))->assertRedirect(route('surat-perintah.index'));

        $sp->refresh()->load('anggota');
        $this->assertSame('Reviu LKPD (revisi)', $sp->keterangan);
        $this->assertCount(2, $sp->anggota);
        $this->assertSame('Ketua Awal', $sp->anggota[0]->nama);
        $this->assertSame('Anggota Tambahan', $sp->anggota[1]->nama);

        // Kurangi lagi jadi satu orang.
        $this->actingAs($pptk)->put(route('surat-perintah.update', $sp), $this->payloadEdit($sp->fresh()->load('anggota'), [
            'anggota' => [$anggotaLama],
        ]))->assertRedirect(route('surat-perintah.index'));

        $this->assertCount(1, $sp->fresh()->anggota);
    }

    public function test_edit_boleh_mengosongkan_anggota(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $sp = $this->buatSp($pptk);

        // Berbeda dengan form Input, anggota tidak wajib saat edit - sama
        // seperti editSP() di GAS yang tidak mengirim parameter "wajib".
        $this->actingAs($pptk)->put(route('surat-perintah.update', $sp), $this->payloadEdit($sp, ['anggota' => []]))
            ->assertRedirect(route('surat-perintah.index'));

        $this->assertCount(0, $sp->fresh()->anggota);
    }

    public function test_edit_menolak_perubahan_jenis_permintaan(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $sp = $this->buatSp($pptk);

        $this->actingAs($pptk)->put(route('surat-perintah.update', $sp), $this->payloadEdit($sp, [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
        ]))->assertSessionHasErrors('jenis_permintaan');

        $this->assertSame(SuratPerintah::JENIS_UANG_HARIAN, $sp->fresh()->jenis_permintaan);
    }

    public function test_edit_menolak_nomor_sp_yang_sudah_dipakai_sp_lain(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $satu = $this->buatSp($pptk);
        $dua = $this->buatSp($pptk, ['nomor_sp' => '088/PW.02.01/Sekre']);

        $this->actingAs($pptk)->put(route('surat-perintah.update', $dua), $this->payloadEdit($dua, [
            'nomor_sp' => $satu->nomor_sp,
        ]))->assertSessionHasErrors('nomor_sp');

        // Menyimpan nomornya sendiri tetap boleh.
        $this->actingAs($pptk)->put(route('surat-perintah.update', $dua), $this->payloadEdit($dua))
            ->assertRedirect(route('surat-perintah.index'));
    }

    public function test_sp_reimburse_tidak_muncul_di_pembuatan_npd_perjalanan_dinas(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatSp($pptk);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ])->assertRedirect(route('surat-perintah.index'));

        $reimburse = SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->sole();

        // Dropdown NPD Perjadin hanya memuat SP Uang Harian/Akomodasi.
        $this->actingAs($pptk)->get(route('npd.pd.create'))
            ->assertOk()
            ->assertViewHas('suratPerintahList', fn ($daftar) => $daftar->contains('id', $induk->id)
                && ! $daftar->contains('id', $reimburse->id));

        $this->assertCount(1, SuratPerintah::sumberNpdPerjalanan()->get());
    }

    public function test_hapus_sp_reimburse_tidak_menghapus_induknya(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatSp($pptk);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ]);

        $reimburse = SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->sole();

        $this->actingAs($pptk)->delete(route('surat-perintah.destroy', $reimburse))
            ->assertRedirect(route('surat-perintah.index'));

        $this->assertNull(SuratPerintah::find($reimburse->id));
        $this->assertNotNull(SuratPerintah::find($induk->id));

        // Induknya kini bisa dibuatkan entri Reimburse lagi.
        $this->assertCount(1, SuratPerintah::calonIndukReimburse());
    }

    public function test_hapus_sp_induk_melepas_tautan_reimburse_tanpa_ikut_menghapusnya(): void
    {
        Storage::fake('local');
        $pptk = $this->user('pptk');
        $induk = $this->buatSp($pptk);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $induk->id,
            'status_sp' => 'Baru',
        ]);

        $reimburse = SuratPerintah::where('jenis_permintaan', SuratPerintah::JENIS_REIMBURSE)->sole();

        $this->actingAs($pptk)->delete(route('surat-perintah.destroy', $induk));

        // sp_induk_id memakai nullOnDelete: entri Reimburse tetap ada sebagai
        // dokumen, hanya tautan induknya yang lepas.
        $this->assertNull(SuratPerintah::find($induk->id));
        $this->assertNotNull($reimburse->fresh());
        $this->assertNull($reimburse->fresh()->sp_induk_id);
    }
}
