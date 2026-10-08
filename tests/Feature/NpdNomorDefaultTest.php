<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Aturan Keu.1/Keu.2 (adopsi _keuByProgram "i-finance gas") dan nomor default
 * NPD: tiap NPD, juga yang masih draft, sudah bernomor
 * "        /NPD-Keu.<keu>.IBC/<bulan>/<tahun>" sampai Verifikator menetapkan
 * nomornya sendiri.
 */
class NpdNomorDefaultTest extends TestCase
{
    use RefreshDatabase;

    private const RUANG = "\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}";

    private function user(string $role): User
    {
        return User::create([
            'username' => 'nomor-'.$role.'-'.User::count(),
            'nama' => ucfirst($role),
            'role' => $role,
            'password' => 'rahasia',
        ]);
    }

    private function anggaran(string $subKegiatan, string $kodeRekening = '5.1.02.01.01.9999'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Nomor',
            'kegiatan' => 'Kegiatan Nomor',
            'sub_kegiatan' => $subKegiatan,
            'kode_rekening' => $kodeRekening,
            'pagu' => 20_000_000,
            'aktif' => true,
        ]);
    }

    private function payloadBj(MasterAnggaran $anggaran): array
    {
        return [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-10-08',
            'bulan' => 10,
            'tahun' => 2026,
            'penerima' => [[
                'nama' => 'Penerima Nomor',
                'bruto' => 1_000_000,
                'ppn' => 0,
                'biaya_ku_rtgs' => 0,
                'keterangan' => 'Uji nomor default',
            ]],
        ];
    }

    /** HTML dokumen NPD sebelum dijadikan PDF - di situlah nomornya bisa dibaca. */
    private function htmlNpd(Npd $npd): string
    {
        return (new ReflectionMethod(NpdController::class, 'htmlNpd'))
            ->invoke(app(NpdController::class), $npd);
    }

    public function test_keu_mengikuti_aturan_gas(): void
    {
        $harapan = [
            // Perencanaan & pelaporan kinerja: di bawah 6.01.01 tetapi Keu.2.
            '6.01.01.1.01' => '2',
            '6.01.01.1.01.0001' => '2',
            '6.01.01.1.01.0007' => '2',
            // Kesekretariatan lainnya tetap Keu.1.
            '6.01.01.1.02.0001' => '1',
            '6.01.01.1.09.0011' => '1',
            '6.01.01.2.01' => '1',
            // Hanya ruas yang persis "1.01" - bukan sekadar awalan huruf.
            '6.01.01.1.010.0001' => '1',
            '6.01.02.1.01.0001' => '2',
            '6.01.03.1.01.0001' => '2',
            '5.01.01.1.01.0001' => null,
            '' => null,
        ];

        foreach ($harapan as $kode => $keu) {
            $this->assertSame(
                $keu,
                (new MasterAnggaran(['kode_sub_kegiatan' => (string) $kode]))->tentukanKeu(),
                "Keu untuk sub kegiatan \"{$kode}\" keliru."
            );
        }
    }

    public function test_npd_draft_langsung_bernomor_default_sesuai_keu_dan_tanggal(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran('6.01.01.1.01.0001 Penyusunan Dokumen Perencanaan');
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran))
            ->assertSessionHasNoErrors();

        $npd = Npd::sole();
        $default = self::RUANG.'/NPD-Keu.2.IBC/10/2026';

        $this->assertSame('Draft NPD - PPTK', $npd->status);
        $this->assertSame('2', $npd->keu);
        // Nomor default dihitung, bukan disimpan: nomor_lengkap ber-indeks unik.
        $this->assertNull($npd->nomor_lengkap);
        $this->assertSame($default, $npd->nomorDefault());
        $this->assertSame($default, $npd->nomorCetak());
        $this->assertSame('01/NPD-Keu.2.IBC/10/2026', $npd->contohNomor());

        $this->actingAs($pptk)->get(route('npd.show', $npd))
            ->assertOk()
            ->assertSee($default, false)
            ->assertDontSee('Belum bernomor');

        $this->assertStringContainsString('Nomor&nbsp;&nbsp;: '.$default.'</td>', $this->htmlNpd($npd));
        $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd))->assertOk();
    }

    public function test_dua_draft_pada_keu_dan_bulan_yang_sama_tidak_saling_bentrok(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran('6.01.01.1.02.0001 Administrasi Keuangan');
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran))->assertSessionHasNoErrors();
        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran))->assertSessionHasNoErrors();

        $this->assertSame(2, Npd::count());
        $this->assertSame(
            [self::RUANG.'/NPD-Keu.1.IBC/10/2026'],
            Npd::all()->map->nomorDefault()->unique()->values()->all()
        );
    }

    public function test_nomor_dari_verifikator_menggantikan_nomor_default_apa_adanya(): void
    {
        $pptk = $this->user('pptk');
        $bpp = $this->user('bpp');
        $verifikator = $this->user('verifikator');
        $anggaran = $this->anggaran('6.01.02.1.01.0001 Pengawasan Kinerja');
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.bj.store'), $this->payloadBj($anggaran));
        $npd = Npd::sole();

        $this->actingAs($pptk)->post(route('npd.transisi', $npd), ['aksi' => 'ajukan_bpp']);
        $this->actingAs($bpp)->post(route('npd.transisi', $npd), ['aksi' => 'teruskan']);
        $this->tetapkanVerifikator($verifikator);

        // Masih di meja Verifikator: dokumennya tetap memakai nomor default.
        $this->assertSame(self::RUANG.'/NPD-Keu.2.IBC/10/2026', $npd->fresh()->nomorCetak());
        $this->actingAs($verifikator)->get(route('npd.coret', $npd))
            ->assertOk()
            ->assertSee('placeholder="Contoh: 01/NPD-Keu.2.IBC/10/2026"', false);

        // Nomor Verifikator bebas bentuknya - tidak harus mengikuti templat.
        $this->actingAs($verifikator)->post(route('npd.transisi', $npd), [
            'aksi' => 'verifikasi',
            'nomor_lengkap' => '917/NPD-Keu.2-IBC/X/2026',
        ])->assertSessionHasNoErrors();

        $npd->refresh();
        $this->assertSame('917/NPD-Keu.2-IBC/X/2026', $npd->nomor_lengkap);
        $this->assertSame('917/NPD-Keu.2-IBC/X/2026', $npd->nomorCetak());

        $html = $this->htmlNpd($npd);
        $this->assertStringContainsString('Nomor&nbsp;&nbsp;: 917/NPD-Keu.2-IBC/X/2026</td>', $html);
        $this->assertStringNotContainsString('/NPD-Keu.2.IBC/10/2026', $html);
    }

    /**
     * Nomor default dihitung saat dicetak, jadi NPD yang sudah berjalan
     * sebelum fitur ini ada ikut mendapatkannya - di meja mana pun ia berada.
     */
    public function test_npd_yang_sedang_berjalan_dan_belum_bernomor_ikut_tercetak_dengan_nomor_default(): void
    {
        $pptk = $this->user('pptk');
        $anggaran = $this->anggaran('6.01.02.1.01.0001 Pengawasan Kinerja');
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        foreach (['Draft NPD - PPTK', 'Draft NPD - BPP', 'Verifikasi - Verifikator', 'NPD Disetujui - BPP'] as $status) {
            $npd = Npd::create([
                'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '2', 'bulan' => 9, 'tahun' => 2026,
                'tanggal_npd' => '2026-09-15', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000,
                'terbilang' => 'satu juta rupiah', 'status' => $status,
            ]);

            $this->assertStringContainsString(
                'Nomor&nbsp;&nbsp;: '.self::RUANG.'/NPD-Keu.2.IBC/9/2026</td>',
                $this->htmlNpd($npd),
                "NPD berstatus \"{$status}\" tercetak tanpa nomor default."
            );
            $this->actingAs($pptk)->get(route('npd.cetak-npd', $npd))->assertOk();
        }
    }

    public function test_migrasi_membetulkan_keu_npd_lama_tanpa_menyentuh_nomornya(): void
    {
        $perencanaan = $this->anggaran('6.01.01.1.01.0001 Penyusunan Dokumen Perencanaan', '5.1.02.01.01.0001');
        $sekretariat = $this->anggaran('6.01.01.1.02.0001 Administrasi Keuangan', '5.1.02.01.01.0002');

        $buat = fn (MasterAnggaran $anggaran, ?string $nomor) => Npd::create([
            'jenis' => 'bj', 'master_anggaran_id' => $anggaran->id, 'keu' => '1', 'bulan' => 4, 'tahun' => 2026,
            'tanggal_npd' => '2026-04-10', 'jenis_panjar' => 'Tanpa Panjar', 'nominal' => 1_000_000,
            'terbilang' => 'satu juta rupiah', 'status' => 'Selesai', 'nomor_lengkap' => $nomor,
        ]);

        $lama = $buat($perencanaan, '02/NPD-Keu.2-IBC/04/2026');
        $draft = $buat($perencanaan, null);
        $lain = $buat($sekretariat, '03/NPD-Keu.1-IBC/04/2026');

        (require database_path('migrations/2026_10_08_090000_betulkan_keu_npd_perencanaan_pelaporan.php'))->up();

        $this->assertSame('2', $lama->fresh()->keu);
        $this->assertSame('02/NPD-Keu.2-IBC/04/2026', $lama->fresh()->nomor_lengkap);
        $this->assertSame('2', $draft->fresh()->keu);
        $this->assertSame(self::RUANG.'/NPD-Keu.2.IBC/4/2026', $draft->fresh()->nomorCetak());
        $this->assertSame('1', $lain->fresh()->keu);
    }
}
