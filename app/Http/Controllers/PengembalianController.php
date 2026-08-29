<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\StorePengembalianRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pengembalian;
use App\Models\Spm;
use App\Models\SpmDetail;
use App\Models\User;
use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengembalianController extends Controller
{
    /** Role yang boleh menyetujui pengembalian - HANYA bendahara_pengeluaran (superadmin selalu boleh semua). */
    private const ROLE_BOLEH_SETUJUI = ['superadmin', 'bendahara_pengeluaran'];

    public function index(Request $request)
    {
        $filters = array_merge(['status' => '', 'dokumen_tipe' => '', 'dari' => '', 'sampai' => '', 'cari' => ''], $request->validate([
            'status' => ['nullable', Rule::in([Pengembalian::STATUS_DRAFT, Pengembalian::STATUS_DISETUJUI])],
            'dokumen_tipe' => ['nullable', Rule::in(Pengembalian::TIPE_LIST)],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'cari' => ['nullable', 'string', 'max:255'],
        ]));

        $query = Pengembalian::query()
            ->with(['detail.masterAnggaran', 'dibuatOleh', 'disetujuiOleh'])
            ->orderByDesc('tanggal_pengembalian')
            ->orderByDesc('id');

        $query->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['dokumen_tipe'] !== '', fn ($q) => $q->where('dokumen_tipe', $filters['dokumen_tipe']))
            ->when($filters['dari'] !== '', fn ($q) => $q->whereDate('tanggal_pengembalian', '>=', $filters['dari']))
            ->when($filters['sampai'] !== '', fn ($q) => $q->whereDate('tanggal_pengembalian', '<=', $filters['sampai']));

        if ($filters['cari'] !== '') {
            $cari = $filters['cari'];
            $npdIds = Npd::where('nomor_lengkap', 'like', "%{$cari}%")->pluck('id');
            $spmIds = Spm::where('jenis_spm', 'ls')->where('nomor_dokumen', 'like', "%{$cari}%")->pluck('id');
            $query->where(function ($q) use ($npdIds, $spmIds) {
                $q->where(fn ($q2) => $q2->where('dokumen_tipe', Pengembalian::TIPE_NPD)->whereIn('dokumen_id', $npdIds))
                    ->orWhere(fn ($q2) => $q2->where('dokumen_tipe', Pengembalian::TIPE_SPM_LS)->whereIn('dokumen_id', $spmIds));
            });
        }

        $pengembalian = $query->paginate(25)->withQueryString();

        $npdIdsHalaman = $pengembalian->getCollection()->where('dokumen_tipe', Pengembalian::TIPE_NPD)->pluck('dokumen_id');
        $spmIdsHalaman = $pengembalian->getCollection()->where('dokumen_tipe', Pengembalian::TIPE_SPM_LS)->pluck('dokumen_id');
        $npdMap = Npd::whereIn('id', $npdIdsHalaman)->get(['id', 'nomor_lengkap'])->keyBy('id');
        $spmMap = Spm::whereIn('id', $spmIdsHalaman)->get(['id', 'nomor_dokumen'])->keyBy('id');

        return view('pengembalian.index', [
            'filters' => $filters,
            'pengembalian' => $pengembalian,
            'npdMap' => $npdMap,
            'spmMap' => $spmMap,
            'bolehSetujui' => in_array($request->user()->role, self::ROLE_BOLEH_SETUJUI, true),
        ]);
    }

    public function show(Pengembalian $pengembalian)
    {
        $pengembalian->load(['detail.masterAnggaran', 'dibuatOleh', 'disetujuiOleh']);

        $dokumen = $pengembalian->dokumen();
        $labelDokumen = $pengembalian->dokumen_tipe === Pengembalian::TIPE_NPD
            ? ($dokumen?->nomor_lengkap ?? '(Draft #'.$pengembalian->dokumen_id.')')
            : ($dokumen?->nomor_dokumen ?? '#'.$pengembalian->dokumen_id);

        return view('pengembalian.show', [
            'pengembalian' => $pengembalian,
            'labelDokumen' => $labelDokumen,
        ]);
    }

    public function edit(Pengembalian $pengembalian)
    {
        abort_unless($this->bolehKelolaDraft($pengembalian, request()->user()), 403);
        abort_unless($pengembalian->status === Pengembalian::STATUS_DRAFT, 409);

        $pengembalian->load('detail');

        $dokumen = $pengembalian->dokumen();
        $labelDokumen = $pengembalian->dokumen_tipe === Pengembalian::TIPE_NPD
            ? ($dokumen?->nomor_lengkap ?? '(Draft #'.$pengembalian->dokumen_id.')')
            : ($dokumen?->nomor_dokumen ?? '#'.$pengembalian->dokumen_id);

        $sudahDikembalikan = Pengembalian::sudahDikembalikanPerMataAnggaran(
            $pengembalian->dokumen_tipe,
            $pengembalian->dokumen_id,
            $pengembalian->id
        );
        $nominalAsli = Pengembalian::nominalAsliPerMataAnggaran($pengembalian->dokumen_tipe, $pengembalian->dokumen_id);
        $nominalLama = $pengembalian->detail->pluck('nominal', 'master_anggaran_id')->map(fn ($v) => (float) $v);

        $breakdown = $nominalAsli === []
            ? []
            : collect($nominalAsli)->map(function ($asli, $masterAnggaranId) use ($sudahDikembalikan, $nominalLama) {
                $masterAnggaran = MasterAnggaran::find($masterAnggaranId);
                $sudah = (float) ($sudahDikembalikan[$masterAnggaranId] ?? 0);

                return [
                    'master_anggaran_id' => $masterAnggaranId,
                    'label' => $this->labelMataAnggaran($masterAnggaran),
                    'nominal_asli' => $asli,
                    'sudah_dikembalikan' => $sudah,
                    'sisa' => max(0, $asli - $sudah) + (float) ($nominalLama[$masterAnggaranId] ?? 0),
                    'nominal_lama' => (float) ($nominalLama[$masterAnggaranId] ?? 0),
                ];
            })->values()->all();

        return view('pengembalian.edit', [
            'pengembalian' => $pengembalian,
            'labelDokumen' => $labelDokumen,
            'breakdown' => $breakdown,
        ]);
    }

    public function update(StorePengembalianRequest $request, Pengembalian $pengembalian)
    {
        abort_unless($this->bolehKelolaDraft($pengembalian, $request->user()), 403);
        abort_unless($pengembalian->status === Pengembalian::STATUS_DRAFT, 409);

        $data = $request->validated();
        $pathLama = $pengembalian->dokumen_pendukung;
        $pathBaru = null;

        if ($request->hasFile('dokumen_pendukung')) {
            $file = $request->file('dokumen_pendukung');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $pathBaru = $file->storeAs('pengembalian', Str::uuid().'.'.$ext, 'local');

            if (! $pathBaru) {
                return back()->withInput()->withErrors(['dokumen_pendukung' => 'Dokumen pendukung gagal disimpan pada penyimpanan private.']);
            }

            $data['dokumen_pendukung'] = $pathBaru;
        }

        try {
            $pengembalian->updateDraft($data);
        } catch (RuntimeException $e) {
            if ($pathBaru) {
                Storage::disk('local')->delete($pathBaru);
            }

            return back()->withInput()->withErrors(['baris' => $e->getMessage()]);
        }

        if ($pathBaru && $pathLama) {
            Storage::disk('local')->delete($pathLama);
        }

        AuditLog::catat('Edit Draft Pengembalian', sprintf(
            'Pengembalian #%d (%s #%d), Total: Rp %s',
            $pengembalian->id,
            strtoupper($pengembalian->dokumen_tipe),
            $pengembalian->dokumen_id,
            number_format($pengembalian->totalNominal(), 2, ',', '.')
        ));

        return redirect()->route('pengembalian.index')->with('success', 'Draft pengembalian berhasil diperbarui.');
    }

    public function create()
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        $npdList = Npd::with('masterAnggaran.tagging')
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->orderByDesc('tanggal_npd')
            ->get();

        $spmList = Spm::with('detail.masterAnggaran.tagging')
            ->where('jenis_spm', 'ls')
            ->whereYear('tanggal_dokumen', $tahun)
            ->orderByDesc('tanggal_dokumen')
            ->get();

        return view('pengembalian.create', [
            'dokumenJs' => $this->dokumenJs($npdList, $spmList),
            'bulanList' => AnggaranRealisasiService::BULAN,
        ]);
    }

    public function store(StorePengembalianRequest $request)
    {
        $data = $request->validated();

        $path = null;

        if ($request->hasFile('dokumen_pendukung')) {
            $file = $request->file('dokumen_pendukung');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs('pengembalian', Str::uuid().'.'.$ext, 'local');

            if (! $path) {
                return back()->withInput()->withErrors(['dokumen_pendukung' => 'Dokumen pendukung gagal disimpan pada penyimpanan private.']);
            }
        }

        try {
            // array_merge, BUKAN operator "+": $data hasil validasi masih memuat
            // objek UploadedFile pada kunci yang sama, dan "+" mempertahankan
            // nilai lama sehingga yang tersimpan justru path berkas sementara.
            $pengembalian = Pengembalian::buatDraft(array_merge($data, ['dokumen_pendukung' => $path]), $request->user()->id);
        } catch (RuntimeException $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return back()->withInput()->withErrors(['baris' => $e->getMessage()]);
        }

        AuditLog::catat('Buat Draft Pengembalian', sprintf(
            'Dokumen: %s #%d, Total: Rp %s',
            strtoupper($pengembalian->dokumen_tipe),
            $pengembalian->dokumen_id,
            number_format($pengembalian->totalNominal(), 2, ',', '.')
        ));

        return redirect()->route('pengembalian.index')->with('success', 'Draft pengembalian berhasil disimpan.');
    }

    public function setujui(Request $request, Pengembalian $pengembalian)
    {
        try {
            $pengembalian->setujui($request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['pengembalian' => $e->getMessage()]);
        }

        AuditLog::catat('Setujui Pengembalian', sprintf(
            'Pengembalian #%d (%s #%d), Total: Rp %s',
            $pengembalian->id,
            strtoupper($pengembalian->dokumen_tipe),
            $pengembalian->dokumen_id,
            number_format($pengembalian->totalNominal(), 2, ',', '.')
        ));

        return back()->with('success', 'Pengembalian berhasil disetujui.');
    }

    public function destroy(Request $request, Pengembalian $pengembalian)
    {
        abort_unless($this->bolehKelolaDraft($pengembalian, $request->user()), 403);
        abort_unless($pengembalian->status === Pengembalian::STATUS_DRAFT, 409);

        $path = $pengembalian->dokumen_pendukung;
        $id = $pengembalian->id;
        $pengembalian->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }

        AuditLog::catat('Hapus Draft Pengembalian', 'Pengembalian #'.$id);

        return back()->with('success', 'Draft pengembalian berhasil dihapus.');
    }

    public function unduhDokumenPendukung(Pengembalian $pengembalian): StreamedResponse
    {
        abort_unless(filled($pengembalian->dokumen_pendukung) && Storage::disk('local')->exists($pengembalian->dokumen_pendukung), 404);

        $ext = pathinfo($pengembalian->dokumen_pendukung, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $pengembalian->dokumen_pendukung,
            'dokumen-pendukung-pengembalian-'.$pengembalian->id.($ext ? '.'.$ext : '')
        );
    }

    /** @return array<int, array> */
    private function dokumenJs(Collection $npdList, Collection $spmList): array
    {
        $npdRows = $npdList->map(function (Npd $npd) {
            $m = $npd->masterAnggaran;
            $sudah = Pengembalian::sudahDikembalikanPerMataAnggaran(Pengembalian::TIPE_NPD, $npd->id);
            $sudahNominal = (float) ($sudah[$m->id] ?? 0);

            return [
                'tipe' => Pengembalian::TIPE_NPD,
                'id' => $npd->id,
                'label' => $npd->nomor_lengkap ?? ('(Draft #'.$npd->id.')'),
                'tanggal' => $npd->tanggal_npd->format('d-m-Y'),
                'bulan' => (int) $npd->bulan,
                'program_list' => [$m->program_lengkap],
                'kegiatan_list' => [$m->kegiatan_lengkap],
                'sub_kegiatan_list' => [$m->sub_kegiatan_lengkap],
                'breakdown' => [[
                    'master_anggaran_id' => $m->id,
                    'label' => $this->labelMataAnggaran($m),
                    'nominal_asli' => (float) $npd->nominal,
                    'sudah_dikembalikan' => $sudahNominal,
                    'sisa' => max(0, (float) $npd->nominal - $sudahNominal),
                ]],
            ];
        });

        $spmRows = $spmList->map(function (Spm $spm) {
            $sudah = Pengembalian::sudahDikembalikanPerMataAnggaran(Pengembalian::TIPE_SPM_LS, $spm->id);

            $breakdown = $spm->detail->map(function (SpmDetail $d) use ($sudah) {
                $sudahNominal = (float) ($sudah[$d->master_anggaran_id] ?? 0);

                return [
                    'master_anggaran_id' => $d->master_anggaran_id,
                    'label' => $this->labelMataAnggaran($d->masterAnggaran),
                    'nominal_asli' => (float) $d->nominal,
                    'sudah_dikembalikan' => $sudahNominal,
                    'sisa' => max(0, (float) $d->nominal - $sudahNominal),
                ];
            })->values()->all();

            return [
                'tipe' => Pengembalian::TIPE_SPM_LS,
                'id' => $spm->id,
                'label' => $spm->nomor_dokumen,
                'tanggal' => $spm->tanggal_dokumen->format('d-m-Y'),
                'bulan' => (int) $spm->tanggal_dokumen->month,
                'program_list' => $spm->detail->pluck('masterAnggaran.program')->unique()->values()->all(),
                'kegiatan_list' => $spm->detail->pluck('masterAnggaran.kegiatan')->unique()->values()->all(),
                'sub_kegiatan_list' => $spm->detail->pluck('masterAnggaran.sub_kegiatan')->unique()->values()->all(),
                'breakdown' => $breakdown,
            ];
        });

        return $npdRows->concat($spmRows)->values()->all();
    }

    /** Boleh edit/hapus draft: pembuatnya sendiri atau yang berwenang menyetujui (Bendahara Pengeluaran/superadmin). */
    private function bolehKelolaDraft(Pengembalian $pengembalian, User $user): bool
    {
        return $pengembalian->dibuat_oleh === $user->id || in_array($user->role, self::ROLE_BOLEH_SETUJUI, true);
    }

    private function labelMataAnggaran($masterAnggaran): string
    {
        $label = $masterAnggaran->kode_rekening.' — '.$masterAnggaran->sub_kegiatan_lengkap;

        return $masterAnggaran->tagging?->nama ? $label.' — '.$masterAnggaran->tagging->nama : $label;
    }
}
