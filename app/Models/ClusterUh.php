<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'tarif', 'jarak', 'aktif'])]
class ClusterUh extends Model
{
    protected $table = 'cluster_uh';

    /** Titik berangkat baku untuk seluruh NPD Perjalanan Dinas (lihat CodePerjalanan.gs). */
    public const ASAL_PERJALANAN = 'Kota Bandung';

    /**
     * Seluruh kode cluster yang dikenal sistem, urut seperti dropdown GAS.
     *
     * Dipakai validasi StoreNpdPdRequest dan dicocokkan ulang oleh
     * ClusterUhSeederTest, jadi menambah cluster cukup disentuh di sini dan
     * di ClusterUhSeeder - tidak ada lagi daftar kode yang dihardcode di
     * formulir atau di Request.
     */
    public const KODE = ['A', 'B', 'C', 'D', 'DK1', 'DK2', 'LP'];

    /**
     * Perjalanan DALAM Kota Bandung: asal dan tujuan sama-sama Kota Bandung,
     * yang membedakan tarifnya hanya lama perjalanan (di bawah/di atas 8 jam).
     * Tarifnya tetap seperti cluster jarak, bukan diketik manual.
     */
    public const KODE_DALAM_KOTA = ['DK1', 'DK2'];

    /** Luar Provinsi: tarif uang harian dan nama kotanya diketik manual per NPD. */
    public const KODE_MANUAL = 'LP';

    protected function casts(): array
    {
        return [
            'tarif' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function wilayah(): HasMany
    {
        return $this->hasMany(ClusterWilayah::class, 'cluster_id');
    }

    /**
     * Teks pilihan cluster pada formulir NPD Perjalanan Dinas.
     *
     * Cluster jarak diberi prefiks kodenya ("A (4 km s.d. 30 km)") karena
     * kode itulah yang dipakai di kantor saat menyebut tujuan. Dalam Kota dan
     * Luar Provinsi tampil apa adanya tanpa prefiks - persis seperti
     * clusterOptions() di "i-finance gas/index.html", supaya petugas yang
     * pindah dari GAS menemukan pilihan yang sama bunyinya.
     */
    public function labelPilihan(): string
    {
        $tanpaPrefiks = array_merge(self::KODE_DALAM_KOTA, [self::KODE_MANUAL]);

        return in_array($this->kode, $tanpaPrefiks, true)
            ? $this->jarak
            : $this->kode.' ('.$this->jarak.')';
    }
}
