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
 * Input SP: Rincian Tanggal lewat kalender & Tujuan Transfer lewat dropdown.
 *
 * Adopsi dari GAS (#48 dan #51 di README_PERUBAHAN). Keduanya murni tampilan —
 * yang tersimpan tetap satu string per kolom — jadi yang dijaga di sini adalah
 * kabelnya: komponennya benar-benar terkirim, isian yang benar yang bernama
 * `tujuan_transfer`, dan formulir edit membuka mode yang tepat untuk nilai
 * yang sudah ada.
 */
class InputSpKalenderTujuanTest extends TestCase
{
    use RefreshDatabase;

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

    private function superadmin(): User
    {
        return User::create([
            'username' => 'uji-superadmin-sp-ui',
            'nama' => 'Penguji Superadmin',
            'role' => 'superadmin',
            'password' => 'rahasia-uji',
        ]);
    }

    private function buatSp(User $aktor, string $tujuan): SuratPerintah
    {
        Storage::fake('local');
        $orang = $this->pegawai('Ketua Tim Uji');

        $this->actingAs($aktor)->post(route('surat-perintah.store'), [
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'nomor_sp' => '900/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => $tujuan,
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '1-2, 4-7 Juli 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'komponen' => ['Uang Harian'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ])->assertRedirect(route('surat-perintah.index'));

        return SuratPerintah::latest('id')->firstOrFail();
    }

    public function test_komponen_kalender_ikut_di_semua_layout(): void
    {
        foreach (['app', 'standalone', 'standalone-wide'] as $layout) {
            $isi = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));

            $this->assertStringContainsString(
                "@include('layouts.partials.kalender-tanggal')",
                $isi,
                "Layout {$layout} kehilangan komponen pemilih kalender."
            );
        }
    }

    public function test_rincian_tanggal_jadi_pemicu_kalender_bukan_isian_teks_bebas(): void
    {
        $response = $this->lolosGerbangLayanan()->get(route('sp.input.create'));

        $response->assertStatus(200);
        // Isiannya tetap bernama sama - hanya jadi read-only pemicu kalender.
        $response->assertSee('id="rincian_tgl_bayar" name="rincian_tgl_bayar" data-kalender readonly', false);
        $response->assertSee('input[data-kalender]', false);
        // Contoh ketikan lama diganti ajakan mengklik.
        $response->assertDontSee('Contoh: 1 - 2 Mei 2026', false);
    }

    public function test_tujuan_transfer_jadi_dropdown_pegawai_dengan_pilihan_isi_manual(): void
    {
        $this->pegawai('Budi Santoso', ['jabatan' => 'Auditor Ahli Muda', 'bidang' => 'Inspektur Pembantu I']);

        $response = $this->lolosGerbangLayanan()->get(route('sp.input.create'));

        $response->assertStatus(200);
        // Label tidak lagi menyebut "(Nama Orang)": tujuannya boleh non-pegawai.
        $response->assertSee('>Tujuan Transfer</label>', false);
        $response->assertDontSee('Tujuan Transfer (Nama Orang)', false);
        // Dropdown-nya ikut komponen pencarian yang sama dengan isian lain.
        $response->assertSee('<select id="tujuan_transfer_pilih" data-cari data-tujuan-select', false);
        $response->assertSee('<option value="Budi Santoso"', false);
        $response->assertSee('data-sub="Auditor Ahli Muda — Inspektur Pembantu I"', false);
        $response->assertSee('data-tujuan-manual', false);
        // Yang terkirim tetap satu isian bernama tujuan_transfer.
        $response->assertSee('name="tujuan_transfer" data-tujuan-nilai', false);
    }

    public function test_edit_sp_membuka_mode_dropdown_bila_tujuan_cocok_nama_pegawai(): void
    {
        $aktor = $this->superadmin();
        $this->pegawai('Siti Rahayu');
        $sp = $this->buatSp($aktor, 'Siti Rahayu');

        $response = $this->actingAs($aktor)->get(route('surat-perintah.edit', $sp));

        $response->assertStatus(200);
        $response->assertSee('<option value="Siti Rahayu" selected', false);
        // Isian bebasnya tersembunyi & mati, jadi tidak bisa ikut terisi.
        $response->assertSee('id="tujuan_transfer_manual" data-tujuan-teks', false);
        $response->assertSee('value="" hidden disabled>', false);
        // Rincian tanggal tersimpan tetap tampil apa adanya.
        $response->assertSee('value="1-2, 4-7 Juli 2026"', false);
    }

    public function test_edit_sp_membuka_mode_isi_manual_bila_tujuan_di_luar_data_pegawai(): void
    {
        $aktor = $this->superadmin();
        $sp = $this->buatSp($aktor, 'CV Mitra Sejahtera');

        $response = $this->actingAs($aktor)->get(route('surat-perintah.edit', $sp));

        $response->assertStatus(200);
        // Centang "Isi Manual" sudah aktif dan nilainya tetap utuh di isian bebas.
        $response->assertSee('<input type="checkbox" data-tujuan-manual checked>', false);
        $response->assertSee('value="CV Mitra Sejahtera"', false);
        // Dropdown pegawai yang justru disembunyikan.
        $response->assertSee('data-cari data-tujuan-select hidden disabled>', false);
    }

    public function test_penyimpanan_tidak_berubah_tetap_satu_string_per_kolom(): void
    {
        $aktor = $this->superadmin();
        $this->pegawai('Siti Rahayu');
        $sp = $this->buatSp($aktor, 'Siti Rahayu');

        $this->assertSame('Siti Rahayu', $sp->tujuan_transfer);
        $this->assertSame('1-2, 4-7 Juli 2026', $sp->rincian_tgl_bayar);
    }

    public function test_isian_yang_ditolak_validasi_kembali_ke_mode_yang_sama(): void
    {
        $aktor = $this->superadmin();
        $this->pegawai('Siti Rahayu');

        // Nomor SP dikosongkan supaya validasi gagal dan formulir dimuat ulang
        // dari old(); mode Tujuan Transfer harus mengikuti nilai yang diketik,
        // bukan kembali ke dropdown dan menghilangkan isian pengguna.
        $response = $this->actingAs($aktor)
            ->from(route('surat-perintah.create'))
            ->post(route('surat-perintah.store'), [
                'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
                'nomor_sp' => '',
                'tujuan_transfer' => 'CV Mitra Sejahtera',
                'rincian_tgl_bayar' => '5 Juli 2026',
            ]);

        $response->assertRedirect(route('surat-perintah.create'));

        $lanjutan = $this->actingAs($aktor)->get(route('surat-perintah.create'));
        $lanjutan->assertSee('<input type="checkbox" data-tujuan-manual checked>', false);
        $lanjutan->assertSee('value="CV Mitra Sejahtera"', false);
        $lanjutan->assertSee('value="5 Juli 2026"', false);
    }
}
