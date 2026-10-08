<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NpdTransportTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => ucfirst($username),
            'role' => $role,
            'password' => 'rahasia',
            // Pelimpahan menunjuk pegawai, bukan akun: akun PPTK harus sudah
            // tertaut sejak dibuat supaya objek yang dipegang test membawa
            // tautannya (lihat buatIndukSelesai).
            'pegawai_id' => $role === 'pptk'
                ? Pegawai::create(['nama' => 'Pegawai '.$username, 'nip' => sprintf('1975010119950%05d', Pegawai::count() + 1), 'jabatan' => 'PPTK', 'bidang' => 'Sekretariat', 'pangkat' => 'Penata', 'aktif' => true])->id
                : null,
        ]);
    }

    private function buatMasterAnggaran(float $pagu = 100_000_000, string $kodeRekening = '5.1.02.04.01.0001'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Transport',
            'kegiatan' => 'Kegiatan Uji Transport',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Transport',
            'kode_rekening' => $kodeRekening,
            'tagging_id' => null,
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatIndukSelesai(MasterAnggaran $masterAnggaran, int $jumlahAnggota = 2): Npd
    {
        // PPTK hanya boleh membuat Transport di atas Perjalanan Dinas pada
        // Sub Kegiatan limpahannya, jadi akun PPTK uji yang sudah dibuat
        // dilimpahi Sub Kegiatan induk ini. Pembatasannya sendiri diuji di
        // test_pptk_tidak_bisa_menumpang_pada_perjalanan_dinas_sub_kegiatan_orang_lain.
        User::where('role', 'pptk')->get()
            ->each(fn (User $pptk) => $this->limpahkanSubKegiatan($pptk, $masterAnggaran));

        $induk = Npd::create([
            'jenis' => 'pd',
            'master_anggaran_id' => $masterAnggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-20',
            'jenis_panjar' => 'Panjar',
            'nominal' => 1_830_000,
            'terbilang' => 'satu juta delapan ratus tiga puluh ribu rupiah',
            'status' => 'Selesai',
            'detail_json' => [
                'nomor_sp' => '001/SP/TEST/2026',
                'tanggal_sp' => '2026-07-15',
                'uraian_sp' => 'Perjalanan pengujian',
                'berangkat_dari' => 'Kabupaten Bekasi',
                'tujuan' => 'Bandung',
                'tanggal_berangkat' => '2026-07-20',
                'tanggal_pulang' => '2026-07-22',
                'keterangan_lampiran' => null,
            ],
        ]);

        $nama = ['Anggota Pertama', 'Anggota Kedua', 'Anggota Ketiga'];
        for ($i = 0; $i < $jumlahAnggota; $i++) {
            $tim = $induk->tim()->create([
                'nama' => $nama[$i],
                'jabatan' => 'Auditor',
                'bidang_snapshot' => 'Sekretariat',
                'nip' => '19800101200001100'.$i,
                'rekening' => '11111'.$i,
                'bbm_liter' => 0,
                'bbm_tarif' => 0,
                'tol' => 0,
                'tiket' => 0,
                'representatif' => 0,
                'is_penerima' => $i === 0,
            ]);
            $tim->paket()->create([
                'cluster' => 'A',
                'wilayah' => 'Bandung',
                'lama_hari' => 2,
                'tarif_uh' => 100_000,
                'malam' => 1,
                'tarif_akom' => 300_000,
            ]);
        }

        return $induk;
    }

    private function payload(Npd $induk, int $jumlahAnggota = 2): array
    {
        $tim = [];
        for ($i = 0; $i < $jumlahAnggota; $i++) {
            $tim[] = [
                'bbm_liter' => 10,
                'bbm_tarif' => 10_000,
                'tol' => 20_000,
                'tiket' => 150_000,
                'representatif' => $i === 0 ? 50_000 : 0,
            ];
        }

        return [
            'npd_induk_id' => $induk->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-25',
            'bulan' => 7,
            'tahun' => 2026,
            'penerima_index' => 0,
            'tim' => $tim,
        ];
    }


    /**
     * NPD Transport yang "sudah telanjur ada". Dibuat langsung lewat model
     * karena pintu pembuatannya sudah dihapus - isinya meniru apa yang dulu
     * disimpan formulirnya: identitas anggota disalin dari induk, paket
     * perjalanan sengaja kosong, hanya komponen transport yang terisi.
     *
     * Per anggota: BBM 10 liter x 10.000 = 100.000 + tol 20.000 + tiket
     * 150.000 = 270.000; anggota pertama (penerima) ditambah representatif
     * 50.000. Dua anggota = 590.000.
     */
    private function buatTransport(Npd $induk, User $pembuat, string $status = 'Draft NPD - PPTK'): Npd
    {
        $induk->load('tim');

        $npd = Npd::create([
            'jenis' => 'tr',
            'npd_induk_id' => $induk->id,
            'master_anggaran_id' => $induk->master_anggaran_id,
            'surat_perintah_id' => $induk->surat_perintah_id,
            'keu' => $induk->keu,
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => '2026-07-25',
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => 270_000 * $induk->tim->count() + 50_000,
            'terbilang' => 'uji transport',
            'status' => $status,
            'detail_json' => $induk->detail_json,
            'dibuat_oleh' => $pembuat->id,
        ]);

        foreach ($induk->tim->values() as $i => $anggota) {
            $npd->tim()->create([
                'pegawai_id' => $anggota->pegawai_id,
                'nama' => $anggota->nama,
                'jabatan' => $anggota->jabatan,
                'bidang_snapshot' => $anggota->bidang_snapshot,
                'nip' => $anggota->nip,
                'rekening' => $anggota->rekening,
                'bbm_liter' => 10,
                'bbm_tarif' => 10_000,
                'tol' => 20_000,
                'tiket' => 150_000,
                'representatif' => $i === 0 ? 50_000 : 0,
                'is_penerima' => $i === 0,
            ]);
        }

        return $npd;
    }

    /**
     * Transport kini dibayar lewat NPD Perjalanan Dinas, jadi NPD Transport
     * tidak boleh lagi bisa dibuat - baik lewat tampilan maupun langsung ke
     * alamatnya.
     */
    public function test_pembuatan_npd_transport_sudah_dihapus(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-tutup-pptk');
        $superadmin = $this->buatUser('superadmin', 'tr-tutup-superadmin');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());

        $this->assertFalse(Route::has('npd.tr.create'));
        $this->assertFalse(Route::has('npd.tr.store'));

        foreach ([$pptk, $superadmin] as $aktor) {
            // 404 atau 405: alamatnya tidak lagi punya pintu GET, walau pola
            // /npd/tr/{npd} masih dipakai untuk menyimpan suntingan (PUT).
            $this->assertContains($this->actingAs($aktor)->get('/npd/tr/create')->status(), [404, 405]);

            $status = $this->actingAs($aktor)->post('/npd/tr', $this->payload($induk))->status();
            $this->assertContains($status, [404, 405], 'Alamat lama pembuatan NPD Transport masih menerima kiriman.');
        }

        $this->assertSame(0, Npd::where('jenis', 'tr')->count());

        // Halaman Buat NPD: empat jenis, tanpa Transport.
        $this->actingAs($pptk)->get(route('npd.index'))
            ->assertOk()
            ->assertSee('NPD Barang/Jasa')
            ->assertSee('NPD Perjalanan Dinas')
            ->assertSee('NPD Narasumber')
            ->assertSee('NPD Kontribusi Diklat')
            ->assertDontSee('NPD Transport')
            ->assertDontSee('/npd/tr/create', false);
    }

    public function test_npd_transport_yang_sudah_ada_tetap_bisa_dilihat_dan_dicetak(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-lama-lihat');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());
        $npd = $this->buatTransport($induk, $pptk);

        // Uang harian & akomodasi tetap nol walau induknya punya paket.
        foreach ($npd->tim as $anggota) {
            $hitung = $anggota->hitung();
            $this->assertEquals(0.0, $hitung['jml_harian']);
            $this->assertEquals(0.0, $hitung['jml_akom']);
        }
        $this->assertEquals(320_000.0, $npd->tim->firstWhere('is_penerima', true)->hitung()['jumlah']);

        $this->actingAs($pptk)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee('Anggota Pertama')
            ->assertSee('Induk NPD Perjalanan Dinas');

        // Induknya masih menampilkan NPD Transport turunannya.
        $this->actingAs($pptk)->get(route('npd.show', $induk))
            ->assertOk()
            ->assertSee('NPD Transport Terkait');

        // Tetap tampil di Data NPD dan tombol suntingnya mengarah ke rute edit.
        $this->actingAs($pptk)->get(route('npd.index'))
            ->assertOk()
            ->assertSee(route('npd.tr.edit', $npd), false);

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar', 'npd.cetak-spd'] as $route) {
            $this->actingAs($pptk)->get(route($route, $npd))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_npd_transport_yang_sudah_ada_tetap_bisa_disunting(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-lama-sunting');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());
        $npd = $this->buatTransport($induk, $pptk);

        $this->actingAs($pptk)->get(route('npd.tr.edit', $npd))
            ->assertOk()
            ->assertSee('Edit Nota Pencairan Dana Transport')
            ->assertSee('Induk tidak dapat diganti setelah NPD Transport dibuat.')
            // NPD lama (liter x tarif) dibuka sebagai Total Nominal BBM.
            ->assertSee('data-bbm-nominal name="tim[0][bbm_nominal]" value="100000"', false)
            ->assertSee('Jumlah Liter (otomatis)')
            ->assertDontSee('data-bbm-liter name=', false)
            ->assertDontSee('<select id="npd_induk_id"', false);

        $payload = $this->payload($induk);
        $payload['tim'] = [
            ['bbm_nominal' => 300_000, 'bbm_tarif' => 12_000, 'tol' => 50_000],
            ['bbm_nominal' => '', 'bbm_tarif' => 12_000, 'tiket' => 100_000],
        ];

        $this->actingAs($pptk)->put(route('npd.tr.update', $npd), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $npd = Npd::with('tim')->findOrFail($npd->id);
        $this->assertSame(450_000.0, (float) $npd->nominal);
        $this->assertSame(300_000.0, (float) $npd->tim[0]->bbm_nominal);
        $this->assertSame(25.0, (float) $npd->tim[0]->bbm_liter);
        // Anggota tanpa BBM: tarif standar yang ikut terkirim tidak membuat BBM.
        $this->assertNull($npd->tim[1]->bbm_nominal);
        $this->assertSame(0.0, (float) $npd->tim[1]->hitung()['bbm']);
        // Identitas anggota selalu disalin ulang dari induk, bukan dari kiriman.
        $this->assertSame(['Anggota Pertama', 'Anggota Kedua'], $npd->tim->pluck('nama')->all());
        $this->assertSame('Sekretariat', $npd->tim[0]->bidang_snapshot);

        $this->actingAs($pptk)->get(route('npd.cetak-spd', $npd))->assertOk();
    }

    public function test_sunting_transport_menolak_ganti_induk_dan_jumlah_anggota_berbeda(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-lama-tolak');
        $masterAnggaran = $this->buatMasterAnggaran();
        $induk = $this->buatIndukSelesai($masterAnggaran);
        $indukLain = $this->buatIndukSelesai($masterAnggaran);
        $npd = $this->buatTransport($induk, $pptk);

        $this->actingAs($pptk)->put(route('npd.tr.update', $npd), $this->payload($indukLain))
            ->assertSessionHasErrors(['npd_induk_id']);

        $payload = $this->payload($induk);
        unset($payload['tim'][1]);
        $payload['tim'] = array_values($payload['tim']);

        $this->actingAs($pptk)->put(route('npd.tr.update', $npd), $payload)
            ->assertSessionHasErrors(['tim']);

        $this->assertSame(590_000.0, (float) $npd->fresh()->nominal);
        $this->assertSame($induk->id, $npd->fresh()->npd_induk_id);
    }

    public function test_sunting_transport_tetap_mengikuti_meja_npd(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-meja-pptk');
        $verifikator = $this->buatUser('verifikator', 'tr-meja-verif');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());
        $npd = $this->buatTransport($induk, $pptk, 'Draft NPD - BPP');

        // Sudah di meja BPP: PPTK dan Verifikator tidak lagi boleh menyunting.
        $this->actingAs($pptk)->get(route('npd.tr.edit', $npd))->assertForbidden();
        $this->actingAs($verifikator)->get(route('npd.tr.edit', $npd))->assertForbidden();
        $this->actingAs($pptk)->put(route('npd.tr.update', $npd), $this->payload($induk))->assertForbidden();

        // Rute edit Transport bukan pintu untuk jenis NPD lain.
        $superadmin = $this->buatUser('superadmin', 'tr-meja-superadmin');
        $this->actingAs($superadmin)->get(route('npd.tr.edit', $induk))->assertNotFound();
    }

    public function test_pembatalan_induk_diblokir_selama_ada_turunan_aktif(): void
    {
        $pptk = $this->buatUser('pptk', 'tr-blokir-batal');
        $superadmin = $this->buatUser('superadmin', 'tr-blokir-superadmin');
        $induk = $this->buatIndukSelesai($this->buatMasterAnggaran());
        $transport = $this->buatTransport($induk, $pptk);

        // destroy() oleh superadmin harus ditolak selama turunan Transport masih aktif.
        $this->actingAs($superadmin)->delete(route('npd.destroy', $induk), ['alasan' => 'Coba batalkan induk'])
            ->assertSessionHasErrors(['alasan']);
        $this->assertSame('Selesai', $induk->fresh()->status);

        // Transisi batal_selesai (Selesai -> Draft NPD - BPP) juga harus ditolak.
        $this->actingAs($superadmin)->post(route('npd.transisi', $induk), [
            'aksi' => 'batal_selesai',
            'catatan' => 'Coba batalkan status selesai',
        ])->assertSessionHasErrors(['aksi']);
        $this->assertSame('Selesai', $induk->fresh()->status);

        // Setelah Transport-nya dibatalkan, induk tidak lagi terkunci.
        $this->actingAs($pptk)->delete(route('npd.destroy', $transport), ['alasan' => 'Tidak jadi dibayar terpisah']);
        $this->assertFalse($induk->fresh()->punyaTurunanTransportAktif());
    }
}
