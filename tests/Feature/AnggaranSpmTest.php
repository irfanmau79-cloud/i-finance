<?php

namespace Tests\Feature;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Spm;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AnggaranSpmTest extends TestCase
{
    use RefreshDatabase;

    private function buatAnggaran(float $pagu = 10_000_000): MasterAnggaran
    {
        return MasterAnggaran::create([
            'program' => 'Program Uji Anggaran',
            'kegiatan' => 'Kegiatan Uji Anggaran',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Anggaran',
            'kode_rekening' => '5.1.02.99.99.0001 Belanja Pengujian',
            'pagu' => $pagu,
            'aktif' => true,
        ]);
    }

    private function buatNpd(MasterAnggaran $anggaran, float $nominal, string $status, string $tanggal = '2026-07-20'): Npd
    {
        return Npd::create([
            'jenis' => 'bj',
            'master_anggaran_id' => $anggaran->id,
            'keu' => '1',
            'bulan' => 7,
            'tahun' => 2026,
            'tanggal_npd' => $tanggal,
            'jenis_panjar' => 'Tanpa Panjar',
            'nominal' => $nominal,
            'terbilang' => 'nilai pengujian rupiah',
            'status' => $status,
        ]);
    }

    private function dataSpm(string $nomor, string $tanggal, float $nominal): array
    {
        return ['nomor_dokumen' => $nomor, 'tanggal_dokumen' => $tanggal, 'nominal' => $nominal];
    }

    /** Payload Spm::buatLs()/updateLs(): satu baris mata anggaran. */
    private function dataSpmLs(string $nomor, string $tanggal, float $nominal, int $masterAnggaranId): array
    {
        return [
            'nomor_dokumen' => $nomor,
            'tanggal_dokumen' => $tanggal,
            'baris' => [['master_anggaran_id' => $masterAnggaranId, 'nominal' => $nominal]],
        ];
    }

    public function test_dana_terikat_realisasi_aktual_dan_sisa_tersedia_dibedakan(): void
    {
        $anggaran = $this->buatAnggaran();
        $draft = $this->buatNpd($anggaran, 2_000_000, 'Draft NPD - PPTK');
        $selesai = $this->buatNpd($anggaran, 3_000_000, 'Selesai');

        Spm::buatLs($this->dataSpmLs('001/SPM-LS/2026', '2026-07-18', 1_000_000, $anggaran->id));
        Spm::buatUpGu($this->dataSpm('001/SPM-UP/2026', '2026-07-19', 4_000_000));
        Spm::buatUpGu($this->dataSpm('001/SPM-GU/2026', '2026-07-20', 2_000_000));

        $this->assertSame(5_000_000.0, $anggaran->danaTerikatNpd());
        $this->assertSame(3_000_000.0, $anggaran->realisasiNpd());
        $this->assertSame(1_000_000.0, $anggaran->realisasiLs());
        $this->assertSame(4_000_000.0, $anggaran->realisasiAktual());
        $this->assertSame(4_000_000.0, $anggaran->sisaTersedia());
        $this->assertSame(0, Spm::where('jenis_spm', 'up_gu')->firstOrFail()->detail()->count());

        $selesai->update(['status' => 'Draft NPD - BPP']);

        $this->assertSame(5_000_000.0, $anggaran->danaTerikatNpd(), 'Revisi status tetap mengikat dana NPD.');
        $this->assertSame(0.0, $anggaran->realisasiNpd(), 'Pembatalan selesai menghapus realisasi aktual NPD.');
        $this->assertSame(4_000_000.0, $anggaran->sisaTersedia(), 'Dana draft tetap terikat setelah pembatalan selesai.');
        $this->assertSame('Draft NPD - PPTK', $draft->status);
    }

    public function test_sisa_sebelum_npd_memakai_npd_dan_spm_ls_yang_relevan_menurut_tanggal(): void
    {
        $anggaran = $this->buatAnggaran();
        $this->buatNpd($anggaran, 2_000_000, 'Draft NPD - PPTK', '2026-07-10');
        $this->buatNpd($anggaran, 3_000_000, 'Selesai', '2026-07-11');
        $npdSaatIni = $this->buatNpd($anggaran, 1_000_000, 'Draft NPD - BPP', '2026-07-20');
        $this->buatNpd($anggaran, 500_000, 'Draft NPD - PPTK', '2026-07-21');

        Spm::buatLs($this->dataSpmLs('002/SPM-LS/2026', '2026-07-15', 1_000_000, $anggaran->id));
        Spm::buatLs($this->dataSpmLs('003/SPM-LS/2026', '2026-07-21', 500_000, $anggaran->id));

        $this->assertSame(4_000_000.0, $anggaran->sisaAnggaranSebelum($npdSaatIni));
    }

    public function test_spm_ls_ditolak_jika_melebihi_sisa_tersedia_tetapi_up_gu_tidak_mengurangi_pagu(): void
    {
        $anggaran = $this->buatAnggaran(1_000_000);
        $this->buatNpd($anggaran, 800_000, 'Draft NPD - PPTK');
        Spm::buatUpGu($this->dataSpm('004/SPM-UP-GU/2026', '2026-07-20', 5_000_000) + ['master_anggaran_id' => $anggaran->id]);

        $this->assertSame(200_000.0, $anggaran->sisaTersedia());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('melebihi sisa tersedia');

        Spm::buatLs($this->dataSpmLs('004/SPM-LS/2026', '2026-07-20', 200_001, $anggaran->id));
    }

    public function test_identitas_dokumen_spm_unik_per_jenis_nomor_dan_tanggal(): void
    {
        $data = $this->dataSpm('005/SPM/2026', '2026-07-20', 100_000);
        Spm::buatUpGu($data);

        $this->expectException(QueryException::class);
        Spm::buatUpGu($data);
    }

    public function test_nomor_spm_yang_sama_masih_boleh_untuk_jenis_atau_tanggal_berbeda(): void
    {
        $anggaran = $this->buatAnggaran();
        $data = $this->dataSpm('006/SPM/2026', '2026-07-20', 100_000);

        Spm::buatUpGu($data);
        Spm::buatUpGu(array_merge($data, ['tanggal_dokumen' => '2026-07-21']));
        Spm::buatLs($this->dataSpmLs($data['nomor_dokumen'], $data['tanggal_dokumen'], $data['nominal'], $anggaran->id));

        $this->assertSame(3, Spm::count());
    }

    public function test_form_npd_barang_jasa_dan_perjalanan_memakai_sisa_tersedia(): void
    {
        $pptk = User::create([
            'username' => 'pptk-anggaran-form',
            'nama' => 'PPTK Anggaran Form',
            'role' => 'pptk',
            'password' => 'rahasia',
        ]);
        $anggaran = $this->buatAnggaran();
        $this->limpahkanSubKegiatan($pptk, $anggaran);
        $this->buatNpd($anggaran, 2_000_000, 'Draft NPD - PPTK');
        Spm::buatLs($this->dataSpmLs('007/SPM-LS/2026', '2026-07-20', 1_000_000, $anggaran->id));

        $this->actingAs($pptk)->get(route('npd.bj.create'))
            ->assertOk()
            ->assertSee('"sisa":7000000', false);

        $this->actingAs($pptk)->get(route('npd.pd.create'))
            ->assertOk()
            ->assertSee('"sisa":7000000', false);
    }
}
