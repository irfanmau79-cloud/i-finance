<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Pengumuman;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    /** Ambil teks pengumuman terkini. Publik — dipakai halaman Monitoring SP (termasuk oleh role layanan, tanpa login). */
    public function show()
    {
        return response()->json([
            'teks' => Pengumuman::query()->first()?->teks,
        ]);
    }

    /** Simpan teks pengumuman baru. Hanya bendahara/pptk/bpp/verifikator. Selalu satu baris aktif. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'teks' => ['nullable', 'string', 'max:1000'],
        ]);

        $pengumuman = Pengumuman::query()->first() ?? new Pengumuman();
        $pengumuman->teks = $validated['teks'] ?? null;
        $pengumuman->updated_by = $request->user()->id;
        $pengumuman->save();

        AuditLog::catat('Ubah Pemberitahuan', filled($pengumuman->teks) ? $pengumuman->teks : '(kosong)');

        return response()->json(['teks' => $pengumuman->teks]);
    }
}
