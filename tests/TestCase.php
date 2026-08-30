<?php

namespace Tests;

use App\Helpers\GuestSession;
use App\Models\Kpa;
use App\Models\KpaPptk;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private int $urutanPelimpahanUji = 0;

    /**
     * Limpahkan Sub Kegiatan sebuah Mata Anggaran ke akun PPTK.
     *
     * Sejak App\Support\AnggaranNpd, PPTK hanya boleh membuat NPD pada Sub
     * Kegiatan limpahannya sendiri — jadi test yang membuat NPD sebagai PPTK
     * perlu rantai pejabatnya lebih dulu. Semuanya dibereskan di sini: PA &
     * Bendahara tingkat OPD, tautan akun ke Data Pegawai, KPA + BPP, dan
     * pasangan KPA-PPTK. Satu KPA dipakai ulang untuk tiap pegawai PPTK,
     * karena satu PPTK tidak boleh aktif di dua KPA sekaligus.
     */
    protected function limpahkanSubKegiatan(User $pptk, MasterAnggaran $anggaran): void
    {
        if (PejabatOpd::aktif() === null) {
            PejabatOpd::simpan([
                'pa_pegawai_id' => $this->pegawaiPelimpahanUji('PA OPD Uji')->id,
                'bendahara_pengeluaran_pegawai_id' => $this->pegawaiPelimpahanUji('Bendahara OPD Uji')->id,
            ]);
        }

        if ($pptk->pegawai_id === null) {
            $pptk->forceFill(['pegawai_id' => $this->pegawaiPelimpahanUji('PPTK '.$pptk->username)->id])->save();
        }

        $kpaPptk = KpaPptk::where('pptk_pegawai_id', $pptk->pegawai_id)->where('aktif', true)->first();

        $kpa = $kpaPptk
            ? Kpa::findOrFail($kpaPptk->kpa_id)
            : Kpa::create([
                'kpa_pegawai_id' => $this->pegawaiPelimpahanUji('KPA Uji')->id,
                'bpp_pegawai_id' => $this->pegawaiPelimpahanUji('BPP Uji')->id,
                'aktif' => true,
            ]);

        if (! $kpaPptk) {
            KpaPptk::create(['kpa_id' => $kpa->id, 'pptk_pegawai_id' => $pptk->pegawai_id, 'aktif' => true]);
        }

        // Pelimpahan dicocokkan pada bentuk lengkap (kode + nama), sementara
        // kolom program/sub_kegiatan menyimpan namanya saja.
        Pelimpahan::tetapkan(
            [['program' => $anggaran->program_lengkap, 'sub_kegiatan' => $anggaran->sub_kegiatan_lengkap]],
            $kpa->id,
            $kpa->bpp_pegawai_id,
            $pptk->pegawai_id,
        );
    }

    private function pegawaiPelimpahanUji(string $nama): Pegawai
    {
        $this->urutanPelimpahanUji++;

        return Pegawai::create([
            'nama' => $nama,
            'nip' => sprintf('19700101199001%04d', $this->urutanPelimpahanUji),
            'jabatan' => 'Pejabat Pengujian',
            'bidang' => 'Sekretariat',
            'pangkat' => 'Pembina',
            'aktif' => true,
        ]);
    }

    /**
     * Lolos gerbang kata sandi Pengguna Layanan.
     *
     * Halaman layanan (Input SP, Monitoring SP, Cetak SPJ, Perubahan
     * Tunjangan Keluarga) tanpa akun, tetapi sejak aplikasi dihosting berada
     * di balik satu kata sandi bersama. Test yang menguji ISI halaman itu
     * memakai jalan pintas ini supaya tidak perlu mengulang POST sandi;
     * gerbangnya sendiri diuji tersendiri di GerbangLayananTest.
     */
    protected function lolosGerbangLayanan(): static
    {
        return $this->withSession([GuestSession::kunciSesi() => true]);
    }
}
