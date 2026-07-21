<?php

namespace App\Services;

use App\Models\Npd;
use App\Support\BidangOrganisasi;
use Illuminate\Support\Collection;

class SpjDashboardService
{
    public function ringkasan(array $filters, int $tahun): array
    {
        $rows = Npd::query()
            ->with(['masterAnggaran', 'suratPerintah', 'dibuatOleh.pegawai', 'spjVerifiedBy'])
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->orderByDesc('tanggal_npd')
            ->get()
            ->filter(fn (Npd $npd) => self::adalahPengawasan($npd))
            ->map(fn (Npd $npd) => $this->baris($npd))
            ->filter(fn (array $row) => $row['bidang'] !== null);

        $pilihanBidang = collect(BidangOrganisasi::PENGAWASAN)->filter(fn (string $bidang) => $rows->contains('bidang', $bidang))->values()->all();
        $filtered = $rows
            ->when($filters['bidang'] ?? '', fn (Collection $items, string $bidang) => $items->where('bidang', $bidang))
            ->when($filters['status'] ?? '', fn (Collection $items, string $status) => $items->where('status_spj', $status))
            ->when($filters['cari'] ?? '', function (Collection $items, string $cari) {
                $needle = mb_strtolower($cari);

                return $items->filter(fn (array $row) => str_contains(mb_strtolower(implode(' ', [
                    $row['nomor_npd'], $row['nomor_sp'], $row['sub_kegiatan'], $row['bidang'], $row['uraian'],
                ])), $needle));
            })->values();

        $perBidang = collect(BidangOrganisasi::PENGAWASAN)->map(function (string $bidang) use ($filtered) {
            $items = $filtered->where('bidang', $bidang);
            $verified = $items->where('status_spj', 'terverifikasi')->count();

            return ['bidang' => $bidang, 'total' => $items->count(), 'terverifikasi' => $verified, 'belum' => $items->count() - $verified, 'persen' => $items->count() ? round($verified / $items->count() * 100, 1) : 0.0];
        })->filter(fn (array $row) => $row['total'] > 0)->values()->all();

        $verified = $filtered->where('status_spj', 'terverifikasi')->count();

        return [
            'tahun' => $tahun,
            'total' => $filtered->count(),
            'terverifikasi' => $verified,
            'belum' => $filtered->count() - $verified,
            'persen' => $filtered->count() ? round($verified / $filtered->count() * 100, 1) : 0.0,
            'nominal' => (float) $filtered->sum('nominal'),
            'bidang' => $perBidang,
            'rows' => $filtered->values()->all(),
            'pilihan_bidang' => $pilihanBidang,
            'kosong' => $filtered->isEmpty(),
        ];
    }

    public static function adalahPengawasan(Npd $npd): bool
    {
        $sub = trim((string) $npd->masterAnggaran?->sub_kegiatan);

        return str_starts_with($sub, '6.01.02') || str_starts_with($sub, '6.01.03');
    }

    public static function bidang(Npd $npd): ?string
    {
        $sumber = $npd->suratPerintah?->unit_kerja
            ?? ($npd->detail_json['unit_kerja'] ?? null)
            ?? ($npd->detail_json['pelaksana'] ?? null)
            ?? $npd->dibuatOleh?->pegawai?->bidang;

        return BidangOrganisasi::petakan($sumber, true);
    }

    private function baris(Npd $npd): array
    {
        return [
            'id' => $npd->id,
            'tanggal' => $npd->tanggal_npd,
            'nomor_npd' => $npd->nomor_lengkap ?: 'NPD #'.$npd->id,
            'nomor_sp' => $npd->suratPerintah?->nomor_sp ?? ($npd->detail_json['nomor_sp'] ?? '-'),
            'sub_kegiatan' => $npd->masterAnggaran->subKegiatanNormal(),
            'uraian' => $npd->detail_json['uraian_sp'] ?? $npd->detail_json['uraian'] ?? '-',
            'bidang' => self::bidang($npd),
            'nominal' => (float) $npd->nominal,
            'status_spj' => $npd->spj_verified_at ? 'terverifikasi' : 'belum',
            'verified_at' => $npd->spj_verified_at,
            'verified_by' => $npd->spjVerifiedBy?->nama,
        ];
    }
}
