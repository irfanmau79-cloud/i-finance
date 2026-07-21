<?php

namespace App\Services;

use App\Models\MasterAnggaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RakBulananDuplicateAudit
{
    public function auditDatabase(): array
    {
        if (! Schema::hasTable('rak_bulanan') || DB::table('rak_bulanan')->count() === 0) {
            return [];
        }

        if (Schema::hasColumn('rak_bulanan', 'master_anggaran_id')) {
            $rows = DB::table('rak_bulanan as r')
                ->join('master_anggaran as m', 'm.id', '=', 'r.master_anggaran_id')
                ->get([
                    'r.id', 'r.tahun', 'r.bulan', 'r.target', 'r.master_anggaran_id',
                    'm.sub_kegiatan', 'm.sub_kegiatan_kunci', 'm.kode_rekening', 'm.tagging_id',
                ]);
        } else {
            // Schema baru sudah memiliki unique key tepat pada grain RAK per
            // bulan, sehingga duplikasi lintas Tagging tidak dapat terbentuk.
            return [];
        }

        return self::kelompokkan($rows)->all();
    }

    public static function kelompokkan(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($r) => $r->tahun.'|'.($r->sub_kegiatan_kunci ?: MasterAnggaran::normalisasiKunci($r->sub_kegiatan)).'|'.$r->kode_rekening)
            ->map(function (Collection $group) {
                $perSumber = $group->groupBy(fn ($r) => $r->master_anggaran_id ?? 'langsung-'.$r->id)
                    ->map(fn (Collection $source) => $source->sortBy('bulan')->pluck('target', 'bulan')->map(fn ($v) => (string) $v)->all());

                if ($perSumber->count() < 2) {
                    return null;
                }

                $fingerprints = $perSumber->map(fn ($bulan) => hash('sha256', json_encode($bulan)))->unique();
                $first = $group->first();

                return [
                    'tahun' => (int) $first->tahun,
                    'sub_kegiatan' => $first->sub_kegiatan,
                    'kode_rekening' => $first->kode_rekening,
                    'jumlah_sumber' => $perSumber->count(),
                    'jenis' => $fingerprints->count() === 1 ? 'identik' : 'konflik',
                    'strategi' => $fingerprints->count() === 1
                        ? 'Kandidat normalisasi aman: pertahankan satu rangkaian setelah verifikasi total dan arsipkan pemetaan sumber; jangan diterapkan otomatis.'
                        : 'Keputusan manual wajib: nilai bulanan berbeda; jangan memilih nilai terbaru, terbesar, atau menjumlahkan.',
                ];
            })->filter()->values();
    }
}
