<?php

namespace Tests\Feature;

use App\Models\AnggotaKeluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanTunjangan;
use App\Models\TunjanganKeluarga;
use App\Models\TunjanganKeluargaImport;
use App\Models\User;
use App\Services\TunjanganKeluargaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TunjanganKeluargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_batas_usia_gas_tepat_21_dan_25_tahun_serta_perpanjangan_kuliah(): void
    {
        $service = app(TunjanganKeluargaService::class);
        $acuan = CarbonImmutable::parse('2026-07-21');
        $this->assertSame('lt21', $service->bucketUsia(CarbonImmutable::parse('2005-07-22'), $acuan));
        $this->assertSame('21to25', $service->bucketUsia(CarbonImmutable::parse('2005-07-21'), $acuan));
        $this->assertSame('21to25', $service->bucketUsia(CarbonImmutable::parse('2001-07-21'), $acuan));
        $this->assertSame('gt25', $service->bucketUsia(CarbonImmutable::parse('2001-07-20'), $acuan));
        $this->assertNull(TunjanganKeluargaService::parseTanggal('31/02/2026'));

        $anak = new AnggotaKeluarga(['hubungan' => 'anak', 'tanggal_lahir' => '2005-07-21', 'status_tunjangan' => true, 'perpanjangan_kuliah' => false]);
        $this->assertFalse($service->kelayakan($anak, $acuan)['aktif']);
        $anak->perpanjangan_kuliah = true;
        $this->assertTrue($service->kelayakan($anak, $acuan)['aktif']);
        $anak->status_tunjangan = false;
        $this->assertFalse($service->kelayakan($anak, $acuan)['aktif']);
    }

    public function test_maksimal_dua_anak_penerima_ditegakkan_sebelum_master_berubah(): void
    {
        $pegawai = $this->pegawai('100');
        $this->expectException(ValidationException::class);
        try {
            app(TunjanganKeluargaService::class)->simpanKeluarga($pegawai, ['anak' => [
                ['nama' => 'A', 'status_tunjangan' => true], ['nama' => 'B', 'status_tunjangan' => true], ['nama' => 'C', 'status_tunjangan' => true],
            ]]);
        } finally {
            $this->assertSame(0, TunjanganKeluarga::count());
        }
    }

    public function test_form_publik_menyimpan_pengajuan_dan_lampiran_dengan_nama_acak_di_private_storage(): void
    {
        Storage::fake('local');
        $pegawai = $this->pegawai('200');
        $response = $this->post(route('tunjangan.submit'), [
            'nama_pegawai' => $pegawai->nama, 'nip' => $pegawai->nip, 'keterangan' => 'Perubahan data keluarga untuk pengujian.',
            'anak' => [['nama' => 'Anak Uji', 'tanggal_lahir' => '2010-01-01', 'status_tunjangan' => '1']],
            'lampiran' => UploadedFile::fake()->create('dokumen-rahasia.pdf', 100, 'application/pdf'),
        ]);
        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $pengajuan = PengajuanPerubahanTunjangan::with('lampiran')->sole();
        $this->assertSame('diajukan', $pengajuan->status);
        $this->assertNotSame('dokumen-rahasia.pdf', basename($pengajuan->lampiran->first()->path));
        Storage::disk('local')->assertExists($pengajuan->lampiran->first()->path);
        $this->assertSame(0, TunjanganKeluarga::count());
        $this->actingAs($this->user('pptk'))->get(route('tunjangan.lampiran.download', $pengajuan->lampiran->first()))->assertForbidden();
        $this->actingAs($this->user('bendahara_pengeluaran'))->get(route('tunjangan.lampiran.download', $pengajuan->lampiran->first()))->assertOk();
    }

    public function test_mime_upload_dan_spam_honeypot_ditolak(): void
    {
        Storage::fake('local');
        $base = ['nama_pegawai' => 'Uji', 'keterangan' => 'Keterangan perubahan yang cukup panjang.'];
        $this->post(route('tunjangan.submit'), $base + ['website' => 'spam', 'lampiran' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('website');
        $this->post(route('tunjangan.submit'), $base + ['lampiran' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('lampiran');
        $this->assertSame(0, PengajuanPerubahanTunjangan::count());
    }

    public function test_form_publik_dibatasi_lima_permintaan_per_menit(): void
    {
        $this->get(route('tunjangan.form'))->assertOk();
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
        for ($i = 0; $i < 5; $i++) {
            $client->post(route('tunjangan.submit'), [])->assertRedirect();
        }
        $client->post(route('tunjangan.submit'), [])->assertTooManyRequests();
    }

    public function test_persetujuan_transaksional_mengubah_master_dan_akses_role_dijaga(): void
    {
        Storage::fake('local');
        $pegawai = $this->pegawai('300');
        $pengajuan = PengajuanPerubahanTunjangan::create(['pegawai_id' => $pegawai->id, 'nama_pegawai' => $pegawai->nama, 'nip' => $pegawai->nip,
            'payload' => ['pasangan' => ['nama' => 'Pasangan'], 'anak' => [['nama' => 'Anak', 'tanggal_lahir' => '2010-01-01', 'status_tunjangan' => true]]],
            'keterangan' => 'Uji', 'status' => 'diajukan', 'diajukan_at' => now()]);
        $pptk = $this->user('pptk');
        $bendahara = $this->user('bendahara_pengeluaran');
        $perencanaan = $this->user('perencanaan');
        $this->actingAs($pptk)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui', 'pegawai_id' => $pegawai->id])->assertForbidden();
        $this->actingAs($bendahara)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui', 'pegawai_id' => $pegawai->id])->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $pengajuan->fresh()->status);
        $this->assertDatabaseHas('anggota_keluarga', ['nama' => 'Anak', 'status_tunjangan' => true]);
        $this->assertDatabaseHas('audit_log', ['user_id' => $bendahara->id, 'aktivitas' => 'Setujui Perubahan Tunjangan']);
        $this->actingAs($perencanaan)->get(route('tunjangan.dashboard'))->assertOk();
        $layanan = $this->user('layanan');
        $this->actingAs($layanan)->get(route('tunjangan.monitoring'))->assertForbidden();
    }

    public function test_import_awal_preview_tidak_mengubah_master_lalu_konfirmasi_menyimpan(): void
    {
        $pegawai = $this->pegawai('400');
        $admin = $this->user('superadmin');
        $this->actingAs($this->user('pptk'))->get(route('tunjangan.import.create'))->assertForbidden();
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['Nama Pegawai', 'NIP', 'Nama Pasangan', 'Tanggal Lahir Pasangan', 'Status Pasangan', 'Nama Anak 1', 'Tanggal Lahir Anak 1', 'Status Anak 1', 'Keterangan Anak 1'],
            [$pegawai->nama, $pegawai->nip, 'Pasangan Import', '01/01/1985', 'Aktif', 'Anak Import', '01/01/2010', 'Aktif', ''],
        ]);
        $temporary = tempnam(sys_get_temp_dir(), 'tk-import-');
        $path = $temporary.'.xlsx';
        unlink($temporary);
        (new Xlsx($sheet))->save($path);
        try {
            $this->actingAs($admin)->post(route('tunjangan.import.store'), ['file' => new UploadedFile($path, 'lama.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])->assertRedirect();
            $this->assertSame(0, TunjanganKeluarga::count());
            $import = TunjanganKeluargaImport::sole();
            $this->assertSame(1, $import->baris_valid);
            $this->actingAs($admin)->post(route('tunjangan.import.confirm', $import))->assertRedirect(route('tunjangan.monitoring'));
            $this->assertDatabaseHas('tunjangan_keluarga', ['pegawai_id' => $pegawai->id]);
            $this->assertDatabaseHas('anggota_keluarga', ['nama' => 'Anak Import']);
            $this->assertDatabaseHas('audit_log', ['user_id' => $admin->id, 'aktivitas' => 'Import Awal Tunjangan Keluarga']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function pegawai(string $nip): Pegawai
    {
        return Pegawai::create(['nama' => 'Pegawai '.$nip, 'nip' => $nip, 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat', 'aktif' => true]);
    }

    private function user(string $role): User
    {
        return User::create(['username' => 'tk-'.$role, 'nama' => $role, 'role' => $role, 'password' => 'rahasia']);
    }
}
