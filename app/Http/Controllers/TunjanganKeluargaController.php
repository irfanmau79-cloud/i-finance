<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog as AuditHelper;
use App\Helpers\GuestSession;
use App\Http\Requests\StorePengajuanTunjanganRequest;
use App\Models\AuditLog;
use App\Models\LampiranTunjangan;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanTunjangan;
use App\Models\TunjanganKeluarga;
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
        GuestSession::login();

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

    /**
     * Data Tunjangan Keluarga: sumber data mentah yang dibaca dashboard.
     * Diisi manual oleh superadmin (belum ada alur pengajuan/approval di
     * sini — beda dari form self-service "Perubahan Data").
     */
    public function data(Request $request, TunjanganKeluargaService $service): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $pegawaiList = Pegawai::query()
            ->with('tunjanganKeluarga.anggota')
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq->where('nama', 'like', "%{$cari}%")->orWhere('nip', 'like', "%{$cari}%")))
            ->orderBy('nama')
            ->paginate(30)->withQueryString();

        return view('tunjangan-keluarga.data', ['pegawaiList' => $pegawaiList, 'cari' => $cari, 'service' => $service]);
    }

    public function createPegawai(): View
    {
        return view('tunjangan-keluarga.pegawai-create');
    }

    public function storePegawai(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:30', 'unique:pegawai,nip'],
            'jabatan' => ['required', 'string', 'max:255'],
            'golongan' => ['nullable', 'string', 'max:20'],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'bidang' => ['required', 'string', 'max:100'],
            'rekening' => ['nullable', 'string', 'max:100'],
        ]);
        $data['aktif'] = $request->boolean('aktif', true);

        $pegawai = Pegawai::create($data);

        AuditHelper::catat('Tambah Pegawai', "Pegawai: {$pegawai->nama} (NIP {$pegawai->nip})");

        return redirect()->route('tunjangan.data.index')->with('success', "Pegawai {$pegawai->nama} berhasil ditambahkan.");
    }

    public function editData(Pegawai $pegawai, TunjanganKeluargaService $service): View
    {
        $pegawai->load('tunjanganKeluarga.anggota');

        return view('tunjangan-keluarga.data-edit', ['pegawai' => $pegawai, 'service' => $service]);
    }

    public function simpanData(Request $request, Pegawai $pegawai, TunjanganKeluargaService $service): RedirectResponse
    {
        $data = $request->validate([
            'pasangan.nama' => ['nullable', 'string', 'max:150'],
            'pasangan.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'pasangan.status_tunjangan' => ['nullable', 'boolean'],
            'anak' => ['nullable', 'array', 'max:2'],
            'anak.*.nama' => ['nullable', 'string', 'max:150'],
            'anak.*.tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'anak.*.status_tunjangan' => ['nullable', 'boolean'],
            'anak.*.perpanjangan_kuliah' => ['nullable', 'boolean'],
            'dokumen_pendukung' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $payload = ['pasangan' => $data['pasangan'] ?? [], 'anak' => array_values($data['anak'] ?? [])];
        $dokumenLama = null;

        if ($request->hasFile('dokumen_pendukung')) {
            $file = $request->file('dokumen_pendukung');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs('tunjangan-keluarga', Str::uuid().'.'.$ext, 'local');
            if (! $path) {
                return back()->withErrors(['dokumen_pendukung' => 'Dokumen gagal disimpan pada penyimpanan private.']);
            }
            $dokumenLama = $pegawai->tunjanganKeluarga?->dokumen_pendukung_path;
            $payload['dokumen_pendukung_path'] = $path;
            $payload['dokumen_pendukung_nama'] = $file->getClientOriginalName();
        }

        $service->simpanKeluarga($pegawai, $payload, $request->user()->id);

        if ($dokumenLama) {
            Storage::disk('local')->delete($dokumenLama);
        }

        AuditHelper::catat('Perbarui Data Tunjangan Keluarga', "Pegawai: {$pegawai->nama}");

        return redirect()->route('tunjangan.data.index')->with('success', "Data tunjangan keluarga {$pegawai->nama} berhasil disimpan.");
    }

    public function unduhDokumenData(TunjanganKeluarga $tunjanganKeluarga): StreamedResponse
    {
        abort_unless(
            $tunjanganKeluarga->dokumen_pendukung_path && Storage::disk('local')->exists($tunjanganKeluarga->dokumen_pendukung_path),
            404
        );

        return Storage::disk('local')->download($tunjanganKeluarga->dokumen_pendukung_path, $tunjanganKeluarga->dokumen_pendukung_nama ?? 'dokumen-pendukung');
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
