<?php

namespace Tests\Feature;

use App\Models\AnggotaKeluarga;
use App\Models\GajiInduk;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Halaman layanan kini di balik gerbang kata sandi bersama. Yang diuji
        // di berkas ini isi halamannya, bukan gerbangnya - gerbangnya punya
        // GerbangLayananTest sendiri.
        $this->lolosGerbangLayanan();
    }

    public function test_form_perubahan_pengguna_terautentikasi_memakai_shell_dan_menu_aktif(): void
    {
        $response = $this->actingAs($this->user('pptk'))->get(route('tunjangan.form'));

        $response->assertOk()
            ->assertSee('id="app-shell"', false)
            ->assertSee('id="nav-tk-parent"', false)
            ->assertSee('class="sb-item sub active" href="'.route('tunjangan.form').'"', false)
            // Langkah pertama: panel NIP. Formulirnya baru muncul sesudah itu.
            ->assertSee('action="'.route('tunjangan.form.buka').'"', false)
            ->assertSee('name="rek4"', false)
            ->assertDontSee('name="lampiran"', false);

        $this->assertMatchesRegularExpression(
            '/<div class="sb-group open">\s*<div class="sb-item sb-parent" id="nav-tk-parent">/s',
            $response->getContent()
        );
    }

    public function test_form_perubahan_tamu_layanan_memakai_shell_dan_sidebar_layanan(): void
    {
        $response = $this->get(route('tunjangan.form'));

        $response->assertOk()
            ->assertSessionHas('guest_layanan', true)
            ->assertSee('id="app-shell"', false)
            ->assertSee('Pengguna Layanan')
            ->assertSee('id="nav-tk-parent"', false)
            ->assertSee('class="sb-item sub active" href="'.route('tunjangan.form').'"', false)
            ->assertSee('action="'.route('tunjangan.form.buka').'"', false);

        $this->assertMatchesRegularExpression(
            '/<div class="sb-group open">\s*<div class="sb-item sb-parent" id="nav-tk-parent">/s',
            $response->getContent()
        );
    }

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
        $pegawai = $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Lama');
        $this->post(route('tunjangan.form.buka'), ['nip' => '199001012015011001', 'rek4' => '4567'])->assertRedirect(route('tunjangan.form'));

        $response = $this->post(route('tunjangan.submit'), [
            // Nama dan NIP karangan diabaikan: pegawainya dari NIP yang dibuka.
            'nama_pegawai' => 'Nama Karangan', 'nip' => '000',
            'keterangan' => 'Perubahan data keluarga untuk pengujian.',
            'anak' => [['nama' => 'Anak Uji', 'tanggal_lahir' => '2010-01-01', 'status_tunjangan' => '1']],
            'lampiran' => UploadedFile::fake()->create('dokumen-rahasia.pdf', 100, 'application/pdf'),
        ]);
        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $pengajuan = PengajuanPerubahanTunjangan::with('lampiran')->sole();
        $this->assertSame('diajukan', $pengajuan->status);
        $this->assertSame($pegawai->id, $pengajuan->pegawai_id);
        $this->assertSame($pegawai->nama, $pengajuan->nama_pegawai);
        $this->assertSame('199001012015011001', $pengajuan->nip);
        $this->assertNotSame('dokumen-rahasia.pdf', basename($pengajuan->lampiran->first()->path));
        Storage::disk('local')->assertExists($pengajuan->lampiran->first()->path);
        // Master belum berubah sampai di-approve.
        $this->assertSame(['Anak Lama'], AnggotaKeluarga::pluck('nama')->all());
        $this->actingAs($this->user('pptk'))->get(route('tunjangan.lampiran.download', $pengajuan->lampiran->first()))->assertForbidden();
        $this->actingAs($this->user('bendahara_pengeluaran'))->get(route('tunjangan.lampiran.download', $pengajuan->lampiran->first()))->assertOk();
        $this->actingAs($this->user('kepegawaian'))->get(route('tunjangan.lampiran.download', $pengajuan->lampiran->first()))->assertOk();
    }

    public function test_formulir_perubahan_terkunci_sampai_nip_dan_rekening_cocok(): void
    {
        Storage::fake('local');
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Sendiri');
        $this->pegawaiBergaji('198505052010012002', '0009876543', 'Anak Orang Lain');

        // Belum membuka NIP: tidak ada data siapa pun, dan mengirim ditolak.
        $this->get(route('tunjangan.form'))->assertOk()
            ->assertSee('Masukkan NIP Pegawai')
            ->assertDontSee('Anak Sendiri')
            ->assertDontSee('Anak Orang Lain');
        $this->post(route('tunjangan.submit'), [
            'nama_pegawai' => 'Pegawai 198505052010012002', 'nip' => '198505052010012002',
            'keterangan' => 'Mencoba mengirim tanpa membuka NIP.',
            'lampiran' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('tunjangan.form'))->assertSessionHasErrors('nip');
        $this->assertSame(0, PengajuanPerubahanTunjangan::count());

        // NIP orang lain dengan rekening sendiri: ditolak, tetap terkunci.
        $this->post(route('tunjangan.form.buka'), ['nip' => '198505052010012002', 'rek4' => '4567'])->assertSessionHasErrors('nip');
        $this->get(route('tunjangan.form'))->assertSee('Masukkan NIP Pegawai')->assertDontSee('Anak Orang Lain');

        // Cocok: nama pegawai dan data keluarganya sendiri tampil.
        $this->post(route('tunjangan.form.buka'), ['nip' => '19900101 201501 1 001', 'rek4' => '4567'])->assertRedirect(route('tunjangan.form'));
        $halaman = $this->get(route('tunjangan.form'))->assertOk();
        $halaman->assertSee('Pegawai 199001012015011001')
            ->assertSee('Anak Sendiri')
            ->assertSee('value="2015-05-05"', false)
            ->assertSee('Ubah Data')
            ->assertSee('+ Tambah Anak')
            ->assertSee('action="'.route('tunjangan.submit').'"', false)
            ->assertDontSee('Anak Orang Lain');

        // Ganti NIP mengunci kembali.
        $this->post(route('tunjangan.form.ganti-nip'))->assertRedirect(route('tunjangan.form'));
        $this->get(route('tunjangan.form'))->assertSee('Masukkan NIP Pegawai')->assertDontSee('Anak Sendiri');
    }

    public function test_kepegawaian_membuka_formulir_cukup_dengan_nip_dan_nip_tak_dikenal_ditolak(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Pegawai');
        $kepegawaian = $this->user('kepegawaian');

        $this->actingAs($kepegawaian)->get(route('tunjangan.form'))->assertOk()
            ->assertSee('Masukkan NIP Pegawai')
            ->assertDontSee('name="rek4"', false);

        $this->actingAs($kepegawaian)->post(route('tunjangan.form.buka'), ['nip' => '111'])->assertSessionHasErrors('nip');
        $this->actingAs($kepegawaian)->post(route('tunjangan.form.buka'), ['nip' => '199001012015011001'])->assertRedirect(route('tunjangan.form'));
        $this->actingAs($kepegawaian)->get(route('tunjangan.form'))->assertSee('Anak Pegawai')->assertSee('Ganti NIP');

        // Jalan pintas tanpa rekening itu hanya milik role bebas gerbang.
        $pptk = $this->user('pptk');
        $this->actingAs($pptk)->post(route('tunjangan.form.ganti-nip'));
        $this->actingAs($pptk)->post(route('tunjangan.form.buka'), ['nip' => '199001012015011001'])->assertSessionHasErrors('rek4');
        $this->actingAs($pptk)->get(route('tunjangan.form'))->assertSee('Masukkan NIP Pegawai')->assertDontSee('Anak Pegawai');
    }

    /**
     * Alur utuhnya: pegawai membuka datanya, mengubah satu anggota dan
     * menambah satu, lalu Kepegawaian meng-approve - Data Tunjangan
     * Keluarga pegawai ITU berubah, anggota yang tidak disentuh tetap ada.
     */
    public function test_perubahan_yang_diapprove_langsung_menjadi_data_tunjangan_keluarga_pegawainya(): void
    {
        Storage::fake('local');
        $pegawai = $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Pertama');
        $this->post(route('tunjangan.form.buka'), ['nip' => '199001012015011001', 'rek4' => '4567']);

        $this->post(route('tunjangan.submit'), [
            'keterangan' => 'Menikah dan lahir anak kedua.',
            'pasangan' => ['nama' => 'Pasangan Baru', 'tanggal_lahir' => '1992-02-02', 'status_tunjangan' => '1'],
            'anak' => [
                // Tidak diubah: terkirim apa adanya dari kartu yang tidak dibuka.
                ['nama' => 'Anak Pertama', 'tanggal_lahir' => '2015-05-05', 'status_tunjangan' => '0', 'perpanjangan_kuliah' => '0'],
                ['nama' => 'Anak Kedua', 'tanggal_lahir' => '2026-08-08', 'status_tunjangan' => '1', 'perpanjangan_kuliah' => '0'],
            ],
            'lampiran' => UploadedFile::fake()->create('akta.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $pengajuan = PengajuanPerubahanTunjangan::sole();
        $this->assertSame(['Anak Pertama'], AnggotaKeluarga::pluck('nama')->all());

        $this->actingAs($this->user('kepegawaian'))
            ->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui'])
            ->assertSessionHasNoErrors();

        $keluarga = TunjanganKeluarga::with('anggota')->sole();
        $this->assertSame($pegawai->id, $keluarga->pegawai_id);
        $this->assertSame(
            ['Anak Kedua', 'Anak Pertama', 'Pasangan Baru'],
            $keluarga->anggota->pluck('nama')->sort()->values()->all()
        );
        // Anak Pertama tidak bertunjangan, jadi yang terhitung satu anak.
        $this->assertSame('K/1', app(TunjanganKeluargaService::class)->statusTunjangan($keluarga));
    }

    public function test_mime_upload_dan_spam_honeypot_ditolak(): void
    {
        Storage::fake('local');
        $base = ['keterangan' => 'Keterangan perubahan yang cukup panjang.'];
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
        $kepegawaian = $this->user('kepegawaian');
        $perencanaan = $this->user('perencanaan');

        // Approve/Tolak hanya milik Kepegawaian dan superadmin. Bendahara
        // Pengeluaran, yang dulu ikut memproses, kini hanya memantau.
        $this->actingAs($pptk)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui'])->assertForbidden();
        $this->actingAs($bendahara)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui'])->assertForbidden();
        $this->assertSame('diajukan', $pengajuan->fresh()->status);

        // Pegawainya sudah tertaut sejak diajukan, jadi Approve tidak perlu
        // memilih pegawai lagi - datanya langsung masuk ke master.
        $this->actingAs($kepegawaian)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui'])->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $pengajuan->fresh()->status);
        $this->assertDatabaseHas('anggota_keluarga', ['nama' => 'Anak', 'status_tunjangan' => true]);
        $this->assertSame($pegawai->id, TunjanganKeluarga::sole()->pegawai_id);
        $this->assertDatabaseHas('audit_log', ['user_id' => $kepegawaian->id, 'aktivitas' => 'Setujui Perubahan Tunjangan']);
        // Dashboard Tunjangan Keluarga tidak lagi dipegang Perencanaan.
        $this->actingAs($perencanaan)->get(route('tunjangan.dashboard'))->assertForbidden();
    }

    public function test_kolom_aksi_monitoring_hanya_untuk_kepegawaian_dan_superadmin(): void
    {
        $pegawai = $this->pegawai('310');
        PengajuanPerubahanTunjangan::create(['pegawai_id' => $pegawai->id, 'nama_pegawai' => $pegawai->nama, 'nip' => $pegawai->nip,
            'payload' => ['pasangan' => ['nama' => 'Pasangan Aksi'], 'anak' => []],
            'keterangan' => 'Uji kolom aksi', 'status' => 'diajukan', 'diajukan_at' => now()]);

        foreach (['kepegawaian', 'superadmin'] as $role) {
            $this->actingAs($this->user($role))->get(route('tunjangan.monitoring'))->assertOk()
                ->assertSee('<th>Aksi</th>', false)
                ->assertSee('value="setujui">Approve</button>', false)
                ->assertSee('value="tolak" formnovalidate>Tolak</button>', false)
                ->assertSee('Tertaut ke <b>'.e($pegawai->nama).'</b>', false)
                ->assertDontSee('<th>Proses</th>', false);
        }

        // Role lain: tabel berhenti di kolom Status.
        foreach (['bendahara_pengeluaran', 'pptk', 'perencanaan', 'pengawas'] as $role) {
            $this->actingAs($this->user($role))->get(route('tunjangan.monitoring'))->assertOk()
                ->assertSee('<th>Status</th>', false)
                ->assertDontSee('<th>Aksi</th>', false)
                ->assertDontSee('<th>Proses</th>', false)
                ->assertDontSee('Approve')
                ->assertDontSee('tk-proses"', false);
        }
    }

    public function test_pengajuan_yang_belum_tertaut_harus_dipilihkan_pegawai_sebelum_diapprove(): void
    {
        $pegawai = $this->pegawai('320');
        $kepegawaian = $this->user('kepegawaian');
        $pengajuan = PengajuanPerubahanTunjangan::create(['pegawai_id' => null, 'nama_pegawai' => 'Tanpa NIP', 'nip' => null,
            'payload' => ['pasangan' => ['nama' => 'Pasangan Lama'], 'anak' => []],
            'keterangan' => 'Pengajuan lama tanpa NIP', 'status' => 'diajukan', 'diajukan_at' => now()]);

        $this->actingAs($kepegawaian)->get(route('tunjangan.monitoring'))->assertOk()->assertSee('Belum tertaut');

        $this->actingAs($kepegawaian)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui'])
            ->assertSessionHasErrors('pegawai_id');
        $this->assertSame('diajukan', $pengajuan->fresh()->status);
        $this->assertSame(0, TunjanganKeluarga::count());

        // Menolak tidak butuh pegawai.
        $lain = PengajuanPerubahanTunjangan::create(['pegawai_id' => null, 'nama_pegawai' => 'Ditolak', 'nip' => null,
            'payload' => ['pasangan' => [], 'anak' => []], 'keterangan' => 'Akan ditolak', 'status' => 'diajukan', 'diajukan_at' => now()]);
        $this->actingAs($kepegawaian)->post(route('tunjangan.pengajuan.proses', $lain), ['aksi' => 'tolak', 'catatan' => 'Tidak lengkap'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $lain->fresh()->status);

        $this->actingAs($kepegawaian)->post(route('tunjangan.pengajuan.proses', $pengajuan), ['aksi' => 'setujui', 'pegawai_id' => $pegawai->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($pegawai->id, $pengajuan->fresh()->pegawai_id);
        $this->assertSame($pegawai->id, TunjanganKeluarga::sole()->pegawai_id);
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

    public function test_data_tunjangan_keluarga_hanya_dikelola_superadmin_dan_jadi_acuan_dashboard(): void
    {
        $pegawai = $this->pegawai('500');
        $admin = $this->user('superadmin');

        // PPTK, Bendahara Pengeluaran, dan Pengawas membaca daftarnya tanpa
        // tombol ubah; mengubah isinya tetap ditolak.
        foreach (['pptk', 'bendahara_pengeluaran', 'pengawas'] as $role) {
            $pembaca = $this->user($role);
            $this->actingAs($pembaca)->get(route('tunjangan.data.index'))
                ->assertOk()
                ->assertSee($pegawai->nama)
                ->assertDontSee('Import Excel')
                ->assertDontSee(route('tunjangan.data.edit', $pegawai), false);
            $this->actingAs($pembaca)->get(route('tunjangan.data.edit', $pegawai))->assertForbidden();
            $this->actingAs($pembaca)->post(route('tunjangan.data.simpan', $pegawai), [])->assertForbidden();
            $this->actingAs($pembaca)->delete(route('tunjangan.data.hapus', $pegawai))->assertForbidden();
        }

        // Role tanpa kunci menunya tidak membuka daftarnya sama sekali.
        foreach (['bpp', 'verifikator', 'inspektur'] as $role) {
            $this->actingAs($this->user($role))->get(route('tunjangan.data.index'))->assertForbidden();
        }

        // Pegawai tanpa data keluarga tetap terdaftar, berstatus TK/0.
        $this->actingAs($admin)->get(route('tunjangan.data.index'))
            ->assertOk()->assertSee($pegawai->nama)->assertSee('TK/0');

        $this->actingAs($admin)->post(route('tunjangan.data.simpan', $pegawai), [
            'pasangan' => ['nama' => 'Pasangan Admin', 'status_tunjangan' => '1'],
            'anak' => [
                ['nama' => 'Anak Satu', 'tanggal_lahir' => now()->subYears(5)->toDateString(), 'status_tunjangan' => '1'],
                ['nama' => 'Anak Dua', 'tanggal_lahir' => now()->subYears(23)->toDateString(), 'status_tunjangan' => '0'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('tunjangan.data.index'));

        $this->assertDatabaseHas('tunjangan_keluarga', ['pegawai_id' => $pegawai->id, 'diperbarui_oleh' => $admin->id]);
        $this->assertDatabaseHas('anggota_keluarga', ['nama' => 'Pasangan Admin', 'hubungan' => 'pasangan', 'status_tunjangan' => true]);
        $this->assertDatabaseHas('audit_log', ['user_id' => $admin->id, 'aktivitas' => 'Perbarui Data Tunjangan Keluarga']);

        // Data yang baru saja diisi manual langsung jadi acuan Dashboard Tunjangan Keluarga.
        $this->actingAs($admin)->get(route('tunjangan.dashboard'))
            ->assertOk()->assertSee('Pasangan Admin')->assertSee('Anak Satu');

        // Tabel Data Tunjangan Keluarga kini meringkas jadi Status Tunjangan:
        // K = punya pasangan, 1 = satu anak yang berhak (anak 23 tahun tanpa
        // surat kuliah tidak dihitung).
        $this->actingAs($admin)->get(route('tunjangan.data.index'))
            ->assertOk()->assertSee($pegawai->nama)->assertSee('K/1');
    }

    public function test_data_tunjangan_keluarga_maksimal_dua_anak_dan_dokumen_pendukung_tersimpan_privat(): void
    {
        Storage::fake('local');
        $pegawai = $this->pegawai('600');
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->post(route('tunjangan.data.simpan', $pegawai), [
            'anak' => [['nama' => 'A'], ['nama' => 'B'], ['nama' => 'C']],
        ])->assertSessionHasErrors('anak');

        $this->actingAs($admin)->post(route('tunjangan.data.simpan', $pegawai), [
            'dokumen_pendukung' => UploadedFile::fake()->create('bukti-rahasia.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $tunjangan = TunjanganKeluarga::where('pegawai_id', $pegawai->id)->sole();
        $this->assertNotNull($tunjangan->dokumen_pendukung_path);
        $this->assertSame('bukti-rahasia.pdf', $tunjangan->dokumen_pendukung_nama);
        Storage::disk('local')->assertExists($tunjangan->dokumen_pendukung_path);

        $this->actingAs($admin)->get(route('tunjangan.data.dokumen', $tunjangan))->assertOk();
        $this->actingAs($this->user('pptk'))->get(route('tunjangan.data.dokumen', $tunjangan))->assertForbidden();
    }

    private function pegawai(string $nip): Pegawai
    {
        return Pegawai::create(['nama' => 'Pegawai '.$nip, 'nip' => $nip, 'jabatan' => 'Auditor', 'bidang' => 'Sekretariat', 'aktif' => true]);
    }

    private function user(string $role): User
    {
        return User::create(['username' => 'tk-'.$role, 'nama' => $role, 'role' => $role, 'password' => 'rahasia']);
    }

    // ---------------- Gerbang privasi Dashboard ----------------

    /** Pegawai + keluarganya + baris gaji yang jadi acuan verifikasi. */
    private function pegawaiBergaji(string $nip, string $rekening, string $namaAnak): Pegawai
    {
        $pegawai = $this->pegawai($nip);

        $keluarga = TunjanganKeluarga::create([
            'pegawai_id' => $pegawai->id,
            'nama_pegawai' => $pegawai->nama,
            'nip' => $nip,
        ]);
        AnggotaKeluarga::create([
            'tunjangan_keluarga_id' => $keluarga->id,
            'hubungan' => 'anak',
            'nama' => $namaAnak,
            'tanggal_lahir' => '2015-05-05',
        ]);

        GajiInduk::create([
            'bulan' => 8, 'tahun' => 2026,
            'nama_pegawai' => $pegawai->nama,
            'nip' => $nip,
            'nomor_rekening_bank_pegawai' => $rekening,
        ]);

        return $pegawai;
    }

    public function test_dashboard_tk_terkunci_untuk_role_di_luar_daftar(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Rahasia');

        $this->actingAs($this->user('pptk'))->get(route('tunjangan.dashboard'))
            ->assertOk()
            ->assertSee('Verifikasi Identitas')
            // Nama anak pegawai lain tidak boleh ikut terkirim ke browser.
            ->assertDontSee('Anak Rahasia');
    }

    public function test_role_penuh_melihat_dashboard_tk_tanpa_verifikasi(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Rahasia');

        $this->actingAs($this->user('superadmin'))->get(route('tunjangan.dashboard'))
            ->assertOk()
            ->assertDontSee('Verifikasi Identitas')
            ->assertSee('Anak Rahasia');

        $this->actingAs($this->user('sekretaris'))->get(route('tunjangan.dashboard'))
            ->assertOk()
            ->assertSee('Anak Rahasia');

        // Kepegawaian ada di daftar bebas gerbang karena merekalah yang
        // memelihara data ini, dan sekarang memegang kunci menu dashboardnya.
        $this->assertContains('kepegawaian', config('akses.role_tk_data_penuh'));
        $this->actingAs($this->user('kepegawaian'))->get(route('tunjangan.dashboard'))
            ->assertOk()
            ->assertDontSee('Verifikasi Identitas')
            ->assertSee('Anak Rahasia');
    }

    public function test_verifikasi_benar_membuka_baris_sendiri_saja(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Sendiri');
        $this->pegawaiBergaji('198505052010012002', '0009876543', 'Anak Orang Lain');
        $pptk = $this->user('pptk');

        $this->actingAs($pptk)
            ->post(route('tunjangan.dashboard.verifikasi'), ['nip' => '199001012015011001', 'rek4' => '4567'])
            ->assertSessionHasNoErrors();

        $halaman = $this->actingAs($pptk)->get(route('tunjangan.dashboard'))->assertOk();

        $halaman->assertSee('Anak Sendiri');
        $halaman->assertDontSee('Anak Orang Lain');
        $halaman->assertSee('Ganti NIP');
        // Kartu agregat tetap menghitung SELURUH pegawai - statistik kantor,
        // bukan data pribadi siapa pun.
        $halaman->assertSee('Jumlah Pegawai');
        $this->assertStringContainsString('>2</div>', $halaman->getContent());
    }

    public function test_verifikasi_salah_ditolak_dan_halaman_tetap_terkunci(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Rahasia');
        $pptk = $this->user('pptk');

        $this->actingAs($pptk)
            ->post(route('tunjangan.dashboard.verifikasi'), ['nip' => '199001012015011001', 'rek4' => '9999'])
            ->assertSessionHasErrors('nip');

        $this->actingAs($pptk)->get(route('tunjangan.dashboard'))
            ->assertSee('Verifikasi Identitas')
            ->assertDontSee('Anak Rahasia');
    }

    public function test_ganti_nip_mengunci_kembali_dashboard(): void
    {
        $this->pegawaiBergaji('199001012015011001', '0001234567', 'Anak Sendiri');
        $pptk = $this->user('pptk');

        $this->actingAs($pptk)->post(route('tunjangan.dashboard.verifikasi'), ['nip' => '199001012015011001', 'rek4' => '4567']);
        $this->actingAs($pptk)->post(route('tunjangan.dashboard.ganti-nip'));

        $this->actingAs($pptk)->get(route('tunjangan.dashboard'))
            ->assertSee('Verifikasi Identitas')
            ->assertDontSee('Anak Sendiri');
    }
}
