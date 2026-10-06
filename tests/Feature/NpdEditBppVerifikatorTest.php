<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdRevisi;
use App\Models\SuratPerintah;
use App\Models\User;
use App\Services\NpdRevisiService;
use App\Support\CoretanOtomatis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit NPD oleh BPP dan Verifikator: siapa boleh di meja mana, apa yang
 * tercatat sebagai perubahan, dan dua versi cetaknya (draft awal PPTK dengan
 * coretan otomatis, serta dokumen terverifikasi yang bersih).
 */
class NpdEditBppVerifikatorTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'username' => 'edit-'.$role.'-'.User::count(),
            'nama' => 'Akun '.ucfirst($role).' '.User::count(),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function anggaran(string $sub = '6.01.01.2.01 Sub Edit', string $rekening = '5.1.02.01.01.9999'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Edit',
            'kegiatan' => 'Kegiatan Edit',
            'sub_kegiatan' => $sub,
            'kode_rekening' => $rekening,
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);
    }

    /** @param  array<int, array{0: string, 1: float}>  $penerima  [nama, bruto] */
    private function payloadBj(MasterAnggaran $anggaran, array $penerima): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima' => array_map(fn (array $p) => [
                'nama' => $p[0],
                'rekening' => '123-'.$p[0],
                'bruto' => $p[1],
                'ppn' => 0,
                'biaya_ku_rtgs' => 0,
                'keterangan' => 'Belanja '.$p[0],
            ], $penerima),
        ];
    }

    private function payloadPd(MasterAnggaran $anggaran, SuratPerintah $sp, float $tarif, array $namaTim = ['Anggota Satu']): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'surat_perintah_id' => $sp->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_sp' => 'Perjalanan uji edit',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Kota Yogyakarta',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-21',
            'penerima_index' => 0,
            'tim' => array_map(fn (string $nama) => [
                'nama' => $nama,
                'jabatan' => 'Auditor',
                'paket' => [[
                    'cluster' => 'LP',
                    'wilayah' => 'Kota Yogyakarta',
                    'lama_hari' => 2,
                    'tarif_uh' => $tarif,
                    'malam' => 0,
                    'tarif_akom' => 0,
                ]],
            ], $namaTim),
        ];
    }

    private function sp(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '055/SP/EDIT/2026',
            'tanggal_sp' => '2026-07-18',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Bandung',
            'nama_pengirim' => 'Penguji',
            'tujuan_transfer' => 'Penguji',
            'irban_dibayar' => false,
            'rincian_tgl_bayar' => '20 Juli 2026',
            'keterangan' => 'Perjalanan uji edit',
            'file_url' => 'sp/edit.pdf',
            'status_sp' => 'Baru',
            'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
        ]);
    }

    /**
     * NPD Barang/Jasa buatan PPTK yang sudah diterima BPP (Draft NPD - BPP).
     *
     * @return array{0: Npd, 1: MasterAnggaran, 2: User, 3: User}  npd, anggaran, pptk, bpp
     */
    private function npdDiMejaBpp(array $penerima = [['Toko Asal', 1_000_000]]): array
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran, $penerima));
        $npd = Npd::sole();
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'terima_npd']);

        return [$npd->fresh(), $anggaran, $pptk, $bpp];
    }

    public function test_suntingan_pptk_selama_draft_tidak_dicatat_sebagai_perubahan(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran, [['Toko Asal', 1_000_000]]));
        $npd = Npd::sole();

        $this->actingAs($pptk)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, [['Toko Asal', 1_200_000]]))
            ->assertRedirect(route('npd.show', $npd));

        $this->assertSame(1_200_000.0, (float) $npd->fresh()->nominal);
        $this->assertSame(0, NpdRevisi::count());
        $this->assertNull(app(NpdRevisiService::class)->draftAwal($npd));
    }

    public function test_bpp_menyunting_npd_di_mejanya_dan_tiap_bagian_yang_berubah_tercatat(): void
    {
        [$npd, $anggaran, , $bpp] = $this->npdDiMejaBpp();

        $this->actingAs($bpp)->get(route('npd.persetujuan'))->assertOk()->assertSee(route('npd.bj.edit', $npd), false);
        $this->actingAs($bpp)->get(route('npd.bj.edit', $npd))->assertOk();

        $payload = $this->payloadBj($anggaran, [['Toko Asal', 1_750_000]]);
        $payload['sisa_anggaran_manual'] = 40_000_000;
        $this->actingAs($bpp)->put(route('npd.bj.update', $npd), $payload)->assertRedirect(route('npd.show', $npd));

        $npd->refresh();
        $this->assertSame(1_750_000.0, (float) $npd->nominal);
        $this->assertSame('Draft NPD - BPP', $npd->status);

        $revisi = NpdRevisi::sole();
        $this->assertSame($bpp->id, $revisi->user_id);
        $this->assertSame('bpp', $revisi->peran);

        $perubahan = collect($revisi->perubahan)->keyBy('bagian');
        $this->assertSame(['Rp 1.000.000,00', 'Rp 1.750.000,00'], [$perubahan['Nominal NPD']['lama'], $perubahan['Nominal NPD']['baru']]);
        $this->assertSame(['Angka sistem', 'Rp 40.000.000,00'], [$perubahan['Sisa Anggaran di PDF']['lama'], $perubahan['Sisa Anggaran di PDF']['baru']]);
        $this->assertSame('Rp 1.750.000,00', $perubahan['Penerima Toko Asal - Bruto']['baru']);
        $this->assertCount(3, $revisi->perubahan);

        $halaman = $this->actingAs($bpp)->get(route('npd.show', $npd))->assertOk();
        $halaman->assertSee('<h3>Histori Perubahan Data</h3>', false);
        $halaman->assertSee('Penerima Toko Asal - Bruto');
        $halaman->assertSee('Cetak Draft NPD');
        // Belum bernomor, jadi belum boleh disebut terverifikasi.
        $halaman->assertSee('Cetak NPD Hasil Edit');
        $halaman->assertDontSee('Cetak NPD Terverifikasi');
        $halaman->assertSee(route('npd.cetak-npd', ['npd' => $npd, 'versi' => 'draft']), false);
    }

    public function test_formulir_yang_disimpan_tanpa_mengubah_apa_pun_tidak_membuat_catatan_perubahan(): void
    {
        [$npd, $anggaran, , $bpp] = $this->npdDiMejaBpp();

        $this->actingAs($bpp)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, [['Toko Asal', 1_000_000]]))
            ->assertRedirect(route('npd.show', $npd));

        $this->assertSame(0, NpdRevisi::count());
    }

    public function test_hak_edit_mengikuti_meja_npd_dan_ikatan_verifikator(): void
    {
        [$npd, $anggaran, $pptk, $bpp] = $this->npdDiMejaBpp();
        $verifikator = $this->user('verifikator');
        $verifikatorLain = $this->user('verifikator');
        $pemantau = $this->user('bendahara_pengeluaran');
        $superadmin = $this->user('superadmin');
        $payload = $this->payloadBj($anggaran, [['Toko Asal', 900_000]]);

        // Meja BPP: Verifikator, PPTK, dan pemantau tidak boleh.
        $this->tetapkanVerifikator($verifikator);
        foreach ([$verifikator, $pptk, $pemantau] as $bukanBpp) {
            $this->actingAs($bukanBpp)->get(route('npd.bj.edit', $npd))->assertForbidden();
            $this->actingAs($bukanBpp)->put(route('npd.bj.update', $npd), $payload)->assertForbidden();
        }

        // Meja Verifikator: hanya Verifikator Sub Kegiatan itu (dan superadmin).
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);
        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);

        foreach ([$bpp, $pptk, $verifikatorLain, $pemantau] as $bukanVerifikatornya) {
            $this->actingAs($bukanVerifikatornya)->get(route('npd.bj.edit', $npd))->assertForbidden();
            $this->actingAs($bukanVerifikatornya)->put(route('npd.bj.update', $npd), $payload)->assertForbidden();
        }

        $this->actingAs($verifikator)->get(route('npd.bj.edit', $npd))->assertOk();
        $this->actingAs($superadmin)->get(route('npd.bj.edit', $npd))->assertOk();
        // Sesudah menyimpan, Verifikator kembali ke halaman Verifikasi NPD.
        $this->actingAs($verifikator)->put(route('npd.bj.update', $npd), $payload)->assertRedirect(route('npd.coret', $npd));
        $this->assertSame(900_000.0, (float) $npd->fresh()->nominal);
        $this->assertSame('verifikator', NpdRevisi::sole()->peran);

        // Sesudah diverifikasi NPD kembali ke meja BPP; Verifikator selesai.
        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '11/NPD-Keu.1.IBC/7/2026']);
        $this->actingAs($verifikator)->get(route('npd.bj.edit', $npd))->assertForbidden();

        // Disetujui/Selesai: tidak ada lagi yang boleh menyunting.
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'setuju']);
        foreach ([$bpp, $verifikator, $pptk, $superadmin] as $siapaPun) {
            $this->actingAs($siapaPun)->get(route('npd.bj.edit', $npd))->assertForbidden();
        }
    }

    /**
     * Meja Verifikator hanya punya satu tombol. Verifikasi, Kembalikan ke
     * BPP, dan Edit NPD ada di halaman yang dibukanya.
     */
    public function test_antrean_verifikator_hanya_menampilkan_satu_tombol_verifikasi_npd(): void
    {
        [$npd, , $pptk, $bpp] = $this->npdDiMejaBpp();
        $verifikator = $this->user('verifikator');
        $verifikatorLain = $this->user('verifikator');
        $this->tetapkanVerifikator($verifikator);

        // "Terima NPD" milik BPP (dan superadmin), bukan PPTK.
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'kembali_pptk', 'catatan' => 'Uji tombol']);
        $this->actingAs($pptk)->get(route('npd.index'))->assertOk()->assertDontSee('data-wf-confirm="terima_npd"', false);
        $this->actingAs($bpp)->get(route('npd.persetujuan'))->assertOk()->assertSee('data-wf-confirm="terima_npd"', false);
        $this->actingAs($pptk)->post(route('npd.transisi', $npd), ['aksi' => 'terima_npd']);
        $this->assertSame('Draft NPD - PPTK', $npd->fresh()->status);

        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'terima_npd']);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);

        $antrean = $this->actingAs($verifikator)->get(route('npd.verifikasi'))->assertOk();
        $antrean->assertSee('title="Verifikasi NPD" href="'.route('npd.coret', $npd).'"', false);
        $antrean->assertDontSee(route('npd.bj.edit', $npd), false);
        $antrean->assertDontSee('data-wf-open="verifikasi"', false);
        $antrean->assertDontSee('Kembalikan ke BPP (bisa beri coretan', false);

        // Verifikator Sub Kegiatan lain tidak mendapat pintunya.
        $this->actingAs($verifikatorLain)->get(route('npd.coret', $npd))->assertForbidden();

        $halaman = $this->actingAs($verifikator)->get(route('npd.coret', $npd))->assertOk();
        $halaman->assertSee('data-coret-aksi="verifikasi">Verifikasi</button>', false);
        $halaman->assertSee('data-coret-aksi="kembali_bpp">Kembalikan ke BPP</button>', false);
        $halaman->assertSee('href="'.route('npd.bj.edit', $npd).'" id="coret-edit"', false);
    }

    public function test_verifikasi_dari_halaman_verifikasi_npd_memberi_nomor_dan_kembali_ke_detail(): void
    {
        [$npd, , , $bpp] = $this->npdDiMejaBpp();
        $verifikator = $this->user('verifikator');
        $this->tetapkanVerifikator($verifikator);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);

        // Tanpa nomor: ditolak, dan tetap di halaman yang sama.
        $this->actingAs($verifikator)->from(route('npd.coret', $npd))
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'ke_detail' => 1, 'nomor_lengkap' => ''])
            ->assertRedirect(route('npd.coret', $npd))
            ->assertSessionHasErrors(['nomor_lengkap']);

        $this->actingAs($verifikator)->from(route('npd.coret', $npd))
            ->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'ke_detail' => 1, 'nomor_lengkap' => '21/NPD-Keu.1.IBC/7/2026'])
            ->assertRedirect(route('npd.show', $npd));

        $npd->refresh();
        $this->assertSame('Draft NPD - BPP', $npd->status);
        $this->assertSame('21/NPD-Keu.1.IBC/7/2026', $npd->nomor_lengkap);
    }

    public function test_sub_kegiatan_tanpa_verifikator_tidak_bisa_disunting_di_meja_verifikator_termasuk_oleh_superadmin(): void
    {
        [$npd, $anggaran, , $bpp] = $this->npdDiMejaBpp();
        $superadmin = $this->user('superadmin');
        $verifikator = $this->user('verifikator');

        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);
        $this->assertSame('Verifikasi - Verifikator', $npd->fresh()->status);

        foreach ([$superadmin, $verifikator] as $akun) {
            $this->actingAs($akun)->get(route('npd.bj.edit', $npd))->assertForbidden();
            $this->actingAs($akun)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, [['Toko Asal', 900_000]]))->assertForbidden();
        }

        $this->assertSame(1_000_000.0, (float) $npd->fresh()->nominal);
    }

    public function test_draft_awal_tetap_buatan_pptk_walau_disunting_berkali_kali(): void
    {
        [$npd, $anggaran, , $bpp] = $this->npdDiMejaBpp([['Toko Asal', 1_000_000], ['Toko Kedua', 500_000]]);
        $verifikator = $this->user('verifikator');
        $this->tetapkanVerifikator($verifikator);

        // BPP menaikkan bruto; Verifikator menghapus penerima kedua dan
        // menambah penerima baru.
        $this->actingAs($bpp)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, [['Toko Asal', 1_100_000], ['Toko Kedua', 500_000]]));
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);
        $this->actingAs($verifikator)->put(route('npd.bj.update', $npd), $this->payloadBj($anggaran, [['Toko Asal', 1_100_000], ['Toko Baru', 250_000]]));

        $this->assertSame(1_350_000.0, (float) $npd->fresh()->nominal);
        $this->assertSame(['bpp', 'verifikator'], NpdRevisi::orderBy('id')->pluck('peran')->all());

        // Penerima yang diganti namanya terbaca sebagai SATU baris yang
        // berubah, bukan penerima lain yang ikut bergeser.
        $kedua = collect(NpdRevisi::orderByDesc('id')->first()->perubahan)->keyBy('bagian');
        $this->assertSame(['Toko Kedua', 'Toko Baru'], [$kedua['Penerima Toko Kedua - Nama']['lama'], $kedua['Penerima Toko Kedua - Nama']['baru']]);
        $this->assertArrayNotHasKey('Penerima Toko Asal - Bruto', $kedua->all());

        $draft = app(NpdRevisiService::class)->draftAwal($npd);
        $this->assertSame(1_500_000.0, (float) $draft->nominal);
        $this->assertSame(['Toko Asal', 'Toko Kedua'], $draft->penerima->pluck('nama')->all());
        $this->assertSame(1_000_000.0, (float) $draft->penerima->first()->bruto);
        $this->assertNull($draft->nomor_lengkap);

        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), ['aksi' => 'verifikasi', 'nomor_lengkap' => '12/NPD-Keu.1.IBC/7/2026']);

        $halaman = $this->actingAs($bpp)->get(route('npd.show', $npd))->assertOk();
        $halaman->assertSee('Cetak Draft NPD');
        $halaman->assertSee('Cetak NPD Terverifikasi');
        $halaman->assertDontSee('Cetak NPD Hasil Edit');

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-gabungan'] as $rute) {
            foreach ([[], ['versi' => 'draft']] as $versi) {
                $pdf = $this->actingAs($bpp)->get(route($rute, ['npd' => $npd] + $versi))->assertOk();
                $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            }
        }

        $this->assertStringContainsString(
            'npd-'.$npd->id.'-draft.pdf',
            (string) $this->actingAs($bpp)->get(route('npd.cetak-npd', ['npd' => $npd, 'versi' => 'draft']))->headers->get('Content-Disposition')
        );
    }

    public function test_npd_yang_tidak_pernah_disunting_hanya_punya_satu_versi_cetak(): void
    {
        [$npd, , , $bpp] = $this->npdDiMejaBpp();

        $halaman = $this->actingAs($bpp)->get(route('npd.show', $npd))->assertOk();
        $halaman->assertSee('Cetak Draft NPD');
        $halaman->assertDontSee('<h3>Histori Perubahan Data</h3>', false);
        $halaman->assertDontSee('versi=draft', false);

        // Diminta sebagai draft pun hasilnya dokumen yang sama - tidak galat.
        $this->actingAs($bpp)->get(route('npd.cetak-npd', ['npd' => $npd, 'versi' => 'draft']))->assertOk();
    }

    public function test_verifikator_menyunting_perjalanan_dinas_dan_seluruh_dokumen_draftnya_tercetak(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $verifikator = $this->user('verifikator');
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $this->tetapkanVerifikator($verifikator);
        $sp = $this->sp();

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $sp, 500_000, ['Anggota Satu', 'Anggota Dua']));
        $npd = Npd::sole();
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'terima_npd']);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);

        $payload = $this->payloadPd($anggaran, $sp, 400_000, ['Anggota Satu']);
        $payload['tujuan'] = 'Kota Bogor';
        $this->actingAs($verifikator)->get(route('npd.pd.edit', $npd))->assertOk();
        $this->actingAs($verifikator)->put(route('npd.pd.update', $npd), $payload)->assertRedirect(route('npd.coret', $npd));

        $npd->refresh();
        $this->assertSame(800_000.0, (float) $npd->nominal);
        $this->assertSame('Verifikasi - Verifikator', $npd->status);
        $this->assertSame('Verifikasi - Verifikator', $sp->fresh()->status);

        $perubahan = collect(NpdRevisi::sole()->perubahan)->keyBy('bagian');
        $this->assertSame(['Kota Yogyakarta', 'Kota Bogor'], [$perubahan['Tujuan']['lama'], $perubahan['Tujuan']['baru']]);
        $this->assertStringContainsString('Rp 400.000,00', $perubahan['Anggota Tim Anggota Satu - Paket 1']['baru']);
        $this->assertSame('Dihapus', $perubahan['Anggota Tim Anggota Dua']['baru']);

        // Draft awal dihidupkan dari potret, lengkap dengan paket tiap anggota.
        $draft = app(NpdRevisiService::class)->draftAwal($npd);
        $this->assertSame(2, $draft->tim->count());
        $this->assertSame(2_000_000.0, (float) $draft->tim->sum(fn ($t) => $t->hitung()['jumlah']));

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar', 'npd.cetak-spd', 'npd.cetak-gabungan'] as $rute) {
            $pdf = $this->actingAs($verifikator)->get(route($rute, ['npd' => $npd, 'versi' => 'draft']))->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());

            // Sama seperti NpdPdfRenderTest: setel PDF_AUDIT_DUMP_DIR untuk
            // memeriksa coretan otomatisnya dengan mata.
            if ($dumpDir = env('PDF_AUDIT_DUMP_DIR')) {
                is_dir($dumpDir) || mkdir($dumpDir, 0777, true);
                file_put_contents(rtrim($dumpDir, '/\\').'/draft-'.str_replace('npd.cetak-', '', $rute).'.pdf', $pdf->getContent());
            }
        }
    }

    public function test_coretan_otomatis_mencoret_nilai_lama_dan_menulis_penggantinya(): void
    {
        $bungkus = fn (string $isi) => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>td{color:#000;}</style></head><body>'.$isi.'</body></html>';

        $hasil = CoretanOtomatis::gabung(
            $bungkus('<div>Nominal : <b>Rp1.000.000</b></div><table><tr><td class="num">1.000.000</td><td>Tetap</td></tr></table>'),
            $bungkus('<div>Nominal : <b>Rp1.750.000</b></div><table><tr><td class="num">1.750.000</td><td>Tetap</td></tr></table>'),
        );

        $coret = 'text-decoration:line-through;color:#c00000;';
        $this->assertStringContainsString('<span style="'.$coret.'">Rp1.000.000</span> <span style="color:#c00000;">Rp1.750.000</span>', $hasil);
        // Di dalam sel tabel penggantinya turun baris, tidak berjajar.
        $this->assertStringContainsString('<span style="'.$coret.'">1.000.000</span><br><span style="color:#c00000;">1.750.000</span>', $hasil);
        $this->assertStringContainsString('<td>Tetap</td>', $hasil);
        $this->assertStringContainsString('td{color:#000;}', $hasil);

        // Dokumen yang sama persis dikembalikan apa adanya, tanpa diurai ulang.
        $sama = $bungkus('<p>Tidak berubah &amp; aman</p>');
        $this->assertSame($sama, CoretanOtomatis::gabung($sama, $sama));
    }

    public function test_coretan_otomatis_mencatat_siapa_pengubahnya_di_dekat_coretan(): void
    {
        $hasil = CoretanOtomatis::gabung(
            '<html><body><p>Nominal : <b>Rp100</b></p><table><tr><td>100</td></tr></table></body></html>',
            '<html><body><p>Nominal : <b>Rp250</b></p><table><tr><td>250</td></tr></table></body></html>',
            fn (string $lama, string $baru) => $baru === '250' ? 'Vera Verifikator' : 'Bima BPP',
        );

        $catatan = '<span style="color:#c00000;font-size:6pt;font-style:italic;font-weight:normal;">';

        // Di tengah kalimat: dalam kurung, sebaris. Di sel tabel: baris sendiri.
        $this->assertStringContainsString('Rp250</span> '.$catatan.'(Diubah oleh Bima BPP)</span>', $hasil);
        $this->assertStringContainsString('250</span><br>'.$catatan.'Diubah oleh Vera Verifikator</span>', $hasil);

        $this->assertSame(
            [['Rp100', 'Rp250'], ['100', '250']],
            CoretanOtomatis::perubahan(
                '<html><body><p>Nominal : <b>Rp100</b></p><table><tr><td>100</td></tr></table></body></html>',
                '<html><body><p>Nominal : <b>Rp250</b></p><table><tr><td>250</td></tr></table></body></html>',
            )
        );
    }

    /**
     * BPP mengubah Sisa Anggaran, lalu Verifikator mengubah bruto. Di draft,
     * tiap coretan harus menyebut orang yang benar - bukan semuanya atas
     * nama penyunting terakhir.
     */
    public function test_cetak_draft_menyebut_pengubah_yang_benar_saat_penyuntingnya_lebih_dari_satu(): void
    {
        [$npd, $anggaran, , $bpp] = $this->npdDiMejaBpp();
        $verifikator = $this->user('verifikator');
        $this->tetapkanVerifikator($verifikator);

        $payload = $this->payloadBj($anggaran, [['Toko Asal', 1_000_000]]);
        $payload['sisa_anggaran_manual'] = 40_000_000;
        $this->actingAs($bpp)->put(route('npd.bj.update', $npd), $payload);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);

        $payload = $this->payloadBj($anggaran, [['Toko Asal', 1_300_000]]);
        $payload['sisa_anggaran_manual'] = 40_000_000;
        $this->actingAs($verifikator)->put(route('npd.bj.update', $npd), $payload);

        $layanan = app(NpdRevisiService::class);
        $revisi = NpdRevisi::with('user')->orderBy('id')->get();
        $bangun = fn (Npd $n): string => view('npd.pdf.npd', [
            'npd' => $n->loadMissing('masterAnggaran.tagging'),
            'kpa' => (object) ['nama' => 'KPA', 'pangkat' => '', 'nip' => ''],
            'pptk' => (object) ['nama' => 'PPTK', 'pangkat' => '', 'nip' => ''],
            'noDpa' => '',
            'sisaAnggaran' => $n->sisaAnggaranCetak(50_000_000),
            'logoPath' => null,
        ])->render();

        $penentu = (new \ReflectionMethod(\App\Http\Controllers\NpdController::class, 'penentuPengubah'))->invoke(
            app(\App\Http\Controllers\NpdController::class),
            $revisi,
            $awal = $bangun($layanan->hidupkan($revisi->first()->potret_sebelum)),
            $kini = $bangun($npd->fresh()),
            $bangun,
        );
        $hasil = CoretanOtomatis::gabung($awal, $kini, $penentu);

        $catatan = '<span style="color:#c00000;font-size:6pt;font-style:italic;font-weight:normal;">Diubah oleh ';
        $this->assertStringContainsString('40.000.000,00</span><br>'.$catatan.$bpp->nama.'</span>', $hasil);
        $this->assertStringContainsString('1.300.000,00</span><br>'.$catatan.$verifikator->nama.'</span>', $hasil);
        $this->assertStringNotContainsString('40.000.000,00</span><br>'.$catatan.$verifikator->nama, $hasil);

        $this->actingAs($verifikator)->get(route('npd.cetak-npd', ['npd' => $npd, 'versi' => 'draft']))->assertOk();
    }

    public function test_coretan_otomatis_menyelaraskan_baris_yang_dihapus_dan_ditambah(): void
    {
        $tabel = fn (array $baris) => '<html><body><table>'.implode('', array_map(
            fn (array $b) => '<tr><td>'.$b[0].'</td><td>'.$b[1].'</td><td class="num">'.$b[2].'</td></tr>',
            $baris
        )).'</table></body></html>';

        $hasil = CoretanOtomatis::gabung(
            $tabel([[1, 'Andi', '100'], [2, 'Budi', '200'], [3, 'Cici', '300'], ['', 'J U M L A H', '600']]),
            $tabel([[1, 'Andi', '100'], [2, 'Cici', '300'], [3, 'Dedi', '50'], ['', 'J U M L A H', '450']]),
        );

        $coret = '<span style="text-decoration:line-through;color:#c00000;">';
        $baru = '<span style="color:#c00000;">';

        // Budi dihapus: barisnya tercoret seluruhnya.
        $this->assertStringContainsString('<td>'.$coret.'Budi</span></td>', $hasil);
        // Cici hanya berganti nomor urut - namanya TIDAK ikut tercoret.
        $this->assertStringContainsString('<td>Cici</td>', $hasil);
        $this->assertStringContainsString('<td>'.$coret.'3</span><br>'.$baru.'2</span></td><td>Cici</td>', $hasil);
        // Dedi baris baru: disisipkan merah, sebelum baris jumlah.
        $this->assertStringContainsString('<td>'.$baru.'Dedi</span></td>', $hasil);
        $this->assertLessThan(strpos($hasil, 'J U M L A H'), strpos($hasil, 'Dedi'));
        $this->assertStringContainsString($coret.'600</span><br>'.$baru.'450</span>', $hasil);
        $this->assertStringNotContainsString($coret.'Andi', $hasil);
    }

    public function test_coretan_otomatis_tidak_menyisipkan_baris_ke_tabel_yang_selnya_membentang(): void
    {
        $hasil = CoretanOtomatis::gabung(
            '<html><body><table><tr><td rowspan="1">Andi</td><td>10</td></tr><tr><td>J U M L A H</td><td>10</td></tr></table></body></html>',
            '<html><body><table><tr><td rowspan="2">Andi</td><td>10</td></tr><tr><td>5</td></tr><tr><td>J U M L A H</td><td>15</td></tr></table></body></html>',
        );

        $this->assertSame(2, substr_count($hasil, '<tr>'));
        $this->assertStringContainsString('<span style="color:#c00000;">15</span>', $hasil);
    }
}
