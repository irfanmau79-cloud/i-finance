<?php

namespace Tests\Feature;

use App\Models\Kpa;
use App\Models\KpaPptk;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembuatan NPD oleh PPTK dibatasi ke Sub Kegiatan limpahannya sendiri.
 *
 * Dropdown Program/Kegiatan/Sub Kegiatan/Kode Rekening/Tagging semuanya
 * diturunkan dari satu daftar Mata Anggaran, jadi menyaring daftarnya sudah
 * cukup untuk membuat Program tanpa Sub Kegiatan limpahan ikut hilang. Yang
 * diuji di sini: daftar itu benar-benar tersaring di keempat jenis NPD
 * berdropdown, batasnya juga ditegakkan saat menyimpan (bukan sekadar
 * disembunyikan di layar), dan draft lama tetap bisa disunting.
 */
class NpdAnggaranPptkTest extends TestCase
{
    use RefreshDatabase;

    private int $urutan = 0;

    private function pegawai(string $nama): Pegawai
    {
        $this->urutan++;

        return Pegawai::create([
            'nama' => $nama,
            'nip' => sprintf('19900101202001%04d', $this->urutan),
            'jabatan' => 'Pejabat Pengujian',
            'bidang' => 'Sekretariat',
            'pangkat' => 'Pembina',
            'aktif' => true,
        ]);
    }

    private function anggaran(string $program, string $sub, string $kode): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => $program,
            'kegiatan' => 'Kegiatan '.$program,
            'sub_kegiatan' => $sub,
            'kode_rekening' => MasterAnggaran::gabungKodeUraian($kode, 'Belanja Pengujian'),
            'pagu' => 500_000_000,
            'aktif' => true,
        ]);
    }

    /** Rantai pejabat lengkap; mengembalikan pegawai PPTK-nya. */
    private function limpahkan(MasterAnggaran $anggaran, ?Pegawai $pptk = null): Pegawai
    {
        if (PejabatOpd::aktif() === null) {
            PejabatOpd::simpan([
                'pa_pegawai_id' => $this->pegawai('PA OPD')->id,
                'bendahara_pengeluaran_pegawai_id' => $this->pegawai('Bendahara OPD')->id,
            ]);
        }

        $pptk ??= $this->pegawai('PPTK Pengujian');

        $kpa = Kpa::create([
            'kpa_pegawai_id' => $this->pegawai('KPA Pengujian')->id,
            'bpp_pegawai_id' => $this->pegawai('BPP Pengujian')->id,
            'aktif' => true,
        ]);
        KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptk->id, 'aktif' => true]);

        // program/sub_kegiatan disimpan terpecah dari kodenya; pelimpahan
        // dicocokkan pada bentuk lengkapnya (kode + nama).
        Pelimpahan::tetapkan(
            [['program' => $anggaran->program_lengkap, 'sub_kegiatan' => $anggaran->sub_kegiatan_lengkap]],
            $kpa->id,
            $kpa->bpp_pegawai_id,
            $pptk->id,
        );

        return $pptk;
    }

    private function user(string $role, string $username, ?Pegawai $pegawai = null): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'pegawai_id' => $pegawai?->id,
            'password' => 'rahasia-uji',
        ]);
    }

    /** @return array<int, string> nama rute create untuk tiap jenis NPD berdropdown anggaran */
    private static function ruteCreate(): array
    {
        return ['npd.bj.create', 'npd.pd.create', 'npd.ns.create', 'npd.kd.create'];
    }

    public function test_pptk_hanya_melihat_sub_kegiatan_yang_dilimpahkan_kepadanya(): void
    {
        $milikSaya = $this->anggaran('Program Pengawasan Internal', '6.01.01.2.01 Sub Kegiatan Milik Saya', '5.1.02.01.01.0001');
        $milikOrangLain = $this->anggaran('Program Penunjang Urusan', '6.01.01.2.02 Sub Kegiatan Milik Orang Lain', '5.1.02.01.01.0002');

        $pptkPegawai = $this->limpahkan($milikSaya);
        $this->limpahkan($milikOrangLain, $this->pegawai('PPTK Lain'));

        $pptk = $this->user('pptk', 'pptk-tersaring', $pptkPegawai);

        foreach (self::ruteCreate() as $rute) {
            $response = $this->actingAs($pptk)->get(route($rute));

            $response->assertOk();
            $response->assertSee('6.01.01.2.01 Sub Kegiatan Milik Saya', false);
            $response->assertDontSee('6.01.01.2.02 Sub Kegiatan Milik Orang Lain', false);
            // Programnya ikut hilang, bukan cuma sub kegiatannya: dropdown
            // Program dibangun dari daftar yang sama.
            $response->assertSee('Program Pengawasan Internal', false);
            $response->assertDontSee('Program Penunjang Urusan', false);
        }
    }

    public function test_superadmin_tetap_melihat_seluruh_mata_anggaran(): void
    {
        $satu = $this->anggaran('Program Pengawasan Internal', '6.01.01.2.01 Sub Kegiatan Satu', '5.1.02.01.01.0001');
        $dua = $this->anggaran('Program Penunjang Urusan', '6.01.01.2.02 Sub Kegiatan Dua', '5.1.02.01.01.0002');
        $this->limpahkan($satu);

        $superadmin = $this->user('superadmin', 'superadmin-anggaran');

        foreach (self::ruteCreate() as $rute) {
            $response = $this->actingAs($superadmin)->get(route($rute));

            $response->assertOk();
            $response->assertSee($satu->sub_kegiatan_lengkap, false);
            $response->assertSee($dua->sub_kegiatan_lengkap, false);
        }
    }

    public function test_pptk_tanpa_pelimpahan_mendapat_keterangan_bukan_dropdown_kosong(): void
    {
        $this->anggaran('Program Penunjang Urusan', '6.01.01.2.02 Sub Kegiatan Orang Lain', '5.1.02.01.01.0002');
        $pptk = $this->user('pptk', 'pptk-tanpa-limpahan', $this->pegawai('PPTK Menganggur'));

        foreach (self::ruteCreate() as $rute) {
            $response = $this->actingAs($pptk)->get(route($rute));

            $response->assertOk();
            $response->assertSee('Belum ada Sub Kegiatan yang dilimpahkan ke akun ini.', false);
            $response->assertDontSee('6.01.01.2.02 Sub Kegiatan Orang Lain', false);
        }
    }

    public function test_akun_pptk_tanpa_tautan_pegawai_tidak_mendapat_seluruh_anggaran(): void
    {
        // Pelimpahan menunjuk pegawai, bukan akun. Akun yang belum ditautkan
        // tidak boleh diperlakukan sebagai "tanpa pembatasan".
        $anggaran = $this->anggaran('Program Pengawasan Internal', '6.01.01.2.01 Sub Kegiatan Satu', '5.1.02.01.01.0001');
        $this->limpahkan($anggaran);

        $pptk = $this->user('pptk', 'pptk-tanpa-pegawai');

        $response = $this->actingAs($pptk)->get(route('npd.bj.create'));

        $response->assertOk();
        $response->assertDontSee($anggaran->sub_kegiatan_lengkap, false);
        $response->assertSee('Belum ada Sub Kegiatan yang dilimpahkan ke akun ini.', false);
    }

    public function test_menyimpan_npd_pada_sub_kegiatan_orang_lain_ditolak(): void
    {
        $milikSaya = $this->anggaran('Program Pengawasan Internal', '6.01.01.2.01 Sub Kegiatan Milik Saya', '5.1.02.01.01.0001');
        $milikOrangLain = $this->anggaran('Program Penunjang Urusan', '6.01.01.2.02 Sub Kegiatan Milik Orang Lain', '5.1.02.01.01.0002');

        $pptkPegawai = $this->limpahkan($milikSaya);
        $this->limpahkan($milikOrangLain, $this->pegawai('PPTK Lain'));

        $pptk = $this->user('pptk', 'pptk-nekat', $pptkPegawai);

        $payload = fn (int $id) => [
            'master_anggaran_id' => $id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-21',
            'bulan' => 7,
            'tahun' => config('anggaran.tahun_aktif'),
            'uraian_kegiatan' => 'Belanja pengujian pembatasan pelimpahan',
            'penerima' => [[
                'nama' => 'CV Uji Pembatasan',
                'rekening' => '1234567890',
                'bruto' => 1_000_000,
                'ppn' => 0,
                'biaya_ku_rtgs' => 0,
                'pph_list' => [],
                'keterangan' => 'Uji',
            ]],
        ];

        // Sub kegiatan orang lain: ditolak walau id-nya dikirim langsung,
        // melewati dropdown yang sudah disaring.
        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $payload($milikOrangLain->id))
            ->assertSessionHasErrors('master_anggaran_id');
        $this->assertSame(0, Npd::count());

        // Sub kegiatan sendiri: tetap bisa dibuat seperti biasa.
        $this->actingAs($pptk)
            ->post(route('npd.bj.store'), $payload($milikSaya->id))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Npd::where('master_anggaran_id', $milikSaya->id)->count());
    }

    public function test_draft_lama_tetap_dapat_disunting_walau_pelimpahannya_sudah_berpindah(): void
    {
        $anggaran = $this->anggaran('Program Pengawasan Internal', '6.01.01.2.01 Sub Kegiatan Berpindah', '5.1.02.01.01.0001');
        $pptkPegawai = $this->limpahkan($anggaran);
        $pptk = $this->user('pptk', 'pptk-pemilik-draft', $pptkPegawai);

        $this->actingAs($pptk)->post(route('npd.bj.store'), [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-21',
            'bulan' => 7,
            'tahun' => config('anggaran.tahun_aktif'),
            'uraian_kegiatan' => 'Belanja pengujian draft berpindah',
            'penerima' => [[
                'nama' => 'CV Uji Pindah',
                'rekening' => '1234567890',
                'bruto' => 1_000_000,
                'ppn' => 0,
                'biaya_ku_rtgs' => 0,
                'pph_list' => [],
                'keterangan' => 'Uji',
            ]],
        ])->assertSessionHasNoErrors();

        $npd = Npd::latest('id')->firstOrFail();

        // Sub kegiatan yang sama dipindahkan ke PPTK lain sesudah draft dibuat.
        $this->limpahkan($anggaran, $this->pegawai('PPTK Penerus'));

        $response = $this->actingAs($pptk)->get(route('npd.bj.edit', $npd));

        $response->assertOk();
        // Nilai yang sedang terpakai tetap ada di daftar; tanpa itu draftnya
        // tidak bisa disunting sama sekali karena pilihannya sendiri hilang.
        $response->assertSee($anggaran->sub_kegiatan_lengkap, false);
        $response->assertDontSee('Belum ada Sub Kegiatan yang dilimpahkan ke akun ini.', false);
    }
}
