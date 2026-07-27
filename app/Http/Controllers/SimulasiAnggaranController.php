<?php

namespace App\Http\Controllers;

use App\Exports\SimulasiAnggaranExport;
use App\Helpers\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\SimulasiAnggaran;
use App\Models\SimulasiAnggaranRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Simulasi Pergeseran/Perubahan Anggaran: alat what-if murni (tidak pernah
 * mengubah master_anggaran yang asli) untuk mencoba geser/ubah Pagu per mata
 * anggaran dan langsung lihat total dampaknya, sebelum benar-benar diajukan
 * lewat jalur resmi (import Master Anggaran). Setiap simulasi tersimpan
 * dengan nama sendiri (bisa banyak, bisa dibuka lagi), dan setiap baris
 * menyimpan SNAPSHOT label (program/kegiatan/sub_kegiatan/kode_rekening/
 * uraian/tagging) supaya simulasi lama tetap konsisten walau data master
 * aslinya berubah setelahnya.
 */
class SimulasiAnggaranController extends Controller
{
    public function index()
    {
        $simulasi = SimulasiAnggaran::with('user')->latest()->paginate(15);

        return view('analisis.simulasi.index', compact('simulasi'));
    }

    public function create()
    {
        return view('analisis.simulasi.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $simulasi = DB::transaction(function () use ($data, $request) {
            $simulasi = SimulasiAnggaran::create([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $master = MasterAnggaran::with('tagging:id,nama')
                ->where('aktif', true)
                ->orderBy('sub_kegiatan')
                ->orderBy('kode_rekening_bersih')
                ->get();

            $now = now();
            $rows = $master->map(fn (MasterAnggaran $m) => [
                'simulasi_anggaran_id' => $simulasi->id,
                'master_anggaran_id' => $m->id,
                'program' => $m->program,
                'kegiatan' => $m->kegiatan,
                'sub_kegiatan' => $m->sub_kegiatan,
                'sub_kegiatan_kunci' => $m->sub_kegiatan_kunci,
                'kode_rekening' => $m->kode_rekening_bersih,
                'uraian_rekening' => $m->uraian_rekening,
                'tagging_nama' => $m->tagging?->nama,
                'pagu_eksisting' => $m->pagu,
                'pagu_simulasi' => $m->pagu,
                'selisih' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows !== []) {
                SimulasiAnggaranRow::insert($rows);
            }

            $totalPagu = (float) $master->sum('pagu');
            $simulasi->update([
                'total_pagu_eksisting' => $totalPagu,
                'total_pagu_simulasi' => $totalPagu,
                'total_selisih' => 0,
            ]);

            return $simulasi;
        });

        AuditLog::catat('Buat Simulasi Anggaran', 'Nama: '.$simulasi->nama);

        return redirect()->route('simulasi-anggaran.show', $simulasi)
            ->with('success', 'Simulasi baru dibuat dari '.$simulasi->rows()->count().' mata anggaran aktif.');
    }

    public function show(SimulasiAnggaran $simulasiAnggaran)
    {
        $rows = SimulasiAnggaranRow::lampirkanRealisasi($simulasiAnggaran->rows);
        $tree = $this->bangunTree($rows);

        return view('analisis.simulasi.show', [
            'simulasiAnggaran' => $simulasiAnggaran,
            'tree' => $tree,
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, SimulasiAnggaran $simulasiAnggaran)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'rows' => ['required', 'array'],
            'rows.*' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $simulasiAnggaran) {
            $simulasiAnggaran->update([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $rowsById = $simulasiAnggaran->rows()->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($data['rows'] as $rowId => $paguSimulasiInput) {
                $row = $rowsById->get((int) $rowId);
                if (! $row) {
                    continue;
                }

                $paguBaru = round((float) $paguSimulasiInput, 2);

                if ($paguBaru < (float) $row->pagu_eksisting && $row->master_anggaran_id) {
                    $master = MasterAnggaran::find($row->master_anggaran_id);
                    if ($master) {
                        $minimum = $master->danaTerikatNpd() + $master->realisasiLs();
                        if ($paguBaru < $minimum) {
                            $errors[] = "{$row->kode_rekening} {$row->uraian_rekening}: tidak boleh di bawah Rp "
                                .number_format($minimum, 2, ',', '.').' (dana terikat NPD + realisasi LS berjalan).';

                            continue;
                        }
                    }
                }

                $row->pagu_simulasi = $paguBaru;
                $row->selisih = $paguBaru - (float) $row->pagu_eksisting;
                $row->save();
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(['rows' => $errors]);
            }

            $fresh = $simulasiAnggaran->rows()->get();
            $simulasiAnggaran->update([
                'total_pagu_eksisting' => $fresh->sum('pagu_eksisting'),
                'total_pagu_simulasi' => $fresh->sum('pagu_simulasi'),
                'total_selisih' => $fresh->sum('selisih'),
            ]);
        });

        AuditLog::catat('Simpan Simulasi Anggaran', 'Nama: '.$simulasiAnggaran->nama);

        return back()->with('success', 'Simulasi disimpan.');
    }

    public function destroy(SimulasiAnggaran $simulasiAnggaran)
    {
        AuditLog::catat('Hapus Simulasi Anggaran', 'Nama: '.$simulasiAnggaran->nama);
        $simulasiAnggaran->delete();

        return redirect()->route('simulasi-anggaran.index')->with('success', 'Simulasi dihapus.');
    }

    public function exportExcel(SimulasiAnggaran $simulasiAnggaran)
    {
        AuditLog::catat('Export Excel Simulasi Anggaran', 'Nama: '.$simulasiAnggaran->nama);

        $namaFile = 'simulasi-anggaran-'.str($simulasiAnggaran->nama)->slug().'.xlsx';

        return Excel::download(new SimulasiAnggaranExport($simulasiAnggaran), $namaFile);
    }

    public function exportPdf(SimulasiAnggaran $simulasiAnggaran)
    {
        $rows = SimulasiAnggaranRow::lampirkanRealisasi($simulasiAnggaran->rows);
        $tree = $this->bangunTree($rows);

        $html = view('analisis.simulasi.pdf', [
            'simulasiAnggaran' => $simulasiAnggaran,
            'tree' => $tree,
        ])->render();

        $mpdf = new Mpdf([
            'format' => 'A4-L',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Export PDF Simulasi Anggaran', 'Nama: '.$simulasiAnggaran->nama);

        $fileName = 'simulasi-anggaran-'.str($simulasiAnggaran->nama)->slug().'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /** Kelompokkan baris flat jadi tree Program > Kegiatan > Sub Kegiatan > Kode Rekening > Tagging. */
    private function bangunTree($rows): array
    {
        $tree = $rows
            ->groupBy('program')
            ->map(function ($programItems, string $programNama) {
                $kegiatan = $programItems
                    ->groupBy('kegiatan')
                    ->map(function ($kegiatanItems, string $kegiatanNama) {
                        $subKegiatan = $kegiatanItems
                            ->groupBy('sub_kegiatan_kunci')
                            ->map(function ($subItems) {
                                $rekening = $subItems->groupBy('kode_rekening')->map(function ($rekeningItems, string $kode) {
                                    return [
                                        'kode' => $kode,
                                        'uraian' => $rekeningItems->first()->uraian_rekening,
                                        'baris' => $rekeningItems->values(),
                                        'eksisting' => (float) $rekeningItems->sum('pagu_eksisting'),
                                        'realisasi' => (float) $rekeningItems->sum('realisasi'),
                                        'simulasi' => (float) $rekeningItems->sum('pagu_simulasi'),
                                        'selisih' => (float) $rekeningItems->sum('selisih'),
                                    ];
                                })->values();

                                return [
                                    'nama' => $subItems->first()->sub_kegiatan,
                                    'rekening' => $rekening,
                                    'eksisting' => (float) $subItems->sum('pagu_eksisting'),
                                    'realisasi' => (float) $subItems->sum('realisasi'),
                                    'simulasi' => (float) $subItems->sum('pagu_simulasi'),
                                    'selisih' => (float) $subItems->sum('selisih'),
                                ];
                            })
                            ->values();

                        return [
                            'nama' => $kegiatanNama,
                            'subKegiatan' => $subKegiatan,
                            'eksisting' => (float) $kegiatanItems->sum('pagu_eksisting'),
                            'realisasi' => (float) $kegiatanItems->sum('realisasi'),
                            'simulasi' => (float) $kegiatanItems->sum('pagu_simulasi'),
                            'selisih' => (float) $kegiatanItems->sum('selisih'),
                        ];
                    })
                    ->values();

                return [
                    'nama' => $programNama,
                    'kegiatan' => $kegiatan,
                    'eksisting' => (float) $programItems->sum('pagu_eksisting'),
                    'realisasi' => (float) $programItems->sum('realisasi'),
                    'simulasi' => (float) $programItems->sum('pagu_simulasi'),
                    'selisih' => (float) $programItems->sum('selisih'),
                ];
            })
            ->values();

        return $tree->all();
    }
}
