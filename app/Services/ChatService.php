<?php

namespace App\Services;

use App\Models\ChatBaca;
use App\Models\ChatPesan;
use App\Models\ChatRuang;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Chat internal antar akun: percakapan pribadi dan ruang per role.
 *
 * ATURAN "MENGIKUTI" RUANG ROLE. Ruang role terbuka bagi semua akun, tetapi
 * tidak semuanya muncul di daftar (dan menambah angka belum-dibaca) tiap
 * orang - kalau begitu, satu pesan di Ruang BPP akan membunyikan semua akun.
 * Sebuah ruang role diikuti seseorang bila:
 *   - itu ruang rolenya sendiri, ATAU
 *   - ia pernah membukanya (punya baris chat_baca di sana).
 * Jadi PPTK yang menulis ke Ruang BPP ikut menerima balasannya, sedangkan
 * akun lain yang tidak pernah membukanya tidak terganggu.
 */
class ChatService
{
    /** Jumlah pesan terakhir yang dimuat saat sebuah ruang dibuka. */
    public const BATAS_MUAT = 60;

    /** Ruang pribadi dua akun - dibuat bila belum ada. */
    public function ruangPribadi(User $satu, User $dua): ChatRuang
    {
        if ((int) $satu->id === (int) $dua->id) {
            throw new InvalidArgumentException('Tidak bisa membuka percakapan dengan diri sendiri.');
        }

        // Id kecil selalu di user_a, supaya pasangan (A,B) dan (B,A) adalah
        // ruang yang sama dan dijaga satu indeks unik.
        [$a, $b] = (int) $satu->id < (int) $dua->id ? [$satu->id, $dua->id] : [$dua->id, $satu->id];

        return $this->cariAtauBuat(
            ['jenis' => ChatRuang::JENIS_PRIBADI, 'user_a_id' => $a, 'user_b_id' => $b],
        );
    }

    /** Ruang sebuah role - dibuat bila belum ada. */
    public function ruangRole(string $role): ChatRuang
    {
        if (! in_array($role, User::ROLE_OPTIONS, true)) {
            throw new InvalidArgumentException("Role \"{$role}\" tidak dikenal.");
        }

        return $this->cariAtauBuat(['jenis' => ChatRuang::JENIS_ROLE, 'role' => $role]);
    }

    /**
     * firstOrCreate yang tahan balapan: dua permintaan yang membuat ruang
     * yang sama berbarengan ditahan indeks unik, dan yang kalah cukup
     * membaca ulang ruang yang sudah dibuat pemenangnya.
     *
     * @param  array<string, mixed>  $kunci
     */
    private function cariAtauBuat(array $kunci): ChatRuang
    {
        $ada = ChatRuang::query()->where($kunci)->first();

        if ($ada) {
            return $ada;
        }

        try {
            return ChatRuang::create($kunci);
        } catch (QueryException $e) {
            return ChatRuang::query()->where($kunci)->firstOrFail();
        }
    }

    public function kirim(ChatRuang $ruang, User $pengirim, string $isi): ChatPesan
    {
        $isi = trim($isi);

        if ($isi === '') {
            throw new InvalidArgumentException('Pesan tidak boleh kosong.');
        }

        return DB::transaction(function () use ($ruang, $pengirim, $isi) {
            $pesan = ChatPesan::create([
                'chat_ruang_id' => $ruang->id,
                'pengirim_id' => $pengirim->id,
                'pengirim_nama' => (string) $pengirim->nama,
                'isi' => $isi,
            ]);

            $ruang->update([
                'pesan_terakhir_id' => $pesan->id,
                'pesan_terakhir_at' => $pesan->created_at,
            ]);

            // Menulis berarti sudah membaca sampai pesannya sendiri - dan,
            // untuk ruang role, sekaligus mulai mengikutinya.
            $this->tandaiDibaca($ruang, $pengirim, (int) $pesan->id);

            return $pesan;
        });
    }

    /** Catat bahwa $user sudah membaca ruang ini sampai pesan $sampaiId (bawaan: pesan terakhir). */
    public function tandaiDibaca(ChatRuang $ruang, User $user, ?int $sampaiId = null): void
    {
        $sampaiId ??= (int) $ruang->pesan_terakhir_id;

        $baca = ChatBaca::query()->firstOrNew(['chat_ruang_id' => $ruang->id, 'user_id' => $user->id]);

        // Tidak pernah mundur: permintaan lama yang datang terlambat tidak
        // boleh membuat pesan yang sudah dibaca jadi "belum dibaca" lagi.
        if (! $baca->exists || $sampaiId > (int) $baca->dibaca_sampai_id) {
            $baca->dibaca_sampai_id = max($sampaiId, (int) $baca->dibaca_sampai_id);
            $baca->save();
        }
    }

    /**
     * Pesan sebuah ruang, urut lama ke baru.
     *
     * $sesudahId = 0  -> BATAS_MUAT pesan terakhir (saat ruang dibuka);
     * $sesudahId > 0  -> hanya pesan yang lebih baru dari itu (polling).
     *
     * @return Collection<int, ChatPesan>
     */
    public function pesan(ChatRuang $ruang, int $sesudahId = 0): Collection
    {
        if ($sesudahId > 0) {
            return $ruang->pesan()->where('id', '>', $sesudahId)->orderBy('id')->limit(200)->get();
        }

        return $ruang->pesan()->orderByDesc('id')->limit(self::BATAS_MUAT)->get()->reverse()->values();
    }

    /**
     * Daftar ruang milik $user, yang paling baru ramai di atas.
     *
     * @return Collection<int, array{id: int, jenis: string, nama: string, keterangan: string, cuplikan: string, waktu: ?string, belum: int, url: string}>
     */
    public function daftar(User $user): Collection
    {
        // Ruang rolenya sendiri selalu ada di daftar, walau belum ada pesan.
        if (in_array($user->role, User::ROLE_OPTIONS, true)) {
            $this->ruangRole($user->role);
        }

        $diikuti = ChatBaca::query()->where('user_id', $user->id)->pluck('dibaca_sampai_id', 'chat_ruang_id');

        $ruang = ChatRuang::query()
            ->with(['userA:id,nama,role', 'userB:id,nama,role'])
            ->where(function ($q) use ($user, $diikuti) {
                $q->where(function ($pribadi) use ($user) {
                    // Percakapan pribadi baru tampil setelah ada pesannya:
                    // sekadar membuka kontak tidak memunculkan ruang kosong
                    // di daftar lawan bicara.
                    $pribadi->where('jenis', ChatRuang::JENIS_PRIBADI)
                        ->whereNotNull('pesan_terakhir_id')
                        ->where(fn ($p) => $p->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id));
                })->orWhere(function ($role) use ($user, $diikuti) {
                    $role->where('jenis', ChatRuang::JENIS_ROLE)
                        ->where(fn ($r) => $r->where('role', $user->role)->orWhereIn('id', $diikuti->keys()));
                });
            })
            ->get();

        $pesanTerakhir = ChatPesan::query()
            ->whereIn('id', $ruang->pluck('pesan_terakhir_id')->filter())
            ->get(['id', 'pengirim_id', 'pengirim_nama', 'isi'])
            ->keyBy('id');

        $labelRole = config('akses.role_label');

        return $ruang
            ->map(function (ChatRuang $satu) use ($user, $diikuti, $pesanTerakhir, $labelRole) {
                $dibacaSampai = (int) ($diikuti[$satu->id] ?? 0);
                $terakhir = $pesanTerakhir->get($satu->pesan_terakhir_id);

                // Hanya dihitung bila memang ada pesan sesudah yang terakhir dibaca.
                $belum = (int) $satu->pesan_terakhir_id > $dibacaSampai
                    ? ChatPesan::query()
                        ->where('chat_ruang_id', $satu->id)
                        ->where('id', '>', $dibacaSampai)
                        ->where(fn ($q) => $q->whereNull('pengirim_id')->orWhere('pengirim_id', '!=', $user->id))
                        ->count()
                    : 0;

                $cuplikan = '';
                if ($terakhir) {
                    $awalan = (int) $terakhir->pengirim_id === (int) $user->id
                        ? 'Anda: '
                        : ($satu->isRole() ? $terakhir->pengirim_nama.': ' : '');
                    $cuplikan = $awalan.str($terakhir->isi)->squish()->limit(70);
                }

                return [
                    'id' => (int) $satu->id,
                    'jenis' => (string) $satu->jenis,
                    'nama' => $satu->namaUntuk($user),
                    'keterangan' => $satu->isRole()
                        ? 'Ruang role'
                        : ($labelRole[$satu->lawan($user)?->role] ?? ''),
                    'cuplikan' => (string) $cuplikan,
                    'waktu' => $satu->pesan_terakhir_at?->toIso8601String(),
                    'belum' => $belum,
                    'url' => route('chat.index', ['ruang' => $satu->id]),
                ];
            })
            // Yang baru ramai di atas; ruang tanpa pesan di bawah, urut nama.
            ->sort(function (array $a, array $b) {
                if ($a['waktu'] !== $b['waktu']) {
                    return strcmp((string) $b['waktu'], (string) $a['waktu']);
                }

                return strnatcasecmp($a['nama'], $b['nama']);
            })
            ->values();
    }

    /** Jumlah seluruh pesan yang belum dibaca $user - angka di ikon chat. */
    public function belumDibaca(User $user): int
    {
        return (int) $this->daftar($user)->sum('belum');
    }

    /**
     * Bentuk satu pesan untuk dikirim ke peramban.
     *
     * @return array{id: int, isi: string, pengirim: string, milik_saya: bool, waktu: string}
     */
    public function sajikan(ChatPesan $pesan, User $pembaca): array
    {
        return [
            'id' => (int) $pesan->id,
            'isi' => (string) $pesan->isi,
            'pengirim' => (string) $pesan->pengirim_nama,
            'milik_saya' => (int) $pesan->pengirim_id === (int) $pembaca->id,
            'waktu' => $pesan->created_at->toIso8601String(),
        ];
    }
}
