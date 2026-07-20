<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Kpa;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PelimpahanController extends Controller
{
    public function index()
    {
        $pejabatOpd = PejabatOpd::aktif();
        $pegawaiList = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip', 'jabatan']);
        $kpaList = Kpa::with(['kpaPegawai', 'bppPegawai'])->orderBy('id')->get();

        // program/kegiatan/sub_kegiatan di master_anggaran kadang punya varian
        // whitespace berbeda untuk baris yang sebetulnya sub kegiatan yang sama
        // (hasil impor) — kelompokkan berdasarkan versi ternormalisasi, kalau
        // tidak daftar ini akan menampilkan ratusan "duplikat" semu.
        $subKegiatanList = MasterAnggaran::select('program', 'kegiatan', 'sub_kegiatan')
            ->get()
            ->map(fn (MasterAnggaran $m) => (object) [
                'program' => $m->programNormal(),
                'kegiatan' => $m->kegiatanNormal(),
                'sub_kegiatan' => $m->subKegiatanNormal(),
            ])
            ->unique('sub_kegiatan')
            ->sortBy([
                ['program', 'asc'],
                ['kegiatan', 'asc'],
                ['sub_kegiatan', 'asc'],
            ])
            ->values();

        $pelimpahanMap = Pelimpahan::with(['kpa.kpaPegawai', 'kpa.bppPegawai', 'pptkPegawai'])
            ->get()
            ->keyBy('kode_sub_kegiatan');

        return view('pelimpahan.index', compact('pejabatOpd', 'pegawaiList', 'kpaList', 'subKegiatanList', 'pelimpahanMap'));
    }

    /** Simpan/ubah Pejabat OPD (PA & Bendahara Pengeluaran). Selalu satu baris aktif. */
    public function updateOpd(Request $request)
    {
        $validated = $request->validate([
            'pa_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'bendahara_pengeluaran_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
        ]);

        PejabatOpd::simpan($validated);

        AuditLog::catat('Ubah Pejabat OPD', 'PA & Bendahara Pengeluaran diperbarui');

        return back()->with('success', 'Pejabat OPD berhasil disimpan.');
    }

    public function storeKpa(Request $request)
    {
        $validated = $request->validate([
            'kpa_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true), 'different:bpp_pegawai_id'],
            'bpp_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'nama_jabatan' => ['nullable', 'string', 'max:150'],
        ]);

        $validated['aktif'] = true;
        $kpa = DB::transaction(function () use ($validated) {
            Kpa::query()->lockForUpdate()->get();

            if (Kpa::sudahJadiKpaAktifLain((int) $validated['kpa_pegawai_id'])) {
                throw ValidationException::withMessages([
                    'kpa_pegawai_id' => 'Pegawai ini sudah jadi KPA aktif di baris lain.',
                ]);
            }

            return Kpa::create($validated)->load(['kpaPegawai', 'bppPegawai']);
        });

        AuditLog::catat('Tambah KPA', "KPA: {$kpa->kpaPegawai->nama}, BPP: {$kpa->bppPegawai->nama}");

        return back()->with('success', 'KPA berhasil ditambahkan.');
    }

    public function updateKpa(Request $request, Kpa $kpa)
    {
        $validated = $request->validate([
            'kpa_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true), 'different:bpp_pegawai_id'],
            'bpp_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'nama_jabatan' => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($validated, $kpa) {
            Kpa::query()->lockForUpdate()->get();

            if (Kpa::sudahJadiKpaAktifLain((int) $validated['kpa_pegawai_id'], $kpa->id)) {
                throw ValidationException::withMessages([
                    'kpa_pegawai_id' => 'Pegawai ini sudah jadi KPA aktif di baris lain.',
                ]);
            }

            $kpa->update($validated);
        });
        $kpa->load(['kpaPegawai', 'bppPegawai']);

        AuditLog::catat('Ubah KPA', "KPA: {$kpa->kpaPegawai->nama}, BPP: {$kpa->bppPegawai->nama}");

        return back()->with('success', 'KPA berhasil diperbarui.');
    }

    public function toggleKpaAktif(Kpa $kpa)
    {
        DB::transaction(function () use ($kpa) {
            Kpa::query()->lockForUpdate()->get();

            if (! $kpa->aktif && Kpa::sudahJadiKpaAktifLain($kpa->kpa_pegawai_id, $kpa->id)) {
                throw ValidationException::withMessages([
                    'kpa_pegawai_id' => 'Pegawai ini sudah jadi KPA aktif di baris lain.',
                ]);
            }

            $kpa->aktif = ! $kpa->aktif;
            $kpa->save();
        });
        $kpa->load('kpaPegawai');

        AuditLog::catat($kpa->aktif ? 'Aktifkan KPA' : 'Nonaktifkan KPA', "KPA: {$kpa->kpaPegawai->nama}");

        return back()->with('success', 'Status KPA berhasil diperbarui.');
    }

    /** Set borongan: KPA + PPTK yang sama untuk banyak sub kegiatan sekaligus (dicentang di UI). */
    public function setSubKegiatan(Request $request)
    {
        $validated = $request->validate([
            'kpa_id' => ['required', Rule::exists('kpa', 'id')->where('aktif', true)],
            'pptk_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'kode_sub_kegiatan' => ['required', 'array', 'min:1'],
            'kode_sub_kegiatan.*' => ['string', 'max:255'],
        ]);

        Pelimpahan::setBorongan($validated['kode_sub_kegiatan'], (int) $validated['kpa_id'], (int) $validated['pptk_pegawai_id']);

        $jumlah = count($validated['kode_sub_kegiatan']);
        AuditLog::catat('Set Pelimpahan Sub Kegiatan', "{$jumlah} sub kegiatan diset");

        return back()->with('success', "{$jumlah} sub kegiatan berhasil diset.");
    }
}
