<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\UpdatePegawaiRequest;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PegawaiDataController extends Controller
{
    public function index(Request $request): View
    {
        $filters = array_merge(['cari' => ''], $request->validate([
            'cari' => ['nullable', 'string', 'max:255'],
        ]));

        $pegawai = Pegawai::query()
            ->when($filters['cari'] !== '', function ($q) use ($filters) {
                $needle = $filters['cari'];
                $q->where(function ($q2) use ($needle) {
                    $q2->where('nama', 'like', "%{$needle}%")
                        ->orWhere('nip', 'like', "%{$needle}%")
                        ->orWhere('jabatan', 'like', "%{$needle}%")
                        ->orWhere('bidang', 'like', "%{$needle}%");
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('data-pegawai.index', [
            'pegawai' => $pegawai,
            'filters' => $filters,
            'bolehEdit' => $request->user()?->isSuperadmin() ?? false,
        ]);
    }

    public function update(UpdatePegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $data = $request->validated();
        $pegawai->update($data);

        AuditLog::catat('Edit Data Pegawai', "Pegawai: {$pegawai->nama} (NIP {$pegawai->nip})");

        return back()->with('success', "Data pegawai {$pegawai->nama} berhasil diperbarui.");
    }
}
