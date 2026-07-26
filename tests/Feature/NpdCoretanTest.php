<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpdCoretanTest extends TestCase
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

    private function buatNpd(string $status = 'Verifikasi - Verifikator'): Npd
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
            'status' => $status,
        ]);
    }

    private function contohCoretan(): string
    {
        return json_encode([
            'strokes' => [
                ['color' => '#e11d48', 'width' => 3, 'points' => [[0.1, 0.1], [0.2, 0.15], [0.3, 0.2]]],
            ],
        ]);
    }

    public function test_verifikator_kembali_bpp_dengan_coretan_tersimpan_di_histori(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-verif');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Lampiran kurang, ada coretan',
                'coretan_json' => $this->contohCoretan(),
            ])
            ->assertSessionHasNoErrors();

        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);

        $histori = $npd->historiStatus()->latest('nomor_urut')->first();
        $this->assertNotNull($histori->coretan_json);
        $decoded = json_decode($histori->coretan_json, true);
        $this->assertCount(1, $decoded['strokes']);
    }

    public function test_kembali_bpp_tanpa_coretan_tetap_berhasil(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-verif-kosong');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Tanpa coretan, cukup catatan',
            ])
            ->assertSessionHasNoErrors();

        $npd->refresh();
        $histori = $npd->historiStatus()->latest('nomor_urut')->first();
        $this->assertNull($histori->coretan_json);
    }

    public function test_coretan_json_tidak_valid_ditolak(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-verif-invalid');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Catatan wajib',
                'coretan_json' => '{"bukan_strokes": true}',
            ])
            ->assertSessionHasErrors(['coretan_json']);

        $npd->refresh();
        $this->assertSame('Verifikasi - Verifikator', $npd->status, 'Status tidak boleh berubah saat coretan tidak valid.');
    }

    public function test_verifikator_melihat_tombol_coret_pada_halaman_detail(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-lihat-tombol');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee('Beri Coretan pada Dokumen &amp; Kembalikan ke BPP', false);
    }

    public function test_verifikator_bisa_membuka_halaman_coret_saat_npd_menunggu_verifikasi(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-halaman-verif');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)->get(route('npd.coret', $npd))->assertOk();
    }

    public function test_role_lain_tidak_bisa_membuka_halaman_coret(): void
    {
        $bpp = $this->buatUser('bpp', 'coret-halaman-bpp');
        $npd = $this->buatNpd();

        $this->actingAs($bpp)->get(route('npd.coret', $npd))->assertForbidden();
    }

    public function test_verifikator_tidak_bisa_membuka_halaman_coret_saat_status_belum_sesuai(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-halaman-status');
        $npd = $this->buatNpd('Draft NPD - BPP');

        $this->actingAs($verifikator)->get(route('npd.coret', $npd))->assertForbidden();
    }

    public function test_submit_kembali_bpp_dari_halaman_coret_tidak_403(): void
    {
        // Regression: transisi() sukses lalu return back(), yang memantulkan ke
        // Referer (halaman npd.coret) - tapi begitu status berubah, npd.coret
        // langsung 403 karena kembali_bpp sudah tidak lagi tersedia untuk NPD
        // ini. Redirect setelah sukses harus eksplisit ke npd.show, bukan back().
        $verifikator = $this->buatUser('verifikator', 'coret-redirect-verif');
        $npd = $this->buatNpd();

        $response = $this->actingAs($verifikator)
            ->from(route('npd.coret', $npd))
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Ada revisi',
                'coretan_json' => $this->contohCoretan(),
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('npd.show', $npd));

        // Pastikan tujuan redirect itu benar-benar bisa dibuka (bukan 403).
        $this->actingAs($verifikator)->get(route('npd.show', $npd))->assertOk();
    }

    public function test_bpp_melihat_badge_coretan_dan_catatan_di_histori_setelah_verifikator_mengembalikan(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-alur-verif');
        $bpp = $this->buatUser('bpp', 'coret-alur-bpp');
        $npd = $this->buatNpd();

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Ada bagian yang perlu direvisi',
                'coretan_json' => $this->contohCoretan(),
            ])
            ->assertSessionHasNoErrors();

        $npd->refresh();

        $response = $this->actingAs($bpp)->get(route('npd.show', $npd))->assertOk();
        $response->assertSee('Ada bagian yang perlu direvisi');
        $response->assertSee('Ada Coretan');
        $response->assertSee('memuat coretan dari Verifikator', false);
        // BPP bukan Verifikator dan status sudah bukan 'Verifikasi - Verifikator', jadi tombol coret tidak muncul.
        $response->assertDontSee('Beri Coretan pada PDF', false);
    }

    public function test_cetak_npd_menyertakan_coretan_yang_tersimpan(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-cetak-verif');
        $npd = $this->buatNpd();

        $pdfTanpaCoretan = $this->actingAs($verifikator)->get(route('npd.cetak-npd', $npd))->getContent();

        $this->actingAs($verifikator)
            ->post(route('npd.transisi', $npd), [
                'aksi' => 'kembali_bpp',
                'catatan' => 'Ada revisi dengan coretan',
                'coretan_json' => $this->contohCoretan(),
            ])
            ->assertSessionHasNoErrors();

        $npd->refresh();

        $response = $this->actingAs($verifikator)->get(route('npd.cetak-npd', $npd));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        // Regression: overlay coretan sempat "hilang" karena ditempel setelah
        // </html> penuh (diabaikan mPDF) - byte PDF harus berbeda begitu ada
        // coretan tersimpan, bukan identik dengan versi tanpa coretan.
        $this->assertNotSame($pdfTanpaCoretan, $response->getContent());
    }

    public function test_coretan_json_terbaru_mengambil_histori_paling_baru_bukan_paling_lama(): void
    {
        // Regression: historiStatus() sudah punya orderBy('nomor_urut') bawaan,
        // jadi orderByDesc() naif akan MENAMBAH clause (bukan menggantinya) dan
        // diam-diam mengembalikan baris coretan paling LAMA, bukan yang terbaru.
        $verifikator = $this->buatUser('verifikator', 'coret-terbaru-verif');
        $bpp = $this->buatUser('bpp', 'coret-terbaru-bpp');
        $npd = $this->buatNpd();

        $coretanLama = json_encode(['strokes' => [['page' => 1, 'color' => '#000000', 'width' => 0.01, 'points' => [[0, 0], [0.1, 0.1]]]]]);
        $coretanBaru = json_encode(['strokes' => [['page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0.5, 0.5], [0.9, 0.9]]]]]);

        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), [
            'aksi' => 'kembali_bpp', 'catatan' => 'Revisi pertama', 'coretan_json' => $coretanLama,
        ])->assertSessionHasNoErrors();

        // Status setelah kembali_bpp otomatis 'Draft NPD - BPP', pas untuk aksi 'teruskan'.
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan'])->assertSessionHasNoErrors();

        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), [
            'aksi' => 'kembali_bpp', 'catatan' => 'Revisi kedua', 'coretan_json' => $coretanBaru,
        ])->assertSessionHasNoErrors();

        $npd->refresh();
        $this->assertSame($coretanBaru, $npd->coretanJsonTerbaru());
    }

    public function test_coretan_per_dokumen_terisolasi_di_cetak_npd_dan_cetak_lampiran(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-isolasi-verif');
        $npd = $this->buatNpd();

        $npdKosong = $this->actingAs($verifikator)->get(route('npd.cetak-npd', $npd))->getContent();
        $lampiranKosong = $this->actingAs($verifikator)->get(route('npd.cetak-lampiran', $npd))->getContent();

        // Coretan hanya untuk dokumen 'lampiran', bukan 'npd'.
        $coretanLampiranSaja = json_encode([
            'strokes' => [['dokumen' => 'lampiran', 'page' => 1, 'color' => '#e11d48', 'width' => 0.01, 'points' => [[0.1, 0.1], [0.5, 0.5]]]],
        ]);

        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), [
            'aksi' => 'kembali_bpp', 'catatan' => 'Coretan lampiran saja', 'coretan_json' => $coretanLampiranSaja,
        ])->assertSessionHasNoErrors();

        $npdSetelah = $this->actingAs($verifikator)->get(route('npd.cetak-npd', $npd))->getContent();
        $lampiranSetelah = $this->actingAs($verifikator)->get(route('npd.cetak-lampiran', $npd))->getContent();

        // Cetak NPD tidak berubah - coretan itu bukan miliknya.
        $this->assertSame($npdKosong, $npdSetelah, 'Cetak NPD tidak boleh ikut tercoret oleh strokes milik dokumen lampiran.');
        // Cetak Lampiran berubah - coretan itu memang miliknya.
        $this->assertNotSame($lampiranKosong, $lampiranSetelah, 'Cetak Lampiran harus memuat coretan yang ditandai untuknya.');
    }

    public function test_halaman_coret_menampilkan_daftar_dokumen_sesuai_jenis_npd(): void
    {
        $verifikator = $this->buatUser('verifikator', 'coret-daftar-dok-bj');
        $npdBarangJasa = $this->buatNpd();

        $response = $this->actingAs($verifikator)->get(route('npd.coret', $npdBarangJasa))->assertOk();
        $response->assertSee('NPD', false);
        $response->assertSee('Lampiran', false);
        $response->assertDontSee('Daftar Pembayaran', false);
        $response->assertDontSee('SPD Rampung', false);
    }
}
