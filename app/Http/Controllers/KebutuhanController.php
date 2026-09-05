<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\StoreKebutuhanAnggaranRequest;
use App\Models\KebutuhanAnggaran;
use App\Services\KebutuhanAnggaranService;
use App\Support\BidangOrganisasi;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Estimasi Kebutuhan Kegiatan Pengawasan - dua halaman:
 *
 *  - index()  Data Kebutuhan Anggaran Pengawasan. Perencanaan, Bendahara,
 *             dan Superadmin melihat SELURUH unit (hanya baca). Irban hanya
 *             melihat unitnya sendiri, dan hanya di sana tombol Hapus muncul.
 *  - create() Formulir input, khusus Irban. Unit Kerjanya tidak diisi -
 *             melekat pada role yang login.
 *
 * Penyaringan per unit dilakukan di kueri, bukan di tampilan: kalau hanya
 * tombolnya yang disembunyikan, data unit lain tetap terkirim ke browser.
 */
class KebutuhanController extends Controller
{
    private const PER_HALAMAN = 10;

    public function index(Request $request)
    {
        $unitRole = BidangOrganisasi::unitRole($request->user()?->role);

        $baris = KebutuhanAnggaran::query()
            ->tahun()
            ->with('rincian')
            ->when($unitRole !== null, fn ($q) => $q->where('unit_kerja', $unitRole))
            ->get()
            ->sortBy(fn (KebutuhanAnggaran $k) => [BidangOrganisasi::urutanPkpt($k->unit_kerja), $k->created_at?->timestamp ?? 0])
            ->values();

        $halaman = max(1, (int) $request->query('page', 1));
        $totalHalaman = max(1, (int) ceil($baris->count() / self::PER_HALAMAN));
        $halaman = min($halaman, $totalHalaman);

        return view('analisis.kebutuhan.index', [
            'baris' => new LengthAwarePaginator(
                $baris->slice(($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)->values(),
                $baris->count(),
                self::PER_HALAMAN,
                $halaman,
                ['path' => $request->url(), 'query' => $request->query()]
            ),
            'jumlah' => $baris->count(),
            'totalEstimasi' => (float) $baris->sum('total_estimasi'),
            'unitRole' => $unitRole,
            'tahun' => (int) config('anggaran.tahun_aktif'),
        ]);
    }

    public function create(Request $request, KebutuhanAnggaranService $service)
    {
        $unit = BidangOrganisasi::unitRole($request->user()?->role);

        // Kunci menu keb-input hanya diberikan ke role Irban, jadi ini pagar
        // kedua - bukan jalur yang biasa terpakai.
        abort_if($unit === null, 403, 'Formulir ini khusus Inspektur Pembantu (Irban).');

        return view('analisis.kebutuhan.create', [
            'unit' => $unit,
            'bahan' => $service->bahanFormulir($unit),
            'tahun' => (int) config('anggaran.tahun_aktif'),
        ]);
    }

    public function store(StoreKebutuhanAnggaranRequest $request, KebutuhanAnggaranService $service)
    {
        $unit = BidangOrganisasi::unitRole($request->user()->role);
        abort_if($unit === null, 403, 'Hanya Inspektur Pembantu (Irban) yang dapat menyimpan kebutuhan anggaran.');

        $jumlah = $service->simpan($request->user(), $unit, $request->validated()['kegiatan']);

        AuditLog::catat('Simpan Kebutuhan Anggaran', "Unit: {$unit}, Kegiatan: {$jumlah}");

        return redirect()->route('kebutuhan.index')
            ->with('success', "{$jumlah} kegiatan kebutuhan anggaran berhasil disimpan.");
    }

    public function destroy(Request $request, KebutuhanAnggaran $kebutuhan)
    {
        $unit = BidangOrganisasi::unitRole($request->user()?->role);

        abort_if($unit === null, 403, 'Hanya Inspektur Pembantu (Irban) yang dapat menghapus data kebutuhan.');
        abort_if($kebutuhan->unit_kerja !== $unit, 403, 'Anda hanya dapat menghapus data unit Anda sendiri.');

        $keterangan = $kebutuhan->keteranganTampil();
        $kebutuhan->delete();

        AuditLog::catat('Hapus Kebutuhan Anggaran', "Unit: {$unit}, Kegiatan: {$keterangan}");

        return redirect()->route('kebutuhan.index')->with('success', 'Data kebutuhan anggaran dihapus.');
    }
}
