<?php

namespace Tests\Feature;

use App\Helpers\PejabatResolver;
use App\Models\AuditLog;
use App\Models\Kpa;
use App\Models\KpaPptk;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use App\Models\PptkRoster;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubKegiatanDistributionTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function pegawai(string $nama, bool $aktif = true): Pegawai
    {
        $this->sequence++;

        return Pegawai::create([
            'nama' => $nama,
            'nip' => sprintf('19800101201001%04d', $this->sequence),
            'jabatan' => 'Pejabat Pengujian',
            'bidang' => 'Pengujian',
            'pangkat' => 'Pembina',
            'aktif' => $aktif,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'username' => $role.'-'.$this->sequence,
            'nama' => ucfirst($role),
            'role' => $role,
            'password' => 'development-test-only',
        ]);
    }

    private function anggaran(string $program, string $sub, ?string $kode = null): MasterAnggaran
    {
        $this->sequence++;

        return MasterAnggaran::create([
            'program' => $program,
            'kegiatan' => 'Kegiatan '.$program,
            'sub_kegiatan' => $sub,
            'kode_rekening' => $kode ?? sprintf('5.1.%02d', $this->sequence),
            'uraian_rekening' => 'Belanja Pengujian',
            'pagu' => 1_000_000,
            'aktif' => true,
        ]);
    }

    private function opdAktif(): void
    {
        PejabatOpd::simpan([
            'pa_pegawai_id' => $this->pegawai('PA Aktif')->id,
            'bendahara_pengeluaran_pegawai_id' => $this->pegawai('Bendahara Pengeluaran Aktif')->id,
        ]);
    }

    /** @return array{0: Kpa, 1: Pegawai, 2: KpaPptk} */
    private function rantai(string $label): array
    {
        $kpa = Kpa::create([
            'kpa_pegawai_id' => $this->pegawai('KPA '.$label)->id,
            'bpp_pegawai_id' => $this->pegawai('BPP '.$label)->id,
            'aktif' => true,
        ]);
        $pptk = $this->pegawai('PPTK '.$label);
        $mapping = KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptk->id, 'aktif' => true]);

        return [$kpa, $pptk, $mapping];
    }

    private function scope(MasterAnggaran $anggaran): string
    {
        return base64_encode(json_encode([
            'program' => $anggaran->program,
            'sub_kegiatan' => $anggaran->sub_kegiatan,
        ], JSON_THROW_ON_ERROR));
    }

    private function rowsPayload(MasterAnggaran $anggaran, int $kpaId, int $pptkPegawaiId): array
    {
        return ['rows' => [[
            'scope' => $this->scope($anggaran),
            'kpa_id' => $kpaId,
            'pptk_pegawai_id' => $pptkPegawaiId,
        ]]];
    }

    public function test_satu_kpa_dapat_memiliki_banyak_pptk_dengan_beberapa_sub_kegiatan(): void
    {
        $this->opdAktif();
        [$kpa, $pptkSatu] = $this->rantai('Utama');
        $pptkDua = $this->pegawai('PPTK Kedua');
        KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptkDua->id, 'aktif' => true]);
        $a = $this->anggaran('Program A', 'Sub A');
        $b = $this->anggaran('Program A', 'Sub B');
        $c = $this->anggaran('Program A', 'Sub C');

        Pelimpahan::tetapkan([['program' => $a->program, 'sub_kegiatan' => $a->sub_kegiatan], ['program' => $b->program, 'sub_kegiatan' => $b->sub_kegiatan]], $kpa->id, $kpa->bpp_pegawai_id, $pptkSatu->id);
        Pelimpahan::tetapkan([['program' => $c->program, 'sub_kegiatan' => $c->sub_kegiatan]], $kpa->id, $kpa->bpp_pegawai_id, $pptkDua->id);

        $this->assertSame(3, Pelimpahan::aktif()->count());
        $this->assertSame(2, Pelimpahan::aktif()->where('pptk_pegawai_id', $pptkSatu->id)->count());
        $this->assertSame('PPTK Kedua', PejabatResolver::untukSubKegiatan($c->program, $c->sub_kegiatan)['pptk']->nama);
    }

    public function test_pptk_yang_sudah_aktif_di_kpa_lain_ditolak(): void
    {
        $this->opdAktif();
        $admin = $this->user('superadmin');
        [$kpaSatu] = $this->rantai('Satu');
        [, $pptkDua] = $this->rantai('Dua');
        $anggaran = $this->anggaran('Program Lingkup', 'Sub Lingkup');

        $this->actingAs($admin)->post(
            route('pelimpahan.sub-kegiatan.set'),
            $this->rowsPayload($anggaran, $kpaSatu->id, $pptkDua->id)
        )->assertSessionHasErrors('pptk_pegawai_id');

        $this->assertSame(0, Pelimpahan::count());
    }

    public function test_normalisasi_case_dan_whitespace_mencegah_duplikat_aktif(): void
    {
        $this->opdAktif();
        [$kpa, $pptk] = $this->rantai('Normalisasi');
        $anggaran = $this->anggaran('Program Normal', "Sub   Kegiatan\nNormal");

        Pelimpahan::tetapkan([
            ['program' => ' program normal ', 'sub_kegiatan' => ' sub kegiatan normal '],
            ['program' => 'PROGRAM NORMAL', 'sub_kegiatan' => "SUB\tKEGIATAN NORMAL"],
        ], $kpa->id, $kpa->bpp_pegawai_id, $pptk->id);

        $this->assertSame(1, Pelimpahan::aktif()->count());
        $this->assertSame(MasterAnggaran::normalisasiKunci($anggaran->sub_kegiatan), Pelimpahan::firstOrFail()->sub_kegiatan_kunci);
    }

    public function test_database_menolak_dua_baris_aktif_untuk_scope_yang_sama(): void
    {
        $this->opdAktif();
        [$kpa, $pptk, $mapping] = $this->rantai('Constraint');
        $anggaran = $this->anggaran('Program Constraint', 'Sub Constraint');
        $attributes = [
            'kode_sub_kegiatan' => $anggaran->sub_kegiatan_normal,
            'program_normal' => $anggaran->program_normal,
            'program_kunci' => $anggaran->program_kunci,
            'sub_kegiatan_kunci' => $anggaran->sub_kegiatan_kunci,
            'kpa_id' => $kpa->id,
            'pptk_pegawai_id' => $pptk->id,
            'kpa_pptk_id' => $mapping->id,
            'aktif' => true,
        ];
        Pelimpahan::create($attributes);

        $this->expectException(QueryException::class);
        Pelimpahan::create($attributes);
    }

    public function test_pegawai_nonaktif_tidak_dapat_didaftarkan_sebagai_pptk(): void
    {
        $admin = $this->user('superadmin');
        $pptkNonaktif = $this->pegawai('PPTK Nonaktif', false);

        $this->actingAs($admin)->post(route('pelimpahan.pptk.store'), [
            'pegawai_id' => $pptkNonaktif->id,
        ])->assertSessionHasErrors('pegawai_id');

        $this->assertFalse(PptkRoster::where('pegawai_id', $pptkNonaktif->id)->exists());
    }

    public function test_sub_kegiatan_dengan_program_ambigu_ditolak(): void
    {
        $this->opdAktif();
        [$kpa, $pptk] = $this->rantai('Ambigu');
        $this->anggaran('Program Pertama', 'Sub Sama', '5.1.91');
        $this->anggaran('Program Kedua', ' sub  sama ', '5.1.92');

        $this->expectException(ValidationException::class);
        Pelimpahan::tetapkan([
            ['program' => 'Program Pertama', 'sub_kegiatan' => 'SUB SAMA'],
        ], $kpa->id, $kpa->bpp_pegawai_id, $pptk->id);
    }

    public function test_reassignment_aman_mempertahankan_histori_dan_mencatat_audit(): void
    {
        $this->opdAktif();
        $admin = $this->user('superadmin');
        [$kpaSatu, $pptkSatu] = $this->rantai('Lama');
        [$kpaDua, $pptkDua] = $this->rantai('Baru');
        $anggaran = $this->anggaran('Program Reassign', 'Sub Reassign');
        Pelimpahan::tetapkan([['program' => $anggaran->program, 'sub_kegiatan' => $anggaran->sub_kegiatan]], $kpaSatu->id, $kpaSatu->bpp_pegawai_id, $pptkSatu->id);

        $this->actingAs($admin)->post(
            route('pelimpahan.sub-kegiatan.set'),
            $this->rowsPayload($anggaran, $kpaDua->id, $pptkDua->id)
        )->assertSessionHasNoErrors();

        $this->assertSame(2, Pelimpahan::count());
        $this->assertSame(1, Pelimpahan::aktif()->count());
        $this->assertNotNull(Pelimpahan::where('aktif', false)->firstOrFail()->dinonaktifkan_at);
        $this->assertSame($kpaDua->id, Pelimpahan::aktif()->firstOrFail()->kpa_id);
        $this->assertTrue(AuditLog::where('aktivitas', 'Set Pelimpahan Sub Kegiatan')->exists());
    }

    public function test_ringkasan_warning_pagination_dan_filter_dikerjakan_backend(): void
    {
        $this->opdAktif();
        $admin = $this->user('superadmin');
        [$kpa, $pptk] = $this->rantai('Filter');
        $assigned = $this->anggaran('Program Filter', 'Sub 01');
        for ($i = 2; $i <= 27; $i++) {
            $this->anggaran('Program Filter', sprintf('Sub %02d', $i));
        }
        Pelimpahan::tetapkan([['program' => $assigned->program, 'sub_kegiatan' => $assigned->sub_kegiatan]], $kpa->id, $kpa->bpp_pegawai_id, $pptk->id);

        $this->actingAs($admin)->get(route('pelimpahan.index'))
            ->assertOk()->assertSee('26 Sub Kegiatan belum memiliki PPTK')->assertSee('BELUM DITUGASKAN')
            ->assertViewHas('subKegiatanList', fn ($paginator) => $paginator->perPage() === 25 && $paginator->total() === 27);
        $this->actingAs($admin)->get(route('pelimpahan.index', ['status' => 'assigned', 'kpa_id' => $kpa->id]))
            ->assertOk()->assertViewHas('subKegiatanList', fn ($paginator) => $paginator->total() === 1);
        $this->actingAs($admin)->get(route('pelimpahan.index', ['status' => 'unassigned', 'program' => 'PROGRAM FILTER', 'cari' => 'sub 2']))
            ->assertOk()->assertViewHas('subKegiatanList', fn ($paginator) => $paginator->total() === 8);
    }

    public function test_resolver_menandai_sumber_pelimpahan_dan_fallback_secara_eksplisit(): void
    {
        $this->opdAktif();
        [$kpa, $pptk] = $this->rantai('Resolver');
        $assigned = $this->anggaran('Program Resolver', 'Sub Assigned');
        $unassigned = $this->anggaran('Program Resolver', 'Sub Unassigned');
        Pelimpahan::tetapkan([['program' => $assigned->program, 'sub_kegiatan' => $assigned->sub_kegiatan]], $kpa->id, $kpa->bpp_pegawai_id, $pptk->id);

        $resolved = PejabatResolver::untukSubKegiatan($assigned->program, $assigned->sub_kegiatan);
        $fallback = PejabatResolver::untukSubKegiatan($unassigned->program, $unassigned->sub_kegiatan);

        $this->assertFalse($resolved['fallback_digunakan']);
        $this->assertSame('pelimpahan', $resolved['sumber']);
        $this->assertTrue($fallback['fallback_digunakan']);
        $this->assertSame('data_tambahan', $fallback['sumber']);
        $this->assertNotNull($fallback['peringatan']);
    }

    public function test_hanya_superadmin_dapat_mengubah_dan_bendahara_pengeluaran_hanya_memantau_npd(): void
    {
        $admin = $this->user('superadmin');
        $bendahara = $this->user('bendahara_pengeluaran');
        $pptkUser = $this->user('pptk');
        $pegawaiPptk = $this->pegawai('PPTK Otorisasi');

        $this->actingAs($admin)->post(route('pelimpahan.pptk.store'), ['pegawai_id' => $pegawaiPptk->id])->assertSessionHasNoErrors();
        $this->actingAs($bendahara)->get(route('npd.index'))->assertOk();
        $this->actingAs($bendahara)->get(route('pelimpahan.index'))->assertForbidden();
        $this->actingAs($bendahara)->post(route('pelimpahan.pptk.store'), ['pegawai_id' => $pegawaiPptk->id])->assertForbidden();
        $this->actingAs($pptkUser)->post(route('pelimpahan.pptk.store'), ['pegawai_id' => $pegawaiPptk->id])->assertForbidden();
    }
}
