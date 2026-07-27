<?php

namespace App\Services;

use App\Models\BantexSpj;
use App\Models\Npd;
use App\Models\SpjDetail;
use App\Support\BidangOrganisasi;
use Illuminate\Support\Collection;

class InventarisasiSpjService
{
    public const JENIS_DOKUMEN = ['NPD', 'Lampiran NPD', 'Daftar Bayar', 'SPD Rampung', 'Dokumen Pendukung'];

    public function data(array $filters): array
    {
        $npds = Npd::query()
            ->with([
                'masterAnggaran.tagging', 'penerima.pegawai', 'penerima.vendor',
                'tim.pegawai', 'narasumber.pegawai', 'narasumber.vendor', 'peserta.pegawai',
                'suratPerintah', 'induk.suratPerintah', 'arsipSpj', 'arsipSpjAktif', 'spjDetail',
            ])
            ->where('status', 'Selesai')
            ->orderByDesc('tanggal_npd')
            ->get()
            ->reject(fn (Npd $npd) => self::dikecualikan($npd));

        $semua = $npds->flatMap(fn (Npd $npd) => $this->dokumen($npd));
        $pilihan = [
            'bulan' => $semua->pluck('bulan')->unique()->sort()->values()->all(),
            'sub_kegiatan' => $semua->pluck('sub_kegiatan')->unique()->sort()->values()->all(),
            'kode_rekening' => $semua->pluck('kode_rekening')->unique()->sort()->values()->all(),
            'tagging' => $semua->pluck('tagging')->filter()->unique()->sort()->values()->all(),
        ];
        $pilihanBerlabel = [
            'sub_kegiatan' => collect($pilihan['sub_kegiatan'])->map(fn (string $v) => ['value' => $v, 'label' => $v])->all(),
            'kode_rekening' => $semua->groupBy('kode_rekening')->map(function (Collection $items, string $kode) {
                $uraian = $items->first()['uraian_rekening'] ?? null;

                return ['value' => $kode, 'label' => $uraian ? "{$kode} — {$uraian}" : $kode];
            })->sortBy('value', SORT_NATURAL)->values()->all(),
            'tagging' => collect($pilihan['tagging'])->map(fn (string $v) => ['value' => $v, 'label' => $v])->all(),
        ];
        $filterHierarchy = $semua->map(fn (array $row) => [
            'sub_kegiatan' => $row['sub_kegiatan'],
            'kode_rekening' => $row['kode_rekening'],
            'kode_label' => $row['uraian_rekening']
                ? "{$row['kode_rekening']} — {$row['uraian_rekening']}"
                : $row['kode_rekening'],
            'tagging' => $row['tagging'],
        ])->unique(fn (array $row) => implode('|', [
            $row['sub_kegiatan'], $row['kode_rekening'], $row['tagging'],
        ]))->values()->all();

        $rows = $semua
            ->when($filters['bulan'] ?? '', fn (Collection $items, string $value) => $items->where('bulan', (int) $value))
            ->when($filters['sub_kegiatan'] ?? '', fn (Collection $items, string $value) => $items->where('sub_kegiatan', $value))
            ->when($filters['kode_rekening'] ?? '', fn (Collection $items, string $value) => $items->where('kode_rekening', $value))
            ->when($filters['tagging'] ?? '', fn (Collection $items, string $value) => $items->where('tagging', $value))
            ->when($filters['cari'] ?? '', function (Collection $items, string $value) {
                $needle = mb_strtolower($value);

                return $items->filter(fn (array $row) => str_contains(mb_strtolower(implode(' ', [
                    $row['nomor_npd'], $row['jenis_dokumen'], $row['penerima'], $row['uraian'], $row['lokasi'], $row['sub_kegiatan'],
                ])), $needle));
            })->values();

        $dokumenPerLokasi = $rows->groupBy('lokasi');
        $lokasi = BantexSpj::query()->where('aktif', true)->orderBy('nama')->get()
            ->map(function (BantexSpj $bantex) use ($dokumenPerLokasi) {
                $items = $dokumenPerLokasi->get($bantex->nama, collect());

                return [
                    'id' => $bantex->id,
                    'lokasi' => $bantex->nama,
                    'keterangan' => $bantex->keterangan,
                    'jumlah_dokumen' => $items->count(),
                    'jumlah_npd' => $items->pluck('npd_id')->unique()->count(),
                    'nominal' => (float) $items->unique('npd_id')->sum('nominal'),
                    'dokumen' => $items->values()->all(),
                ];
            });

        $namaMaster = $lokasi->pluck('lokasi');
        $lokasiLegacy = $dokumenPerLokasi->reject(fn (Collection $items, string $nama) => $namaMaster->contains($nama))
            ->map(function (Collection $items, string $lokasi) {
            return [
                'id' => null,
                'lokasi' => $lokasi,
                'keterangan' => null,
                'jumlah_dokumen' => $items->count(),
                'jumlah_npd' => $items->pluck('npd_id')->unique()->count(),
                'nominal' => (float) $items->unique('npd_id')->sum('nominal'),
                'dokumen' => $items->values()->all(),
            ];
        })->values();
        $lokasi = $lokasi->concat($lokasiLegacy)->values();

        $jumlahLokasi = $lokasi->count();

        $detailSpj = $this->detailSpj($npds, $filters);

        return [
            'rows' => $rows->all(),
            'lokasi' => $lokasi->all(),
            'bantex' => BantexSpj::query()->where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'keterangan'])->all(),
            'pilihan' => $pilihan,
            'pilihan_berlabel' => $pilihanBerlabel,
            'filter_hierarchy' => $filterHierarchy,
            'jumlah_dokumen' => $rows->count(),
            'jumlah_lokasi' => $jumlahLokasi,
            'total_nominal' => (float) $rows->unique('npd_id')->sum('nominal'),
            'rata_rata_dokumen_per_bantex' => $jumlahLokasi > 0 ? round($rows->count() / $jumlahLokasi, 1) : 0.0,
            'kosong' => $rows->isEmpty(),
            'detail_spj' => $detailSpj,
            'bidang_list' => BidangOrganisasi::SPJ,
        ];
    }

    public static function dikecualikan(Npd $npd): bool
    {
        $sub = (string) $npd->masterAnggaran?->sub_kegiatan;

        return str_contains($sub, '6.01.01.1.02.0001')
            || (bool) preg_match('/penyediaan\s+gaji\s+dan\s+tunjangan\s+asn/iu', $sub);
    }

    private function dokumen(Npd $npd): Collection
    {
        $arsip = $npd->arsipSpjAktif;
        if ($arsip->isEmpty()) {
            return collect([$this->baris($npd, 'NPD', '(Tanpa Lokasi)', null)]);
        }

        return $arsip->map(fn ($item) => $this->baris($npd, $item->jenis_dokumen, $item->lokasi, $item->catatan));
    }

    private function baris(Npd $npd, string $jenis, string $lokasi, ?string $catatan): array
    {
        $penerima = match ($npd->jenis) {
            'pd', 'tr' => $npd->tim->firstWhere('is_penerima', true)?->nama ?? $npd->tim->first()?->nama,
            'ns' => $npd->narasumber->pluck('nama')->join(', '),
            'kd' => $npd->peserta->pluck('nama')->join(', '),
            default => $npd->penerima->pluck('nama')->join(', '),
        };

        return [
            'id' => $npd->id.'|'.$jenis,
            'npd_id' => $npd->id,
            'tanggal' => $npd->tanggal_npd->format('Y-m-d'),
            'bulan' => (int) $npd->tanggal_npd->month,
            'bulan_label' => $npd->tanggal_npd->locale('id')->translatedFormat('F'),
            'nomor_npd' => $npd->nomor_lengkap ?: 'NPD #'.$npd->id,
            'jenis_dokumen' => $jenis,
            'sub_kegiatan' => $npd->masterAnggaran->subKegiatanNormal(),
            'kode_rekening' => $npd->masterAnggaran->kode_rekening_bersih,
            'uraian_rekening' => $npd->masterAnggaran->uraian_rekening,
            'tagging' => $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging?->nama ?? ''),
            'uraian' => $npd->detail_json['uraian'] ?? $npd->detail_json['uraian_sp'] ?? $npd->detail_json['keterangan_lampiran'] ?? '-',
            'nominal' => (float) $npd->nominal,
            'penerima' => $penerima ?: '-',
            'lokasi' => $lokasi,
            'catatan_arsip' => $catatan,
        ];
    }

    /**
     * "Tabel Detail SPJ": SATU baris per NPD (bukan per jenis dokumen seperti
     * $rows di atas). Kolom hasil hitung (Bulan/Nomor SP/Nominal/Koordinator/
     * Bidang/Uraian/Lokasi) bisa ditimpa manual lewat App\Models\SpjDetail -
     * kalau ada override, dipakai; kalau tidak, dihitung dari data NPD.
     *
     * @param  Collection<int, Npd>  $npds
     */
    private function detailSpj(Collection $npds, array $filters): array
    {
        $rows = $npds->map(fn (Npd $npd) => $this->detailBaris($npd));

        return $rows
            ->when($filters['bulan'] ?? '', fn (Collection $items, string $value) => $items->where('bulan', (int) $value))
            ->when($filters['sub_kegiatan'] ?? '', fn (Collection $items, string $value) => $items->where('sub_kegiatan', $value))
            ->when($filters['kode_rekening'] ?? '', fn (Collection $items, string $value) => $items->where('kode_rekening', $value))
            ->when($filters['tagging'] ?? '', fn (Collection $items, string $value) => $items->where('tagging', $value))
            ->when($filters['cari'] ?? '', function (Collection $items, string $value) {
                $needle = mb_strtolower($value);

                return $items->filter(fn (array $row) => str_contains(mb_strtolower(implode(' ', [
                    $row['nomor_sp'], $row['nomor_npd'], $row['koordinator'], $row['bidang'], $row['uraian'], $row['lokasi'],
                ])), $needle));
            })
            ->values()
            ->all();
    }

    private function detailBaris(Npd $npd): array
    {
        $override = $npd->spjDetail;
        [$koordinatorDefault, $bidangDefault] = $this->koordinatorDanBidang($npd);
        $uraianDefault = $npd->detail_json['uraian'] ?? $npd->detail_json['uraian_sp'] ?? $npd->detail_json['keterangan_lampiran'] ?? '-';

        $bulan = (int) ($override?->bulan ?? $npd->tanggal_npd->month);

        return [
            'npd_id' => $npd->id,
            'bulan' => $bulan,
            'bulan_label' => now()->setMonth($bulan)->locale('id')->translatedFormat('F'),
            'nomor_sp' => $override?->nomor_sp ?? $this->nomorSp($npd),
            'nomor_npd' => $npd->nomor_lengkap ?: 'NPD #'.$npd->id,
            'nominal' => (float) ($override?->nominal ?? $npd->nominal),
            'koordinator' => $override?->koordinator ?? $koordinatorDefault ?? '-',
            'bidang' => $override?->bidang ?? $bidangDefault,
            'uraian' => $override?->uraian ?? $uraianDefault,
            'lokasi' => $override?->lokasi ?? $this->lokasiDefault($npd),
            'status' => $override?->status ?? SpjDetail::STATUS_BELUM_LENGKAP,
            'catatan' => $override?->catatan,
            'sub_kegiatan' => $npd->masterAnggaran->subKegiatanNormal(),
            'kode_rekening' => $npd->masterAnggaran->kode_rekening_bersih,
            'tagging' => $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging?->nama ?? ''),
            'ada_override' => $override !== null,
            'diedit_oleh' => $override?->dieditOleh?->nama,
            'diedit_at' => $override?->diedit_at?->format('d-m-Y H:i'),
        ];
    }

    /**
     * Koordinator (penerima) dan Bidang default satu NPD. Bidang diambil
     * dari data Pegawai penerima (dipetakan ke salah satu dari 7 bidang
     * lewat BidangOrganisasi::petakan()); kalau penerimanya Vendor atau
     * tidak ada Pegawai yang cocok, Bidang default "Sekretariat".
     *
     * @return array{0: ?string, 1: string}
     */
    private function koordinatorDanBidang(Npd $npd): array
    {
        [$nama, $pegawai, $adaVendor] = match ($npd->jenis) {
            'bj' => (function () use ($npd) {
                $p = $npd->penerima->first();

                return [$p?->nama, $p?->pegawai, $p?->vendor_id !== null];
            })(),
            'pd', 'tr' => (function () use ($npd) {
                $t = $npd->tim->firstWhere('is_penerima', true) ?? $npd->tim->first();

                return [$t?->nama, $t?->pegawai, false];
            })(),
            'ns' => (function () use ($npd) {
                $n = $npd->narasumber->first();

                return [$n?->nama, $n?->pegawai, $n?->vendor_id !== null];
            })(),
            'kd' => (function () use ($npd) {
                $p = $npd->peserta->first();

                return [$p?->nama, $p?->pegawai, false];
            })(),
            default => [null, null, false],
        };

        if ($adaVendor) {
            return [$nama, 'Sekretariat'];
        }

        $bidang = $pegawai ? BidangOrganisasi::petakan($pegawai->bidang) : null;
        $bidang = in_array($bidang, BidangOrganisasi::SPJ, true) ? $bidang : 'Sekretariat';

        return [$nama, $bidang];
    }

    /** NPD Transport ('tr') tidak punya SP sendiri - warisi dari NPD Perjalanan Dinas induknya. */
    private function nomorSp(Npd $npd): ?string
    {
        if ($npd->jenis === 'tr' && $npd->induk) {
            return $npd->induk->suratPerintah?->nomor_sp ?? ($npd->induk->detail_json['nomor_sp'] ?? null);
        }

        return $npd->suratPerintah?->nomor_sp ?? ($npd->detail_json['nomor_sp'] ?? null);
    }

    /** Lokasi default dari arsip_spj aktif (utamakan jenis dokumen "NPD") - lihat NPD show > Lokasi Arsip SPJ. */
    private function lokasiDefault(Npd $npd): ?string
    {
        $aktif = $npd->arsipSpj->where('aktif', true);

        return ($aktif->firstWhere('jenis_dokumen', 'NPD') ?? $aktif->first())?->lokasi;
    }
}
