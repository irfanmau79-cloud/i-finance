<?php

namespace App\Http\Controllers;

use App\Models\AuditLog as AuditLogModel;
use App\Models\Npd;
use App\Services\SpjDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpjDashboardController extends Controller
{
    public function index(Request $request, SpjDashboardService $service): View
    {
        $filters = array_merge(['bidang' => '', 'status' => '', 'cari' => ''], $request->validate([
            'bidang' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['belum', 'terverifikasi'])],
            'cari' => ['nullable', 'string', 'max:255'],
        ]));

        return view('dashboard-spj.index', [
            'filters' => $filters,
            'dashboard' => $service->ringkasan($filters, (int) config('anggaran.tahun_aktif')),
            'bolehVerifikasi' => in_array($request->user()?->role, ['superadmin', 'verifikator'], true),
        ]);
    }

    public function verify(Request $request, Npd $npd): RedirectResponse
    {
        $request->validate(['aksi' => ['required', Rule::in(['verifikasi', 'batalkan'])]]);
        $aksi = $request->string('aksi')->toString();

        DB::transaction(function () use ($request, $npd, $aksi) {
            $npd = Npd::query()->with(['masterAnggaran', 'suratPerintah', 'dibuatOleh.pegawai'])->lockForUpdate()->findOrFail($npd->id);
            if ($npd->status !== 'Selesai' || ! SpjDashboardService::adalahPengawasan($npd)) {
                throw ValidationException::withMessages(['aksi' => 'Verifikasi SPJ hanya tersedia untuk NPD Pengawasan berstatus Selesai.']);
            }

            if ($aksi === 'verifikasi' && $npd->spj_verified_at) {
                throw ValidationException::withMessages(['aksi' => 'SPJ sudah terverifikasi.']);
            }
            if ($aksi === 'batalkan' && ! $npd->spj_verified_at) {
                throw ValidationException::withMessages(['aksi' => 'SPJ belum terverifikasi.']);
            }

            $user = $request->user();
            $npd->forceFill([
                'spj_verified_at' => $aksi === 'verifikasi' ? now() : null,
                'spj_verified_by' => $aksi === 'verifikasi' ? $user->id : null,
            ])->save();
            $npd->catatHistoriStatus(
                $user,
                $aksi === 'verifikasi' ? 'verifikasi_spj' : 'batalkan_verifikasi_spj',
                $npd->status,
                $npd->status,
                $aksi === 'verifikasi' ? 'SPJ diverifikasi selesai.' : 'Verifikasi SPJ dibatalkan.',
            );
            AuditLogModel::create([
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'aktivitas' => $aksi === 'verifikasi' ? 'Verifikasi SPJ' : 'Batalkan Verifikasi SPJ',
                'keterangan' => ($npd->nomor_lengkap ?: 'NPD #'.$npd->id).' | Status NPD tetap '.$npd->status,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', $aksi === 'verifikasi' ? 'SPJ berhasil diverifikasi.' : 'Verifikasi SPJ berhasil dibatalkan.');
    }
}
