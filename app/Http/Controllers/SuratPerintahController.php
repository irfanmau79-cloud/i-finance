<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratPerintahRequest;
use App\Http\Requests\UpdateSuratPerintahRequest;
use App\Models\SuratPerintah;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class SuratPerintahController extends Controller
{
    public function index()
    {
        $suratPerintahs = SuratPerintah::orderBy('tanggal_sp', 'desc')->get();

        return view('surat-perintah.index', compact('suratPerintahs'));
    }

    public function exportPdf()
    {
        $suratPerintahs = SuratPerintah::orderBy('tanggal_sp', 'desc')->get();

        $html = view('surat-perintah.pdf', compact('suratPerintahs'))->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'orientation' => 'L',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'default_font' => 'arial',
        ]);

        $mpdf->WriteHTML($html);

        $fileName = 'daftar-sp-'.now()->format('Ymd').'.pdf';

        return response($mpdf->Output($fileName, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function create()
    {
        return view('surat-perintah.create');
    }

    public function store(StoreSuratPerintahRequest $request)
    {
        $data = $request->validated();

        $data['file_url'] = $request->file('file_url')->store('sp', 'public');

        SuratPerintah::create($data);

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil disimpan.');
    }

    public function edit(SuratPerintah $suratPerintah)
    {
        return view('surat-perintah.edit', compact('suratPerintah'));
    }

    public function update(UpdateSuratPerintahRequest $request, SuratPerintah $suratPerintah)
    {
        $data = $request->validated();

        if ($request->hasFile('file_url')) {
            Storage::disk('public')->delete($suratPerintah->file_url);
            $data['file_url'] = $request->file('file_url')->store('sp', 'public');
        } else {
            unset($data['file_url']);
        }

        $suratPerintah->update($data);

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil diperbarui.');
    }

    public function destroy(SuratPerintah $suratPerintah)
    {
        Storage::disk('public')->delete($suratPerintah->file_url);
        $suratPerintah->delete();

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil dihapus.');
    }
}
