<?php

namespace App\Http\Controllers;

use App\Services\PkptService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Monitoring PKPT - halaman baca-saja di bawah grup Analisis dan Tren.
 *
 * Penyaringan dan paginasi dikerjakan di server (pola sama dengan Dashboard
 * SPJ Perjalanan Dinas) supaya halaman tetap berguna tanpa JavaScript dan
 * tautannya bisa dibagikan apa adanya. Agregat kartu & batang per unit
 * SENGAJA dihitung dari data penuh, bukan dari hasil filter: angkanya adalah
 * capaian PKPT setahun, dan ikut menyusut saat orang menyaring tabel justru
 * menyesatkan.
 */
class PkptController extends Controller
{
    private const PER_HALAMAN = 10;

    public function index(Request $request, PkptService $pkpt)
    {
        $ringkasan = $pkpt->ringkasan();

        $filters = [
            'area' => trim((string) $request->query('area', '')),
            'unit' => trim((string) $request->query('unit', '')),
            'periode' => trim((string) $request->query('periode', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $rows = collect($ringkasan['rows'])->filter(function (array $r) use ($filters) {
            foreach (['area' => 'area', 'unit' => 'unit', 'periode' => 'rencana', 'status' => 'status'] as $filter => $kolom) {
                if ($filters[$filter] !== '' && $r[$kolom] !== $filters[$filter]) {
                    return false;
                }
            }

            return true;
        })->values();

        return view('analisis.pkpt', [
            'kartu' => $ringkasan['kartu'],
            'perUnit' => $ringkasan['perUnit'],
            'opsi' => $ringkasan['filterOpts'],
            'filters' => $filters,
            'jumlahTersaring' => $rows->count(),
            'baris' => $this->halaman($rows, $request),
            'tahun' => (int) config('anggaran.tahun_aktif'),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function halaman(Collection $rows, Request $request): LengthAwarePaginator
    {
        $halaman = max(1, (int) $request->query('page', 1));
        $total = $rows->count();
        $totalHalaman = max(1, (int) ceil($total / self::PER_HALAMAN));
        $halaman = min($halaman, $totalHalaman);

        return new LengthAwarePaginator(
            $rows->slice(($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)->values(),
            $total,
            self::PER_HALAMAN,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
