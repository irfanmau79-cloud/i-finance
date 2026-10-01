<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Pegawai;
use App\Models\PerjalananDinasManual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rincian "Data Perjalanan Dinas" - pintu masuknya lewat judul kartu di
 * Manajemen Data, bukan menu sidebar tersendiri.
 *
 * Isinya rincian per orang per bulan yang MENAMBAL periode sebelum migrasi:
 * NPD-nya sudah masuk lewat Import NPD Historis dan nilainya real, tetapi
 * tanpa rincian anggota tim - padahal itu yang dibaca Dashboard Perjalanan
 * Dinas.
 *
 * Yang tampil di sini HANYA baris manual. Baris yang berasal dari NPD tidak
 * bisa disunting dari sini: mengubahnya berarti mengubah dokumen yang sudah
 * ditandatangani beserta perhitungan anggarannya.
 */
class DataPerjalananDinasController extends Controller
{
    public function index(Request $request): View
    {
        $tahun = (int) ($request->query('tahun') ?: config('anggaran.tahun_aktif'));
        $cari = trim((string) $request->query('cari', ''));

        $baris = PerjalananDinasManual::query()
            ->with('pegawai')
            ->where('tahun', $tahun)
            ->when($cari !== '', fn ($q) => $q->whereHas('pegawai', fn ($qq) => $qq
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('nip', 'like', "%{$cari}%")))
            ->join('pegawai', 'pegawai.id', '=', 'perjalanan_dinas_manual.pegawai_id')
            ->orderBy('pegawai.nama')
            ->orderBy('perjalanan_dinas_manual.bulan')
            ->select('perjalanan_dinas_manual.*')
            ->paginate(25)
            ->withQueryString();

        return view('manajemen-data.rincian.perjalanan-dinas', [
            'baris' => $baris,
            'tahun' => $tahun,
            'cari' => $cari,
            'daftarTahun' => $this->daftarTahun(),
        ]);
    }

    public function create(): View
    {
        return view('manajemen-data.rincian.perjalanan-dinas-form', [
            'data' => null,
            'pegawaiList' => $this->pegawaiList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $baris = PerjalananDinasManual::create($data);

        AuditLog::catat('Tambah Rincian Perjalanan Dinas', sprintf(
            '%s, bulan %d/%d',
            $baris->pegawai->nama,
            $baris->bulan,
            $baris->tahun
        ));

        return redirect()->route('manajemen-data.rincian.perjalanan-dinas', ['tahun' => $baris->tahun])
            ->with('success', 'Rincian perjalanan dinas berhasil ditambahkan.');
    }

    public function edit(PerjalananDinasManual $rincian): View
    {
        return view('manajemen-data.rincian.perjalanan-dinas-form', [
            'data' => $rincian->load('pegawai'),
            'pegawaiList' => $this->pegawaiList(),
        ]);
    }

    public function update(Request $request, PerjalananDinasManual $rincian): RedirectResponse
    {
        $rincian->update($this->validasi($request, $rincian->id));

        AuditLog::catat('Edit Rincian Perjalanan Dinas', sprintf(
            '%s, bulan %d/%d',
            $rincian->pegawai->nama,
            $rincian->bulan,
            $rincian->tahun
        ));

        return redirect()->route('manajemen-data.rincian.perjalanan-dinas', ['tahun' => $rincian->tahun])
            ->with('success', 'Rincian perjalanan dinas berhasil diperbarui.');
    }

    public function destroy(PerjalananDinasManual $rincian): RedirectResponse
    {
        $keterangan = sprintf('%s, bulan %d/%d', $rincian->pegawai?->nama ?? '-', $rincian->bulan, $rincian->tahun);
        $tahun = $rincian->tahun;

        $rincian->delete();

        AuditLog::catat('Hapus Rincian Perjalanan Dinas', $keterangan);

        return redirect()->route('manajemen-data.rincian.perjalanan-dinas', ['tahun' => $tahun])
            ->with('success', 'Rincian perjalanan dinas berhasil dihapus.');
    }

    /**
     * Satu orang hanya boleh punya SATU baris per bulan. Kalau boleh ganda,
     * dashboard akan menjumlahkan dua baris yang sebetulnya periode sama -
     * dan tidak ada yang bisa membedakannya dari dobel input.
     *
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        return $request->validate([
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100',
                Rule::unique('perjalanan_dinas_manual')
                    ->where(fn ($q) => $q->where('pegawai_id', $request->input('pegawai_id'))->where('bulan', $request->input('bulan')))
                    ->ignore($abaikanId),
            ],
            'hari' => ['nullable', 'numeric', 'min:0'],
            'uang_harian' => ['nullable', 'numeric', 'min:0'],
            'akomodasi' => ['nullable', 'numeric', 'min:0'],
            'transport' => ['nullable', 'numeric', 'min:0'],
            'representatif' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'tahun.unique' => 'Pegawai ini sudah punya rincian untuk bulan dan tahun tersebut - sunting baris yang ada, jangan tambah baru.',
        ], [
            'pegawai_id' => 'Pegawai',
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Pegawai> */
    private function pegawaiList()
    {
        return Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip', 'bidang']);
    }

    /** @return array<int, int> */
    private function daftarTahun(): array
    {
        $aktif = (int) config('anggaran.tahun_aktif');
        $tersimpan = PerjalananDinasManual::query()->distinct()->orderByDesc('tahun')->pluck('tahun')->all();

        return collect([$aktif, ...$tersimpan])->unique()->sortDesc()->values()->all();
    }
}
