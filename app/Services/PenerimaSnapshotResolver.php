<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Vendor;

class PenerimaSnapshotResolver
{
    public function resolve(string $nama): array
    {
        $nama = trim($nama);
        $pegawai = Pegawai::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])->get();
        $vendor = Vendor::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])->get();

        if ($pegawai->count() + $vendor->count() > 1) {
            return ['status' => 'ambigu', 'pegawai_id' => null, 'vendor_id' => null];
        }

        if ($pegawai->count() === 1) {
            return ['status' => 'pegawai', 'pegawai_id' => $pegawai->first()->id, 'vendor_id' => null];
        }

        if ($vendor->count() === 1) {
            return ['status' => 'vendor', 'pegawai_id' => null, 'vendor_id' => $vendor->first()->id];
        }

        return ['status' => 'manual', 'pegawai_id' => null, 'vendor_id' => null];
    }
}
