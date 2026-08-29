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
        // Rantai bertingkat untuk dropdown: tiap kombinasi yang benar-benar ada
        // di data. Dropdown di layar menyaring dirinya sendiri dari daftar ini,
        // jadi pilihan yang tidak menghasilkan baris tidak pernah muncul.
        $filterHierarchy = $semua->map(fn (array $row) => [
            'program' => $row['program'],
            'kegiatan' => $row['kegiatan'],
            'sub_kegiatan' => $row['sub_kegiatan'],
            'kode_rekening' => $row['kode_rekening'],
            'kode_label' => $row['uraian_rekening']
                ? "{$row['kode_rekening']} — {$row['uraian_rekening']}"
                : $row['kode_rekening'],
            'tagging' => $row['tagging'],
        ])->unique(fn (array $row) => implode('|', [
            $row['program'], $row['kegiatan'], $row['sub_kegiatan'], $row['kode_rekening'], $row['tagging'],
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
        $lokasi = BantexSpj::query()->where('aktif', true)->orderBy('nomor')->orderBy('nama')->get()
            ->map(function (BantexSpj $bantex) use ($dokumenPerLokasi) {
                // Baris arsip lama tersimpan dengan nama saja; yang baru dengan
                // label bernomor. Keduanya dikumpulkan ke satu bantex - tetapi
                // HANYA digabung bila keduanya memang berbeda. Untuk bantex
                // tanpa nomor, label() sama dengan nama, dan menggabungkannya
                // akan menghitung dokumen yang sama dua kali.
                $items = $dokumenPerLokasi->get($bantex->label(), collect());

                if ($bantex->label() !== $bantex->nama) {
                    $items = $items->concat($dokumenPerLokasi->get($bantex->nama, collect()));
                }

                return [
                    'id' => $bantex->id,
                    'nomor' => $bantex->nomor,
                    'nama' => $bantex->nama,
                    'lokasi' => $bantex->label(),
                    'keterangan' => $bantex->keterangan,
                    'jumlah_dokumen' => $items->count(),
                    'jumlah_npd' => $items->pluck('npd_id')->unique()->count(),
                    'nominal' => (float) $items->unique('npd_id')->sum('nominal'),
                    'dokumen' => $items->values()->all(),
                ];
            });

        $namaMaster = $lokasi->pluck('lokasi')->concat($lokasi->pluck('nama'));
        $lokasiLegacy = $dokumenPerLokasi->reject(fn (Collection $items, string $nama) => $namaMaster->contains($nama))
            ->map(function (Collection $items, string $lokasi) {
                return [
                    'id' => null,
                    'nomor' => null,
                    'nama' => $lokasi,
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

        // KPI dihitung dari SATU BARIS PER NPD (bukan per jenis dokumen),
        // sesuai kartu yang ditampilkan: jumlah NPD, lalu berapa yang SPJ-nya
        // sudah lengkap dan berapa yang belum.
        $jumlahNpd = count($detailSpj);
        $lengkap = collect($detailSpj)->where('status', SpjDetail::STATUS_LENGKAP)->count();
        $persen = fn (int $bagian) => $jumlahNpd > 0 ? round($bagian / $jumlahNpd * 100, 1) : 0.0;

        return [
            'rows' => $rows->all(),
            'lokasi' => $lokasi->all(),
            'kpi' => [
                'jumlah_npd' => $jumlahNpd,
                'lengkap' => $lengkap,
                'lengkap_persen' => $persen($lengkap),
                'belum_lengkap' => $jumlahNpd - $lengkap,
                'belum_lengkap_persen' => $persen($jumlahNpd - $lengkap),
            ],
            'status_list' => SpjDetail::STATUS,
            'bantex' => BantexSpj::query()->where('aktif', true)->orderBy('nomor')->get(['id', 'nomor', 'nama', 'keterangan'])
                ->map(fn (BantexSpj $b) => ['id' => $b->id, 'nomor' => $b->nomor, 'nama' => $b->nama, 'label' => $b->label(), 'keterangan' => $b->keterangan])->all(),
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

    /**
     * Rincian lengkap satu NPD untuk panel Edit - seluruhnya baca saja.
     * Diambil lewat permintaan tersendiri, bukan ditanam di halaman, supaya
     * tabel yang memuat ratusan NPD tidak ikut membawa seluruh anggota tim,
     * penerima, dan data Surat Perintah sekaligus.
     *
     * @return array<string, mixed>
     */
    public function rincianNpd(Npd $npd): array
    {
        $npd->loadMissing([
            'masterAnggaran.tagging', 'penerima.pegawai', 'penerima.vendor',
            'tim.pegawai', 'tim.paket', 'narasumber', 'peserta',
            'suratPerintah.anggota', 'induk.suratPerintah.anggota', 'spjDetail',
        ]);

        $rupiah = fn ($n) => 'Rp '.number_format((float) $n, 2, ',', '.');

        // NPD Transport tidak punya SP sendiri - Surat Perintahnya diwarisi
        // dari NPD Perjalanan Dinas induknya.
        $sp = $npd->jenis === 'tr' && $npd->induk
            ? $npd->induk->suratPerintah
            : $npd->suratPerintah;

        $orang = match ($npd->jenis) {
            'pd', 'tr' => $npd->tim->map(fn ($t) => [
                'nama' => $t->nama,
                'nip' => $t->nip,
                'jabatan' => $t->jabatan,
                'nominal' => $rupiah($t->hitung()['jumlah'] ?? 0),
                'penerima' => (bool) $t->is_penerima,
            ])->values()->all(),
            'ns' => $npd->narasumber->map(fn ($n) => [
                'nama' => $n->nama, 'nip' => null, 'jabatan' => $n->jabatan,
                'nominal' => $rupiah($n->jumlah_jp * $n->tarif_jp), 'penerima' => false,
            ])->values()->all(),
            'kd' => $npd->peserta->map(fn ($p) => [
                'nama' => $p->nama, 'nip' => $p->nip, 'jabatan' => $p->jabatan,
                'nominal' => $rupiah($p->tarif_kontribusi), 'penerima' => false,
            ])->values()->all(),
            default => $npd->penerima->map(fn ($p) => [
                'nama' => $p->nama, 'nip' => null, 'jabatan' => null,
                'nominal' => $rupiah($p->bruto), 'penerima' => true,
            ])->values()->all(),
        };

        return [
            'npd_id' => $npd->id,
            'nomor_npd' => $npd->nomor_lengkap ?: 'NPD #'.$npd->id,
            'tanggal' => $npd->tanggal_npd->locale('id')->translatedFormat('d F Y'),
            'jenis' => Npd::JENIS_LABEL[$npd->jenis] ?? $npd->jenis,
            'status_npd' => $npd->status,
            'nominal' => $rupiah($npd->nominal),
            'terbilang' => $npd->terbilang,
            'program' => $npd->masterAnggaran->programNormal(),
            'kegiatan' => $npd->masterAnggaran->kegiatanNormal(),
            'sub_kegiatan' => $npd->masterAnggaran->subKegiatanNormal(),
            'kode_rekening' => $npd->masterAnggaran->kode_rekening_bersih,
            'uraian_rekening' => $npd->masterAnggaran->uraian_rekening,
            'tagging' => $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging?->nama ?? '-'),
            'uraian' => $npd->detail_json['uraian'] ?? $npd->detail_json['uraian_sp'] ?? $npd->detail_json['keterangan_lampiran'] ?? '-',
            'label_orang' => match ($npd->jenis) {
                'pd', 'tr' => 'Anggota Tim',
                'ns' => 'Narasumber',
                'kd' => 'Peserta',
                default => 'Penerima',
            },
            'orang' => $orang,
            'surat_perintah' => $sp ? [
                'nomor' => $sp->nomor_sp,
                'tanggal' => $sp->tanggal_sp?->locale('id')->translatedFormat('d F Y'),
                'unit_kerja' => $sp->unit_kerja,
                'lokasi' => $sp->lokasi,
                'keterangan' => $sp->keterangan,
                'jumlah_anggota' => $sp->anggota->count(),
                'diwarisi_dari_induk' => $npd->jenis === 'tr' && $npd->induk !== null,
            ] : null,
            'lokasi' => $npd->spjDetail?->lokasi ?? $this->lokasiDefault($npd),
            'status' => $npd->spjDetail?->status ?? SpjDetail::STATUS_BELUM_LENGKAP,
            'catatan' => $npd->spjDetail?->catatan,
        ];
    }

    public static function dikecualikan(Npd $npd): bool
    {
        $sub = (string) $npd->masterAnggaran?->sub_kegiatan_lengkap;

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
            'program' => $npd->masterAnggaran->programNormal(),
            'kegiatan' => $npd->masterAnggaran->kegiatanNormal(),
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
            'program' => $npd->masterAnggaran->programNormal(),
            'kegiatan' => $npd->masterAnggaran->kegiatanNormal(),
            'sub_kegiatan' => $npd->masterAnggaran->subKegiatanNormal(),
            'kode_rekening' => $npd->masterAnggaran->kode_rekening_bersih,
            'tagging' => $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging?->nama ?? ''),
            'jenis_npd' => $npd->jenis,
            'status_label' => SpjDetail::labelStatus($override?->status),
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
