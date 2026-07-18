<?php

namespace App\Http\Controllers;

class MenuPlaceholderController extends Controller
{
    /**
     * Judul halaman untuk tiap kunci menu yang belum punya halaman
     * sungguhan. Dipakai sebagai judul halaman & isi placeholder.
     */
    private const TITLES = [
        'dashboard' => 'Dashboard Realisasi Anggaran',
        'dashpd' => 'Dashboard Perjalanan Dinas',
        'tk-monitor' => 'Dashboard Tunjangan Keluarga',
        'dashspj' => 'Dashboard SPJ Pengawasan',
        'rincian' => 'Rincian Realisasi',
        'analisis' => 'Analisis dan Tren',
        'invspj' => 'Inventarisasi SPJ',
        'npd-selesai' => 'NPD Selesai (Pembuatan NPD)',
        'persetujuan-selesai' => 'NPD Selesai (Persetujuan NPD)',
        'verifikasi-selesai' => 'NPD Selesai (Verifikasi NPD)',
        'sp-monitor' => 'Monitoring SP',
        'tk-form' => 'Tunjangan Keluarga — Perubahan Data',
        'users' => 'Manajemen Users',
        'profil' => 'Profil Saya',
    ];

    public function show(string $key)
    {
        $title = self::TITLES[$key] ?? 'Fitur';

        return view('menu.placeholder', [
            'activeKey' => $key,
            'title' => $title,
        ]);
    }
}
