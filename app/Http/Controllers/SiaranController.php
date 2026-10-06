<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Siaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Siaran (broadcast) di lonceng notifikasi. Mengirim dan menghapus hanya
 * superadmin (dijaga di rute); menandai dibaca boleh semua akun yang login.
 */
class SiaranController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pesan' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], ['pesan' => 'Pesan broadcast']);

        $siaran = Siaran::create(['pesan' => trim($data['pesan']), 'dibuat_oleh' => $request->user()->id]);

        // Pengirimnya sendiri tidak perlu diberi tahu pesannya sendiri.
        $siaran->pembaca()->attach($request->user()->id, ['dibaca_at' => now()]);

        AuditLog::catat('Kirim Broadcast', $siaran->pesan);

        return back()->with('success', 'Broadcast terkirim ke semua role.');
    }

    /** Tandai satu siaran sudah dibaca oleh akun ini. Aman dipanggil berulang. */
    public function baca(Request $request, Siaran $siaran)
    {
        DB::table('siaran_dibaca')->insertOrIgnore([
            'siaran_id' => $siaran->id,
            'user_id' => $request->user()->id,
            'dibaca_at' => now(),
        ]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroy(Siaran $siaran): RedirectResponse
    {
        AuditLog::catat('Hapus Broadcast', $siaran->pesan);
        $siaran->delete();

        return back()->with('success', 'Broadcast dihapus.');
    }
}
