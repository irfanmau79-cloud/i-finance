<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Penugasan KPA + PPTK per sub kegiatan (BPP ikut otomatis dari KPA).
 * Satu baris per kode_sub_kegiatan — set ulang berarti update.
 */
#[Fillable(['kode_sub_kegiatan', 'kpa_id', 'pptk_pegawai_id'])]
class Pelimpahan extends Model
{
    protected $table = 'pelimpahan';

    public function kpa(): BelongsTo
    {
        return $this->belongsTo(Kpa::class);
    }

    public function pptkPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pptk_pegawai_id');
    }

    /** Set borongan: KPA + PPTK yang sama untuk banyak sub kegiatan sekaligus (upsert per kode). */
    public static function setBorongan(array $kodeSubKegiatanList, int $kpaId, int $pptkPegawaiId): void
    {
        $kodeNormal = collect($kodeSubKegiatanList)
            ->map(fn ($kode) => MasterAnggaran::normalisasiTeks((string) $kode))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($kodeNormal, $kpaId, $pptkPegawaiId) {
            foreach ($kodeNormal as $kode) {
                self::updateOrCreate(
                    ['kode_sub_kegiatan' => $kode],
                    ['kpa_id' => $kpaId, 'pptk_pegawai_id' => $pptkPegawaiId]
                );
            }
        });
    }
}
