<?php

namespace Tests\Feature;

use App\Http\Controllers\NpdController;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\User;
use Database\Seeders\ClusterUhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Mode "PPTK Sebagai Penerima" (adopsi GAS #88 & #89).
 *
 * Inti yang dijaga: mode ini mengalihkan KE MANA uang ditransfer, bukan
 * berapa nilainya dan bukan siapa yang berangkat. Pada Perjalanan Dinas itu
 * berarti Daftar Pembayaran dan SPD Rampung HARUS tetap memerinci anggota
 * tim seperti biasa - kalau ikut berubah, dokumen pertanggungjawabannya
 * kehilangan daftar orang yang benar-benar melakukan perjalanan.
 */
class PptkSebagaiPenerimaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClusterUhSeeder::class);
    }

    private function pptk(): User
    {
        return User::create([
            'username' => 'pptk-penerima',
            'nama' => 'PPTK Penerima',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);
    }

    private function anggaran(string $kodeRekening = '5.1.02.01.01.0024'): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji PPTK',
            'kegiatan' => 'Kegiatan Uji PPTK',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji PPTK',
            'kode_rekening' => $kodeRekening,
            'tagging_id' => null,
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);
    }

    /** Nama PPTK hasil pelimpahan - sama dengan yang menandatangani dokumen. */
    private function namaPptk(MasterAnggaran $anggaran): string
    {
        return \App\Support\PptkPenerima::nama($anggaran);
    }

    // ---------------- Barang/Jasa ----------------

    public function test_barang_jasa_memusatkan_seluruh_pencairan_ke_pptk(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $namaPptk = $this->namaPptk($anggaran);
        Pegawai::cariByNama($namaPptk)->update(['rekening' => '55556666']);

        $this->actingAs($pptk)->post(route('npd.bj.store'), [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'pptk_penerima' => '1',
            'penerima' => [
                ['nama' => 'CV Satu', 'rekening' => '111', 'bruto' => 1_000_000, 'keterangan' => 'Pengadaan ATK'],
                ['nama' => 'CV Dua', 'rekening' => '222', 'bruto' => 500_000, 'keterangan' => 'Pengadaan kertas'],
            ],
        ])->assertSessionHasNoErrors();

        $npd = Npd::with('penerima')->sole();

        // Satu baris saja, atas nama PPTK, dengan rekening dari Data Pegawai.
        $this->assertCount(1, $npd->penerima);
        $this->assertSame($namaPptk, $npd->penerima[0]->nama);
        $this->assertSame('55556666', $npd->penerima[0]->rekening);

        // Nominalnya TIDAK berubah - yang berpindah cuma tujuannya.
        $this->assertSame(1_500_000.0, (float) $npd->nominal);
        $this->assertSame(1_500_000.0, (float) $npd->penerima[0]->bruto);
    }

    public function test_potongan_pajak_dipertahankan_bukan_dinolkan(): void
    {
        // Menyimpang sadar dari GAS, yang menolkan PPN/PPh. Mode ini mengubah
        // tujuan transfer, bukan kewajiban pajaknya: menolkan potongan akan
        // menaikkan nilai yang benar-benar ditransfer tanpa ada yang meminta.
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        Pegawai::cariByNama($this->namaPptk($anggaran))->update(['rekening' => '55556666']);

        $this->actingAs($pptk)->post(route('npd.bj.store'), [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'pptk_penerima' => '1',
            'penerima' => [
                [
                    'nama' => 'CV Satu', 'rekening' => '111', 'bruto' => 1_000_000,
                    'ppn' => 100_000, 'biaya_ku_rtgs' => 5_000, 'keterangan' => 'Pengadaan ATK',
                    'pph_list' => [['jenis' => 'PPh 23', 'nilai' => 20_000]],
                ],
                [
                    'nama' => 'CV Dua', 'rekening' => '222', 'bruto' => 500_000,
                    'ppn' => 50_000, 'keterangan' => 'Pengadaan kertas',
                    'pph_list' => [['jenis' => 'PPh 23', 'nilai' => 10_000]],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $baris = Npd::with('penerima.pphList')->sole()->penerima[0];

        $this->assertSame(150_000.0, (float) $baris->ppn);
        $this->assertSame(5_000.0, (float) $baris->biaya_ku_rtgs);
        // PPh sejenis digabung jadi satu baris, bukan dua entri terpisah.
        $this->assertCount(1, $baris->pphList);
        $this->assertSame('PPh 23', $baris->pphList[0]->jenis);
        $this->assertSame(30_000.0, (float) $baris->pphList[0]->nilai);
    }

    public function test_pptk_tanpa_rekening_ditolak_sampai_diisi_manual(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        Pegawai::cariByNama($this->namaPptk($anggaran))->update(['rekening' => null]);

        $dasar = [
            'master_anggaran_id' => $anggaran->id,
            'jenis_panjar' => 'Tanpa Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'pptk_penerima' => '1',
            'penerima' => [['nama' => 'CV Satu', 'rekening' => '111', 'bruto' => 1_000_000, 'keterangan' => 'ATK']],
        ];

        $this->actingAs($pptk)->post(route('npd.bj.store'), $dasar)
            ->assertSessionHasErrors(['pptk_rekening']);
        $this->assertSame(0, Npd::count());

        // Diisi manual di formulir -> lolos, dan itu yang tersimpan.
        $this->actingAs($pptk)->post(route('npd.bj.store'), $dasar + ['pptk_rekening' => '98887777'])
            ->assertSessionHasNoErrors();

        $this->assertSame('98887777', Npd::with('penerima')->sole()->penerima[0]->rekening);
    }

    // ---------------- Perjalanan Dinas ----------------

    /** @return array<string, mixed> */
    private function payloadPd(MasterAnggaran $anggaran, SuratPerintah $sp, array $ganti = []): array
    {
        return array_merge([
            'master_anggaran_id' => $anggaran->id,
            'surat_perintah_id' => $sp->id,
            'jenis_panjar' => 'Panjar',
            'tanggal_npd' => '2026-07-20',
            'bulan' => 7,
            'tahun' => 2026,
            'uraian_sp' => 'Pemeriksaan reguler',
            'berangkat_dari' => 'Kota Bandung',
            'tujuan' => 'Cirebon',
            'tanggal_berangkat' => '2026-07-20',
            'tanggal_pulang' => '2026-07-22',
            'penerima_index' => 0,
            'tim' => [
                [
                    'nama' => 'Anggota Satu', 'jabatan' => 'Auditor', 'nip' => '198001012000011001', 'rekening' => '111',
                    'paket' => [['cluster' => 'D', 'wilayah' => 'Kota Cirebon', 'lama_hari' => 2, 'tarif_uh' => 430_000, 'malam' => 0, 'tarif_akom' => 0]],
                ],
                [
                    'nama' => 'Anggota Dua', 'jabatan' => 'Auditor', 'nip' => '198001012000011002', 'rekening' => '222',
                    'paket' => [['cluster' => 'D', 'wilayah' => 'Kota Cirebon', 'lama_hari' => 2, 'tarif_uh' => 430_000, 'malam' => 0, 'tarif_akom' => 0]],
                ],
            ],
        ], $ganti);
    }

    private function suratPerintah(): SuratPerintah
    {
        return SuratPerintah::create([
            'nomor_sp' => '070/SP/PPTK/2026', 'tanggal_sp' => '2026-07-15',
            'unit_kerja' => 'Sekretariat', 'lokasi' => 'Cirebon',
            'nama_pengirim' => 'Penguji', 'tujuan_transfer' => 'Rekening Penguji',
            'irban_dibayar' => false, 'rincian_tgl_bayar' => '20 - 22 Juli 2026',
            'keterangan' => 'Pemeriksaan reguler', 'file_url' => 'sp/pptk.pdf',
            'status_sp' => 'Baru', 'status' => SuratPerintah::STATUS_DITERIMA_PPTK,
            'jenis_permintaan' => SuratPerintah::JENIS_UANG_HARIAN,
            'sumber_npd' => true, 'dipantau' => true,
        ]);
    }

    public function test_perjalanan_dinas_mengalihkan_lampiran_tanpa_menyentuh_tim(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran('5.1.02.04.001.00001');
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $namaPptk = $this->namaPptk($anggaran);
        Pegawai::cariByNama($namaPptk)->update(['rekening' => '77778888']);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd(
            $anggaran,
            $this->suratPerintah(),
            ['pptk_penerima' => '1'],
        ))->assertSessionHasNoErrors();

        $npd = Npd::with('tim.paket', 'masterAnggaran')->sole();

        // Tim UTUH - inilah yang dipakai Daftar Pembayaran dan SPD Rampung.
        $this->assertCount(2, $npd->tim);
        $this->assertSame('Anggota Satu', $npd->tim[0]->nama);
        $this->assertSame('Anggota Dua', $npd->tim[1]->nama);

        // Yang berubah hanya penerima pada Lampiran.
        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranPd');
        $metode->setAccessible(true);
        $lampiran = $metode->invoke(app(NpdController::class), $npd);

        $this->assertSame($namaPptk, $lampiran['penerima']->nama);
        $this->assertSame('77778888', $lampiran['penerima']->rekening);
        $this->assertStringContainsString('an. '.$namaPptk, $lampiran['keterangan']);
    }

    public function test_tanpa_centang_penerima_tetap_anggota_tim(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran('5.1.02.04.001.00001');
        $this->limpahkanSubKegiatan($pptk, $anggaran);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd($anggaran, $this->suratPerintah()))
            ->assertSessionHasNoErrors();

        $npd = Npd::with('tim.paket', 'masterAnggaran')->sole();

        $metode = new ReflectionMethod(NpdController::class, 'bangunLampiranPd');
        $metode->setAccessible(true);

        $this->assertSame('Anggota Satu', $metode->invoke(app(NpdController::class), $npd)['penerima']->nama);
    }

    public function test_seluruh_dokumen_perjalanan_tetap_tercetak_dalam_mode_pptk(): void
    {
        $pptk = $this->pptk();
        $anggaran = $this->anggaran('5.1.02.04.001.00001');
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        Pegawai::cariByNama($this->namaPptk($anggaran))->update(['rekening' => '77778888']);

        $this->actingAs($pptk)->post(route('npd.pd.store'), $this->payloadPd(
            $anggaran,
            $this->suratPerintah(),
            ['pptk_penerima' => '1'],
        ));

        $npd = Npd::sole();

        foreach (['npd.cetak-npd', 'npd.cetak-lampiran', 'npd.cetak-daftar', 'npd.cetak-spd'] as $rute) {
            $this->actingAs($pptk)->get(route($rute, $npd))->assertOk();
        }
    }
}
