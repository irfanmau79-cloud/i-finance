<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\PelimpahanVerifikator;
use App\Models\Siaran;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Isi lonceng notifikasi di bilah atas.
 *
 * Dua macam isi:
 *
 *  - PEKERJAAN, khusus tiga role alur NPD: berapa NPD yang sedang menunggu di
 *    meja role itu. Dihitung langsung dari status NPD tiap halaman dibuka -
 *    tidak disimpan - jadi angkanya tidak pernah basi dan tidak punya
 *    keadaan "sudah dibaca": ia hilang sendiri begitu mejanya kosong.
 *
 *  - SIARAN, pesan broadcast superadmin untuk semua role. Belum dibaca
 *    sampai pesannya diklik (lihat SiaranController::baca).
 */
class LoncengService
{
    /** Siaran terbaru yang ditampilkan; yang lebih lama tidak dimuat. */
    public const BATAS_SIARAN = 15;

    /**
     * @return array{
     *     pekerjaan: array{jumlah: int, teks: string, url: string}|null,
     *     siaran: array<int, array{id: int, pesan: string, waktu: string, pengirim: string, dibaca: bool}>,
     *     belum: int,
     *     bisa_tandai: bool,
     * }
     */
    public function untuk(?User $user): array
    {
        // Lonceng digambar di rangka SETIAP halaman. Kalau tabel siaran belum
        // ada - kode sudah ditarik tetapi `php artisan migrate` belum
        // dijalankan - galatnya akan menjatuhkan seluruh aplikasi, bukan
        // hanya loncengnya. Dalam keadaan itu lonceng tampil kosong saja.
        try {
            return $this->isi($user);
        } catch (QueryException) {
            return ['pekerjaan' => null, 'siaran' => [], 'belum' => 0, 'bisa_tandai' => false];
        }
    }

    private function isi(?User $user): array
    {
        $pekerjaan = $user ? $this->pekerjaan($user) : null;

        $dibaca = $user
            ? DB::table('siaran_dibaca')->where('user_id', $user->id)->pluck('siaran_id')->flip()
            : collect();

        $siaran = Siaran::query()->with('dibuatOleh:id,nama')->latest('id')->limit(self::BATAS_SIARAN)->get()
            ->map(fn (Siaran $s) => [
                'id' => $s->id,
                'pesan' => $s->pesan,
                'waktu' => $s->created_at->format('d-m-Y H:i'),
                'pengirim' => $s->dibuatOleh?->nama ?? 'Superadmin',
                // Pengguna Layanan tidak punya akun, jadi tidak ada tempat
                // mencatat bacaannya: siaran tampil tanpa penanda belum-dibaca.
                'dibaca' => $user === null || $dibaca->has($s->id),
            ])->all();

        return [
            'pekerjaan' => $pekerjaan,
            'siaran' => $siaran,
            'belum' => ($pekerjaan ? 1 : 0) + count(array_filter($siaran, fn (array $s) => ! $s['dibaca'])),
            'bisa_tandai' => $user !== null,
        ];
    }

    /**
     * NPD yang menunggu di meja role ini, atau null bila tidak ada / role
     * ini tidak punya meja di alur NPD.
     *
     * @return array{jumlah: int, teks: string, url: string}|null
     */
    private function pekerjaan(User $user): ?array
    {
        // NPD historis adalah arsip final dan tidak mengikuti alur.
        $npd = fn (string $status) => Npd::query()->where('status', $status)->where('sumber_data', '!=', 'import_historis');

        [$jumlah, $teks, $rute] = match ($user->role) {
            User::ROLE_BPP => [
                $npd('Draft NPD - BPP')->count(),
                'Terdapat :n Nota Pencairan Dana untuk diperiksa dan disetujui',
                'npd.persetujuan',
            ],
            User::ROLE_PPTK => [
                $npd('Draft NPD - PPTK')->count(),
                'Terdapat :n Draft Nota Pencairan Dana, mohon dipantau/ditindaklanjuti',
                'npd.index',
            ],
            User::ROLE_VERIFIKATOR => [
                $this->antreanVerifikator($user),
                'Terdapat :n Nota Pencairan Dana untuk diverifikasi.',
                'npd.verifikasi',
            ],
            default => [0, '', null],
        };

        if ($jumlah === 0 || $rute === null) {
            return null;
        }

        return ['jumlah' => $jumlah, 'teks' => str_replace(':n', (string) $jumlah, $teks), 'url' => route($rute)];
    }

    /**
     * Hanya NPD Sub Kegiatan yang ditugaskan ke akun Verifikator ini - sama
     * dengan isi antrean Verifikasi NPD-nya, supaya angka di lonceng cocok
     * dengan jumlah baris yang ia temui saat membukanya.
     */
    private function antreanVerifikator(User $user): int
    {
        $peta = PelimpahanVerifikator::peta();

        return Npd::query()
            ->with('masterAnggaran:id,program_kunci,sub_kegiatan_kunci')
            ->where('status', 'Verifikasi - Verifikator')
            ->where('sumber_data', '!=', 'import_historis')
            ->get(['id', 'master_anggaran_id'])
            ->filter(fn (Npd $npd) => (int) $npd->verifikatorDitugaskan($peta)?->id === (int) $user->id)
            ->count();
    }
}
