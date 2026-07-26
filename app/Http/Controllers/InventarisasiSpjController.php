<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArsipSpjRequest;
use App\Http\Requests\UpdateSpjDetailRequest;
use App\Models\ArsipSpj;
use App\Models\AuditLog;
use App\Models\Npd;
use App\Models\SpjDetail;
use App\Services\InventarisasiSpjService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventarisasiSpjController extends Controller
{
    public function index(Request $request, InventarisasiSpjService $service): View
    {
        $filters = array_merge(['bulan' => '', 'sub_kegiatan' => '', 'kode_rekening' => '', 'tagging' => '', 'cari' => ''], $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'], 'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'kode_rekening' => ['nullable', 'string', 'max:50'], 'tagging' => ['nullable', 'string', 'max:255'], 'cari' => ['nullable', 'string', 'max:255'],
        ]));

        return view('inventarisasi-spj.index', [
            'filters' => $filters,
            'inventaris' => $service->data($filters),
            'bolehEditDetail' => in_array($request->user()?->role, ['superadmin', 'bendahara_pengeluaran', 'bpp'], true),
        ]);
    }

    public function store(StoreArsipSpjRequest $request, Npd $npd): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $npd, $data) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->status === 'Selesai', 422, 'Lokasi arsip hanya dapat ditetapkan untuk NPD berstatus Selesai.');
            $lama = ArsipSpj::query()->where('npd_id', $npd->id)->where('jenis_dokumen', $data['jenis_dokumen'])->where('aktif', true)->lockForUpdate()->get();
            $lama->each->update(['aktif' => false]);
            $baru = $npd->arsipSpj()->create($data + ['ditetapkan_oleh' => $request->user()->id, 'ditetapkan_at' => now(), 'aktif' => true]);
            AuditLog::create([
                'user_id' => $request->user()->id, 'username' => $request->user()->username, 'role' => $request->user()->role,
                'aktivitas' => $lama->isEmpty() ? 'Tetapkan Lokasi SPJ' : 'Pindahkan Lokasi SPJ',
                'keterangan' => ($npd->nomor_lengkap ?: 'NPD #'.$npd->id).' | '.$baru->jenis_dokumen.' | '.($lama->first()?->lokasi ?? '(belum ada)').' -> '.$baru->lokasi,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Lokasi arsip SPJ berhasil disimpan; histori sebelumnya tetap dipertahankan.');
    }

    public function updateDetail(UpdateSpjDetailRequest $request, Npd $npd): RedirectResponse
    {
        abort_unless($npd->status === 'Selesai', 422, 'Tabel Detail SPJ hanya dapat diedit untuk NPD berstatus Selesai.');

        $data = $request->validated();

        DB::transaction(function () use ($request, $npd, $data) {
            $detail = SpjDetail::query()->lockForUpdate()->firstOrNew(['npd_id' => $npd->id]);
            $detail->fill($data + ['diedit_oleh' => $request->user()->id, 'diedit_at' => now()]);
            $detail->npd_id = $npd->id;
            $detail->save();

            AuditLog::create([
                'user_id' => $request->user()->id, 'username' => $request->user()->username, 'role' => $request->user()->role,
                'aktivitas' => 'Edit Detail SPJ',
                'keterangan' => ($npd->nomor_lengkap ?: 'NPD #'.$npd->id).' | Status: '.$data['status'],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Detail SPJ berhasil diperbarui.');
    }

    public function restoreDetail(Request $request, Npd $npd): RedirectResponse
    {
        DB::transaction(function () use ($request, $npd) {
            $detail = SpjDetail::query()->lockForUpdate()->where('npd_id', $npd->id)->first();

            if ($detail) {
                $detail->restoreKeDefault();
            }

            AuditLog::create([
                'user_id' => $request->user()->id, 'username' => $request->user()->username, 'role' => $request->user()->role,
                'aktivitas' => 'Restore Detail SPJ',
                'keterangan' => $npd->nomor_lengkap ?: 'NPD #'.$npd->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Detail SPJ dikembalikan ke posisi default (Status dan Catatan tidak ikut direset).');
    }
}
