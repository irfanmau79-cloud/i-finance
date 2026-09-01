<?php

namespace App\Http\Controllers;

use App\Exports\RealisasiPeriodeExport;
use App\Helpers\AuditLog;
use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Data Realisasi Anggaran per rentang tanggal (Manajemen Data).
 *
 * Menarik realisasi seluruh mata anggaran aktif dirinci Program > Kegiatan >
 * Sub Kegiatan > Kode Rekening > Tagging, dibatasi tanggal awal dan akhir yang
 * dipilih pengguna - misalnya 1 Januari s.d. 31 Agustus, atau 1 s.d. 31
 * Agustus saja. Hasilnya bisa ditampilkan di layar, diunduh sebagai Excel,
 * atau dicetak sebagai PDF.
 *
 * Angkanya SELALU dihitung dari transaksi lewat AnggaranRealisasiService,
 * tidak pernah dibaca dari kolom tersimpan.
 */
class RealisasiPeriodeController extends Controller
{
    public function __construct(private readonly AnggaranRealisasiService $service) {}

    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        return view('manajemen-data.realisasi-periode.index', [
            'dari' => $dari,
            'sampai' => $sampai,
            'hasil' => $this->service->realisasiPeriode($dari, $sampai),
        ]);
    }

    public function excel(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $namaFile = 'realisasi-anggaran-'.$dari.'-sd-'.$sampai.'.xlsx';
        AuditLog::catat('Export Realisasi Anggaran', "Periode: {$dari} s.d. {$sampai}, Format: Excel, File: {$namaFile}");

        return Excel::download(new RealisasiPeriodeExport($this->service, $dari, $sampai), $namaFile);
    }

    public function pdf(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $html = view('manajemen-data.realisasi-periode.pdf', [
            'dari' => $dari,
            'sampai' => $sampai,
            'hasil' => $this->service->realisasiPeriode($dari, $sampai),
        ])->render();

        // A4 melintang: tabelnya tujuh kolom dengan uraian panjang, tidak
        // muat pada orientasi tegak.
        $mpdf = new Mpdf([
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        $namaFile = 'realisasi-anggaran-'.$dari.'-sd-'.$sampai.'.pdf';
        AuditLog::catat('Export Realisasi Anggaran', "Periode: {$dari} s.d. {$sampai}, Format: PDF, File: {$namaFile}");

        return response($mpdf->Output($namaFile, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$namaFile.'"',
        ]);
    }

    /**
     * Rentang tanggal yang dipakai ketiga aksi di atas, supaya tampilan
     * layar dan berkas unduhannya tidak pernah memakai periode berbeda.
     *
     * Bawaannya 1 Januari tahun anggaran aktif sampai hari ini - periode yang
     * paling sering dicari - dan tetap bisa dipersempit ke satu bulan saja.
     *
     * @return array{0: string, 1: string}
     */
    private function rentang(Request $request): array
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
        ]);

        $dari = $data['dari'] ?? $tahun.'-01-01';
        $sampai = $data['sampai'] ?? now()->format('Y-m-d');

        return [
            Carbon::parse($dari)->format('Y-m-d'),
            Carbon::parse($sampai)->format('Y-m-d'),
        ];
    }
}
