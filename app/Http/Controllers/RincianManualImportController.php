<?php

namespace App\Http\Controllers;

use App\Exports\PerjalananDinasManualTemplateExport;
use App\Exports\SpjPerjalananDinasManualTemplateExport;
use App\Helpers\AuditLog;
use App\Models\PerjalananDinasManualImport;
use App\Models\SpjPerjalananDinasManualImport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Import parsial untuk DUA jenis rincian manual: Perjalanan Dinas dan SPJ
 * Perjalanan Dinas.
 *
 * Satu controller melayani keduanya karena alurnya identik - hanya model dan
 * teksnya yang berbeda. Memecahnya jadi dua kelas berarti menyalin enam
 * method yang sama persis, lalu harus ingat memperbaiki keduanya setiap kali
 * alurnya berubah.
 */
class RincianManualImportController extends Controller
{
    private const JENIS = [
        'perjalanan-dinas' => [
            'model' => PerjalananDinasManualImport::class,
            'template' => PerjalananDinasManualTemplateExport::class,
            'judul' => 'Rincian Perjalanan Dinas',
            'berkas_template' => 'template-rincian-perjalanan-dinas.xlsx',
            'rute_rincian' => 'manajemen-data.rincian.perjalanan-dinas',
        ],
        'spj-perjalanan-dinas' => [
            'model' => SpjPerjalananDinasManualImport::class,
            'template' => SpjPerjalananDinasManualTemplateExport::class,
            'judul' => 'Rincian SPJ Perjalanan Dinas',
            'berkas_template' => 'template-rincian-spj-perjalanan-dinas.xlsx',
            'rute_rincian' => 'manajemen-data.rincian.spj-perjalanan-dinas',
        ],
    ];

    public function create(string $jenis)
    {
        $meta = $this->meta($jenis);

        return view('manajemen-data.import.rincian.create', [
            'jenis' => $jenis,
            'meta' => $meta,
            'tahunSekarang' => (int) config('anggaran.tahun_aktif'),
            'petunjuk' => (new $meta['template'])->petunjukKolom(),
            'catatan' => $meta['template']::CATATAN,
        ]);
    }

    public function template(string $jenis)
    {
        $meta = $this->meta($jenis);

        return Excel::download(new $meta['template'], $meta['berkas_template']);
    }

    public function store(Request $request, string $jenis)
    {
        $meta = $this->meta($jenis);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            // Tahun diambil dari formulir, bukan dari kolom berkas - satu
            // berkas tidak boleh setengah menulis ke tahun yang salah.
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ], [], ['file' => 'File Excel', 'tahun' => 'Tahun Anggaran']);

        $meta['model']::bersihkanKedaluwarsa();

        $import = $meta['model']::buatDariUpload($request->file('file'), $request->user()->id, (int) $data['tahun']);

        return redirect()->route('manajemen-data.import.rincian.preview', [$jenis, $import]);
    }

    public function preview(string $jenis, int $import)
    {
        $meta = $this->meta($jenis);
        $batch = $meta['model']::findOrFail($import);

        if ($batch->status === $meta['model']::STATUS_STAGED && $batch->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.rincian.create', $jenis)
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        return view('manajemen-data.import.rincian.preview', [
            'jenis' => $jenis,
            'meta' => $meta,
            'import' => $batch,
            'baris' => $batch->baris()->orderBy('nomor_baris')->paginate(50),
        ]);
    }

    public function konfirmasi(string $jenis, int $import)
    {
        $meta = $this->meta($jenis);
        $batch = $meta['model']::findOrFail($import);

        try {
            $hasil = $batch->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.rincian.preview', [$jenis, $batch])
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import '.$meta['judul'], sprintf(
            'File: %s, Tahun: %d, Baru: %d, Update: %d, Ditolak: %d',
            $batch->nama_file,
            $batch->tahun,
            $hasil['baru'],
            $hasil['update'],
            $batch->fresh()->jumlah_ditolak
        ));

        return redirect()->route($meta['rute_rincian'], ['tahun' => $batch->tahun])->with('success', sprintf(
            'Import %s berhasil: %d baru, %d diperbarui, %d ditolak.',
            $meta['judul'],
            $hasil['baru'],
            $hasil['update'],
            $batch->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(string $jenis, int $import)
    {
        $meta = $this->meta($jenis);
        $batch = $meta['model']::findOrFail($import);

        abort_if($batch->status !== $meta['model']::STATUS_STAGED, 404);

        $batch->delete();

        return redirect()->route('manajemen-data.import.rincian.create', $jenis)
            ->with('success', 'Staging import dibatalkan.');
    }

    /** @return array<string, mixed> */
    private function meta(string $jenis): array
    {
        abort_unless(array_key_exists($jenis, self::JENIS), 404);

        return self::JENIS[$jenis];
    }

    /** Dipakai aturan rute untuk membatasi segmen {jenis}. */
    public static function aturanJenis(): object
    {
        return Rule::in(array_keys(self::JENIS));
    }
}
