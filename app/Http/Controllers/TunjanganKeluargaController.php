<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog as AuditHelper;
use App\Http\Requests\StorePengajuanTunjanganRequest;
use App\Models\AuditLog;
use App\Models\LampiranTunjangan;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanTunjangan;
use App\Services\TunjanganKeluargaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TunjanganKeluargaController extends Controller
{
    public function form(): View
    {
        return view('tunjangan-keluarga.form');
    }

    public function submit(StorePengajuanTunjanganRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('lampiran');
        $path = 'tunjangan-keluarga/'.Str::uuid().'.'.strtolower($file->guessExtension() ?: 'bin');
        $stored = null;
        try {
            $stored = $file->storeAs(dirname($path), basename($path), 'local');
            if (! $stored) {
                throw new \RuntimeException('Lampiran gagal disimpan pada penyimpanan private.');
            }
            DB::transaction(function () use ($request, $data, $file, $stored) {
                $pegawai = filled($data['nip'] ?? null) ? Pegawai::where('nip', trim($data['nip']))->first() : null;
                $pengajuan = PengajuanPerubahanTunjangan::create([
                    'pegawai_id' => $pegawai?->id, 'nama_pegawai' => trim($data['nama_pegawai']), 'nip' => $data['nip'] ?? null,
                    'payload' => ['pasangan' => $data['pasangan'] ?? [], 'anak' => array_values($data['anak'] ?? [])],
                    'keterangan' => $data['keterangan'], 'status' => 'diajukan', 'ip_address' => $request->ip(), 'diajukan_at' => now(),
                ]);
                $pengajuan->lampiran()->create(['disk' => 'local', 'path' => $stored, 'nama_asli' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'ukuran' => $file->getSize()]);
            });
        } catch (Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }
            throw $e;
        }
        AuditHelper::catatSebagai('layanan', 'layanan', 'Pengajuan Perubahan Tunjangan', 'Pengajuan atas nama '.$data['nama_pegawai']);

        return back()->with('success', 'Pengajuan perubahan berhasil dikirim dan lampiran disimpan secara private.');
    }

    public function monitoring(): View
    {
        return view('tunjangan-keluarga.monitoring', [
            'pengajuan' => PengajuanPerubahanTunjangan::with(['pegawai', 'lampiran', 'diprosesOleh'])->latest('diajukan_at')->paginate(30),
            'pegawai' => Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip']),
        ]);
    }

    public function dashboard(TunjanganKeluargaService $service): View
    {
        return view('tunjangan-keluarga.dashboard', ['dashboard' => $service->dashboard()]);
    }

    public function proses(Request $request, PengajuanPerubahanTunjangan $pengajuan, TunjanganKeluargaService $service): RedirectResponse
    {
        $data = $request->validate(['aksi' => ['required', 'in:setujui,tolak'], 'pegawai_id' => ['nullable', 'required_if:aksi,setujui', 'exists:pegawai,id'], 'catatan' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $pengajuan, $service, $data) {
            $pengajuan = PengajuanPerubahanTunjangan::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($pengajuan->status !== 'diajukan') {
                abort(409, 'Pengajuan sudah diproses.');
            }
            if ($data['aksi'] === 'setujui') {
                $pegawai = Pegawai::query()->lockForUpdate()->findOrFail($data['pegawai_id']);
                $service->simpanKeluarga($pegawai, $pengajuan->payload + ['catatan' => $data['catatan'] ?? $pengajuan->keterangan], $request->user()->id);
            }
            $pengajuan->update(['status' => $data['aksi'] === 'setujui' ? 'disetujui' : 'ditolak', 'pegawai_id' => $data['pegawai_id'] ?? $pengajuan->pegawai_id,
                'diproses_oleh' => $request->user()->id, 'diproses_at' => now(), 'catatan_proses' => $data['catatan'] ?? null]);
            AuditLog::create(['user_id' => $request->user()->id, 'username' => $request->user()->username, 'role' => $request->user()->role,
                'aktivitas' => $data['aksi'] === 'setujui' ? 'Setujui Perubahan Tunjangan' : 'Tolak Perubahan Tunjangan',
                'keterangan' => 'Pengajuan #'.$pengajuan->id.' atas nama '.$pengajuan->nama_pegawai, 'ip_address' => $request->ip()]);
        });

        return back()->with('success', 'Pengajuan berhasil diproses.');
    }

    public function download(LampiranTunjangan $lampiran): StreamedResponse
    {
        abort_unless(Storage::disk($lampiran->disk)->exists($lampiran->path), 404);

        return Storage::disk($lampiran->disk)->download($lampiran->path, $lampiran->nama_asli, ['Content-Type' => $lampiran->mime]);
    }
}
