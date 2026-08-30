<?php

namespace App\Support;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * Baris Master Anggaran yang boleh dipakai membuat NPD.
 *
 * PPTK hanya boleh memakai Sub Kegiatan yang dilimpahkan kepadanya. Karena
 * dropdown Program, Kegiatan, Sub Kegiatan, Kode Rekening, dan Tagging pada
 * formulir NPD semuanya diturunkan dari SATU daftar ini, membatasi daftarnya
 * membuat Program yang tidak punya satu pun Sub Kegiatan limpahan ikut hilang
 * dengan sendirinya — tidak perlu penyaringan terpisah per tingkat.
 *
 * Role lain (superadmin) tidak dibatasi.
 *
 * Pembatasan yang sama ditegakkan lagi lewat aturan() saat formulir disimpan.
 * Menyembunyikan pilihan di layar saja tidak cukup: nilai master_anggaran_id
 * dikirim sebagai isian tersembunyi dan bisa diubah dari luar formulir.
 */
class AnggaranNpd
{
    /** Sub Kegiatan mana yang boleh dipakai ditentukan pelimpahan, bukan sekadar role. */
    public static function dibatasi(?User $user): bool
    {
        return $user?->role === User::ROLE_PPTK;
    }

    /**
     * Daftar untuk dropdown formulir NPD.
     *
     * $npd diisi saat menyunting: baris anggaran yang sudah terpakai di NPD
     * itu selalu ikut, walau pelimpahannya sudah berpindah ke PPTK lain
     * belakangan — kalau tidak, draft lama jadi tidak bisa disunting sama
     * sekali karena nilainya sendiri hilang dari pilihan.
     */
    public static function daftar(?User $user, ?Npd $npd = null): Collection
    {
        return MasterAnggaran::with('tagging')
            ->where('aktif', true)
            ->tap(fn ($query) => self::batasi($query, $user, $npd?->master_anggaran_id))
            ->orderBy('sub_kegiatan')
            ->get();
    }

    /**
     * Aturan validasi master_anggaran_id — pembatasan yang sama seperti
     * daftar(), ditegakkan di sisi server.
     *
     * @return array<int, mixed>
     */
    public static function aturan(?User $user, ?Npd $npd = null): array
    {
        return [
            'required',
            Rule::exists('master_anggaran', 'id')->where(function ($query) use ($user, $npd) {
                $query->where('aktif', true);
                self::batasi($query, $user, $npd?->master_anggaran_id);
            }),
        ];
    }

    /**
     * Terapkan pembatasan ke query mana pun atas tabel master_anggaran — baik
     * Eloquent maupun query builder mentah di dalam Rule::exists().
     *
     * Pencocokan memakai program_kunci + sub_kegiatan_kunci, sama seperti
     * PejabatResolver::untukSubKegiatan(), supaya "dilimpahkan" berarti hal
     * yang sama di layar, saat menyimpan, dan saat menentukan tanda tangan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MasterAnggaran>|\Illuminate\Database\Query\Builder  $query
     */
    private static function batasi($query, ?User $user, ?int $kecualikanId = null): void
    {
        if (! self::dibatasi($user)) {
            return;
        }

        $pptkPegawaiId = $user->pegawai_id;

        // Akun PPTK yang belum ditautkan ke Data Pegawai tidak mungkin punya
        // pelimpahan: pelimpahan menunjuk pegawai, bukan akun. Daftarnya
        // dikosongkan alih-alih diloloskan diam-diam.
        if ($pptkPegawaiId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($luar) use ($pptkPegawaiId, $kecualikanId) {
            $luar->whereExists(function ($sub) use ($pptkPegawaiId) {
                $sub->selectRaw('1')
                    ->from('pelimpahan')
                    ->whereColumn('pelimpahan.program_kunci', 'master_anggaran.program_kunci')
                    ->whereColumn('pelimpahan.sub_kegiatan_kunci', 'master_anggaran.sub_kegiatan_kunci')
                    ->where('pelimpahan.aktif', true)
                    ->where('pelimpahan.pptk_pegawai_id', $pptkPegawaiId);
            });

            if ($kecualikanId !== null) {
                $luar->orWhere('master_anggaran.id', $kecualikanId);
            }
        });
    }
}
