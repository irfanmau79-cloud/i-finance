<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penugasan KPA + PPTK per sub kegiatan (BPP ikut otomatis dari KPA).
 * Satu baris per kode_sub_kegiatan — set ulang berarti update.
 */
#[Fillable([
    'kode_sub_kegiatan',
    'program_normal',
    'program_kunci',
    'sub_kegiatan_kunci',
    'kpa_id',
    'pptk_pegawai_id',
    'kpa_pptk_id',
    'aktif',
    'dinonaktifkan_at',
    'dibuat_oleh',
])]
class Pelimpahan extends Model
{
    protected $table = 'pelimpahan';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'dinonaktifkan_at' => 'datetime',
        ];
    }

    public function kpa(): BelongsTo
    {
        return $this->belongsTo(Kpa::class);
    }

    public function pptkPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pptk_pegawai_id');
    }

    public function kpaPptk(): BelongsTo
    {
        return $this->belongsTo(KpaPptk::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /** Set borongan: KPA + PPTK yang sama untuk banyak sub kegiatan sekaligus (upsert per kode). */
    public static function setBorongan(array $kodeSubKegiatanList, int $kpaId, int $pptkPegawaiId): void
    {
        $scopes = collect($kodeSubKegiatanList)->map(function ($subKegiatan) {
            $subKunci = MasterAnggaran::normalisasiKunci((string) $subKegiatan);
            $anggaran = MasterAnggaran::where('sub_kegiatan_kunci', $subKunci)->where('aktif', true)->firstOrFail();

            return ['program' => $anggaran->program_normal, 'sub_kegiatan' => $anggaran->sub_kegiatan_normal];
        })->all();

        $kpa = Kpa::findOrFail($kpaId);
        self::tetapkan($scopes, $kpaId, $kpa->bpp_pegawai_id, $pptkPegawaiId);
    }

    /**
     * Assign/reassign scope Sub Kegiatan. Baris lama selalu dinonaktifkan,
     * tidak ditimpa atau dihapus, sehingga rantai lama tetap dapat diaudit.
     */
    public static function tetapkan(
        array $scopes,
        int $kpaId,
        int $bppPegawaiId,
        int $pptkPegawaiId,
        ?int $userId = null,
    ): array {
        return DB::transaction(function () use ($scopes, $kpaId, $bppPegawaiId, $pptkPegawaiId, $userId) {
            $pejabatOpd = PejabatOpd::query()->with(['paPegawai', 'bendaharaPengeluaranPegawai'])
                ->where('aktif', true)->lockForUpdate()->first();
            if (! $pejabatOpd?->paPegawai?->aktif || ! $pejabatOpd->bendaharaPengeluaranPegawai?->aktif) {
                throw ValidationException::withMessages([
                    'kpa_id' => 'PA dan Bendahara Pengeluaran aktif tingkat OPD harus dilengkapi terlebih dahulu.',
                ]);
            }

            $kpa = Kpa::query()->with(['kpaPegawai', 'bppPegawai'])->lockForUpdate()->findOrFail($kpaId);
            if (! $kpa->aktif || ! $kpa->kpaPegawai?->aktif || ! $kpa->bppPegawai?->aktif) {
                throw ValidationException::withMessages(['kpa_id' => 'KPA atau BPP pada rantai ini tidak aktif.']);
            }
            if ($kpa->bpp_pegawai_id !== $bppPegawaiId) {
                throw ValidationException::withMessages(['bpp_pegawai_id' => 'BPP tidak berada pada KPA yang dipilih.']);
            }

            $kpaPptk = KpaPptk::query()->with('pptkPegawai')->lockForUpdate()
                ->where('kpa_id', $kpaId)
                ->where('pptk_pegawai_id', $pptkPegawaiId)
                ->where('aktif', true)
                ->first();
            if (! $kpaPptk?->pptkPegawai?->aktif) {
                throw ValidationException::withMessages(['pptk_pegawai_id' => 'PPTK tidak aktif atau tidak berada di bawah KPA yang dipilih.']);
            }
            if (in_array($pptkPegawaiId, [$kpa->kpa_pegawai_id, $kpa->bpp_pegawai_id], true)) {
                throw ValidationException::withMessages(['pptk_pegawai_id' => 'KPA atau BPP tidak dapat sekaligus menjadi PPTK pada rantai yang sama.']);
            }

            $hasil = ['baru' => 0, 'dipindahkan' => 0, 'tetap' => 0];
            foreach (collect($scopes)->unique(fn ($scope) => MasterAnggaran::normalisasiKunci($scope['program'] ?? '').'|'.MasterAnggaran::normalisasiKunci($scope['sub_kegiatan'] ?? '')) as $scope) {
                $programKunci = MasterAnggaran::normalisasiKunci($scope['program'] ?? '');
                $subKunci = MasterAnggaran::normalisasiKunci($scope['sub_kegiatan'] ?? '');
                $anggaran = MasterAnggaran::query()->where('aktif', true)
                    ->where('program_kunci', $programKunci)
                    ->where('sub_kegiatan_kunci', $subKunci)
                    ->lockForUpdate()->first();
                if (! $anggaran) {
                    throw ValidationException::withMessages([
                        'scope' => 'Sub Kegiatan tidak ditemukan pada program atau lingkup anggaran yang dipilih.',
                    ]);
                }

                $jumlahProgram = MasterAnggaran::query()->where('aktif', true)
                    ->where('sub_kegiatan_kunci', $subKunci)
                    ->distinct()->count('program_kunci');
                if ($jumlahProgram !== 1) {
                    throw ValidationException::withMessages([
                        'scope' => 'Sub Kegiatan memiliki lingkup program ambigu dan belum dapat ditugaskan.',
                    ]);
                }

                $aktif = self::query()->where('sub_kegiatan_kunci', $subKunci)
                    ->where('aktif', true)->lockForUpdate()->first();
                if ($aktif?->kpa_id === $kpaId && $aktif?->pptk_pegawai_id === $pptkPegawaiId) {
                    $hasil['tetap']++;

                    continue;
                }

                if ($aktif) {
                    $aktif->update(['aktif' => false, 'dinonaktifkan_at' => now()]);
                    $hasil['dipindahkan']++;
                } else {
                    $hasil['baru']++;
                }

                self::create([
                    'kode_sub_kegiatan' => $anggaran->sub_kegiatan_normal,
                    'program_normal' => $anggaran->program_normal,
                    'program_kunci' => $programKunci,
                    'sub_kegiatan_kunci' => $subKunci,
                    'kpa_id' => $kpaId,
                    'pptk_pegawai_id' => $pptkPegawaiId,
                    'kpa_pptk_id' => $kpaPptk->id,
                    'aktif' => true,
                    'dibuat_oleh' => $userId,
                ]);
            }

            return $hasil;
        });
    }
}
