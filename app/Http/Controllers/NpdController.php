<?php

namespace App\Http\Controllers;

use App\Models\Npd;
use Illuminate\Http\Request;

class NpdController extends Controller
{
    public function index(Request $request)
    {
        $query = Npd::with('masterAnggaran')->orderBy('tanggal_npd', 'desc');

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $npds = $query->paginate(30)->withQueryString();

        return view('npd.index', compact('npds'));
    }

    public function show(Npd $npd)
    {
        $npd->load(['masterAnggaran.tagging', 'penerima.pphList', 'dibuatOleh']);

        return view('npd.show', compact('npd'));
    }
}
