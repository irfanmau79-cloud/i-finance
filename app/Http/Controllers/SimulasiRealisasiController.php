<?php

namespace App\Http\Controllers;

use App\Exports\SimulasiRealisasiExport;
use App\Helpers\AuditLog;
use App\Models\MasterAnggaran;
use App\Models\SimulasiRealisasi;
use App\Models\SimulasiRealisasiItem;
use App\Models\SimulasiRealisasiRow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Simulasi Realisasi: memperkirakan capaian anggaran sampai akhir tahun.
 *
 * Saudaranya, Simulasi Pergeseran, mengubah PAGU tiap mata anggaran. Di sini
 * pagunya tetap; yang direncanakan adalah belanja yang BELUM terjadi. Tiap
 * mata anggaran boleh diisi beberapa rencana bernama - misalnya pada tagging
 * "On Call": "Perjalanan dinas ke Cirebon" Rp1.000.000 lalu "Rapat
 * koordinasi" Rp500.000 - sehingga proyeksinya terbaca sebagai daftar
 * rencana, bukan satu angka gelondongan.
 *
 *   Realisasi (Estimasi) = realisasi berjalan + Proyeksi
 *
 * Kolom Proyeksi itulah jumlah seluruh rencana yang diisikan di bawah tiap
 * tagging dan naik ke atas mengikuti pohonnya.
 *
 * Alat what-if murni: tidak pernah menyentuh master_anggaran, NPD, maupun SPM.
 */
class SimulasiRealisasiController extends Controller
{
    public function index()
    {
        return view('analisis.simulasi-realisasi.index', [
            'simulasi' => SimulasiRealisasi::with('user')->latest('updated_at')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('analisis.simulasi-realisasi.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $simulasi = DB::transaction(function () use ($data, $request) {
            $simulasi = SimulasiRealisasi::create([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $master = MasterAnggaran::with('tagging:id,nama')
                ->where('aktif', true)
                ->orderBy('sub_kegiatan')
                ->orderBy('kode_rekening_bersih')
                ->get();

            $sekarang = now();
            $baris = $master->map(fn (MasterAnggaran $m) => [
                'simulasi_realisasi_id' => $simulasi->id,
                'master_anggaran_id' => $m->id,
                'program' => $m->program_lengkap,
                'kegiatan' => $m->kegiatan_lengkap,
                'sub_kegiatan' => $m->sub_kegiatan_lengkap,
                'sub_kegiatan_kunci' => $m->sub_kegiatan_kunci,
                'kode_rekening' => $m->kode_rekening_bersih,
                'uraian_rekening' => $m->uraian_rekening,
                'tagging_nama' => $m->tagging?->nama,
                'pagu' => $m->pagu,
                'proyeksi_total' => 0,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->all();

            if ($baris !== []) {
                SimulasiRealisasiRow::insert($baris);
            }

            $simulasi->update([
                'total_pagu' => (float) $master->sum('pagu'),
                'total_proyeksi' => 0,
            ]);

            return $simulasi;
        });

        AuditLog::catat('Buat Simulasi Realisasi', 'Nama: '.$simulasi->nama);

        return redirect()->route('simulasi-realisasi.show', $simulasi)->with(
            'success',
            'Simulasi baru dibuat dari '.$simulasi->rows()->count().' mata anggaran aktif.'
        );
    }

    public function show(SimulasiRealisasi $simulasiRealisasi)
    {
        [$rows, $tree] = $this->muat($simulasiRealisasi);

        return view('analisis.simulasi-realisasi.show', [
            'simulasiRealisasi' => $simulasiRealisasi,
            'tree' => $tree,
            'total' => $this->totalDari($rows),
        ]);
    }

    /**
     * Menyimpan seluruh rencana sekaligus. Rencana lama dihapus lalu ditulis
     * ulang dari isian formulir: daftarnya dinamis (baris bisa ditambah dan
     * dibuang di layar), sehingga menyamakan per-id justru lebih rapuh
     * daripada menulis ulang satu paket dalam satu transaksi.
     */
    public function update(Request $request, SimulasiRealisasi $simulasiRealisasi)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*' => ['array'],
            'items.*.*.nama' => ['nullable', 'string', 'max:255'],
            'items.*.*.nominal' => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.*.nominal.min' => 'Nominal rencana tidak boleh negatif.',
            'items.*.*.nominal.numeric' => 'Nominal rencana harus berupa angka.',
        ]);

        DB::transaction(function () use ($data, $simulasiRealisasi) {
            $simulasiRealisasi->update([
                'nama' => $data['nama'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $rows = $simulasiRealisasi->rows()->lockForUpdate()->get()->keyBy('id');

            SimulasiRealisasiItem::whereIn('simulasi_realisasi_row_id', $rows->keys())->delete();

            $sekarang = now();
            $baru = [];
            $totalPerRow = [];

            foreach ($data['items'] ?? [] as $rowId => $daftar) {
                if (! $rows->has((int) $rowId) || ! is_array($daftar)) {
                    continue;
                }

                $urutan = 0;
                foreach ($daftar as $item) {
                    $nama = trim((string) ($item['nama'] ?? ''));
                    $nominal = round((float) ($item['nominal'] ?? 0), 2);

                    // Baris kosong adalah sisa formulir, bukan rencana - dilewati
                    // diam-diam supaya pengguna tidak perlu membersihkannya dulu.
                    if ($nama === '' && $nominal <= 0) {
                        continue;
                    }

                    $baru[] = [
                        'simulasi_realisasi_row_id' => (int) $rowId,
                        'nama' => $nama !== '' ? $nama : 'Tanpa nama',
                        'nominal' => $nominal,
                        'urutan' => $urutan++,
                        'created_at' => $sekarang,
                        'updated_at' => $sekarang,
                    ];

                    $totalPerRow[(int) $rowId] = ($totalPerRow[(int) $rowId] ?? 0) + $nominal;
                }
            }

            foreach (array_chunk($baru, 500) as $bagian) {
                SimulasiRealisasiItem::insert($bagian);
            }

            foreach ($rows as $id => $row) {
                $total = round($totalPerRow[$id] ?? 0, 2);
                if ((float) $row->proyeksi_total !== $total) {
                    $row->update(['proyeksi_total' => $total]);
                }
            }

            $simulasiRealisasi->update([
                'total_pagu' => (float) $simulasiRealisasi->rows()->sum('pagu'),
                'total_proyeksi' => array_sum($totalPerRow),
            ]);

            // Kolom "Terakhir Diubah" membaca updated_at. update() di atas tidak
            // selalu cukup: kalau nama, keterangan, dan totalnya kebetulan sama
            // persis, Eloquent tidak menemukan atribut yang berubah dan tidak
            // menjalankan query - stempelnya diam padahal rencananya berubah.
            $simulasiRealisasi->touch();
        });

        AuditLog::catat('Simpan Simulasi Realisasi', 'Nama: '.$simulasiRealisasi->nama);

        return back()->with('success', 'Simulasi realisasi disimpan.');
    }

    public function destroy(SimulasiRealisasi $simulasiRealisasi)
    {
        AuditLog::catat('Hapus Simulasi Realisasi', 'Nama: '.$simulasiRealisasi->nama);
        $simulasiRealisasi->delete();

        return redirect()->route('simulasi-realisasi.index')->with('success', 'Simulasi realisasi dihapus.');
    }

    public function exportExcel(SimulasiRealisasi $simulasiRealisasi)
    {
        AuditLog::catat('Export Excel Simulasi Realisasi', 'Nama: '.$simulasiRealisasi->nama);

        return Excel::download(
            new SimulasiRealisasiExport($simulasiRealisasi),
            'simulasi-realisasi-'.str($simulasiRealisasi->nama)->slug().'.xlsx'
        );
    }

    public function exportPdf(SimulasiRealisasi $simulasiRealisasi)
    {
        [$rows, $tree] = $this->muat($simulasiRealisasi);

        $html = view('analisis.simulasi-realisasi.pdf', [
            'simulasiRealisasi' => $simulasiRealisasi,
            'tree' => $tree,
            'total' => $this->totalDari($rows),
        ])->render();

        $mpdf = new Mpdf([
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Export PDF Simulasi Realisasi', 'Nama: '.$simulasiRealisasi->nama);

        $namaFile = 'simulasi-realisasi-'.str($simulasiRealisasi->nama)->slug().'.pdf';

        return response($mpdf->Output($namaFile, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$namaFile.'"',
        ]);
    }

    /**
     * Baris beserta realisasi terkini dan pohonnya. Dipakai bersama oleh
     * tampilan layar dan cetakan PDF supaya angka keduanya tidak pernah beda.
     *
     * @return array{0: Collection<int, SimulasiRealisasiRow>, 1: array<int, array<string, mixed>>}
     */
    private function muat(SimulasiRealisasi $simulasi): array
    {
        $rows = SimulasiRealisasiRow::lampirkanRealisasi($simulasi->rows()->with('items')->get());

        return [$rows, $this->bangunTree($rows)];
    }

    /** @param  Collection<int, SimulasiRealisasiRow>  $rows */
    private function totalDari(Collection $rows): array
    {
        return $this->ringkas($rows);
    }

    /**
     * Pohon Program > Kegiatan > Sub Kegiatan > Kode Rekening > baris tagging,
     * menyamai susunan Simulasi Pergeseran supaya keduanya dibaca dengan cara
     * yang sama.
     *
     * @param  Collection<int, SimulasiRealisasiRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function bangunTree(Collection $rows): array
    {
        return $rows
            ->groupBy('program')
            ->map(function (Collection $programItems, string $programNama) {
                $kegiatan = $programItems
                    ->groupBy('kegiatan')
                    ->map(function (Collection $kegiatanItems, string $kegiatanNama) {
                        $sub = $kegiatanItems
                            ->groupBy('sub_kegiatan_kunci')
                            ->map(function (Collection $subItems) {
                                $rekening = $subItems
                                    ->groupBy('kode_rekening')
                                    ->map(fn (Collection $item, string $kode) => [
                                        'kode' => $kode,
                                        'uraian' => $item->first()->uraian_rekening,
                                        'baris' => $item->values(),
                                    ] + $this->ringkas($item))
                                    ->values();

                                return [
                                    'nama' => $subItems->first()->sub_kegiatan,
                                    'rekening' => $rekening,
                                ] + $this->ringkas($subItems);
                            })
                            ->values();

                        return ['nama' => $kegiatanNama, 'subKegiatan' => $sub] + $this->ringkas($kegiatanItems);
                    })
                    ->values();

                return ['nama' => $programNama, 'kegiatan' => $kegiatan] + $this->ringkas($programItems);
            })
            ->values()
            ->all();
    }

    /**
     * Angka ringkasan satu simpul.
     *
     *   Sisa Anggaran      = pagu - realisasi        (keadaan hari ini)
     *   Proyeksi           = jumlah rencana yang diisikan
     *   Realisasi Estimasi = realisasi + proyeksi    (perkiraan akhir tahun)
     *   Sisa Estimasi      = pagu - realisasi estimasi
     *
     * @param  Collection<int, SimulasiRealisasiRow>  $items
     */
    private function ringkas(Collection $items): array
    {
        $pagu = (float) $items->sum('pagu');
        $realisasi = (float) $items->sum('realisasi');
        $proyeksi = (float) $items->sum('proyeksi_total');
        $estimasi = $realisasi + $proyeksi;

        return [
            'pagu' => $pagu,
            'realisasi' => $realisasi,
            'sisa_anggaran' => $pagu - $realisasi,
            'proyeksi' => $proyeksi,
            'realisasi_estimasi' => $estimasi,
            'sisa_estimasi' => $pagu - $estimasi,
        ];
    }
}
