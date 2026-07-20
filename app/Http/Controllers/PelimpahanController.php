<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Kpa;
use App\Models\KpaPptk;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PelimpahanController extends Controller
{
    public function index(Request $request)
    {
        $pejabatOpd = PejabatOpd::aktif();
        $pegawaiList = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip', 'jabatan']);
        $kpaList = Kpa::with(['kpaPegawai', 'bppPegawai'])->orderBy('id')->get();
        $pptkAssignments = KpaPptk::with(['kpa.kpaPegawai', 'pptkPegawai'])
            ->where('aktif', true)->orderBy('kpa_id')->get();

        $scopeDasar = MasterAnggaran::query()->where('aktif', true)
            ->selectRaw('program_normal, program_kunci, MIN(kegiatan_normal) kegiatan_normal, sub_kegiatan_normal, sub_kegiatan_kunci')
            ->groupBy('program_normal', 'program_kunci', 'sub_kegiatan_normal', 'sub_kegiatan_kunci');

        $totalSubKegiatan = DB::query()->fromSub(clone $scopeDasar, 'scope')->count();
        $assignedSubKegiatan = Pelimpahan::aktif()->whereExists(function ($query) {
            $query->selectRaw('1')->from('master_anggaran as ma')
                ->where('ma.aktif', true)
                ->whereColumn('ma.program_kunci', 'pelimpahan.program_kunci')
                ->whereColumn('ma.sub_kegiatan_kunci', 'pelimpahan.sub_kegiatan_kunci');
        })->count();
        $ringkasan = [
            'total' => $totalSubKegiatan,
            'assigned' => $assignedSubKegiatan,
            'unassigned' => max(0, $totalSubKegiatan - $assignedSubKegiatan),
            'percentage' => $totalSubKegiatan > 0 ? round($assignedSubKegiatan / $totalSubKegiatan * 100, 1) : 0,
        ];

        $subKegiatanQuery = clone $scopeDasar;
        $status = $request->string('status')->toString();
        $existsCallback = fn ($query) => $this->pelimpahanAktifUntukFilter($query, $request);
        if ($status === 'assigned') {
            $subKegiatanQuery->whereExists($existsCallback);
        } elseif ($status === 'unassigned') {
            $subKegiatanQuery->whereNotExists($existsCallback);
        } elseif ($request->filled('kpa_id') || $request->filled('bpp_pegawai_id') || $request->filled('pptk_pegawai_id')) {
            $subKegiatanQuery->whereExists($existsCallback);
        }

        $subKegiatanQuery
            ->when($request->filled('program'), fn ($query) => $query->where('program_kunci', MasterAnggaran::normalisasiKunci($request->string('program'))))
            ->when($request->filled('cari'), fn ($query) => $query->where('sub_kegiatan_kunci', 'like', '%'.MasterAnggaran::normalisasiKunci($request->string('cari')).'%'));

        $subKegiatanList = $subKegiatanQuery
            ->orderBy('program_normal')->orderBy('kegiatan_normal')->orderBy('sub_kegiatan_normal')
            ->paginate(25)->withQueryString();

        $scopeHalaman = $subKegiatanList->getCollection();
        $pelimpahanMap = collect();
        if ($scopeHalaman->isNotEmpty()) {
            $pelimpahanHalaman = Pelimpahan::aktif()
                ->with(['kpa.kpaPegawai', 'kpa.bppPegawai', 'pptkPegawai'])
                ->where(function ($query) use ($scopeHalaman) {
                    foreach ($scopeHalaman as $scope) {
                        $query->orWhere(fn ($q) => $q
                            ->where('program_kunci', $scope->program_kunci)
                            ->where('sub_kegiatan_kunci', $scope->sub_kegiatan_kunci));
                    }
                })->get();
            $pelimpahanMap = $pelimpahanHalaman->keyBy(fn ($p) => $p->program_kunci.'|'.$p->sub_kegiatan_kunci);
        }

        $programList = MasterAnggaran::query()->where('aktif', true)
            ->select('program_normal', 'program_kunci')->distinct()->orderBy('program_normal')->get();
        $kpaHierarki = Kpa::query()->where('aktif', true)
            ->with([
                'kpaPegawai', 'bppPegawai',
                'pptkAktif.pptkPegawai',
                'pptkAktif.pelimpahan' => fn ($query) => $query->aktif()->orderBy('program_normal')->orderBy('kode_sub_kegiatan'),
            ])->orderBy('id')->get();

        return view('pelimpahan.index', compact(
            'pejabatOpd', 'pegawaiList', 'kpaList', 'pptkAssignments', 'subKegiatanList',
            'pelimpahanMap', 'programList', 'kpaHierarki', 'ringkasan'
        ));
    }

    private function pelimpahanAktifUntukFilter($query, Request $request): void
    {
        $query->selectRaw('1')->from('pelimpahan as pf')
            ->join('kpa as kf', 'kf.id', '=', 'pf.kpa_id')
            ->whereColumn('pf.program_kunci', 'master_anggaran.program_kunci')
            ->whereColumn('pf.sub_kegiatan_kunci', 'master_anggaran.sub_kegiatan_kunci')
            ->where('pf.aktif', true)
            ->when($request->filled('kpa_id'), fn ($q) => $q->where('pf.kpa_id', $request->integer('kpa_id')))
            ->when($request->filled('bpp_pegawai_id'), fn ($q) => $q->where('kf.bpp_pegawai_id', $request->integer('bpp_pegawai_id')))
            ->when($request->filled('pptk_pegawai_id'), fn ($q) => $q->where('pf.pptk_pegawai_id', $request->integer('pptk_pegawai_id')));
    }

    public function updateOpd(Request $request)
    {
        $validated = $request->validate([
            'pa_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'bendahara_pengeluaran_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true), 'different:pa_pegawai_id'],
        ]);
        PejabatOpd::simpan($validated);
        AuditLog::catat('Ubah Pejabat OPD', 'PA & Bendahara Pengeluaran diperbarui');

        return back()->with('success', 'Pejabat OPD berhasil disimpan.');
    }

    public function storeKpa(Request $request)
    {
        $validated = $this->validasiKpa($request);
        $validated['aktif'] = true;
        $kpa = DB::transaction(function () use ($validated) {
            Kpa::query()->lockForUpdate()->get();
            $this->pastikanKpaDanBppBelumAktif($validated);

            return Kpa::create($validated)->load(['kpaPegawai', 'bppPegawai']);
        });
        AuditLog::catat('Tambah KPA', "KPA: {$kpa->kpaPegawai->nama}, BPP: {$kpa->bppPegawai->nama}");

        return back()->with('success', 'KPA berhasil ditambahkan.');
    }

    public function updateKpa(Request $request, Kpa $kpa)
    {
        $validated = $this->validasiKpa($request);
        DB::transaction(function () use ($validated, $kpa) {
            Kpa::query()->lockForUpdate()->get();
            $this->pastikanKpaDanBppBelumAktif($validated, $kpa->id);
            $kpa->update($validated);
        });
        $kpa->load(['kpaPegawai', 'bppPegawai']);
        AuditLog::catat('Ubah KPA', "KPA: {$kpa->kpaPegawai->nama}, BPP: {$kpa->bppPegawai->nama}");

        return back()->with('success', 'KPA berhasil diperbarui.');
    }

    private function validasiKpa(Request $request): array
    {
        return $request->validate([
            'kpa_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true), 'different:bpp_pegawai_id'],
            'bpp_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'nama_jabatan' => ['nullable', 'string', 'max:150'],
        ]);
    }

    private function pastikanKpaDanBppBelumAktif(array $data, ?int $kecualiId = null): void
    {
        if (Kpa::sudahJadiKpaAktifLain((int) $data['kpa_pegawai_id'], $kecualiId)) {
            throw ValidationException::withMessages(['kpa_pegawai_id' => 'Pegawai ini sudah jadi KPA aktif di baris lain.']);
        }
        if (Kpa::query()->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->where('bpp_pegawai_id', $data['bpp_pegawai_id'])->where('aktif', true)->exists()) {
            throw ValidationException::withMessages(['bpp_pegawai_id' => 'Pegawai ini sudah menjadi BPP pada KPA aktif lain.']);
        }
    }

    public function toggleKpaAktif(Kpa $kpa)
    {
        DB::transaction(function () use ($kpa) {
            Kpa::query()->lockForUpdate()->get();
            if ($kpa->aktif && $kpa->pelimpahan()->aktif()->exists()) {
                throw ValidationException::withMessages(['kpa' => 'KPA masih memiliki Sub Kegiatan aktif. Reassign terlebih dahulu.']);
            }
            if (! $kpa->aktif) {
                $this->pastikanKpaDanBppBelumAktif($kpa->only(['kpa_pegawai_id', 'bpp_pegawai_id']), $kpa->id);
            }
            $kpa->aktif = ! $kpa->aktif;
            $kpa->save();
        });
        $kpa->load('kpaPegawai');
        AuditLog::catat($kpa->aktif ? 'Aktifkan KPA' : 'Nonaktifkan KPA', "KPA: {$kpa->kpaPegawai->nama}");

        return back()->with('success', 'Status KPA berhasil diperbarui.');
    }

    public function storePptk(Request $request)
    {
        $validated = $request->validate([
            'kpa_id' => ['required', Rule::exists('kpa', 'id')->where('aktif', true)],
            'pptk_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
        ]);
        $assignment = DB::transaction(function () use ($validated) {
            $kpa = Kpa::query()->with(['kpaPegawai', 'bppPegawai'])->lockForUpdate()->findOrFail($validated['kpa_id']);
            $pptk = Pegawai::query()->lockForUpdate()->findOrFail($validated['pptk_pegawai_id']);
            if (! $kpa->aktif || ! $kpa->kpaPegawai?->aktif || ! $kpa->bppPegawai?->aktif || ! $pptk->aktif) {
                throw ValidationException::withMessages(['pptk_pegawai_id' => 'Seluruh pejabat pada rantai harus aktif.']);
            }
            if (in_array($pptk->id, [$kpa->kpa_pegawai_id, $kpa->bpp_pegawai_id], true)) {
                throw ValidationException::withMessages(['pptk_pegawai_id' => 'KPA atau BPP tidak dapat sekaligus menjadi PPTK.']);
            }
            $aktif = KpaPptk::where('pptk_pegawai_id', $pptk->id)->where('aktif', true)->lockForUpdate()->first();
            if ($aktif && $aktif->kpa_id !== $kpa->id) {
                throw ValidationException::withMessages(['pptk_pegawai_id' => 'PPTK ini sudah berada di bawah KPA aktif lain.']);
            }

            return $aktif ?? KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptk->id, 'aktif' => true]);
        });
        $assignment->load(['kpa.kpaPegawai', 'pptkPegawai']);
        AuditLog::catat('Tambah PPTK KPA', "KPA: {$assignment->kpa->kpaPegawai->nama}, PPTK: {$assignment->pptkPegawai->nama}");

        return back()->with('success', 'PPTK berhasil ditempatkan di bawah KPA.');
    }

    public function togglePptk(KpaPptk $kpaPptk)
    {
        DB::transaction(function () use ($kpaPptk) {
            $kpaPptk = KpaPptk::query()->lockForUpdate()->findOrFail($kpaPptk->id);
            if ($kpaPptk->aktif && $kpaPptk->pelimpahan()->aktif()->exists()) {
                throw ValidationException::withMessages(['pptk' => 'PPTK masih memiliki Sub Kegiatan aktif. Reassign terlebih dahulu.']);
            }
            if (! $kpaPptk->aktif && KpaPptk::whereKeyNot($kpaPptk->id)
                ->where('pptk_pegawai_id', $kpaPptk->pptk_pegawai_id)->where('aktif', true)->exists()) {
                throw ValidationException::withMessages(['pptk' => 'PPTK sudah berada di rantai KPA aktif lain.']);
            }
            $kpaPptk->aktif = ! $kpaPptk->aktif;
            $kpaPptk->dinonaktifkan_at = $kpaPptk->aktif ? null : now();
            $kpaPptk->save();
        });
        $kpaPptk->refresh();
        AuditLog::catat($kpaPptk->aktif ? 'Aktifkan PPTK KPA' : 'Nonaktifkan PPTK KPA', "KPA-PPTK #{$kpaPptk->id}");

        return back()->with('success', 'Status PPTK pada KPA berhasil diperbarui.');
    }

    public function setSubKegiatan(Request $request)
    {
        $validated = $request->validate([
            'kpa_id' => ['required', Rule::exists('kpa', 'id')->where('aktif', true)],
            'bpp_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'pptk_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'scope' => ['required', 'array', 'min:1'],
            'scope.*' => ['string', 'max:2000'],
        ]);
        $scopes = collect($validated['scope'])->map(function (string $encoded) {
            $scope = json_decode((string) base64_decode($encoded, true), true);
            if (! is_array($scope) || ! isset($scope['program'], $scope['sub_kegiatan'])) {
                throw ValidationException::withMessages(['scope' => 'Lingkup Sub Kegiatan tidak valid.']);
            }

            return $scope;
        })->all();

        try {
            $hasil = Pelimpahan::tetapkan(
                $scopes,
                (int) $validated['kpa_id'],
                (int) $validated['bpp_pegawai_id'],
                (int) $validated['pptk_pegawai_id'],
                $request->user()->id,
            );
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || ($e->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['scope' => 'Sub Kegiatan baru saja ditugaskan oleh transaksi lain. Muat ulang halaman.']);
            }
            throw $e;
        }

        $jumlah = count($scopes);
        AuditLog::catat(
            'Set Pelimpahan Sub Kegiatan',
            "{$jumlah} scope diproses; baru {$hasil['baru']}, dipindahkan {$hasil['dipindahkan']}, tetap {$hasil['tetap']}"
        );

        return back()->with('success', "{$jumlah} Sub Kegiatan berhasil diproses.");
    }
}
