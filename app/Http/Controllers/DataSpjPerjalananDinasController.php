<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\SpjPerjalananDinasManual;
use App\Support\BidangOrganisasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rincian "Data SPJ Perjalanan Dinas" - pintu masuknya lewat judul kartu di
 * Manajemen Data, bukan menu sidebar tersendiri.
 *
 * Satu baris = satu DOKUMEN, bentuknya mengikuti SpjDashboardService::baris()
 * supaya bisa disambung apa adanya dengan baris yang berasal dari NPD.
 *
 * Catatan pembeda dari Inventarisasi SPJ: modul itu bekerja atas NPD untuk
 * SELURUH jenis dan dikelola operatornya sendiri. Yang di sini khusus
 * dokumen perjalanan dinas periode sebelum migrasi yang tidak punya padanan
 * baris di tabel npd.
 */
class DataSpjPerjalananDinasController extends Controller
{
    public function index(Request $request): View
    {
        $tahun = (int) ($request->query('tahun') ?: config('anggaran.tahun_aktif'));
        $cari = trim((string) $request->query('cari', ''));

        $baris = SpjPerjalananDinasManual::query()
            ->where('tahun', $tahun)
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('nomor_npd', 'like', "%{$cari}%")
                ->orWhere('nomor_sp', 'like', "%{$cari}%")
                ->orWhere('uraian', 'like', "%{$cari}%")
                ->orWhere('bidang', 'like', "%{$cari}%")))
            ->orderByDesc('tanggal')
            ->orderBy('nomor_npd')
            ->paginate(25)
            ->withQueryString();

        return view('manajemen-data.rincian.spj-perjalanan-dinas', [
            'baris' => $baris,
            'tahun' => $tahun,
            'cari' => $cari,
            'daftarTahun' => $this->daftarTahun(),
        ]);
    }

    public function create(): View
    {
        return view('manajemen-data.rincian.spj-perjalanan-dinas-form', ['data' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baris = SpjPerjalananDinasManual::create($this->validasi($request));

        AuditLog::catat('Tambah Rincian SPJ Perjalanan Dinas', 'Dokumen: '.$baris->nomor_npd);

        return redirect()->route('manajemen-data.rincian.spj-perjalanan-dinas', ['tahun' => $baris->tahun])
            ->with('success', 'Rincian SPJ perjalanan dinas berhasil ditambahkan.');
    }

    public function edit(SpjPerjalananDinasManual $rincian): View
    {
        return view('manajemen-data.rincian.spj-perjalanan-dinas-form', ['data' => $rincian]);
    }

    public function update(Request $request, SpjPerjalananDinasManual $rincian): RedirectResponse
    {
        $rincian->update($this->validasi($request, $rincian->id));

        AuditLog::catat('Edit Rincian SPJ Perjalanan Dinas', 'Dokumen: '.$rincian->nomor_npd);

        return redirect()->route('manajemen-data.rincian.spj-perjalanan-dinas', ['tahun' => $rincian->tahun])
            ->with('success', 'Rincian SPJ perjalanan dinas berhasil diperbarui.');
    }

    public function destroy(SpjPerjalananDinasManual $rincian): RedirectResponse
    {
        $nomor = $rincian->nomor_npd;
        $tahun = $rincian->tahun;

        $rincian->delete();

        AuditLog::catat('Hapus Rincian SPJ Perjalanan Dinas', 'Dokumen: '.$nomor);

        return redirect()->route('manajemen-data.rincian.spj-perjalanan-dinas', ['tahun' => $tahun])
            ->with('success', 'Rincian SPJ perjalanan dinas berhasil dihapus.');
    }

    /**
     * Bidang dibatasi kosakata yang sama dengan yang dipakai dashboard
     * (BidangOrganisasi::PENGAWASAN). Kalau diketik bebas, barisnya akan
     * jatuh ke luar pengelompokan per bidang dan seolah hilang.
     *
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'tanggal' => ['required', 'date'],
            'nomor_npd' => ['required', 'string', 'max:100',
                Rule::unique('spj_perjalanan_dinas_manual', 'nomor_npd')
                    ->where(fn ($q) => $q->where('tahun', $request->input('tahun')))
                    ->ignore($abaikanId),
            ],
            'nomor_sp' => ['nullable', 'string', 'max:100'],
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'uraian' => ['nullable', 'string', 'max:2000'],
            'bidang' => ['required', Rule::in(BidangOrganisasi::PENGAWASAN)],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal_verifikasi' => ['nullable', 'date'],
            'diverifikasi_oleh' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'nomor_npd.unique' => 'Nomor dokumen ini sudah ada untuk tahun tersebut.',
        ], [
            'nomor_npd' => 'Nomor Dokumen',
            'bidang' => 'Bidang',
        ]);

        $data['spj_terverifikasi'] = $request->boolean('spj_terverifikasi');

        // Tanggal & nama verifikator hanya berarti bila SPJ-nya memang sudah
        // terverifikasi; menyimpannya pada baris yang belum terverifikasi
        // meninggalkan jejak yang membingungkan saat dibaca ulang.
        if (! $data['spj_terverifikasi']) {
            $data['tanggal_verifikasi'] = null;
            $data['diverifikasi_oleh'] = null;
        }

        return $data;
    }

    /** @return array<int, int> */
    private function daftarTahun(): array
    {
        $aktif = (int) config('anggaran.tahun_aktif');
        $tersimpan = SpjPerjalananDinasManual::query()->distinct()->orderByDesc('tahun')->pluck('tahun')->all();

        return collect([$aktif, ...$tersimpan])->unique()->sortDesc()->values()->all();
    }
}
