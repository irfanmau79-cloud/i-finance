<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub menu "Daftar Rekanan" pada modul NPD.
 *
 * Yang dijaga di sini: daftarnya memang membaca tabel yang SAMA dengan hasil
 * import Manajemen Data > Data Rekanan (bukan salinan terpisah), rekanan yang
 * diketik manual langsung bisa dipilih di Pembuatan NPD, dan hanya role yang
 * membuat NPD yang boleh membukanya.
 */
class DaftarRekananTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function isian(array $ganti = []): array
    {
        return array_merge([
            'nama' => 'CV Sumber Rejeki',
            'rekening' => '0012345678',
            'nomor_handphone' => '081234567890',
            'npwp' => '01.234.567.8-901.000',
            'jenis_usaha' => 'Perdagangan alat tulis',
            'pkp' => '1',
            'aktif' => '1',
        ], $ganti);
    }

    private function anggaranUji(): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Rekanan',
            'kegiatan' => 'Kegiatan Uji Rekanan',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Rekanan',
            'kode_rekening' => '5.1.02.01.01.0024',
            'tagging_id' => null,
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    public function test_daftar_membaca_tabel_yang_sama_dengan_hasil_import(): void
    {
        // Baris ini mewakili rekanan yang masuk lewat import Manajemen Data:
        // halaman Daftar Rekanan tidak boleh punya salinan datanya sendiri.
        Vendor::create(['nama' => 'PT Hasil Import', 'rekening' => '999', 'aktif' => true]);
        Vendor::create(['nama' => 'CV Sudah Tutup', 'aktif' => false]);

        $this->actingAs($this->buatUser('pptk', 'rek-pptk'))
            ->get(route('rekanan.index'))
            ->assertOk()
            ->assertSee('PT Hasil Import')
            ->assertSee('CV Sudah Tutup')
            ->assertSee('Non Aktif');
    }

    public function test_rekanan_baru_tersimpan_dan_tercatat_di_audit_log(): void
    {
        $superadmin = $this->buatUser('superadmin', 'rek-admin');

        $this->actingAs($superadmin)
            ->post(route('rekanan.store'), $this->isian())
            ->assertRedirect(route('rekanan.index'));

        $rekanan = Vendor::sole();

        $this->assertSame('CV Sumber Rejeki', $rekanan->nama);
        $this->assertSame('0012345678', $rekanan->rekening);
        $this->assertSame('01.234.567.8-901.000', $rekanan->npwp);
        $this->assertTrue($rekanan->pkp);
        $this->assertTrue($rekanan->aktif);

        $this->assertDatabaseHas('audit_log', [
            'aktivitas' => 'Tambah Rekanan',
            'username' => $superadmin->username,
        ]);
    }

    public function test_rekanan_yang_ditambah_manual_langsung_bisa_dipilih_di_pembuatan_npd(): void
    {
        $pptk = $this->buatUser('pptk', 'rek-pakai');
        $this->limpahkanSubKegiatan($pptk, $this->anggaranUji());

        $this->actingAs($pptk)->post(route('rekanan.store'), $this->isian(['nama' => 'CV Rekanan Baru']));

        // Inilah tujuan fitur ini: tanpa import, nama itu sudah ada di pilihan
        // penerima NPD Barang/Jasa, lengkap dengan rekeningnya.
        $isi = $this->actingAs($pptk)->get(route('npd.bj.create'))->assertOk()->getContent();

        $this->assertStringContainsString('CV Rekanan Baru', $isi);
        $this->assertStringContainsString('"sub":"Rekanan"', $isi);
    }

    public function test_rekanan_non_aktif_tidak_ditawarkan_di_pembuatan_npd(): void
    {
        $pptk = $this->buatUser('pptk', 'rek-nonaktif');
        $this->limpahkanSubKegiatan($pptk, $this->anggaranUji());

        $this->actingAs($pptk)->post(route('rekanan.store'), $this->isian([
            'nama' => 'CV Jangan Muncul',
            'aktif' => '0',
        ]));

        $this->assertFalse(Vendor::sole()->aktif);

        $isi = $this->actingAs($pptk)->get(route('npd.bj.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('CV Jangan Muncul', $isi);
    }

    public function test_nama_rekanan_tidak_boleh_ganda(): void
    {
        // Nama adalah identitas baris saat import dan saat menautkan penerima
        // SPM LS, jadi duplikat akan membuat pencocokan itu ambigu.
        Vendor::create(['nama' => 'CV Sumber Rejeki', 'aktif' => true]);

        $this->actingAs($this->buatUser('superadmin', 'rek-ganda'))
            ->post(route('rekanan.store'), $this->isian())
            ->assertSessionHasErrors(['nama']);

        $this->assertSame(1, Vendor::count());
    }

    public function test_rekanan_dapat_disunting(): void
    {
        $rekanan = Vendor::create(['nama' => 'CV Nama Lama', 'rekening' => '111', 'aktif' => true]);

        $this->actingAs($this->buatUser('superadmin', 'rek-sunting'))
            ->put(route('rekanan.update', $rekanan), $this->isian([
                'nama' => 'CV Nama Baru',
                'rekening' => '222',
                'pkp' => '0',
            ]))
            ->assertRedirect(route('rekanan.index'));

        $rekanan->refresh();

        $this->assertSame('CV Nama Baru', $rekanan->nama);
        $this->assertSame('222', $rekanan->rekening);
        $this->assertFalse($rekanan->pkp);
    }

    public function test_hanya_lima_role_keuangan_yang_boleh_membuka_daftar_rekanan(): void
    {
        foreach (['superadmin', 'bendahara_pengeluaran', 'pptk', 'bpp', 'verifikator'] as $i => $role) {
            $this->actingAs($this->buatUser($role, 'rek-boleh-'.$i))
                ->get(route('rekanan.index'))
                ->assertOk();
        }

        foreach (['inspektur', 'perencanaan', 'kepegawaian', 'pengelola_spj'] as $i => $role) {
            $this->actingAs($this->buatUser($role, 'rek-tolak-'.$i))
                ->get(route('rekanan.index'))
                ->assertForbidden();
        }
    }

    public function test_role_baca_saja_tidak_boleh_menambah_rekanan(): void
    {
        // 'pengawas' baca-saja DAN di luar pemegang Daftar Rekanan, jadi tertahan dua
        // lapis - rutenya pun tidak terbuka untuknya.
        $this->actingAs($this->buatUser('pengawas', 'rek-pengawas'))
            ->get(route('rekanan.create'))
            ->assertForbidden();

        $this->assertSame(0, Vendor::count());
    }
}
