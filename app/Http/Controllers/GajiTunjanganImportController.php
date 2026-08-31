<?php

namespace App\Http\Controllers;

use App\Models\GajiImport;
use App\Services\GajiTunjanganImportService;
use App\Support\GajiTunjanganKolom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Import berkas SIPD Gaji Induk / TPP Beban Kerja / TPP Kondisi Kerja:
 * unggah -> staging (preview/dry-run) -> konfirmasi simpan.
 *
 * Jenis penghasilan, bulan, dan tahun dipilih di formulir, bukan diambil dari
 * isi berkas - berkas SIPD memang tidak punya kolom bulan/tahun.
 */
class GajiTunjanganImportController extends Controller
{
    public function __construct(private readonly GajiTunjanganImportService $service) {}

    public function create(): View
    {
        return view('gaji-tunjangan.import.create', [
            'jenisTersedia' => GajiTunjanganKolom::JENIS,
            'namaBulan' => GajiTunjanganKolom::NAMA_BULAN,
            'bulanTerpilih' => (int) now()->month,
            'tahunTerpilih' => (int) now()->year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(GajiTunjanganKolom::JENIS))],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], [
            'jenis' => 'Jenis Penghasilan',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'file' => 'Berkas',
        ]);

        try {
            $import = $this->service->preview(
                $request->file('file'),
                $data['jenis'],
                (int) $data['bulan'],
                (int) $data['tahun'],
                (int) $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('gaji-tunjangan.import.preview', $import);
    }

    public function preview(GajiImport $import): View
    {
        return view('gaji-tunjangan.import.preview', [
            'import' => $import->load('baris'),
        ]);
    }

    public function konfirmasi(GajiImport $import): RedirectResponse
    {
        try {
            $jumlah = $this->service->confirm($import);
        } catch (RuntimeException $e) {
            return back()->withErrors(['konfirmasi' => $e->getMessage()]);
        }

        return redirect()->route('gaji-tunjangan.tabel.'.$import->jenis)
            ->with('success', sprintf(
                '%s %s tersimpan: %d baris.',
                $import->labelJenis(), $import->labelPeriode(), $jumlah
            ));
    }

    /** Buang batch staging yang tidak jadi dipakai. */
    public function batalkan(GajiImport $import): RedirectResponse
    {
        abort_if($import->status === 'committed', 403, 'Batch yang sudah dikonfirmasi tidak dapat dibatalkan.');

        $import->delete();

        return redirect()->route('gaji-tunjangan.import.create')
            ->with('success', 'Berkas import dibatalkan. Tidak ada data yang berubah.');
    }
}
