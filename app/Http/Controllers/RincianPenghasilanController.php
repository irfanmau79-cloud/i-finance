<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Models\Pegawai;
use App\Models\PenandatanganRincian;
use App\Models\RincianPenghasilan;
use App\Models\User;
use App\Services\GajiTunjanganService;
use App\Services\RincianPenghasilanService;
use App\Support\GajiTunjanganKolom;
use App\Support\MpdfFont;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        $kelolaTtd = self::bolehKelolaPenandatangan();

        return view('gaji-tunjangan.cetak', [
            'pegawai' => $this->gaji->daftarPegawai(),
            'tahunTersedia' => $this->gaji->tahunTersedia(),
            'namaBulan' => GajiTunjanganKolom::NAMA_BULAN,
            'penandatangan' => PenandatanganRincian::query()->aktif()->orderBy('nama')->get(),
            'bolehKelolaTtd' => $kelolaTtd,
            // Panel kelola hanya dirakit untuk yang berhak; role lain tidak
            // ikut menerima daftar pegawai maupun penandatangan non-aktif.
            'semuaTtd' => $kelolaTtd
                ? PenandatanganRincian::query()->orderBy('nama')->get()
                : collect(),
            'pegawaiOpd' => $kelolaTtd
                ? Pegawai::query()->where('aktif', true)->orderBy('nama')
                    ->get(['id', 'nama', 'nip', 'jabatan', 'pangkat'])
                : collect(),
        ]);
    }

    /**
     * Tambah penandatangan, diambil dari Data Pegawai. Hanya superadmin -
     * role lain memilih dari daftar yang sudah disediakan.
     *
     * Nama/jabatan/pangkat disalin ke baris sendiri (bukan dibaca ulang dari
     * Data Pegawai tiap kali) supaya redaksi pada surat bisa disesuaikan dan
     * tidak berubah diam-diam saat data pegawai disunting.
     */
    public function simpanPenandatangan(Request $request): RedirectResponse
    {
        $this->pastikanBolehKelolaPenandatangan();

        // Nama isiannya diberi awalan ttd_ karena form ini satu halaman
        // dengan form cetak yang juga punya field "nama" - tanpa awalan,
        // old() dari form ini akan mengisi ulang Nama Pegawai di atasnya.
        // Galatnya pun ditaruh di kantong 'ttd' supaya tidak tercampur.
        $data = Validator::make($request->all(), [
            'ttd_pegawai_id' => ['required', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'ttd_nama' => ['required', 'string', 'max:255'],
            'ttd_jabatan' => ['required', 'string', 'max:255'],
            'ttd_pangkat' => ['required', 'string', 'max:100'],
        ], [], [
            'ttd_pegawai_id' => 'Pegawai',
            'ttd_nama' => 'Nama',
            'ttd_jabatan' => 'Jabatan',
            'ttd_pangkat' => 'Pangkat',
        ])->validateWithBag('ttd');

        $ttd = PenandatanganRincian::create([
            'pegawai_id' => (int) $data['ttd_pegawai_id'],
            'kunci' => PenandatanganRincian::kunciUnik($data['ttd_nama']),
            'nama' => $data['ttd_nama'],
            'jabatan' => $data['ttd_jabatan'],
            'pangkat' => $data['ttd_pangkat'],
            'aktif' => true,
        ]);

        AuditLog::catat('Tambah Penandatangan Rincian Penghasilan', $ttd->label());

        return redirect()->route('gaji-tunjangan.rincian.create')
            ->with('success', 'Penandatangan '.$ttd->nama.' ditambahkan.');
    }

    /**
     * Hapus penandatangan dari daftar pilihan. Dokumen yang sudah dicetak
     * tidak terpengaruh - identitas penandatangannya sudah dibekukan di
     * rincian_penghasilan.
     */
    public function hapusPenandatangan(PenandatanganRincian $penandatangan): RedirectResponse
    {
        $this->pastikanBolehKelolaPenandatangan();

        $label = $penandatangan->label();
        $penandatangan->delete();

        AuditLog::catat('Hapus Penandatangan Rincian Penghasilan', $label);

        return redirect()->route('gaji-tunjangan.rincian.create')
            ->with('success', 'Penandatangan dihapus dari daftar pilihan.');
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
            'penandatangan' => ['required', Rule::exists('penandatangan_rincian', 'kunci')->where('aktif', true)],
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
    public function index(Request $request): View
    {
        $this->pastikanPengelola();

        // Kotak cari di GAS (gtdRender) menyaring nama & NIP. Di sini
        // penyaringannya di server supaya ikut berlaku lintas halaman.
        $cari = trim((string) $request->query('q', ''));

        $dokumen = RincianPenghasilan::query()
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('nama', 'like', '%'.$cari.'%')->orWhere('nip', 'like', '%'.$cari.'%')
            ))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('gaji-tunjangan.daftar', [
            'dokumen' => $dokumen,
            'cari' => $cari,
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

    private function pastikanBolehKelolaPenandatangan(): void
    {
        abort_unless(self::bolehKelolaPenandatangan(), 403);
    }

    /**
     * Hanya superadmin yang menyusun daftar penandatangan. Diperiksa di sini
     * juga, bukan cuma lewat middleware route, supaya panelnya tidak bisa
     * dipakai dengan menembak endpoint-nya langsung.
     */
    private static function bolehKelolaPenandatangan(): bool
    {
        return GuestSession::role() === User::ROLE_SUPERADMIN;
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
