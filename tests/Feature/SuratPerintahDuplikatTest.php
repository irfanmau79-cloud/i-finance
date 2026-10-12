<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Duplikat SP di halaman Data SP: satu Surat Perintah yang dibayarkan lewat
 * beberapa NPD Perjalanan Dinas diduplikat menjadi baris SP bernomor sama,
 * satu baris per NPD. Keterangan "(Duplikat-n)" hanya tampil di Data SP.
 */
class SuratPerintahDuplikatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lolosGerbangLayanan();
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => 'dup-'.$role.'-'.User::count(),
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'rahasia-uji',
        ]);
    }

    private function pegawai(string $nama): Pegawai
    {
        return Pegawai::create([
            'nama' => $nama,
            'nip' => (string) random_int(100000000000000000, 999999999999999999),
            'golongan' => 'III/c',
            'pangkat' => 'Penata',
            'jabatan' => 'Auditor Ahli Muda',
            'bidang' => 'Sekretariat',
            'rekening' => '100200300',
            'aktif' => true,
        ]);
    }

    /** SP dibuat lewat formulir sungguhan supaya berkas PDF dan anggotanya ikut ada. */
    private function buatSp(User $pptk, array $override = []): SuratPerintah
    {
        $orang = $this->pegawai('Ketua '.SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), array_replace([
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'nomor_sp' => '087/PW.02.01/Sekre',
            'tanggal_sp' => '2026-07-20',
            'unit_kerja' => 'Sekretariat',
            'lokasi' => 'Kabupaten Bekasi',
            'nama_pengirim' => 'Pengirim',
            'tujuan_transfer' => 'Koordinator',
            'irban_dibayar' => '0',
            'rincian_tgl_bayar' => '1 - 2 Mei 2026',
            'keterangan' => 'Reviu LKPD',
            'status_sp' => 'Baru',
            'komponen' => ['Uang Harian', 'Akomodasi'],
            'jenis_pembayaran' => ['Dalam Daerah/Luar Daerah'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama, 'jabatan_sp' => 'Ketua Tim']],
        ], $override))->assertRedirect(route('surat-perintah.index'));

        return SuratPerintah::latest('id')->firstOrFail();
    }

    private function duplikat(User $aktor, SuratPerintah $sp): SuratPerintah
    {
        $this->actingAs($aktor)->post(route('surat-perintah.duplikat', $sp))
            ->assertRedirect(route('surat-perintah.index'))
            ->assertSessionHasNoErrors();

        return SuratPerintah::latest('id')->firstOrFail();
    }

    private function npd(SuratPerintah $sp, string $status): Npd
    {
        $urut = Npd::count() + 1;

        $master = MasterAnggaran::create([
            'program' => 'Program Uji Duplikat',
            'kegiatan' => 'Kegiatan Uji Duplikat',
            'sub_kegiatan' => '6.01.02.1.01.0001 Sub Kegiatan Uji Duplikat',
            'kode_rekening' => sprintf('5.1.02.05.01.%04d', $urut),
            'pagu' => 50_000_000,
            'aktif' => true,
        ]);

        $npd = Npd::create([
            'jenis' => 'pd', 'master_anggaran_id' => $master->id, 'surat_perintah_id' => $sp->id,
            'keu' => '2', 'bulan' => 7, 'tahun' => 2026,
            'nomor_lengkap' => sprintf('%02d/NPD-Keu.2.IBC/7/2026', $urut),
            'tanggal_npd' => '2026-07-22', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 3_000_000,
            'terbilang' => 'tiga juta rupiah', 'status' => $status,
            'detail_json' => ['uraian_sp' => 'Reviu LKPD'],
        ]);

        $npd->tim()->create([
            'nama' => 'Anggota '.$urut, 'jabatan' => 'Auditor Ahli Muda', 'nip' => '199001012010011001',
            'rekening' => '100200300', 'bbm_liter' => 0, 'bbm_tarif' => 0, 'tol' => 150_000,
            'tiket' => 0, 'representatif' => 0, 'is_penerima' => true,
        ]);

        return $npd;
    }

    public function test_duplikat_menyalin_sp_dengan_nomor_sama_dan_mulai_sebagai_acuan_npd_baru(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);

        // SP asli sudah dipakai sebuah NPD dan pernah dimatikan penandanya -
        // duplikatnya harus tetap mulai dari awal.
        $asli->update(['status' => 'Selesai', 'dipantau' => false, 'sumber_npd' => false, 'catatan' => 'catatan lama']);

        $duplikat = $this->duplikat($pptk, $asli);

        $this->assertNotSame($asli->id, $duplikat->id);
        $this->assertSame($asli->nomor_sp, $duplikat->nomor_sp);
        $this->assertSame(0, $asli->fresh()->duplikat_ke);
        $this->assertSame(1, $duplikat->duplikat_ke);
        $this->assertSame('087/PW.02.01/Sekre (Duplikat-1)', $duplikat->nomorBerlabel());
        $this->assertSame('087/PW.02.01/Sekre', $asli->fresh()->nomorBerlabel());

        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $duplikat->status);
        $this->assertTrue($duplikat->dipantau);
        $this->assertTrue($duplikat->sumber_npd);
        $this->assertNull($duplikat->catatan);

        foreach (['tanggal_sp', 'jenis_permintaan', 'unit_kerja', 'lokasi', 'nama_pengirim', 'tujuan_transfer', 'irban_dibayar', 'rincian_tgl_bayar', 'keterangan', 'status_sp', 'pengajuan', 'jenis_pembayaran'] as $kolom) {
            $this->assertEquals($asli->getAttribute($kolom), $duplikat->getAttribute($kolom), "Kolom {$kolom} tidak tersalin.");
        }

        // Anggota disalin persis, termasuk tautan ke master pegawai.
        $ambil = fn (SuratPerintah $sp) => $sp->anggota()->get()
            ->map->only(['pegawai_id', 'nama', 'nip', 'golongan', 'pangkat', 'jabatan', 'rekening', 'manual', 'jabatan_sp', 'urutan'])->all();
        $this->assertSame($ambil($asli), $ambil($duplikat));
        $this->assertNotNull($duplikat->anggota()->first()->pegawai_id);

        // Berkas PDF disalin, bukan dipakai bersama.
        $this->assertNotSame($asli->file_url, $duplikat->file_url);
        $this->assertTrue($duplikat->fileTersedia());
        $this->assertTrue($asli->fresh()->fileTersedia());
    }

    public function test_urutan_duplikat_dihitung_per_nomor_sp_termasuk_saat_menduplikat_duplikat(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $lain = $this->buatSp($pptk, ['nomor_sp' => '088/PW.02.01/Sekre']);

        $satu = $this->duplikat($pptk, $asli);
        $dua = $this->duplikat($pptk, $asli);
        $tiga = $this->duplikat($pptk, $satu);
        $lainSatu = $this->duplikat($pptk, $lain);

        $this->assertSame([1, 2, 3], [$satu->duplikat_ke, $dua->duplikat_ke, $tiga->duplikat_ke]);
        $this->assertSame('087/PW.02.01/Sekre', $tiga->nomor_sp);
        $this->assertSame(1, $lainSatu->duplikat_ke);
    }

    public function test_keterangan_duplikat_hanya_tampil_di_data_sp(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $duplikat = $this->duplikat($pptk, $asli);

        $this->actingAs($pptk)->get(route('surat-perintah.index'))
            ->assertOk()
            ->assertSee('087/PW.02.01/Sekre (Duplikat-1)')
            ->assertSee(route('surat-perintah.duplikat', $asli), false)
            ->assertSee(route('surat-perintah.duplikat', $duplikat), false);

        // Monitoring SP: dua baris, keduanya dengan nomor sesuai inputan awal.
        $monitoring = $this->actingAs($pptk)->get(route('surat-perintah.monitoring'))->assertOk();
        $monitoring->assertDontSee('Duplikat-');
        $this->assertSame(2, substr_count($monitoring->getContent(), '<td style="font-weight:600;">087/PW.02.01/Sekre</td>'));

        // Pemilih SP di Buat NPD Perjalanan Dinas juga memakai nomor asli
        // (di sana nomornya tertulis sebagai JSON, jadi "/" ter-escape).
        $this->actingAs($pptk)->get(route('npd.pd.create'))
            ->assertOk()
            ->assertSee('087\/PW.02.01\/Sekre', false)
            ->assertDontSee('Duplikat-');
    }

    public function test_susunan_kolom_data_sp_jenis_dilebur_ke_kolom_status(): void
    {
        $pptk = $this->user('pptk');
        $this->buatSp($pptk);

        $halaman = $this->actingAs($pptk)->get(route('surat-perintah.index'))->assertOk();

        $halaman->assertSeeInOrder([
            '<th>Nomor SP</th>', '<th>Tanggal SP</th>', '<th>Unit Kerja</th>', '<th>Tujuan Transfer</th>',
            '<th>Keterangan</th>', '<th>Status/Jenis Pembayaran</th>', '<th class="mid">Aksi</th>',
        ], false);
        $halaman->assertDontSee('<th>Jenis</th>', false);
        $halaman->assertDontSee('<th>Status</th>', false);
        // Jenis kini berada DI BAWAH status, dalam sel yang sama.
        $halaman->assertSeeInOrder(['st-diterima">Diterima PPTK</span>', 'UH/Akomodasi</span>'], false);
    }

    /** Status di Data SP berwarna sama dengan Monitoring SP, bukan abu-abu semua. */
    public function test_status_di_data_sp_berwarna_sesuai_statusnya(): void
    {
        $pptk = $this->user('pptk');
        $sp = $this->buatSp($pptk);

        foreach (Npd::STATUS_BADGE_CLASS as $status => $kelas) {
            $sp->update(['status' => $status]);

            $this->actingAs($pptk)->get(route('surat-perintah.index'))
                ->assertOk()
                ->assertSee('<span class="badge '.$kelas.'">'.$status.'</span>', false);
        }
    }

    public function test_duplikat_menjadi_acuan_npd_perjalanan_dinas_saat_sp_asli_sudah_terpakai(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $this->npd($asli, 'Selesai');
        $asli->update(['status' => 'Selesai']);

        $this->assertSame(0, SuratPerintah::sumberNpdPerjalanan()->count());

        $duplikat = $this->duplikat($pptk, $asli);

        $this->assertSame([$duplikat->id], SuratPerintah::sumberNpdPerjalanan()->pluck('id')->all());
        // Status SP asli tidak ikut berubah karena duplikatnya.
        $this->assertSame('Selesai', $asli->fresh()->status);
    }

    public function test_cetak_spj_mengumpulkan_npd_dari_sp_asli_dan_seluruh_duplikatnya(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $duplikat = $this->duplikat($pptk, $asli);

        $npdAsli = $this->npd($asli, 'Selesai');
        $npdDuplikat = $this->npd($duplikat, 'Selesai');

        $this->get(route('cetak-spj.index', ['nomor_sp' => $asli->nomor_sp]))
            ->assertOk()
            ->assertSee($npdAsli->nomor_lengkap)
            ->assertSee($npdDuplikat->nomor_lengkap);

        // Awalan nomor yang sama tidak dianggap "banyak SP": nomornya satu.
        $this->get(route('cetak-spj.index', ['nomor_sp' => '087/PW']))
            ->assertOk()
            ->assertSee($npdAsli->nomor_lengkap)
            ->assertSee($npdDuplikat->nomor_lengkap);
    }

    public function test_sp_reimburse_lama_tidak_bisa_diduplikat(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $duplikat = $this->duplikat($pptk, $asli);

        // Entri Reimburse lama: jenis ini tidak lagi bisa diinput, jadi
        // disusun langsung lewat model.
        $reimburse = $asli->replicate(['file_url']);
        $reimburse->fill([
            'nomor_sp' => $asli->nomor_sp.SuratPerintah::SUFFIX_REIMBURSE,
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $asli->id,
            'pengajuan' => 'Transport',
            'file_url' => null,
        ])->save();

        $jumlah = SuratPerintah::count();
        $this->actingAs($pptk)->from(route('surat-perintah.index'))
            ->post(route('surat-perintah.duplikat', $reimburse))
            ->assertSessionHasErrors('duplikat');
        $this->assertSame($jumlah, SuratPerintah::count());

        $this->actingAs($pptk)->get(route('surat-perintah.index'))
            ->assertOk()
            ->assertDontSee(route('surat-perintah.duplikat', $reimburse), false);
        $this->assertNotNull($duplikat);
    }

    public function test_hanya_pptk_dan_superadmin_yang_boleh_menduplikat(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);

        foreach (['bpp', 'verifikator', 'bendahara_pengeluaran', 'pengawas'] as $role) {
            $aktor = $this->user($role);
            $this->actingAs($aktor)->post(route('surat-perintah.duplikat', $asli))->assertForbidden();
        }
        $this->assertSame(1, SuratPerintah::count());

        $this->duplikat($this->user('superadmin'), $asli);
        $this->assertSame(2, SuratPerintah::count());
    }

    public function test_duplikat_bisa_disunting_dan_pembetulan_nomor_ikut_ke_seluruh_kelompoknya(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $lain = $this->buatSp($pptk, ['nomor_sp' => '088/PW.02.01/Sekre']);
        $duplikat = $this->duplikat($pptk, $asli);

        $payload = fn (SuratPerintah $sp, array $ubah = []) => array_replace([
            'nomor_sp' => $sp->nomor_sp,
            'tanggal_sp' => $sp->tanggal_sp->format('Y-m-d'),
            'unit_kerja' => $sp->unit_kerja,
            'lokasi' => $sp->lokasi,
            'nama_pengirim' => $sp->nama_pengirim,
            'tujuan_transfer' => $sp->tujuan_transfer,
            'irban_dibayar' => $sp->irban_dibayar ? '1' : '0',
            'rincian_tgl_bayar' => $sp->rincian_tgl_bayar,
            'keterangan' => $sp->keterangan,
            'status_sp' => $sp->status_sp,
            'komponen' => $sp->pengajuanArray(),
            'anggota' => $sp->anggota->map(fn ($a) => $a->sebagaiInput())->all(),
        ], $ubah);

        // Menyimpan duplikat dengan nomornya sendiri (= nomor SP asli) boleh,
        // begitu pula SP asli yang sudah punya duplikat.
        $this->actingAs($pptk)->put(route('surat-perintah.update', $duplikat), $payload($duplikat, ['keterangan' => 'Reviu LKPD tahap 2']))
            ->assertRedirect(route('surat-perintah.index'));
        $this->actingAs($pptk)->put(route('surat-perintah.update', $asli), $payload($asli))
            ->assertRedirect(route('surat-perintah.index'));
        $this->assertSame('Reviu LKPD tahap 2', $duplikat->fresh()->keterangan);
        $this->assertSame('Reviu LKPD', $asli->fresh()->keterangan);

        // Nomor milik SP lain tetap ditolak.
        $this->actingAs($pptk)->put(route('surat-perintah.update', $duplikat), $payload($duplikat, ['nomor_sp' => $lain->nomor_sp]))
            ->assertSessionHasErrors('nomor_sp');

        // Nomor dibetulkan di SP asli -> duplikatnya ikut, SP lain tidak.
        $this->actingAs($pptk)->put(route('surat-perintah.update', $asli), $payload($asli, ['nomor_sp' => '087/PW.02.01/Sekre-A']))
            ->assertRedirect(route('surat-perintah.index'));
        $this->assertSame('087/PW.02.01/Sekre-A', $duplikat->fresh()->nomor_sp);
        $this->assertSame('087/PW.02.01/Sekre-A (Duplikat-1)', $duplikat->fresh()->nomorBerlabel());
        $this->assertSame('088/PW.02.01/Sekre', $lain->fresh()->nomor_sp);

        // SP baru tetap tidak boleh memakai nomor yang sudah ada.
        $orang = $this->pegawai('Orang Baru');
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $payload($asli->fresh(), [
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'jenis_pembayaran' => ['Dalam Kota'],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
            'anggota' => [['pegawai_id' => $orang->id, 'nama' => $orang->nama]],
        ]))->assertSessionHasErrors('nomor_sp');
    }

    // ---------------- Nomor yang sudah pernah diinput, dari Input SP ----------------

    /** Isian formulir Input SP untuk nomor yang sudah ada, TANPA berkas PDF. */
    private function isianUlang(SuratPerintah $sp, array $ubah = []): array
    {
        return array_replace([
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'pakai_data_sp' => '1',
            'nomor_sp' => $sp->nomor_sp,
            'tanggal_sp' => $sp->tanggal_sp->format('Y-m-d'),
            'unit_kerja' => $sp->unit_kerja,
            'lokasi' => $sp->lokasi,
            'nama_pengirim' => $sp->nama_pengirim,
            'tujuan_transfer' => $sp->tujuan_transfer,
            'irban_dibayar' => $sp->irban_dibayar ? '1' : '0',
            'rincian_tgl_bayar' => $sp->rincian_tgl_bayar,
            'keterangan' => $sp->keterangan,
            'status_sp' => $sp->status_sp,
            'komponen' => $sp->pengajuanArray(),
            'jenis_pembayaran' => $sp->jenisPembayaranArray(),
            'anggota' => $sp->anggota->map(fn ($a) => $a->sebagaiInput())->all(),
        ], $ubah);
    }

    public function test_cek_nomor_mengembalikan_data_sp_yang_sudah_pernah_diinput(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $duplikat = $this->duplikat($pptk, $asli);
        $duplikat->update(['keterangan' => 'Milik duplikat']);

        // Yang dipakai data SP ASLI, bukan duplikatnya.
        $this->actingAs($pptk)->getJson(route('sp.cek-nomor', ['nomor_sp' => '087/PW.02.01/Sekre']))
            ->assertOk()
            ->assertJsonPath('ada', true)
            ->assertJsonPath('punya_berkas', true)
            ->assertJsonPath('sp.nomor_sp', '087/PW.02.01/Sekre')
            ->assertJsonPath('sp.tanggal_sp', '2026-07-20')
            ->assertJsonPath('sp.unit_kerja', 'Sekretariat')
            ->assertJsonPath('sp.irban_dibayar', '0')
            ->assertJsonPath('sp.keterangan', 'Reviu LKPD')
            ->assertJsonPath('sp.komponen', ['Uang Harian', 'Akomodasi'])
            ->assertJsonPath('sp.jenis_pembayaran', ['Dalam Daerah/Luar Daerah'])
            ->assertJsonPath('sp.anggota.0.nama', $asli->anggota->first()->nama)
            ->assertJsonPath('sp.anggota.0.jabatan_sp', 'Ketua Tim')
            ->assertJsonMissingPath('sp.file_url');

        // Nomor dicocokkan PERSIS: awalan tidak membuka data SP mana pun.
        foreach (['087/PW', '999/PW.02.01/Sekre', ''] as $nomor) {
            $this->actingAs($pptk)->getJson(route('sp.cek-nomor', ['nomor_sp' => $nomor]))
                ->assertOk()
                ->assertExactJson(['ada' => false]);
        }
    }

    public function test_cek_nomor_dipakai_formulir_publik_tetapi_tetap_di_balik_gerbang_layanan(): void
    {
        $asli = $this->buatSp($this->user('pptk'));
        auth()->logout();

        // Tamu yang sudah melewati gerbang (lihat setUp) boleh bertanya.
        $this->getJson(route('sp.cek-nomor', ['nomor_sp' => $asli->nomor_sp]))
            ->assertOk()
            ->assertJsonPath('ada', true);

        $this->get(route('sp.input.create'))
            ->assertOk()
            ->assertSee(json_encode(route('sp.cek-nomor')), false)
            ->assertSee('name="pakai_data_sp"', false);

        $this->flushSession();
        $this->getJson(route('sp.cek-nomor', ['nomor_sp' => $asli->nomor_sp]))->assertForbidden();
    }

    public function test_input_sp_bernomor_sama_disimpan_sebagai_duplikat_dan_isinya_boleh_diubah(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $asli->update(['status' => 'Selesai', 'dipantau' => false, 'sumber_npd' => false]);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, [
            'lokasi' => 'Kota Bandung',
            'keterangan' => 'Reviu LKPD tahap 2',
            'komponen' => ['Transport'],
        ]))->assertRedirect(route('surat-perintah.index'))
            ->assertSessionHas('success', 'Surat Perintah berhasil disimpan sebagai 087/PW.02.01/Sekre (Duplikat-1).');

        $baru = SuratPerintah::latest('id')->firstOrFail();

        $this->assertSame(2, SuratPerintah::count());
        $this->assertSame($asli->nomor_sp, $baru->nomor_sp);
        $this->assertSame(1, $baru->duplikat_ke);
        $this->assertSame('087/PW.02.01/Sekre (Duplikat-1)', $baru->nomorBerlabel());

        // Yang diubah di formulir ikut tersimpan; SP asli tidak tersentuh.
        $this->assertSame('Kota Bandung', $baru->lokasi);
        $this->assertSame('Reviu LKPD tahap 2', $baru->keterangan);
        $this->assertSame('Transport', $baru->pengajuan);
        $this->assertSame('Kabupaten Bekasi', $asli->fresh()->lokasi);
        $this->assertSame('Reviu LKPD', $asli->fresh()->keterangan);

        // Mulai dari awal sebagai acuan NPD, seperti tombol Duplikat.
        $this->assertSame(SuratPerintah::STATUS_DITERIMA_PPTK, $baru->status);
        $this->assertTrue($baru->dipantau);
        $this->assertTrue($baru->sumber_npd);
        $this->assertSame([$baru->id], SuratPerintah::sumberNpdPerjalanan()->pluck('id')->all());
        $this->assertSame(1, $baru->anggota()->count());

        // PDF tidak diunggah ulang -> berkas SP asli DISALIN, bukan dipakai bersama.
        $this->assertTrue($baru->fileTersedia());
        $this->assertNotSame($asli->file_url, $baru->file_url);

        $this->actingAs($pptk)->get(route('surat-perintah.index'))
            ->assertOk()
            ->assertSee('087/PW.02.01/Sekre (Duplikat-1)');

        // Urutannya menyambung dengan tombol Duplikat di Data SP.
        $this->assertSame(2, $this->duplikat($pptk, $asli)->duplikat_ke);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, [
            'file_url' => UploadedFile::fake()->create('sp-baru.pdf', 100, 'application/pdf'),
        ]))->assertRedirect(route('surat-perintah.index'));

        $ketiga = SuratPerintah::latest('id')->firstOrFail();
        $this->assertSame(3, $ketiga->duplikat_ke);
        $this->assertTrue($ketiga->fileTersedia());
    }

    public function test_semua_role_penginput_termasuk_formulir_publik_boleh_memakai_data_sp_yang_ada(): void
    {
        $asli = $this->buatSp($this->user('pptk'));

        // Tombol Duplikat di Data SP tetap milik PPTK & superadmin; lewat
        // Input SP siapa pun yang boleh menginput boleh memakai datanya.
        foreach (['bpp', 'verifikator', 'bendahara_pengeluaran'] as $role) {
            $this->actingAs($this->user($role))->post(route('surat-perintah.store'), $this->isianUlang($asli))
                ->assertRedirect(route('surat-perintah.index'));
        }
        auth()->logout();

        $this->post(route('sp.input.store'), $this->isianUlang($asli))
            ->assertRedirect(route('surat-perintah.monitoring'));

        $this->assertSame([0, 1, 2, 3, 4], SuratPerintah::orderBy('id')->pluck('duplikat_ke')->all());
        $this->assertSame(1, SuratPerintah::distinct()->count('nomor_sp'));
    }

    public function test_tanpa_persetujuan_nomor_ganda_tetap_ditolak_dan_penanda_tidak_melonggarkan_nomor_baru(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);

        // Menjawab "Tidak": penanda tidak menyala, nomor ganda ditolak.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, ['pakai_data_sp' => '0']))
            ->assertSessionHasErrors('nomor_sp');

        // Penanda menyala tetapi nomornya ternyata baru: ini Input SP biasa,
        // jadi PDF tetap wajib dan hasilnya BUKAN duplikat.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, ['nomor_sp' => '090/PW.02.01/Sekre']))
            ->assertSessionHasErrors('file_url')
            ->assertSessionDoesntHaveErrors('nomor_sp');
        $this->assertSame(1, SuratPerintah::count());

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, [
            'nomor_sp' => '090/PW.02.01/Sekre',
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ]))->assertRedirect(route('surat-perintah.index'));
        $this->assertSame(0, SuratPerintah::latest('id')->firstOrFail()->duplikat_ke);

        // SP yang berkasnya tidak ada: PDF wajib diunggah walau memakai datanya.
        Storage::disk('local')->delete($asli->filePath());
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli))
            ->assertSessionHasErrors('file_url');

        // Aturan lain tetap berlaku penuh pada entri duplikat.
        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, [
            'anggota' => [],
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('anggota');
        $this->assertSame(2, SuratPerintah::count());
    }

    public function test_nomor_sp_reimburse_lama_tidak_ditawarkan_dan_tidak_bisa_diduplikat_lewat_input(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);

        $reimburse = $asli->replicate(['file_url']);
        $reimburse->fill([
            'nomor_sp' => $asli->nomor_sp.SuratPerintah::SUFFIX_REIMBURSE,
            'jenis_permintaan' => SuratPerintah::JENIS_REIMBURSE,
            'sp_induk_id' => $asli->id,
            'file_url' => null,
        ])->save();

        $this->actingAs($pptk)->getJson(route('sp.cek-nomor', ['nomor_sp' => $reimburse->nomor_sp]))
            ->assertExactJson(['ada' => false]);

        $this->actingAs($pptk)->post(route('surat-perintah.store'), $this->isianUlang($asli, [
            'nomor_sp' => $reimburse->nomor_sp,
            'file_url' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('nomor_sp');
        $this->assertSame(2, SuratPerintah::count());
    }

    public function test_menghapus_duplikat_tidak_menghilangkan_sp_asli_maupun_berkasnya(): void
    {
        $pptk = $this->user('pptk');
        $asli = $this->buatSp($pptk);
        $duplikat = $this->duplikat($pptk, $asli);
        $berkasDuplikat = $duplikat->filePath();

        $this->actingAs($pptk)->delete(route('surat-perintah.destroy', $duplikat))
            ->assertRedirect(route('surat-perintah.index'));

        $this->assertNull(SuratPerintah::find($duplikat->id));
        Storage::disk('local')->assertMissing($berkasDuplikat);
        $this->assertTrue($asli->fresh()->fileTersedia());
        $this->assertSame(1, $asli->fresh()->anggota()->count());
    }
}
