<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Models\RekonsiliasiKunci;
use App\Models\RekonsiliasiKunciBaris;
use App\Models\User;
use App\Services\GajiTunjanganService;
use App\Services\RekonsiliasiGajiService;
use App\Support\GajiTunjanganKolom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sub-menu "Rekonsiliasi Gaji Induk".
 *
 * Membandingkan Status Tunjangan Keluarga yang DIKUNCI di awal bulan dengan
 * status yang tersirat dari nominal gaji bulan itu, lalu menaksir potensi
 * kelebihan pembayarannya.
 *
 * Penguncian, penyuntingan, dan penghapusan log HANYA untuk superadmin.
 * Inilah pengamannya: pengelola Data Tunjangan Keluarga tidak bisa merapikan
 * status belakangan supaya cocok dengan gaji yang sudah terlanjur cair.
 */
class RekonsiliasiGajiController extends Controller
{
    private const PER_HALAMAN = 10;

    public function __construct(
        private readonly RekonsiliasiGajiService $service,
        private readonly GajiTunjanganService $gaji,
    ) {}

    public function index(Request $request): View
    {
        $tahunTersedia = $this->gaji->tahunTersedia();
        $tahun = (int) ($request->integer('tahun') ?: $tahunTersedia[0]);
        $bulan = min(12, max(1, (int) ($request->integer('bulan') ?: now()->month)));
        $cari = trim((string) $request->query('q', ''));

        $kunci = $this->service->kunciPeriode($bulan, $tahun);
        $baris = $kunci ? $this->service->baris($kunci, $cari) : [];

        return view('gaji-tunjangan.rekonsiliasi', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'cari' => $cari,
            'tahunTersedia' => $tahunTersedia,
            'namaBulan' => GajiTunjanganKolom::NAMA_BULAN,
            'kunci' => $kunci,
            // Ditampilkan sebelum periode dikunci, supaya superadmin tahu
            // tanggal mana yang akan dijadikan acuan.
            'tanggalPenggajian' => $this->service->tanggalPenggajian($bulan, $tahun),
            'baris' => $this->paginasi($baris, $request),
            'ringkasan' => self::ringkasan($baris),
            'bolehKelola' => self::bolehKelola(),
            'pilihanStatus' => self::pilihanStatus(),
        ]);
    }

    /** Potret status seluruh pegawai untuk satu periode. Superadmin saja. */
    public function kunci(Request $request): RedirectResponse
    {
        $this->pastikanBolehKelola();

        $data = $request->validate([
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [], ['bulan' => 'Bulan', 'tahun' => 'Tahun']);

        $bulan = (int) $data['bulan'];
        $tahun = (int) $data['tahun'];

        if ($this->service->kunciPeriode($bulan, $tahun) !== null) {
            return back()->withErrors(['bulan' => 'Periode itu sudah pernah dikunci.']);
        }

        $kunci = $this->service->kunci($bulan, $tahun, $request->user());

        AuditLog::catat(
            'Kunci Rekonsiliasi Gaji Induk',
            sprintf('%s, tanggal penggajian %s, %d pegawai',
                $kunci->labelPeriode(),
                $kunci->tanggal_penggajian->format('d-m-Y'),
                $kunci->baris()->count()),
        );

        return redirect()->route('gaji-tunjangan.rekonsiliasi', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('success', 'Periode '.$kunci->labelPeriode().' dikunci.');
    }

    /**
     * Sunting satu baris log. Wajib disertai alasan, dan siapa yang
     * menyuntingnya ikut dicatat di baris itu maupun di audit log.
     */
    public function sunting(Request $request, RekonsiliasiKunciBaris $baris): RedirectResponse
    {
        $this->pastikanBolehKelola();

        $data = $request->validate([
            'status_tk' => ['required', Rule::in(self::pilihanStatus())],
            'catatan_suntingan' => ['required', 'string', 'max:500'],
        ], [], ['status_tk' => 'Status Tunjangan Keluarga', 'catatan_suntingan' => 'Alasan']);

        [$pasangan, $anak] = RekonsiliasiGajiService::uraiStatus($data['status_tk']);
        $sebelum = $baris->status_tk;

        $baris->update([
            'status_tk' => $data['status_tk'],
            'jumlah_pasangan' => $pasangan,
            'jumlah_anak' => $anak,
            'catatan_suntingan' => $data['catatan_suntingan'],
            'disunting_oleh' => $request->user()?->id,
            'disunting_at' => now(),
        ]);

        AuditLog::catat(
            'Sunting Log Rekonsiliasi Gaji Induk',
            sprintf('%s (%s) %s: %s -> %s | %s',
                $baris->nama, $baris->nip, $baris->kunci->labelPeriode(),
                $sebelum, $baris->status_tk, $data['catatan_suntingan']),
        );

        return redirect()->route('gaji-tunjangan.rekonsiliasi', [
            'bulan' => $baris->kunci->bulan, 'tahun' => $baris->kunci->tahun,
        ])->with('success', 'Log '.$baris->nama.' diperbarui.');
    }

    /** Hapus seluruh potret satu periode sehingga bisa dikunci ulang. */
    public function hapus(RekonsiliasiKunci $kunci): RedirectResponse
    {
        $this->pastikanBolehKelola();

        $label = $kunci->labelPeriode();
        $jumlah = $kunci->baris()->count();
        $bulan = $kunci->bulan;
        $tahun = $kunci->tahun;

        $kunci->delete();

        AuditLog::catat('Hapus Kunci Rekonsiliasi Gaji Induk', sprintf('%s, %d baris log', $label, $jumlah));

        return redirect()->route('gaji-tunjangan.rekonsiliasi', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('success', 'Kunci periode '.$label.' dihapus.');
    }

    /**
     * Ringkasan seluruh baris (bukan hanya halaman yang tampil), supaya
     * angka totalnya tidak menyesatkan saat tabelnya berhalaman.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @return array{pegawai: int, selisih: int, jiwa: int, kelebihan: float}
     */
    private static function ringkasan(array $baris): array
    {
        $selisih = array_values(array_filter($baris, fn (array $r) => $r['selisih_jiwa'] > 0));

        return [
            'pegawai' => count($baris),
            'selisih' => count($selisih),
            'jiwa' => array_sum(array_column($selisih, 'selisih_jiwa')),
            'kelebihan' => array_sum(array_column($baris, 'kelebihan')),
        ];
    }

    /**
     * Status yang boleh dipilih saat menyunting log: TK/K dikali jumlah anak
     * yang berhak (maksimal dua, sama dengan TunjanganKeluargaService).
     *
     * @return array<int, string>
     */
    private static function pilihanStatus(): array
    {
        $maks = (int) config('gaji_tunjangan.rekonsiliasi.maks_anak');
        $pilihan = [];

        foreach (['TK', 'K'] as $kawin) {
            for ($anak = 0; $anak <= $maks; $anak++) {
                $pilihan[] = $kawin.'/'.$anak;
            }
        }

        return $pilihan;
    }

    private function pastikanBolehKelola(): void
    {
        abort_unless(self::bolehKelola(), 403);
    }

    /**
     * Hanya superadmin yang mengunci, menyunting, dan menghapus log.
     * Diperiksa di sini juga, bukan cuma lewat middleware route.
     */
    private static function bolehKelola(): bool
    {
        return GuestSession::role() === User::ROLE_SUPERADMIN;
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginasi(array $baris, Request $request): LengthAwarePaginator
    {
        $halaman = max(1, (int) $request->integer('page', 1));

        return new LengthAwarePaginator(
            array_slice($baris, ($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN),
            count($baris),
            self::PER_HALAMAN,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
