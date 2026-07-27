<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuratPerintahAnggotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_anggota_sp_disimpan_dan_disediakan_ke_form_npd_perjalanan_dinas(): void
    {
        Storage::fake('local');
        $pptk = User::create([
            'username' => 'pptk-anggota-sp',
            'nama' => 'PPTK Anggota SP',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);
        $ketua = Pegawai::create([
            'nama' => 'Ketua Dari SP',
            'nip' => '199001012010011001',
            'jabatan' => 'Auditor Ahli',
            'bidang' => 'Inspektur Pembantu I',
            'rekening' => '100200300',
            'aktif' => true,
        ]);
        $anggota = Pegawai::create([
            'nama' => 'Anggota Dari SP',
            'nip' => '199202022012021002',
            'jabatan' => 'Pengawas',
            'bidang' => 'Inspektur Pembantu I',
            'rekening' => '400500600',
            'aktif' => true,
        ]);

        $this->actingAs($pptk)
            ->get(route('surat-perintah.create'))
            ->assertOk()
            ->assertSee('Anggota Surat Perintah')
            ->assertSee('Ketua Dari SP');

        $response = $this->actingAs($pptk)->post(route('surat-perintah.store'), [
            'nomor_sp' => '010/SP/ANGGOTA/2026',
            'tanggal_sp' => '2026-07-27',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Bendahara',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '27 Juli 2026',
            'keterangan' => 'Perjalanan dengan anggota dari SP.',
            'status_sp' => 'Baru',
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
            'anggota' => [
                ['pegawai_id' => $ketua->id, 'jabatan_sp' => 'Ketua Tim'],
                ['pegawai_id' => $anggota->id, 'jabatan_sp' => 'Anggota'],
            ],
        ]);

        $sp = SuratPerintah::with('anggota.pegawai')->sole();
        $response->assertRedirect(route('surat-perintah.index'));
        $this->assertCount(2, $sp->anggota);
        $this->assertSame('Ketua Tim', $sp->anggota[0]->jabatan_sp);
        $this->assertSame($anggota->id, $sp->anggota[1]->pegawai_id);

        $this->actingAs($pptk)
            ->get(route('npd.pd.create'))
            ->assertOk()
            ->assertSee('Ketua Dari SP')
            ->assertSee('Anggota Dari SP')
            ->assertSee('importSpAnggota', false);
    }
}
