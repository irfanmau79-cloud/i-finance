<?php

namespace App\Http\Controllers;

use App\Models\ChatPesan;
use App\Models\ChatRuang;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Chat internal antar akun (yang punya username & kata sandi). Pengguna
 * Layanan tidak punya akun, jadi rutenya berada di balik login.
 *
 * Tidak ada WebSocket: pesan baru diambil dengan polling ringan dari
 * peramban (lihat resources/views/chat/index.blade.php). Untuk jumlah akun
 * kantor ini itu cukup, dan tidak menambah layanan yang harus dijaga hidup.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $ruang = null;

        if ($request->filled('ruang')) {
            $ruang = ChatRuang::query()->with(['userA:id,nama,role', 'userB:id,nama,role'])->find($request->integer('ruang'));
            abort_unless($ruang && $ruang->bolehDibuka($user), 404);
        }

        $pesan = collect();

        if ($ruang) {
            $pesan = $this->chat->pesan($ruang)->map(fn (ChatPesan $p) => $this->chat->sajikan($p, $user));
            // Membuka ruang = membaca semua pesannya (dan mulai mengikutinya).
            $this->chat->tandaiDibaca($ruang, $user);
        }

        $labelRole = config('akses.role_label');

        // Kontak: semua akun aktif selain diri sendiri, dikelompokkan per role.
        $kontak = User::query()
            ->where('aktif', true)
            ->whereKeyNot($user->id)
            ->orderBy('nama')
            ->get(['id', 'nama', 'role'])
            ->groupBy(fn (User $u) => $labelRole[$u->role] ?? $u->role)
            ->sortKeys();

        return view('chat.index', [
            'daftar' => $this->chat->daftar($user),
            'ruang' => $ruang,
            'namaRuang' => $ruang?->namaUntuk($user),
            'keteranganRuang' => $ruang
                ? ($ruang->isRole()
                    ? 'Semua akun bisa membaca dan menulis di ruang ini.'
                    : ($labelRole[$ruang->lawan($user)?->role] ?? ''))
                : null,
            'pesan' => $pesan->values(),
            'kontak' => $kontak,
            'roleList' => collect(User::ROLE_OPTIONS)
                ->mapWithKeys(fn (string $role) => [$role => $labelRole[$role] ?? $role])
                ->sort(),
        ]);
    }

    /** Buka (atau buat) percakapan pribadi dengan satu akun. */
    public function bukaPribadi(Request $request, User $user)
    {
        abort_if((int) $user->id === (int) $request->user()->id, 404);
        abort_unless((bool) $user->aktif, 404);

        $ruang = $this->chat->ruangPribadi($request->user(), $user);

        return redirect()->route('chat.index', ['ruang' => $ruang->id]);
    }

    /** Buka (atau buat) ruang sebuah role. */
    public function bukaRole(Request $request)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLE_OPTIONS)]]);

        $ruang = $this->chat->ruangRole($data['role']);

        return redirect()->route('chat.index', ['ruang' => $ruang->id]);
    }

    /** Pesan baru sebuah ruang sejak ?sesudah=<id> - dipakai polling. */
    public function pesan(Request $request, ChatRuang $ruang): JsonResponse
    {
        $user = $request->user();
        abort_unless($ruang->bolehDibuka($user), 404);

        // sesudah=0 (atau tidak dikirim) berarti muat pesan-pesan terakhir.
        $baru = $this->chat->pesan($ruang, max(0, $request->integer('sesudah')));

        // Ruangnya sedang terbuka di layar, jadi yang baru datang langsung
        // dianggap sudah dibaca.
        if ($baru->isNotEmpty()) {
            $this->chat->tandaiDibaca($ruang, $user, (int) $baru->last()->id);
        }

        return response()->json([
            'pesan' => $baru->map(fn (ChatPesan $p) => $this->chat->sajikan($p, $user))->values(),
        ]);
    }

    public function kirim(Request $request, ChatRuang $ruang)
    {
        $user = $request->user();
        abort_unless($ruang->bolehDibuka($user), 404);

        // Diperiksa sendiri, bukan lewat $request->validate(): di aplikasi ini
        // validasi yang gagal selalu dijawab dengan pengalihan halaman, juga
        // untuk permintaan JSON - sedangkan skrip chat butuh jawaban 422
        // berisi pesannya supaya bisa ditampilkan di bawah kotak tulis.
        $isi = trim((string) $request->input('isi'));

        $galat = match (true) {
            $isi === '' => 'Pesan tidak boleh kosong.',
            mb_strlen($isi) > ChatPesan::MAKS_PANJANG => 'Pesan maksimal '.ChatPesan::MAKS_PANJANG.' karakter.',
            default => null,
        };

        if ($galat !== null) {
            return $request->expectsJson()
                ? response()->json(['message' => $galat, 'errors' => ['isi' => [$galat]]], 422)
                : back()->withErrors(['isi' => $galat]);
        }

        $pesan = $this->chat->kirim($ruang, $user, $isi);

        if ($request->expectsJson()) {
            return response()->json(['pesan' => $this->chat->sajikan($pesan, $user)]);
        }

        // Tanpa JavaScript formulirnya tetap bekerja: kembali ke ruangnya.
        return redirect()->route('chat.index', ['ruang' => $ruang->id]);
    }

    /** Daftar ruang + jumlah belum dibaca - dipakai polling daftar dan ikon di bilah atas. */
    public function ringkas(Request $request): JsonResponse
    {
        $daftar = $this->chat->daftar($request->user());

        return response()->json([
            'belum' => (int) $daftar->sum('belum'),
            'ruang' => $daftar,
        ]);
    }
}
