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
use App\Services\GajiTunjanganService;
use App\Services\TunjanganKeluargaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TunjanganKeluargaController extends Controller
{
    /**
     * NIP pegawai yang sedang dibuka di formulir Perubahan Data oleh role
     * bebas gerbang. Role lain memakai NIP terverifikasi milik gerbang
     * privasi (GajiTunjanganController::SESI_NIP) - lihat nipFormulir().
     */
    private const SESI_NIP_FORMULIR = 'tk_formulir_nip';

    /**
     * Formulir Perubahan Data.
     *
     * Dua langkah. Pertama formulir ditautkan ke SATU pegawai lewat NIP;
     * baru sesudah itu nama dan data tunjangan keluarganya ditampilkan untuk
     * diubah. Data keluarga memuat nama dan tanggal lahir anak, jadi langkah
     * pertamanya memakai GERBANG PRIVASI yang sama dengan Dashboard: NIP +
     * 4 digit akhir rekening. Role di config('akses.role_tk_data_penuh')
     * cukup NIP, karena mereka memang berwenang melihat data seluruh pegawai.
     */
    public function form(TunjanganKeluargaService $service): View
    {
        GuestSession::login();

        $nip = $this->nipFormulir();
        $pegawai = $this->pegawaiDariNip($nip);
        $keluarga = $pegawai?->tunjanganKeluarga;

        return view('tunjangan-keluarga.form', [
            'bebasGerbang' => $this->bebasGerbang(),
            'nipSesi' => $nip,
            'pegawai' => $pegawai,
            'statusSekarang' => $service->statusTunjangan($keluarga),
            'keluargaAwal' => $this->keluargaUntukFormulir($keluarga),
        ]);
    }

    /** Langkah pertama formulir: buka data satu pegawai. */
    public function bukaFormulir(Request $request, GajiTunjanganService $gaji): RedirectResponse
    {
        GuestSession::login();

        if ($this->bebasGerbang()) {
            $request->validate(['nip' => ['required', 'string', 'max:30']], [], ['nip' => 'NIP']);
            $nip = preg_replace('/\D/', '', (string) $request->input('nip')) ?? '';

            if ($this->pegawaiDariNip($nip) === null) {
                return back()->withInput()->withErrors(['nip' => 'NIP tidak ditemukan di Data Pegawai.']);
            }

            session([self::SESI_NIP_FORMULIR => $nip]);

            return redirect()->route('tunjangan.form');
        }

        $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'rek4' => ['required', 'string', 'max:10'],
        ], [], ['nip' => 'NIP', 'rek4' => '4 digit akhir rekening']);

        $hasil = $gaji->verifikasi($request->string('nip'), $request->string('rek4'));

        if (! $hasil['ok']) {
            return back()->withInput($request->only('nip'))->withErrors(['nip' => $hasil['err']]);
        }

        session([GajiTunjanganController::SESI_NIP => $hasil['nip']]);

        return redirect()->route('tunjangan.form');
    }

    /** Lepaskan pegawai yang sedang dibuka supaya bisa memasukkan NIP lain. */
    public function gantiNipFormulir(): RedirectResponse
    {
        session()->forget([self::SESI_NIP_FORMULIR, GajiTunjanganController::SESI_NIP]);

        return redirect()->route('tunjangan.form');
    }

    private function bebasGerbang(): bool
    {
        return in_array(GuestSession::role(), config('akses.role_tk_data_penuh'), true);
    }

    /** NIP (angka saja) yang sudah sah dibuka di formulir pada sesi ini, atau null. */
    private function nipFormulir(): ?string
    {
        return $this->bebasGerbang()
            ? session(self::SESI_NIP_FORMULIR)
            : GajiTunjanganController::nipTerverifikasi();
    }

    /**
     * Pegawai ber-NIP ini di Data Pegawai. Dicocokkan pada angkanya saja:
     * NIP di Data Pegawai bisa tersimpan berspasi atau bertitik, sedangkan
     * NIP sesi sudah dibersihkan.
     */
    private function pegawaiDariNip(?string $nip): ?Pegawai
    {
        if (blank($nip)) {
            return null;
        }

        $id = Pegawai::query()->where('nip', $nip)->value('id')
            ?? Pegawai::query()->get(['id', 'nip'])
                ->first(fn (Pegawai $p) => preg_replace('/\D/', '', (string) $p->nip) === $nip)?->id;

        return $id ? Pegawai::with('tunjanganKeluarga.anggota')->find($id) : null;
    }

    /**
     * Data keluarga tersimpan dalam bentuk isian formulir.
     *
     * @return array{pasangan: array<string, mixed>, anak: array<int, array<string, mixed>>}
     */
    private function keluargaUntukFormulir(?TunjanganKeluarga $keluarga): array
    {
        $baris = fn ($anggota) => [
            'nama' => $anggota->nama,
            'tanggal_lahir' => $anggota->tanggal_lahir?->format('Y-m-d'),
            'status_tunjangan' => (bool) $anggota->status_tunjangan,
            'perpanjangan_kuliah' => (bool) $anggota->perpanjangan_kuliah,
            'keterangan' => $anggota->keterangan,
        ];

        $anggota = $keluarga?->anggota ?? collect();
        $pasangan = $anggota->firstWhere('hubungan', 'pasangan');

        return [
            'pasangan' => $pasangan ? $baris($pasangan) : [],
            'anak' => $anggota->where('hubungan', 'anak')->sortBy('id')->map($baris)->values()->all(),
        ];
    }

    public function submit(StorePengajuanTunjanganRequest $request): RedirectResponse
    {
        GuestSession::login();

        // Pegawainya diambil dari NIP yang sudah dibuka di langkah pertama,
        // bukan dari isian: nama dan NIP di formulir tidak bisa dikarang.
        $pegawai = $this->pegawaiDariNip($this->nipFormulir());

        if ($pegawai === null) {
            return redirect()->route('tunjangan.form')
                ->withErrors(['nip' => 'Masukkan NIP pegawai lebih dulu sebelum mengirim perubahan.']);
        }

        $data = $request->validated();
        $file = $request->file('lampiran');
        $path = 'tunjangan-keluarga/'.Str::uuid().'.'.strtolower($file->guessExtension() ?: 'bin');
        $stored = null;
        try {
            $stored = $file->storeAs(dirname($path), basename($path), 'local');
            if (! $stored) {
                throw new \RuntimeException('Lampiran gagal disimpan pada penyimpanan private.');
            }
            DB::transaction(function () use ($request, $data, $file, $stored, $pegawai) {
                $pengajuan = PengajuanPerubahanTunjangan::create([
                    'pegawai_id' => $pegawai->id, 'nama_pegawai' => $pegawai->nama, 'nip' => $pegawai->nip,
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
        AuditHelper::catatSebagai('layanan', 'layanan', 'Pengajuan Perubahan Tunjangan', 'Pengajuan atas nama '.$pegawai->nama);

        return back()->with('success', 'Pengajuan perubahan berhasil dikirim dan menunggu di-approve Kepegawaian. Lampiran disimpan secara private.');
    }

    public function monitoring(): View
    {
        return view('tunjangan-keluarga.monitoring', [
            'pengajuan' => PengajuanPerubahanTunjangan::with(['pegawai', 'lampiran', 'diprosesOleh'])->latest('diajukan_at')->paginate(30),
            'pegawai' => Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip']),
        ]);
    }

    /**
     * Dashboard Tunjangan Keluarga.
     *
     * GERBANG PRIVASI. Halaman ini memuat nama dan tanggal lahir anak seluruh
     * pegawai. Role di luar config('akses.role_tk_data_penuh') harus melewati
     * gerbang NIP + 4 digit akhir rekening lebih dulu - gerbang yang SAMA
     * dengan Data Gaji & Tunjangan, sehingga sekali verifikasi berlaku untuk
     * kedua menu - lalu hanya menerima barisnya sendiri.
     *
     * Kartu agregat tetap tampil setelah verifikasi: angkanya statistik
     * seluruh kantor dan tidak membuka identitas siapa pun. Yang disaring
     * adalah tabel rinciannya, dan penyaringan itu dilakukan DI SINI - bukan
     * di tampilan - supaya baris pegawai lain tidak pernah sampai ke browser.
     */
    public function dashboard(TunjanganKeluargaService $service): View
    {
        $penuh = in_array(GuestSession::role(), config('akses.role_tk_data_penuh'), true);
        $nipSesi = $penuh ? null : GajiTunjanganController::nipTerverifikasi();
        $terkunci = ! $penuh && $nipSesi === null;

        $dashboard = $service->dashboard();

        if ($terkunci) {
            // Belum terverifikasi: kartu dikosongkan juga, bukan cuma tabelnya.
            $dashboard['rincian'] = [];
        } elseif ($nipSesi !== null) {
            $dashboard['rincian'] = array_values(array_filter(
                $dashboard['rincian'],
                fn (array $r) => preg_replace('/\D/', '', (string) ($r['nip'] ?? '')) === $nipSesi
            ));
        }

        return view('tunjangan-keluarga.dashboard', [
            'dashboard' => $dashboard,
            'terkunci' => $terkunci,
            'terbatas' => ! $penuh && ! $terkunci,
            'nipSesi' => $nipSesi,
        ]);
    }

    /** Gerbang privasi dashboard: cek NIP + 4 digit akhir rekening. */
    public function verifikasi(Request $request, GajiTunjanganService $gaji): RedirectResponse
    {
        $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'rek4' => ['required', 'string', 'max:10'],
        ], [], ['nip' => 'NIP', 'rek4' => '4 digit akhir rekening']);

        $hasil = $gaji->verifikasi($request->string('nip'), $request->string('rek4'));

        if (! $hasil['ok']) {
            return back()->withErrors(['nip' => $hasil['err']]);
        }

        session([GajiTunjanganController::SESI_NIP => $hasil['nip']]);

        return back();
    }

    /** Lupakan identitas terverifikasi supaya bisa memeriksa NIP lain. */
    public function gantiNip(): RedirectResponse
    {
        session()->forget(GajiTunjanganController::SESI_NIP);

        return back();
    }

    /**
     * Data Tunjangan Keluarga: sumber data mentah yang dibaca dashboard.
     * Diisi manual oleh superadmin (belum ada alur pengajuan/approval di
     * sini — beda dari form self-service "Perubahan Data").
     */
    public function data(Request $request, TunjanganKeluargaService $service): View
    {
        $cari = trim((string) $request->query('cari', ''));
        // Hanya pegawai yang berhak: PNS & PPPK Penuh Waktu. PPPK Paruh Waktu
        // tidak berhak tunjangan keluarga sehingga tidak perlu didaftar.
        $pegawaiList = Pegawai::query()
            ->berhakTunjangan()
            ->with('tunjanganKeluarga.anggota')
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq->where('nama', 'like', "%{$cari}%")->orWhere('nip', 'like', "%{$cari}%")))
            ->orderBy('nama')
            ->paginate(30)->withQueryString();

        return view('tunjangan-keluarga.data', ['pegawaiList' => $pegawaiList, 'cari' => $cari, 'service' => $service]);
    }

    /**
     * Sub menu "Data Pegawai" pada modul Data Kepegawaian: daftar induk yang
     * menjadi sumber seluruh halaman lain di modul ini.
     */
    public function pegawai(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = trim((string) $request->query('status', ''));

        $pegawaiList = Pegawai::query()
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('nip', 'like', "%{$cari}%")
                ->orWhere('jabatan', 'like', "%{$cari}%")
                ->orWhere('bidang', 'like', "%{$cari}%")))
            ->when(in_array($status, Pegawai::STATUS_KEPEGAWAIAN, true), fn ($q) => $q->where('status_kepegawaian', $status))
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(30)
            ->withQueryString();

        return view('tunjangan-keluarga.pegawai', compact('pegawaiList', 'cari', 'status'));
    }

    public function createPegawai(): View
    {
        return view('tunjangan-keluarga.pegawai-create');
    }

    public function editPegawai(Pegawai $pegawai): View
    {
        return view('tunjangan-keluarga.pegawai-edit', compact('pegawai'));
    }

    public function updatePegawai(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $pegawai->update($this->validasiPegawai($request, $pegawai->id));

        AuditHelper::catat('Edit Pegawai', "Pegawai: {$pegawai->nama} (NIP {$pegawai->nip})");

        return redirect()->route('tunjangan.pegawai.index')->with('success', "Data pegawai {$pegawai->nama} berhasil diperbarui.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasiPegawai(Request $request, ?int $abaikanId = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($abaikanId)],
            'jabatan' => ['required', 'string', 'max:255'],
            'golongan' => ['nullable', 'string', 'max:20'],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'periode_kgb' => ['nullable', 'string', 'max:50'],
            'status_kepegawaian' => ['required', Rule::in(Pegawai::STATUS_KEPEGAWAIAN)],
            'bidang' => ['required', 'string', 'max:100'],
            'rekening' => ['nullable', 'string', 'max:100'],
            'nomor_handphone' => ['nullable', 'string', 'max:30'],
        ], [], [
            'nip' => 'NIP',
            'periode_kgb' => 'Periode KGB',
            'status_kepegawaian' => 'Status Kepegawaian',
            'bidang' => 'Unit Kerja',
            'nomor_handphone' => 'Nomor Handphone',
        ]);

        $data['aktif'] = $request->boolean('aktif', true);

        return $data;
    }

    public function storePegawai(Request $request): RedirectResponse
    {
        $pegawai = Pegawai::create($this->validasiPegawai($request));

        AuditHelper::catat('Tambah Pegawai', "Pegawai: {$pegawai->nama} (NIP {$pegawai->nip})");

        return redirect()->route('tunjangan.pegawai.index')->with('success', "Pegawai {$pegawai->nama} berhasil ditambahkan.");
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

    /**
     * Kosongkan data tunjangan keluarga satu pegawai (status kembali TK/0).
     * Baris pegawainya TIDAK dihapus - pegawai dikelola di sub menu Data
     * Pegawai, dan menghapusnya dari sini akan memutus NPD/SP yang memakainya.
     */
    public function hapusData(Pegawai $pegawai): RedirectResponse
    {
        $keluarga = $pegawai->tunjanganKeluarga;

        if ($keluarga) {
            if (filled($keluarga->dokumen_pendukung_path)) {
                Storage::disk('local')->delete($keluarga->dokumen_pendukung_path);
            }

            $keluarga->anggota()->delete();
            $keluarga->delete();
        }

        AuditHelper::catat('Hapus Data Tunjangan Keluarga', "Pegawai: {$pegawai->nama} (NIP {$pegawai->nip})");

        return back()->with('success', "Data tunjangan keluarga {$pegawai->nama} dikosongkan.");
    }

    public function unduhDokumenData(TunjanganKeluarga $tunjanganKeluarga): StreamedResponse
    {
        abort_unless(
            $tunjanganKeluarga->dokumen_pendukung_path && Storage::disk('local')->exists($tunjanganKeluarga->dokumen_pendukung_path),
            404
        );

        return Storage::disk('local')->download($tunjanganKeluarga->dokumen_pendukung_path, $tunjanganKeluarga->dokumen_pendukung_nama ?? 'dokumen-pendukung');
    }

    /**
     * Approve/Tolak satu pengajuan. Saat di-approve, isi pengajuannya
     * langsung menjadi Data Tunjangan Keluarga pegawai yang ditautkan.
     *
     * Pegawainya diambil dari pengajuan itu sendiri (ditautkan lewat NIP saat
     * diajukan). pegawai_id dari formulir hanya dipakai untuk pengajuan yang
     * belum tertaut - NIP-nya kosong atau tidak cocok dengan Data Pegawai.
     */
    public function proses(Request $request, PengajuanPerubahanTunjangan $pengajuan, TunjanganKeluargaService $service): RedirectResponse
    {
        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,tolak'],
            'pegawai_id' => ['nullable', 'exists:pegawai,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $pengajuan, $service, $data) {
            $pengajuan = PengajuanPerubahanTunjangan::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($pengajuan->status !== 'diajukan') {
                abort(409, 'Pengajuan sudah diproses.');
            }

            $pegawaiId = $pengajuan->pegawai_id ?? ($data['pegawai_id'] ?? null);

            if ($data['aksi'] === 'setujui') {
                if ($pegawaiId === null) {
                    throw ValidationException::withMessages([
                        'pegawai_id' => 'Pengajuan ini belum tertaut ke Data Pegawai. Pilih pegawainya lebih dulu.',
                    ]);
                }

                $pegawai = Pegawai::query()->lockForUpdate()->findOrFail($pegawaiId);
                $service->simpanKeluarga($pegawai, $pengajuan->payload + ['catatan' => $data['catatan'] ?? $pengajuan->keterangan], $request->user()->id);
            }

            $pengajuan->update(['status' => $data['aksi'] === 'setujui' ? 'disetujui' : 'ditolak', 'pegawai_id' => $pegawaiId,
                'diproses_oleh' => $request->user()->id, 'diproses_at' => now(), 'catatan_proses' => $data['catatan'] ?? null]);
            AuditLog::create(['user_id' => $request->user()->id, 'username' => $request->user()->username, 'role' => $request->user()->role,
                'aktivitas' => $data['aksi'] === 'setujui' ? 'Setujui Perubahan Tunjangan' : 'Tolak Perubahan Tunjangan',
                'keterangan' => 'Pengajuan #'.$pengajuan->id.' atas nama '.$pengajuan->nama_pegawai, 'ip_address' => $request->ip()]);
        });

        return back()->with('success', $data['aksi'] === 'setujui'
            ? 'Pengajuan di-approve. Data Tunjangan Keluarga pegawai tersebut sudah diperbarui.'
            : 'Pengajuan ditolak.');
    }

    public function download(LampiranTunjangan $lampiran): StreamedResponse
    {
        abort_unless(Storage::disk($lampiran->disk)->exists($lampiran->path), 404);

        return Storage::disk($lampiran->disk)->download($lampiran->path, $lampiran->nama_asli, ['Content-Type' => $lampiran->mime]);
    }
}
