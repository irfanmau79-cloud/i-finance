<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdTransisiTest extends TestCase
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

    private function buatNpd(): Npd
    {
        $masterAnggaran = MasterAnggaran::create([
            'program' => 'Program Uji',
            'kegiatan' => 'Kegiatan Uji',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji',
            'kode_rekening' => '5.1.02.01.01.0099',
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-18',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah',
            'status' => 'Draft NPD - PPTK',
        ]);
    }

    /**
     * Alur penuh sesuai gas-lama CodeRevisi.gs (transisiNPD/verifikasiNPD):
     * Draft PPTK -> ajukan_bpp -> teruskan -> verifikasi -> setuju -> selesai.
     */
    public function test_alur_penuh_npd_barang_jasa_draft_sampai_selesai(): void
    {
        $pptk = $this->buatUser('pptk', 'e2e-pptk');
        $bpp = $this->buatUser('bpp', 'e2e-bpp');
        $verifikator = $this->buatUser('verifikator', 'e2e-verif');

        $npd = $this->buatNpd();

        // 1. PPTK: ajukan_bpp
        $this->actingAs($pptk)
            ->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);

        // 2. BPP: teruskan ke verifikator
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npd), ['aksi' => 'teruskan'])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('Verifikasi - Verifikator', $npd->status);

        // 3. Verifikator: verifikasi, isi nomor urut 7
        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 7])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);
        $this->assertSame(7, $npd->nomor_urut);
        $this->assertSame('07/NPD-Keu.1.IBC/7/2026', $npd->nomor_lengkap);
        $this->assertSame('[Terverifikasi]', $npd->catatan);

        // 4. BPP: setuju final
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npd), ['aksi' => 'setuju'])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('NPD Disetujui - BPP', $npd->status);

        // 5. BPP: tandai selesai
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npd), ['aksi' => 'selesai'])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('Selesai', $npd->status);
        // Nomor lengkap tetap terjaga sampai akhir alur.
        $this->assertSame('07/NPD-Keu.1.IBC/7/2026', $npd->nomor_lengkap);
    }

    public function test_semua_lima_jenis_npd_menggunakan_lifecycle_backend_yang_sama(): void
    {
        $admin = $this->buatUser('superadmin', 'lifecycle-semua-jenis');
        $master = $this->buatNpd()->masterAnggaran;
        Npd::query()->delete();

        foreach (array_keys(Npd::JENIS_LABEL) as $index => $jenis) {
            $npd = Npd::create([
                'jenis' => $jenis,
                'master_anggaran_id' => $master->id,
                'keu' => '1',
                'bulan' => 7,
                'tahun' => 2026,
                'tanggal_npd' => '2026-07-18',
                'jenis_panjar' => 'Tanpa Panjar',
                'nominal' => 1_000_000,
                'terbilang' => 'satu juta rupiah',
                'status' => 'Draft NPD - PPTK',
            ]);

            foreach ([
                ['ajukan_bpp', []],
                ['teruskan', []],
                ['verifikasi', ['nomor_urut' => 100 + $index]],
                ['setuju', []],
                ['selesai', []],
            ] as [$aksi, $tambahan]) {
                $this->actingAs($admin)->post(route('npd.transisi', $npd), ['aksi' => $aksi] + $tambahan)
                    ->assertSessionHasNoErrors();
            }

            $this->assertSame('Selesai', $npd->fresh()->status, "Lifecycle jenis {$jenis} tidak selesai.");
            $this->assertSame(5, $npd->historiStatus()->count(), "Histori lifecycle jenis {$jenis} tidak lengkap.");
        }
    }

    public function test_role_yang_salah_ditolak_di_setiap_tahap(): void
    {
        $pptk = $this->buatUser('pptk', 'salah-pptk');
        $bpp = $this->buatUser('bpp', 'salah-bpp');
        $verifikator = $this->buatUser('verifikator', 'salah-verif');

        $npd = $this->buatNpd();

        // Verifikator coba ajukan_bpp padahal aksi ini khusus PPTK.
        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])
            ->assertSessionHasErrors(['aksi']);
        $npd->refresh();
        $this->assertSame('Draft NPD - PPTK', $npd->status, 'Status tidak boleh berubah saat role ditolak.');

        // PPTK coba teruskan padahal aksi ini khusus BPP, dan status belum sampai tahap itu.
        $this->actingAs($pptk)
            ->post(route('npd.transisi', $npd), ['aksi' => 'teruskan'])
            ->assertSessionHasErrors(['aksi']);

        // Naikkan status ke Draft NPD - BPP lewat jalur sah, lalu coba aksi verifikasi oleh BPP (bukan Verifikator).
        $npd->update(['status' => 'Draft NPD - BPP']);
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 1])
            ->assertSessionHasErrors(['aksi']);
    }

    public function test_aksi_tidak_sesuai_status_saat_ini_ditolak(): void
    {
        $bpp = $this->buatUser('bpp', 'status-bpp');
        $npd = $this->buatNpd(); // status: Draft NPD - PPTK

        // BPP boleh 'setuju', tapi status NPD belum di 'Draft NPD - BPP'.
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npd), ['aksi' => 'setuju'])
            ->assertSessionHasErrors(['aksi']);
        $npd->refresh();
        $this->assertSame('Draft NPD - PPTK', $npd->status);
    }

    public function test_verifikasi_wajib_nomor_urut_1_sampai_999(): void
    {
        $verifikator = $this->buatUser('verifikator', 'nomor-verif');
        $npd = $this->buatNpd();
        $npd->update(['status' => 'Verifikasi - Verifikator']);

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 0])
            ->assertSessionHasErrors(['nomor_urut']);

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 1000])
            ->assertSessionHasErrors(['nomor_urut']);

        $npd->refresh();
        $this->assertSame('Verifikasi - Verifikator', $npd->status);
    }

    public function test_verifikasi_menolak_nomor_urut_bentrok_pada_keu_yang_sama(): void
    {
        $verifikator = $this->buatUser('verifikator', 'bentrok-verif');

        $npdLain = $this->buatNpd();
        $npdLain->update([
            'status' => 'Draft NPD - BPP',
            'nomor_urut' => 5,
            'nomor_lengkap' => '05/NPD-Keu.1.IBC/6/2026',
        ]);

        $npd = $this->buatNpd();
        $npd->update(['status' => 'Verifikasi - Verifikator']);

        $response = $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_urut' => 5]);

        $response->assertSessionHasErrors(['nomor_urut']);
        $npd->refresh();
        $this->assertSame('Verifikasi - Verifikator', $npd->status, 'Status tidak boleh berubah saat nomor bentrok.');
        $this->assertNull($npd->nomor_urut);
    }

    public function test_kembali_bpp_kembali_pptk_dan_batal_selesai_wajib_catatan(): void
    {
        $bpp = $this->buatUser('bpp', 'wajib-bpp');
        $verifikator = $this->buatUser('verifikator', 'wajib-verif');

        $npdVerif = $this->buatNpd();
        $npdVerif->update(['status' => 'Verifikasi - Verifikator']);
        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npdVerif), ['aksi' => 'kembali_bpp'])
            ->assertSessionHasErrors(['catatan']);

        $npdBpp = $this->buatNpd();
        $npdBpp->update(['status' => 'Draft NPD - BPP']);
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npdBpp), ['aksi' => 'kembali_pptk'])
            ->assertSessionHasErrors(['catatan']);

        $npdSelesai = $this->buatNpd();
        $npdSelesai->update(['status' => 'Selesai']);
        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npdSelesai), ['aksi' => 'batal_selesai'])
            ->assertSessionHasErrors(['catatan']);

        // Dengan catatan terisi, aksi berhasil dan prefix label tersimpan.
        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npdVerif), ['aksi' => 'kembali_bpp', 'catatan' => 'Lampiran kurang lengkap'])
            ->assertSessionHasNoErrors();
        $npdVerif->refresh();
        $this->assertSame('Draft NPD - BPP', $npdVerif->status);
        $this->assertSame('[Perlu Revisi] Lampiran kurang lengkap', $npdVerif->catatan);

        $this->actingAs($bpp)
            ->post(route('npd.transisi', $npdSelesai), ['aksi' => 'batal_selesai', 'catatan' => 'Salah input nominal'])
            ->assertSessionHasNoErrors();
        $npdSelesai->refresh();
        $this->assertSame('Draft NPD - BPP', $npdSelesai->status);
        $this->assertSame('[Pembatalan Selesai] Salah input nominal', $npdSelesai->catatan);
    }

    public function test_superadmin_boleh_melakukan_aksi_apa_pun(): void
    {
        $superadmin = $this->buatUser('superadmin', 'boleh-semua');
        $npd = $this->buatNpd(); // Draft NPD - PPTK

        // Superadmin langsung 'ajukan_bpp' (aksi milik PPTK) — tetap diizinkan.
        $this->actingAs($superadmin)
            ->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);

        // Superadmin juga boleh 'teruskan' (aksi milik BPP).
        $this->actingAs($superadmin)
            ->post(route('npd.transisi', $npd), ['aksi' => 'teruskan'])
            ->assertSessionHasNoErrors();
        $npd->refresh();
        $this->assertSame('Verifikasi - Verifikator', $npd->status);
    }

    public function test_status_npd_dimirror_ke_surat_perintah_tertaut(): void
    {
        $pptk = $this->buatUser('pptk', 'mirror-pptk');

        $sp = SuratPerintah::create([
            'nomor_sp' => 'SP-001',
            'tanggal_sp' => '2026-07-01',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Budi',
            'tujuan_transfer' => 'Dinas Luar',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '18-07-2026',
            'keterangan' => 'Uji mirror status',
            'file_url' => 'https://example.com/sp.pdf',
            'status_sp' => 'Baru',
            'status' => 'Draft NPD - PPTK',
        ]);

        $npd = $this->buatNpd();
        $npd->update(['surat_perintah_id' => $sp->id]);

        $this->actingAs($pptk)
            ->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp'])
            ->assertSessionHasNoErrors();

        $sp->refresh();
        $this->assertSame('Draft NPD - BPP', $sp->status);
    }

    public function test_role_di_luar_alur_npd_ditolak_akses_halaman_detail(): void
    {
        $inspektur = $this->buatUser('inspektur', 'luar-alur');
        $npd = $this->buatNpd();

        $this->actingAs($inspektur)->get(route('npd.show', $npd))->assertForbidden();
    }

    public function test_tombol_aksi_hanya_tampil_untuk_role_dan_status_yang_sesuai(): void
    {
        $pptk = $this->buatUser('pptk', 'tombol-pptk');
        $bpp = $this->buatUser('bpp', 'tombol-bpp');
        $npd = $this->buatNpd(); // Draft NPD - PPTK

        // PPTK di status Draft NPD - PPTK: tombol Ajukan ke BPP muncul.
        $this->actingAs($pptk)->get(route('npd.show', $npd))
            ->assertSee('Ajukan ke BPP')
            ->assertDontSee('Teruskan ke Verifikator');

        // BPP di status yang sama (belum tahapnya): tidak ada tombol aksi apa pun.
        $this->actingAs($bpp)->get(route('npd.show', $npd))
            ->assertDontSee('Teruskan ke Verifikator')
            ->assertDontSee('Setujui Final');
    }
}
