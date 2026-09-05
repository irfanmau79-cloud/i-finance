<?php

namespace Tests\Feature;

use App\Models\KebutuhanAnggaran;
use App\Models\MasterAnggaran;
use App\Models\Pkpt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman-halaman yang menyusun antarmukanya lewat JavaScript sebar (formulir
 * dinamis) tidak bisa dijaga oleh assertSee biasa: salah tanda kurung satu
 * saja membuat SELURUH formulir tidak pernah tergambar, sementara HTTP-nya
 * tetap 200 dan test lain tetap hijau.
 *
 * Test ini mengambil <script> inline dari halaman yang sudah dirender - jadi
 * arahan Blade seperti @json sudah menjadi nilai betulan - lalu memeriksanya
 * dengan `node --check`. Di-skip bila Node tidak terpasang di mesin ini.
 */
class SkripHalamanValidTest extends TestCase
{
    use RefreshDatabase;

    private string $node = '';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['node', 'node.exe'] as $kandidat) {
            $keluaran = @shell_exec($kandidat.' --version 2>&1');
            if (is_string($keluaran) && str_starts_with(trim($keluaran), 'v')) {
                $this->node = $kandidat;
                break;
            }
        }

        if ($this->node === '') {
            $this->markTestSkipped('Node tidak tersedia di mesin ini.');
        }
    }

    private function user(string $role, string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => 'Penguji '.$role,
            'role' => $role,
            'password' => 'test-only-password',
        ]);
    }

    /** Periksa seluruh <script> inline pada respons dengan node --check. */
    private function periksaSkrip(TestResponse $response, string $halaman): void
    {
        $response->assertOk();

        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $response->getContent(), $m);

        $jumlah = 0;

        foreach ($m[1] as $i => $skrip) {
            if (trim($skrip) === '') {
                continue;
            }

            $jumlah++;
            $berkas = tempnam(sys_get_temp_dir(), 'skrip_').'.js';
            file_put_contents($berkas, $skrip);

            $keluaran = shell_exec($this->node.' --check '.escapeshellarg($berkas).' 2>&1');
            @unlink($berkas);

            $this->assertSame(
                '',
                trim((string) $keluaran),
                "Skrip inline #{$i} pada {$halaman} tidak valid:\n".$keluaran
            );
        }

        $this->assertGreaterThan(0, $jumlah, "Tidak ada skrip inline yang diperiksa pada {$halaman}.");
    }

    public function test_formulir_kontribusi_diklat_skripnya_valid(): void
    {
        $pptk = $this->user('pptk', 'skrip-kd-pptk');
        MasterAnggaran::create([
            'program' => 'Program Uji Skrip',
            'kegiatan' => 'Kegiatan Uji Skrip',
            'sub_kegiatan' => '6.01.01.2.01 Sub Kegiatan Uji Skrip',
            'kode_rekening' => '5.1.02.03.01.0001',
            'pagu' => 100_000_000,
            'aktif' => true,
        ]);

        $this->periksaSkrip(
            $this->actingAs($pptk)->get(route('npd.kd.create')),
            'Buat NPD Kontribusi Diklat'
        );
    }

    public function test_formulir_estimasi_kebutuhan_skripnya_valid(): void
    {
        Pkpt::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nomor' => '1',
            'unit_kerja' => 'Inspektur Pembantu I',
            // Tanda kutip pada data sengaja dipakai: nilainya masuk ke JS lewat
            // @json, jadi kalau lolosnya salah, skripnya langsung rusak.
            'area' => 'Kesehatan "Prioritas" & Pendidikan',
            'jenis_kegiatan' => "Audit Kinerja 'Reguler'",
            'estimasi_anggaran' => 1_000_000,
            'terlaksana' => false,
        ]);

        $this->periksaSkrip(
            $this->actingAs($this->user('irban1', 'skrip-keb-irban'))->get(route('kebutuhan.create')),
            'Estimasi Kebutuhan Kegiatan Pengawasan'
        );
    }

    public function test_halaman_monitoring_pkpt_skripnya_valid(): void
    {
        Pkpt::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'nomor' => '1',
            'unit_kerja' => 'Inspektur Pembantu I',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'estimasi_anggaran' => 1_000_000,
            'terlaksana' => true,
        ]);

        $this->periksaSkrip(
            $this->actingAs($this->user('superadmin', 'skrip-pkpt-admin'))->get(route('pkpt.index')),
            'Monitoring PKPT'
        );
    }

    public function test_halaman_data_kebutuhan_skripnya_valid(): void
    {
        KebutuhanAnggaran::create([
            'tahun' => (int) config('anggaran.tahun_aktif'),
            'unit_kerja' => 'Inspektur Pembantu I',
            'dalam_pkpt' => true,
            'nomor_pkpt' => '1',
            'area' => 'Kesehatan',
            'jenis_kegiatan' => 'Audit Kinerja',
            'tanggal_mulai' => '2026-03-02',
            'tanggal_selesai' => '2026-03-05',
            'total_estimasi' => 1_000_000,
        ]);

        $this->periksaSkrip(
            $this->actingAs($this->user('perencanaan', 'skrip-keb-data'))->get(route('kebutuhan.index')),
            'Data Kebutuhan Anggaran Pengawasan'
        );
    }
}
