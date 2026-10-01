<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aksi massal pada antrean NPD (adopsi GAS #77b & #78-#81).
 *
 * Yang paling dijaga di sini adalah PENOMORAN. Verifikasi massal menulis
 * nomor NPD untuk banyak dokumen dalam satu permintaan, dan nomor NPD unik
 * di tingkat basis data - jadi aksi massal tidak boleh jadi pintu belakang
 * yang lebih longgar daripada verifikasi satu per satu.
 */
class NpdAksiMassalTest extends TestCase
{
    use RefreshDatabase;

    private MasterAnggaran $master;

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = MasterAnggaran::create([
            'program' => 'Program Massal',
            'kegiatan' => 'Kegiatan Massal',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Massal',
            'kode_rekening' => '5.1.02.01.01.0024',
            'tagging_id' => null,
            'pagu' => 500_000_000,
            'aktif' => true,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'massal-'.$role.'-'.uniqid(),
            'nama' => 'Petugas '.$role,
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function npd(string $status): Npd
    {
        $this->urut++;

        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $this->master->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'tanggal_npd' => config('anggaran.tahun_aktif').'-07-10',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => $status,
        ]);
    }

    public function test_teruskan_banyak_npd_sekaligus(): void
    {
        $satu = $this->npd('Draft NPD - BPP');
        $dua = $this->npd('Draft NPD - BPP');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'teruskan',
                'npd' => [$satu->id, $dua->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Verifikasi - Verifikator', $satu->fresh()->status);
        $this->assertSame('Verifikasi - Verifikator', $dua->fresh()->status);
    }

    public function test_baris_yang_statusnya_tidak_cocok_dilewati_tanpa_membatalkan_sisanya(): void
    {
        // Inti pilihan rancangannya: satu transaksi per NPD, bukan satu
        // transaksi besar. Yang sah tetap jalan.
        $sah = $this->npd('Draft NPD - BPP');
        $salahStatus = $this->npd('Selesai');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'teruskan',
                'npd' => [$salahStatus->id, $sah->id],
            ])
            ->assertSessionHas('success')
            ->assertSessionHasErrors(['massal']);

        $this->assertSame('Verifikasi - Verifikator', $sah->fresh()->status);
        $this->assertSame('Selesai', $salahStatus->fresh()->status);
    }

    public function test_verifikasi_massal_menulis_nomor_per_npd(): void
    {
        $satu = $this->npd('Verifikasi - Verifikator');
        $dua = $this->npd('Verifikasi - Verifikator');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'verifikasi',
                'npd' => [$satu->id, $dua->id],
                'nomor' => [$satu->id => '11/NPD-Keu.1.IBC/VII/2026', $dua->id => '12/NPD-Keu.1.IBC/VII/2026'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('11/NPD-Keu.1.IBC/VII/2026', $satu->fresh()->nomor_lengkap);
        $this->assertSame('12/NPD-Keu.1.IBC/VII/2026', $dua->fresh()->nomor_lengkap);
        $this->assertSame('Draft NPD - BPP', $satu->fresh()->status);
    }

    public function test_nomor_ganda_dalam_satu_batch_dilewati_bukan_menimpa(): void
    {
        $satu = $this->npd('Verifikasi - Verifikator');
        $dua = $this->npd('Verifikasi - Verifikator');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'verifikasi',
                'npd' => [$satu->id, $dua->id],
                'nomor' => [$satu->id => '20/NPD/2026', $dua->id => '20/NPD/2026'],
            ])
            ->assertSessionHasErrors(['massal']);

        // Yang pertama lolos, yang kedua dilewati - BUKAN menimpa nomor yang
        // sama ke dua dokumen.
        $this->assertSame('20/NPD/2026', $satu->fresh()->nomor_lengkap);
        $this->assertNull($dua->fresh()->nomor_lengkap);
        $this->assertSame('Verifikasi - Verifikator', $dua->fresh()->status);
    }

    public function test_nomor_yang_sudah_dipakai_npd_lain_dilewati(): void
    {
        $lama = $this->npd('Selesai');
        $lama->forceFill(['nomor_lengkap' => '07/NPD/2026'])->save();

        $baru = $this->npd('Verifikasi - Verifikator');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'verifikasi',
                'npd' => [$baru->id],
                'nomor' => [$baru->id => '07/NPD/2026'],
            ])
            ->assertSessionHasErrors(['massal']);

        $this->assertNull($baru->fresh()->nomor_lengkap);
        $this->assertSame('Verifikasi - Verifikator', $baru->fresh()->status);
    }

    public function test_nomor_kosong_dilewati(): void
    {
        $npd = $this->npd('Verifikasi - Verifikator');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'verifikasi',
                'npd' => [$npd->id],
                'nomor' => [$npd->id => '   '],
            ])
            ->assertSessionHasErrors(['massal']);

        $this->assertNull($npd->fresh()->nomor_lengkap);
    }

    public function test_aksi_yang_wajib_catatan_ditolak_massal(): void
    {
        // Alasan pengembalian harus ditulis per NPD; satu alasan yang
        // disalin ke puluhan dokumen tidak menjelaskan apa pun.
        $npd = $this->npd('Verifikasi - Verifikator');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), [
                'aksi' => 'kembali_bpp',
                'npd' => [$npd->id],
            ])
            ->assertSessionHasErrors(['aksi']);

        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);
    }

    public function test_aksi_warisan_ditolak_massal(): void
    {
        $npd = $this->npd('Draft NPD - PPTK');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), ['aksi' => 'ajukan_bpp', 'npd' => [$npd->id]])
            ->assertSessionHasErrors(['aksi']);

        $this->assertSame('Draft NPD - PPTK', $npd->fresh()->status);
    }

    public function test_hanya_superadmin_yang_boleh_menjalankan_aksi_massal(): void
    {
        $npd = $this->npd('Draft NPD - BPP');

        foreach (['bpp', 'pptk', 'verifikator', 'bendahara_pengeluaran'] as $role) {
            $this->actingAs($this->user($role))
                ->post(route('npd.transisi-massal'), ['aksi' => 'teruskan', 'npd' => [$npd->id]])
                ->assertForbidden();
        }

        $this->assertSame('Draft NPD - BPP', $npd->fresh()->status);
    }

    public function test_npd_historis_dilewati(): void
    {
        $npd = $this->npd('Draft NPD - BPP');
        $npd->forceFill(['sumber_data' => 'import_historis'])->save();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), ['aksi' => 'teruskan', 'npd' => [$npd->id]])
            ->assertSessionHasErrors(['massal']);

        $this->assertSame('Draft NPD - BPP', $npd->fresh()->status);
    }

    public function test_histori_tercatat_untuk_tiap_npd_yang_berhasil(): void
    {
        $satu = $this->npd('Draft NPD - BPP');
        $dua = $this->npd('Draft NPD - BPP');

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('npd.transisi-massal'), ['aksi' => 'teruskan', 'npd' => [$satu->id, $dua->id]]);

        foreach ([$satu, $dua] as $npd) {
            $this->assertContains('teruskan', $npd->historiStatus()->pluck('aksi')->all());
        }
    }
}
