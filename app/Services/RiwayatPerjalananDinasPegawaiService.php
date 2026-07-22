<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\NpdPeserta;
use App\Models\NpdTim;
use App\Models\Pegawai;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RiwayatPerjalananDinasPegawaiService
{
    private const PER_PAGE = 20;

    /**
     * Riwayat NPD Perjalanan Dinas ('pd'), Transport ('tr'), dan Kontribusi
     * Diklat mode 'perjalanan' ('kd') milik satu pegawai — digabung dari
     * npd_tim (pd/tr) dan npd_peserta (kd-perjalanan), lalu diurut tanggal
     * NPD terbaru dulu. Baris dengan pegawai_id null tapi nama snapshot
     * cocok persis tetap disertakan dan ditandai 'kecocokan_nama'.
     */
    public function riwayat(Pegawai $pegawai, array $filters, int $page): array
    {
        $baris = $this->timRows($pegawai)
            ->concat($this->pesertaRows($pegawai))
            ->sortByDesc(fn (array $row) => $row['tanggal_npd']->format('Y-m-d').'-'.str_pad((string) $row['npd_id'], 10, '0', STR_PAD_LEFT))
            ->values();

        $filtered = $baris
            ->when($filters['dari'] ?? '', fn (Collection $rows, string $dari) => $rows->filter(
                fn (array $row) => $row['tanggal_npd']->greaterThanOrEqualTo(Carbon::parse($dari)->startOfDay())
            ))
            ->when($filters['sampai'] ?? '', fn (Collection $rows, string $sampai) => $rows->filter(
                fn (array $row) => $row['tanggal_npd']->lessThanOrEqualTo(Carbon::parse($sampai)->endOfDay())
            ))
            ->when($filters['jenis'] ?? '', fn (Collection $rows, string $jenis) => $rows->where('jenis', $jenis))
            ->when($filters['status'] ?? '', fn (Collection $rows, string $status) => $rows->where('status', $status))
            ->values();

        $perPage = self::PER_PAGE;
        $halaman = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return [
            'ringkasan' => [
                'total_npd' => $filtered->pluck('npd_id')->unique()->count(),
                'total_hari' => (float) $filtered->sum(fn (array $row) => $row['jumlah_hari'] ?? 0),
                'total_nominal' => (float) $filtered->sum('nominal_bagian'),
                'tanggal_awal' => $filtered->isNotEmpty() ? $filtered->min('tanggal_npd') : null,
                'tanggal_akhir' => $filtered->isNotEmpty() ? $filtered->max('tanggal_npd') : null,
            ],
            'halaman' => $halaman,
            'kosong' => $filtered->isEmpty(),
        ];
    }

    private function timRows(Pegawai $pegawai): Collection
    {
        return NpdTim::query()
            ->whereHas('npd', fn ($q) => $q->whereIn('jenis', ['pd', 'tr']))
            ->where(fn ($q) => $this->cocokPegawai($q, $pegawai))
            ->with(['npd.suratPerintah', 'paket'])
            ->get()
            ->map(fn (NpdTim $tim) => $this->fromTim($tim));
    }

    private function pesertaRows(Pegawai $pegawai): Collection
    {
        return NpdPeserta::query()
            ->whereHas('npd', fn ($q) => $q->where('jenis', 'kd')->where('mode_kd', 'perjalanan'))
            ->where(fn ($q) => $this->cocokPegawai($q, $pegawai))
            ->with(['npd.suratPerintah'])
            ->get()
            ->map(fn (NpdPeserta $peserta) => $this->fromPeserta($peserta));
    }

    /** pegawai_id sama, ATAU pegawai_id null tapi nama snapshot cocok persis (case/spasi diabaikan). */
    private function cocokPegawai($query, Pegawai $pegawai): void
    {
        $query->where('pegawai_id', $pegawai->id)
            ->orWhere(function ($q) use ($pegawai) {
                $q->whereNull('pegawai_id')
                    ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim($pegawai->nama))]);
            });
    }

    private function fromTim(NpdTim $tim): array
    {
        $npd = $tim->npd;
        $hasil = $tim->hitung();

        return [
            'npd_id' => $npd->id,
            'tanggal_npd' => $npd->tanggal_npd,
            'nomor_lengkap' => $npd->nomor_lengkap,
            'jenis' => $npd->jenis,
            'nomor_sp' => $npd->suratPerintah?->nomor_sp,
            'uraian' => $npd->detail_json['tujuan'] ?? null,
            'jumlah_hari' => $npd->jenis === 'pd' ? (float) $tim->paket->sum('lama_hari') : null,
            'nominal_bagian' => (float) $hasil['jumlah'],
            'status' => $npd->status,
            'kecocokan_nama' => $tim->pegawai_id === null,
        ];
    }

    private function fromPeserta(NpdPeserta $peserta): array
    {
        $npd = $peserta->npd;

        return [
            'npd_id' => $npd->id,
            'tanggal_npd' => $npd->tanggal_npd,
            'nomor_lengkap' => $npd->nomor_lengkap,
            'jenis' => $npd->jenis,
            'nomor_sp' => $npd->suratPerintah?->nomor_sp,
            'uraian' => $npd->detail_json['nama_pelatihan'] ?? null,
            'jumlah_hari' => (float) $peserta->hari_uh,
            'nominal_bagian' => (float) $peserta->sub_perjalanan,
            'status' => $npd->status,
            'kecocokan_nama' => $peserta->pegawai_id === null,
        ];
    }
}
