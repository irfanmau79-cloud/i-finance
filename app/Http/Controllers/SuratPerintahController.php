<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratPerintahRequest;
use App\Models\SuratPerintah;

class SuratPerintahController extends Controller
{
    public function index()
    {
        $suratPerintahs = SuratPerintah::orderBy('tanggal_sp', 'desc')->get();

        return view('surat-perintah.index', compact('suratPerintahs'));
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
}
