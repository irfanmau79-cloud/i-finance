<?php

namespace App\Services;

use App\Models\GajiInduk;
use App\Models\Pegawai;
use App\Models\RekonsiliasiKunci;
use App\Models\RekonsiliasiKunciBaris;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi Gaji Induk: membandingkan Status Tunjangan Keluarga yang
 * dikunci di awal bulan dengan status yang TERSIRAT dari nominal gaji bulan
 * itu, lalu menaksir potensi kelebihan pembayarannya.
 *
 * Status penggajian tidak pernah diambil dari kolom status mana pun - tidak
 * ada kolom seperti itu di berkas SIPD. Ia dibalik dari nominal yang benar-
 * benar dibayarkan: tunjangan pasangan 10% x gaji pokok dan tunjangan anak
 * 2% x gaji pokok per anak. Jadi yang dibandingkan adalah "siapa yang berhak"
 * versus "siapa yang benar-benar dibayar".
 */
class RekonsiliasiGajiService
{
    public function __construct(
        private readonly TunjanganKeluargaService $tunjangan,
    ) {}

    /**
     * Tanggal penggajian: tanggal 1, digeser ke hari berikutnya selama masih
     * jatuh pada Sabtu, Minggu, atau hari libur di config.
     */
    public function tanggalPenggajian(int $bulan, int $tahun): CarbonImmutable
    {
        $libur = array_keys(config('gaji_tunjangan.hari_libur', []));
        $tanggal = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();

        // Batas 31 langkah sekadar penjaga: satu bulan tidak mungkin libur
        // seluruhnya, tetapi config yang salah isi jangan sampai menggantung
        // permintaan.
        for ($i = 0; $i < 31; $i++) {
            if (! $tanggal->isWeekend() && ! in_array($tanggal->format('Y-m-d'), $libur, true)) {
                return $tanggal;
            }

            $tanggal = $tanggal->addDay();
        }

        return CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();
    }

    public function kunciPeriode(int $bulan, int $tahun): ?RekonsiliasiKunci
    {
        return RekonsiliasiKunci::query()->where('tahun', $tahun)->where('bulan', $bulan)->first();
    }

    /**
     * Potret status seluruh pegawai aktif untuk satu periode.
     *
     * Dipanggil hanya oleh superadmin (dijaga di controller). Status per
     * pegawai dihitung dengan TunjanganKeluargaService::statusTunjangan()
     * memakai tanggal penggajian sebagai acuan, jadi batas usia anak dinilai
     * pada hari itu - bukan hari ketika halaman dibuka.
     */
    public function kunci(int $bulan, int $tahun, ?User $user): RekonsiliasiKunci
    {
        return DB::transaction(function () use ($bulan, $tahun, $user) {
            $tanggal = $this->tanggalPenggajian($bulan, $tahun);

            $kunci = RekonsiliasiKunci::create([
                'bulan' => $bulan,
                'tahun' => $tahun,
                'tanggal_penggajian' => $tanggal->toDateString(),
                'dikunci_oleh' => $user?->id,
                'dikunci_oleh_nama' => $user?->nama ?? $user?->username,
                'dikunci_at' => now(),
            ]);

            $pegawai = Pegawai::query()
                ->with('tunjanganKeluarga.anggota')
                ->where('aktif', true)
                ->orderBy('nama')
                ->get();

            foreach ($pegawai as $orang) {
                $status = $this->tunjangan->statusTunjangan($orang->tunjanganKeluarga, $tanggal);
                [$pasangan, $anak] = self::uraiStatus($status);

                RekonsiliasiKunciBaris::create([
                    'kunci_id' => $kunci->id,
                    'pegawai_id' => $orang->id,
                    'nama' => $orang->nama,
                    'nip' => $orang->nip,
                    'status_tk' => $status,
                    'jumlah_pasangan' => $pasangan,
                    'jumlah_anak' => $anak,
                ]);
            }

            return $kunci;
        });
    }

    /**
     * Baris tabel rekonsiliasi: potret status dipasangkan dengan gaji induk
     * periode yang sama berdasarkan NIP.
     *
     * @return array<int, array<string, mixed>>
     */
    public function baris(RekonsiliasiKunci $kunci, string $cari = ''): array
    {
        $gaji = GajiInduk::query()
            ->where('bulan', $kunci->bulan)
            ->where('tahun', $kunci->tahun)
            ->get()
            ->keyBy(fn (GajiInduk $g) => self::normalNip($g->nip));

        $hasil = [];

        foreach ($kunci->baris()->orderBy('nama')->get() as $baris) {
            $penggajian = $this->statusPenggajian($gaji->get(self::normalNip($baris->nip)));

            // Hanya selisih yang merugikan negara yang dinilai rupiah: kalau
            // penggajian justru membayar lebih sedikit jiwa, itu kekurangan
            // bayar, bukan kelebihan.
            $selisihPasangan = max(0, $penggajian['pasangan'] - $baris->jumlah_pasangan);
            $selisihAnak = max(0, $penggajian['anak'] - $baris->jumlah_anak);

            $hasil[] = [
                'baris' => $baris,
                'nama' => $baris->nama,
                'nip' => $baris->nip,
                'status_tk' => $baris->status_tk,
                'status_penggajian' => $penggajian['status'],
                'ada_gaji' => $penggajian['ada'],
                'gaji_pokok' => $penggajian['pokok'],
                'selisih_pasangan' => $selisihPasangan,
                'selisih_anak' => $selisihAnak,
                'selisih_jiwa' => $selisihPasangan + $selisihAnak,
                'kelebihan' => $this->potensiKelebihan($penggajian['pokok'], $selisihPasangan, $selisihAnak),
            ];
        }

        if ($cari !== '') {
            $kata = mb_strtolower($cari);
            $hasil = array_values(array_filter($hasil, fn (array $r) => str_contains(mb_strtolower($r['nama']), $kata)
                || str_contains(mb_strtolower((string) $r['nip']), $kata)));
        }

        return $hasil;
    }

    /**
     * Status yang tersirat dari nominal gaji: pasangan dibaca dari ada
     * tidaknya perhitungan suami/istri, jumlah anak dibalik dari nominal
     * tunjangan anak dibagi (2% x gaji pokok).
     *
     * Jumlah anak sengaja TIDAK dibatasi dua di sini. Kalau penggajian
     * ternyata membayar tiga anak, itu justru temuan yang harus terlihat -
     * membatasinya akan menyembunyikan kelebihan bayarnya.
     *
     * @return array{ada: bool, status: string, pasangan: int, anak: int, pokok: float}
     */
    public function statusPenggajian(?GajiInduk $gaji): array
    {
        if ($gaji === null) {
            return ['ada' => false, 'status' => '-', 'pasangan' => 0, 'anak' => 0, 'pokok' => 0.0];
        }

        $persenAnak = (float) config('gaji_tunjangan.rekonsiliasi.persen_anak');
        $pokok = (float) $gaji->belanja_gaji_pokok;

        $pasangan = ((float) $gaji->perhitungan_suami_istri) > 0 ? 1 : 0;

        $anak = 0;
        if ($pokok > 0 && $persenAnak > 0) {
            $anak = max(0, (int) round(((float) $gaji->perhitungan_anak) / ($persenAnak * $pokok)));
        }

        return [
            'ada' => true,
            'status' => ($pasangan ? 'K' : 'TK').'/'.$anak,
            'pasangan' => $pasangan,
            'anak' => $anak,
            'pokok' => $pokok,
        ];
    }

    /**
     * Potensi kelebihan pembayaran: tunjangan tiap jiwa yang kelebihan,
     * ditambah tunjangan beras yang nilainya sama untuk setiap jiwa.
     */
    public function potensiKelebihan(float $pokok, int $selisihPasangan, int $selisihAnak): float
    {
        $rekon = config('gaji_tunjangan.rekonsiliasi');
        $jiwa = $selisihPasangan + $selisihAnak;

        if ($jiwa <= 0) {
            return 0.0;
        }

        return $selisihPasangan * (float) $rekon['persen_pasangan'] * $pokok
            + $selisihAnak * (float) $rekon['persen_anak'] * $pokok
            + $jiwa * (float) $rekon['beras_per_jiwa'];
    }

    /**
     * "K/1" -> [1, 1]; "TK/0" -> [0, 0].
     *
     * @return array{0: int, 1: int}
     */
    public static function uraiStatus(string $status): array
    {
        $bagian = explode('/', $status);
        $pasangan = ($bagian[0] ?? '') === 'K' ? 1 : 0;

        return [$pasangan, max(0, (int) ($bagian[1] ?? 0))];
    }

    private static function normalNip(mixed $nip): string
    {
        return trim(preg_replace('/\s+/', '', (string) $nip) ?? '');
    }
}
