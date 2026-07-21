<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\NpdTim;
use App\Support\BidangOrganisasi;
use Illuminate\Support\Collection;

class PerjalananDinasDashboardService
{
    private const METRICS = ['hari', 'uang_harian', 'akomodasi', 'transport', 'representatif', 'diterima'];

    public function ringkasan(array $filters, int $tahun): array
    {
        $anggota = Npd::query()
            ->with(['tim.paket', 'tim.pegawai'])
            ->whereIn('jenis', ['pd', 'tr'])
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->orderBy('tanggal_npd')
            ->get()
            ->flatMap(fn (Npd $npd) => $npd->tim->map(fn (NpdTim $tim) => $this->baris($npd, $tim)))
            ->filter(fn (array $row) => $row['bidang'] !== null);

        $pilihanBidang = collect(BidangOrganisasi::PERJALANAN)
            ->filter(fn (string $bidang) => $anggota->contains('bidang', $bidang))->values()->all();
        $pilihanPegawai = $anggota->unique('pegawai_key')->sortBy('nama')->map(fn (array $row) => [
            'value' => $row['pegawai_key'], 'label' => $row['nama'], 'bidang' => $row['bidang'],
        ])->values()->all();

        $filtered = $anggota
            ->when($filters['bidang'] ?? '', fn (Collection $rows, string $bidang) => $rows->where('bidang', $bidang))
            ->when($filters['pegawai'] ?? '', fn (Collection $rows, string $pegawai) => $rows->where('pegawai_key', $pegawai))
            ->values();

        $metric = in_array($filters['metrik'] ?? null, self::METRICS, true) ? $filters['metrik'] : 'diterima';
        $bulan = collect(range(1, 12))->map(fn (int $nomor) => [
            'nomor' => $nomor,
            'label' => now()->setMonth($nomor)->locale('id')->translatedFormat('F'),
            'nilai' => (float) $filtered->where('bulan', $nomor)->sum($metric),
        ])->all();

        $perPegawai = $filtered->groupBy('pegawai_key')->map(function (Collection $rows) {
            $first = $rows->first();

            return $this->jumlahkan($rows) + [
                'key' => $first['pegawai_key'], 'nama' => $first['nama'], 'jabatan' => $first['jabatan'], 'bidang' => $first['bidang'],
            ];
        })->values();

        $perBidang = collect(BidangOrganisasi::PERJALANAN)->map(function (string $bidang) use ($perPegawai) {
            $pegawai = $perPegawai->where('bidang', $bidang)->sortBy('nama')->values();

            return $this->jumlahkan($pegawai) + ['bidang' => $bidang, 'pegawai' => $pegawai->all(), 'jumlah_pegawai' => $pegawai->count()];
        })->filter(fn (array $row) => $row['jumlah_pegawai'] > 0)->values()->all();

        return [
            'tahun' => $tahun,
            'total' => $this->jumlahkan($filtered) + ['jumlah_pegawai' => $perPegawai->count(), 'jumlah_npd' => $filtered->pluck('npd_id')->unique()->count()],
            'bidang' => $perBidang,
            'bulan' => $bulan,
            'metrik' => $metric,
            'metrik_label' => ['hari' => 'Jumlah Hari', 'uang_harian' => 'Uang Harian', 'akomodasi' => 'Akomodasi', 'transport' => 'Transport', 'representatif' => 'Representatif', 'diterima' => 'Jumlah Diterima'][$metric],
            'kosong' => $filtered->isEmpty(),
            'pilihan' => ['bidang' => $pilihanBidang, 'pegawai' => $pilihanPegawai, 'metrik' => self::METRICS],
        ];
    }

    private function baris(Npd $npd, NpdTim $tim): array
    {
        $hasil = $tim->hitung();
        $bidang = BidangOrganisasi::petakan($tim->bidang_snapshot ?: $tim->pegawai?->bidang);

        return [
            'npd_id' => $npd->id,
            'bulan' => (int) $npd->tanggal_npd->month,
            'pegawai_key' => $tim->pegawai_id ? 'pegawai:'.$tim->pegawai_id : 'snapshot:'.sha1(mb_strtolower(trim($tim->nip.'|'.$tim->nama))),
            'nama' => $tim->nama,
            'jabatan' => $tim->jabatan,
            'bidang' => $bidang,
            'hari' => (float) $tim->paket->sum('lama_hari'),
            'uang_harian' => (float) $hasil['jml_harian'],
            'akomodasi' => (float) $hasil['jml_akom'],
            'transport' => (float) $hasil['jml_transport'],
            'representatif' => (float) $hasil['representatif'],
            'diterima' => (float) $hasil['jumlah'],
        ];
    }

    private function jumlahkan(Collection $rows): array
    {
        return collect(self::METRICS)->mapWithKeys(fn (string $metric) => [$metric => (float) $rows->sum($metric)])->all();
    }
}
