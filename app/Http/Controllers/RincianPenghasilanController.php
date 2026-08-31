<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Models\RincianPenghasilan;
use App\Services\GajiTunjanganService;
use App\Services\RincianPenghasilanService;
use App\Support\GajiTunjanganKolom;
use App\Support\MpdfFont;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Menu "Cetak Rincian Penghasilan" (semua role, termasuk Pengguna Layanan)
 * dan "Daftar Rincian Penghasilan" (hanya role pengelola).
 *
 * PDF tidak disimpan ke disk: yang tersimpan adalah masukan yang menentukan
 * isinya - nomor, tanggal, periode, nominal PD, dan snapshot penandatangan -
 * sehingga cetak ulang kapan pun menghasilkan dokumen yang sama persis.
 */
class RincianPenghasilanController extends Controller
{
    /**
     * Dokumen yang dibuat dalam sesi ini. Menu "Cetak Rincian Penghasilan"
     * terbuka untuk semua role termasuk Pengguna Layanan tanpa akun, jadi
     * tanpa daftar ini siapa pun bisa menebak /rincian-penghasilan/2/cetak
     * dan membaca surat penghasilan pegawai lain.
     */
    private const SESI_DOKUMEN = 'rp_dokumen_sesi';

    public function __construct(
        private readonly RincianPenghasilanService $service,
        private readonly GajiTunjanganService $gaji,
    ) {}

    public function create(): View
    {
        return view('gaji-tunjangan.cetak', [
            'pegawai' => $this->gaji->daftarPegawai(),
            'tahunTersedia' => $this->gaji->tahunTersedia(),
            'namaBulan' => GajiTunjanganKolom::NAMA_BULAN,
            'penandatangan' => config('gaji_tunjangan.penandatangan'),
        ]);
    }

    /**
     * Uang Harian per bulan untuk pegawai & tahun terpilih. Dipanggil form
     * saat toggle Perjalanan Dinas dinyalakan, pegawai berganti, tahun
     * berganti, atau centang bulan berubah - isian nominalnya read-only.
     */
    public function uangHarian(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periode' => ['array'],
            'periode.*' => ['integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'nominal' => $this->service->uangHarian($data['nip'], (int) $data['tahun'], $data['periode'] ?? []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periode' => ['required', 'array', 'min:1'],
            'periode.*' => ['integer', 'min:1', 'max:12'],
            'penandatangan' => ['required', Rule::in(array_keys(config('gaji_tunjangan.penandatangan')))],
        ], [], [
            'nip' => 'Nama Pegawai',
            'periode' => 'Periode Penghasilan',
            'penandatangan' => 'Penandatangan',
        ]);

        $dokumen = $this->service->simpan(
            [
                'nip' => $data['nip'],
                'nama' => $data['nama'],
                'jabatan' => $data['jabatan'] ?? null,
                'tahun' => (int) $data['tahun'],
                'periode' => $data['periode'],
                'ada_pd' => $request->boolean('ada_pd'),
                'penandatangan' => $data['penandatangan'],
            ],
            auth()->id(),
            auth()->user()?->username ?? 'layanan',
        );

        AuditLog::catat(
            'Cetak Rincian Penghasilan',
            sprintf('Nomor %s - %s (%s), periode %s', $dokumen->nomor, $dokumen->nama, $dokumen->nip, $dokumen->labelPeriode())
        );

        $request->session()->push(self::SESI_DOKUMEN, $dokumen->id);

        return redirect()->route('gaji-tunjangan.rincian.cetak', $dokumen);
    }

    /** Daftar dokumen yang pernah dibuat - hanya untuk role pengelola. */
    public function index(): View
    {
        $this->pastikanPengelola();

        return view('gaji-tunjangan.daftar', [
            'dokumen' => RincianPenghasilan::query()->latest('id')->paginate(20),
        ]);
    }

    /** Bangun ulang PDF dari data tersimpan. */
    public function cetak(RincianPenghasilan $dokumen)
    {
        $this->pastikanBolehMembaca($dokumen);

        $html = view('gaji-tunjangan.pdf.surat', [
            'dokumen' => $dokumen,
            'halaman' => $this->service->halaman($dokumen),
            'logoKop' => $this->berkasLogo('lambang-jabar.png'),
            'logoTte' => $this->berkasLogo('tte-bsre.png'),
        ])->render();

        // A4 margin 15mm semua sisi - perubahan 11 di README_PERUBAHAN.txt.
        $mpdf = new Mpdf(MpdfFont::konfigA4([15, 15, 15, 15]));
        $mpdf->WriteHTML($html);

        $nama = sprintf('KET_PENGHASILAN_%s_%s_%s.pdf', $dokumen->nip, $dokumen->tahun, implode('-', $dokumen->periode));

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nama.'"',
        ]);
    }

    /**
     * Hapus dokumen. Karena nomor berikutnya dihitung dari nomor tertinggi
     * yang masih ada, menghapus membuat nomor sesudahnya mundur satu urutan -
     * perilaku yang sama dengan GAS dan memang dipakai saat uji coba.
     */
    public function destroy(RincianPenghasilan $dokumen): RedirectResponse
    {
        $this->pastikanPengelola();

        $nomor = $dokumen->nomor;
        $this->service->hapus($dokumen);

        AuditLog::catat('Hapus Rincian Penghasilan', 'Nomor '.$nomor);

        return redirect()->route('gaji-tunjangan.rincian.index')
            ->with('success', "Dokumen nomor {$nomor} dihapus.");
    }

    private function pastikanPengelola(): void
    {
        abort_unless(self::pengelola(), 403);
    }

    private static function pengelola(): bool
    {
        return in_array(GuestSession::role(), config('gaji_tunjangan.role_kelola'), true);
    }

    /**
     * Sebuah dokumen hanya boleh dicetak oleh role pengelola - yang memang
     * memegang menu Daftar Rincian Penghasilan - atau oleh sesi yang membuat
     * dokumen itu sendiri.
     *
     * Tanpa penjaga ini, menu Cetak yang sengaja dibuka lebar (semua role,
     * termasuk Pengguna Layanan tanpa akun) berubah menjadi jalan membaca
     * surat penghasilan siapa pun hanya dengan menebak nomor ID di URL.
     */
    private function pastikanBolehMembaca(RincianPenghasilan $dokumen): void
    {
        if (self::pengelola()) {
            return;
        }

        abort_unless(in_array($dokumen->id, session(self::SESI_DOKUMEN, []), true), 403);
    }

    /** Berkas logo untuk mPDF, atau null bila belum disalin ke storage. */
    private function berkasLogo(string $berkas): ?string
    {
        $path = storage_path('app/logo/'.$berkas);

        return file_exists($path) ? $path : null;
    }
}
