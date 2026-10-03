<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Akun Verifikator yang memegang satu Sub Kegiatan.
 *
 * NPD dari Sub Kegiatan ini hanya boleh diverifikasi (atau dikembalikan ke
 * BPP) oleh akun tersebut - atau superadmin. Sub Kegiatan yang BELUM punya
 * Verifikator tidak bisa diverifikasi sama sekali sampai ditetapkan di menu
 * Pelimpahan; lihat Npd::bolehAksiOleh().
 *
 * Dicocokkan lewat program_kunci + sub_kegiatan_kunci, sama seperti
 * Pelimpahan dan PejabatResolver, supaya "Sub Kegiatan yang sama" berarti
 * hal yang sama di seluruh aplikasi.
 */
#[Fillable([
    'program_kunci',
    'sub_kegiatan_kunci',
    'kode_sub_kegiatan',
    'verifikator_user_id',
    'aktif',
    'dinonaktifkan_at',
    'dibuat_oleh',
])]
class PelimpahanVerifikator extends Model
{
    protected $table = 'pelimpahan_verifikator';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'dinonaktifkan_at' => 'datetime',
        ];
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_user_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    public static function kunci(string $programKunci, string $subKegiatanKunci): string
    {
        return $programKunci.'|'.$subKegiatanKunci;
    }

    /**
     * Seluruh penugasan aktif sekaligus, dikunci "program|sub kegiatan".
     *
     * Dipakai daftar NPD supaya pengecekan per baris tidak menjalankan satu
     * query per NPD. Tabelnya kecil (satu baris per Sub Kegiatan).
     *
     * @return Collection<string, User>
     */
    public static function peta(): Collection
    {
        return self::aktif()->with('verifikator')->get()
            ->mapWithKeys(fn (self $baris) => [self::kunci($baris->program_kunci, $baris->sub_kegiatan_kunci) => $baris->verifikator]);
    }

    /**
     * Akun Verifikator untuk Sub Kegiatan mata anggaran ini, atau NULL bila
     * belum ditetapkan.
     *
     * @param  Collection<string, User>|null  $peta  hasil peta(), bila sudah dimuat
     */
    public static function untukMasterAnggaran(?MasterAnggaran $anggaran, ?Collection $peta = null): ?User
    {
        if ($anggaran === null) {
            return null;
        }

        $kunci = self::kunci((string) $anggaran->program_kunci, (string) $anggaran->sub_kegiatan_kunci);

        if ($peta !== null) {
            return $peta->get($kunci);
        }

        return self::aktif()->with('verifikator')
            ->where('program_kunci', $anggaran->program_kunci)
            ->where('sub_kegiatan_kunci', $anggaran->sub_kegiatan_kunci)
            ->first()?->verifikator;
    }

    /**
     * Tetapkan/ganti/kosongkan Verifikator untuk banyak Sub Kegiatan.
     *
     * $rows: list of ['program', 'sub_kegiatan', 'verifikator_user_id' (int|null)].
     * NULL berarti mengosongkan. Baris lama dinonaktifkan, tidak ditimpa.
     *
     * @return array{baru: int, dipindahkan: int, dikosongkan: int, tetap: int}
     */
    public static function tetapkan(array $rows, ?int $userId = null): array
    {
        return DB::transaction(function () use ($rows, $userId) {
            $hasil = ['baru' => 0, 'dipindahkan' => 0, 'dikosongkan' => 0, 'tetap' => 0];

            $idVerifikator = collect($rows)->pluck('verifikator_user_id')->filter()->unique()->values();
            $verifikatorSah = User::query()->whereIn('id', $idVerifikator)
                ->where('role', User::ROLE_VERIFIKATOR)->where('aktif', true)
                ->pluck('id')->all();

            foreach ($idVerifikator as $id) {
                if (! in_array((int) $id, array_map('intval', $verifikatorSah), true)) {
                    throw ValidationException::withMessages([
                        'rows' => 'Verifikator yang dipilih harus akun aktif ber-role Verifikator.',
                    ]);
                }
            }

            foreach ($rows as $row) {
                $programKunci = MasterAnggaran::normalisasiKunci((string) ($row['program'] ?? ''));
                $subKunci = MasterAnggaran::normalisasiKunci((string) ($row['sub_kegiatan'] ?? ''));
                $verifikatorId = $row['verifikator_user_id'] !== null ? (int) $row['verifikator_user_id'] : null;

                $anggaran = MasterAnggaran::query()->where('aktif', true)
                    ->where('program_kunci', $programKunci)
                    ->where('sub_kegiatan_kunci', $subKunci)
                    ->first();
                if (! $anggaran) {
                    throw ValidationException::withMessages([
                        'rows' => 'Sub Kegiatan tidak ditemukan pada program atau lingkup anggaran yang dipilih.',
                    ]);
                }

                $aktif = self::query()->where('sub_kegiatan_kunci', $subKunci)
                    ->where('aktif', true)->lockForUpdate()->first();

                if ((int) ($aktif?->verifikator_user_id) === (int) $verifikatorId) {
                    $hasil['tetap']++;

                    continue;
                }

                if ($aktif) {
                    $aktif->update(['aktif' => false, 'dinonaktifkan_at' => now()]);
                }

                if ($verifikatorId === null) {
                    $hasil['dikosongkan']++;

                    continue;
                }

                $hasil[$aktif ? 'dipindahkan' : 'baru']++;

                self::create([
                    'program_kunci' => $programKunci,
                    'sub_kegiatan_kunci' => $subKunci,
                    'kode_sub_kegiatan' => $anggaran->sub_kegiatan_normal,
                    'verifikator_user_id' => $verifikatorId,
                    'aktif' => true,
                    'dibuat_oleh' => $userId,
                ]);
            }

            return $hasil;
        });
    }
}
