<?php

namespace App\Http\Controllers;

use App\Models\Npd;
use App\Models\SpjBerkas;
use App\Services\SpjBerkasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unggah, lihat, dan hapus berkas SPJ satu NPD.
 *
 * Pintu unggah TERSENDIRI di luar formulir NPD, dipakai halaman detail NPD
 * dan modul Inventarisasi SPJ: keduanya menempelkan SPJ ke NPD yang SUDAH
 * ADA, jadi tidak ada gunanya menumpang formulir pembuatan NPD. Unggahan
 * yang menyertai formulir pembuatan NPD ditangani controller jenis NPD
 * masing-masing lewat service yang sama.
 */
class SpjBerkasController extends Controller
{
    public function __construct(private readonly SpjBerkasService $service) {}

    public function store(Request $request, Npd $npd): RedirectResponse|JsonResponse
    {
        // Divalidasi lewat Validator, BUKAN $request->validate(): aplikasi ini
        // sengaja hanya merender galat sebagai JSON untuk path api/*
        // (bootstrap/app.php: shouldRenderJsonWhen), sehingga
        // $request->validate() di rute web selalu berakhir sebagai redirect.
        // Panel Inventarisasi SPJ mengunggah lewat fetch, dan fetch MENGIKUTI
        // redirect lalu membacanya sebagai berhasil - berkas yang ditolak
        // akan terlihat seolah tersimpan. Jadi galatnya dibentuk di sini.
        $validator = Validator::make(
            $request->all(),
            SpjBerkasService::aturan(),
            [],
            SpjBerkasService::labelIsian(),
        );

        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()], 422)
                : back()->withErrors($validator);
        }

        $data = $validator->validated();
        $dikirim = count($data['spj'] ?? []);
        $jumlah = $this->service->simpan($npd, $data['spj'] ?? [], $request->user());

        if ($jumlah === 0) {
            return $this->galat($request, 'Tidak ada berkas SPJ yang tersimpan. Batas '
                .SpjBerkasService::MAKS_BERKAS.' berkas per NPD kemungkinan sudah penuh.');
        }

        // Kuota bisa habis di tengah kiriman (mis. sudah ada 19 berkas, lalu
        // mengirim 5). Yang tersimpan tetap disimpan, tapi pemakai HARUS
        // diberi tahu bahwa tidak semuanya masuk - kalau tidak, ia
        // menganggap lembar yang hilang sudah terunggah.
        if ($jumlah < $dikirim) {
            return $this->galat($request, sprintf(
                '%d dari %d berkas tersimpan. Sisanya tidak masuk karena batas %d berkas per NPD sudah tercapai.',
                $jumlah,
                $dikirim,
                SpjBerkasService::MAKS_BERKAS,
            ));
        }

        $pesan = $jumlah.' berkas SPJ berhasil diunggah.';

        return $request->expectsJson()
            ? response()->json(['message' => $pesan, 'jumlah' => $jumlah])
            : back()->with('success', $pesan);
    }

    /**
     * Pesan galat yang sama untuk dua pemanggil yang berbeda: halaman detail
     * NPD mengirim formulir biasa (butuh redirect + session), panel
     * Inventarisasi SPJ memakai fetch (butuh 422 + JSON, karena redirect akan
     * diikuti fetch dan terbaca sebagai berhasil).
     */
    private function galat(Request $request, string $pesan): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $pesan, 'errors' => ['spj' => [$pesan]]], 422)
            : back()->withErrors(['spj' => $pesan]);
    }

    /**
     * Tampilkan berkas di tab peramban (inline), bukan diunduh: yang dicari
     * pemakai hampir selalu "lihat SPJ-nya", bukan menyimpan salinan kedua.
     *
     * Berkasnya duduk di disk 'local' (storage/app/private) yang tidak bisa
     * diakses langsung dari web, jadi satu-satunya jalan masuk adalah rute
     * ini - dan rute ini sudah dijaga middleware peran.
     */
    public function show(Npd $npd, SpjBerkas $berkas): StreamedResponse
    {
        abort_unless($berkas->npd_id === $npd->id, 404);
        abort_unless(Storage::disk('local')->exists($berkas->path), 404);

        return Storage::disk('local')->response(
            $berkas->path,
            $berkas->nama_asli,
            ['Content-Type' => $berkas->mime, 'Content-Disposition' => 'inline; filename="'.addslashes($berkas->nama_asli).'"'],
        );
    }

    public function destroy(Npd $npd, SpjBerkas $berkas): RedirectResponse
    {
        abort_unless($berkas->npd_id === $npd->id, 404);

        $this->service->hapus($berkas);

        return back()->with('success', 'Berkas SPJ berhasil dihapus.');
    }
}
