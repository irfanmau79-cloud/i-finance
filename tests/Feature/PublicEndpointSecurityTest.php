<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_sp_publik_memvalidasi_mime_pdf_dan_mengacak_nama_file(): void
    {
        Storage::fake('local');
        $payload = $this->payloadForm();

        $this->post(route('sp.input.store'), $payload + [
            'website' => 'https://spam.invalid',
            'file_url' => UploadedFile::fake()->create('sp.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('website');

        $this->post(route('sp.input.store'), $payload + [
            'file_url' => UploadedFile::fake()->create('bukan-pdf.pdf', 20, 'text/plain'),
        ])->assertSessionHasErrors('file_url');
        $this->assertSame(0, SuratPerintah::count());

        $this->post(route('sp.input.store'), $payload + [
            'file_url' => UploadedFile::fake()->create('dokumen-asli.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('surat-perintah.monitoring'));

        $sp = SuratPerintah::sole();
        $this->assertStringStartsWith('private:sp/', $sp->file_url);
        $this->assertNotSame('dokumen-asli.pdf', basename($sp->filePath()));
        Storage::disk('local')->assertExists($sp->filePath());
        $this->actingAs($this->user('pptk'))->get(route('surat-perintah.file', $sp))->assertOk();
        $this->actingAs($this->user('layanan'))->get(route('surat-perintah.file', $sp))->assertForbidden();
    }

    public function test_form_sp_publik_dibatasi_lima_permintaan_per_menit(): void
    {
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88']);
        for ($i = 0; $i < 5; $i++) {
            $client->post(route('sp.input.store'), [])->assertRedirect();
        }
        $client->post(route('sp.input.store'), [])->assertTooManyRequests();
    }

    public function test_file_sp_legacy_di_disk_public_tetap_dapat_diakses_melalui_route_berotorisasi(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sp/legacy.pdf', '%PDF-legacy');
        $sp = SuratPerintah::create($this->payload() + [
            'file_url' => 'sp/legacy.pdf',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
        ]);

        $this->actingAs($this->user('pptk'))->get(route('surat-perintah.file', $sp))->assertOk();
        $this->actingAs($this->user('layanan'))->get(route('surat-perintah.file', $sp))->assertForbidden();
    }

    /** Atribut dasar SP, tanpa field yang khusus milik form. */
    private function payload(): array
    {
        return [
            'nomor_sp' => '001/SP/PUBLIK/2026',
            'tanggal_sp' => '2026-07-21',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Pengirim Uji',
            'tujuan_transfer' => 'Bendahara',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '21 Juli 2026',
            'keterangan' => 'Pengujian keamanan endpoint publik.',
            'status_sp' => 'Baru',
        ];
    }

    /**
     * Isian form Input SP yang sah. Komponen Pembayaran dan anggota (minimal
     * satu orang) kini wajib, mengikuti aturan GAS - lihat
     * StoreSuratPerintahRequest.
     */
    private function payloadForm(): array
    {
        $pegawai = Pegawai::firstOrCreate(
            ['nip' => '199001012010011001'],
            [
                'nama' => 'Anggota Uji Publik',
                'jabatan' => 'Auditor Ahli Muda',
                'bidang' => 'Sekretariat',
                'rekening' => '100200300',
                'aktif' => true,
            ]
        );

        return $this->payload() + [
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'komponen' => ['Uang Harian'],
            'anggota' => [[
                'pegawai_id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'jabatan_sp' => 'Ketua Tim',
            ]],
        ];
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'public-'.$role,
            'nama' => 'Public '.$role,
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }
}
