<?php

namespace App\Services;

use App\Models\Npd;
use Illuminate\Support\Collection;

class InventarisasiSpjService
{
    public const JENIS_DOKUMEN = ['NPD', 'Lampiran NPD', 'Daftar Bayar', 'SPD Rampung', 'Dokumen Pendukung'];

    public function data(array $filters): array
    {
        $npds = Npd::query()
            ->with(['masterAnggaran.tagging', 'penerima', 'tim', 'narasumber', 'peserta', 'arsipSpjAktif'])
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

        $lokasi = $rows->groupBy('lokasi')->map(function (Collection $items, string $lokasi) {
            return [
                'lokasi' => $lokasi,
                'jumlah_dokumen' => $items->count(),
                'jumlah_npd' => $items->pluck('npd_id')->unique()->count(),
                'nominal' => (float) $items->unique('npd_id')->sum('nominal'),
                'dokumen' => $items->values()->all(),
            ];
        })->sortKeys()->values()->all();

        return [
            'rows' => $rows->all(),
            'lokasi' => $lokasi,
            'pilihan' => $pilihan,
            'jumlah_dokumen' => $rows->count(),
            'jumlah_lokasi' => $rows->pluck('lokasi')->unique()->count(),
            'total_nominal' => (float) $rows->unique('npd_id')->sum('nominal'),
            'kosong' => $rows->isEmpty(),
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
            'kode_rekening' => $npd->masterAnggaran->kode_rekening,
            'tagging' => $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging?->nama ?? ''),
            'uraian' => $npd->detail_json['uraian'] ?? $npd->detail_json['uraian_sp'] ?? $npd->detail_json['keterangan_lampiran'] ?? '-',
            'nominal' => (float) $npd->nominal,
            'penerima' => $penerima ?: '-',
            'lokasi' => $lokasi,
            'catatan_arsip' => $catatan,
        ];
    }
}
