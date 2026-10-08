<?php

namespace App\Http\Controllers;

use App\Models\Npd;
use App\Models\MasterAnggaran;
use App\Models\SuratPerintah;
use App\Services\KeteranganLampiranService;
use App\Support\PptkPenerima;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pratinjau Uraian Lampiran untuk formulir NPD yang BELUM disimpan.
 *
 * Teksnya dirakit di server, bukan di peramban, dan memakai
 * KeteranganLampiranService yang sama dengan yang dipakai saat mencetak PDF.
 * Menyalin kalimatnya ke JavaScript akan membuat dua sumber kebenaran: begitu
 * salah satunya diubah, yang terlihat di layar tidak lagi sama dengan yang
 * tercetak di dokumen yang ditandatangani.
 *
 * Validasinya sengaja longgar - ini memotret formulir yang masih setengah
 * terisi, jadi yang belum lengkap cukup tampil sebagai bagian kalimat yang
 * kosong, bukan sebagai error.
 */
class KeteranganLampiranPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['pd', 'tr', 'kd'])],

            // Perjalanan Dinas & Transport
            'master_anggaran_id' => ['nullable', 'integer', 'exists:master_anggaran,id'],
            'surat_perintah_id' => ['nullable', 'integer', 'exists:surat_perintah,id'],
            'uraian_sp' => ['nullable', 'string', 'max:2000'],
            'tanggal_berangkat' => ['nullable', 'date'],
            'tanggal_pulang' => ['nullable', 'date'],
            'penerima_index' => ['nullable', 'integer', 'min:0'],
            'tim' => ['nullable', 'array', 'max:200'],

            // Transport: identitas perjalanannya milik NPD induk, bukan isian
            // formulir ini - lihat NpdTransportController::snapshotDetailJson.
            'npd_induk_id' => ['nullable', 'integer', 'exists:npd,id'],

            // Kontribusi Diklat
            'mode' => ['nullable', 'string', 'max:30'],
            'nama_pelatihan' => ['nullable', 'string', 'max:500'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'peserta' => ['nullable', 'array', 'max:200'],
            'penerima_transfer' => ['nullable', 'array', 'max:200'],
            'pptk_penerima' => ['nullable', 'boolean'],
            'tahun' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'teks' => match ($data['jenis']) {
                'kd' => $this->teksKd($data),
                'tr' => $this->teksTr($data),
                default => $this->teksPd($data),
            },
        ]);
    }

    /**
     * Transport menumpang identitas perjalanan NPD induknya: tanggal, uraian,
     * dan nomor SP semuanya milik induk. Yang dipakai dari formulir ini hanya
     * biaya timnya sendiri, karena itulah yang menentukan frasa komponen.
     *
     * Bila induk sudah punya Uraian manual, itulah yang diwarisi - sama
     * dengan yang terjadi saat Transport disimpan.
     *
     * @param  array<string, mixed>  $data
     */
    private function teksTr(array $data): string
    {
        // Transport membebani mata anggaran induknya (lihat
        // NpdTransportController::store), jadi frasa belanjanya ikut induk.
        $induk = isset($data['npd_induk_id']) ? Npd::with(['tim', 'masterAnggaran'])->find($data['npd_induk_id']) : null;
        $detail = $induk?->detail_json ?? [];

        if (filled($detail['keterangan_lampiran'] ?? null)) {
            return (string) $detail['keterangan_lampiran'];
        }

        $timInduk = $induk?->tim->values() ?? collect();
        $index = (int) ($data['penerima_index'] ?? 0);
        $penerima = $timInduk->get($index) ?? $timInduk->first();

        return KeteranganLampiranService::pd(
            $detail,
            array_values($data['tim'] ?? []),
            trim((string) ($penerima->nama ?? '')),
            $induk?->masterAnggaran?->kode_rekening_bersih,
        );
    }

    /** @param  array<string, mixed>  $data */
    private function teksPd(array $data): string
    {
        // Nomor dan tanggal SP TIDAK diambil dari isian: keduanya selalu
        // mengikuti Surat Perintah yang dipilih, persis seperti saat NPD
        // disimpan (lihat NpdPdController::store).
        $sp = isset($data['surat_perintah_id']) ? SuratPerintah::find($data['surat_perintah_id']) : null;

        $tim = array_values($data['tim'] ?? []);
        $index = (int) ($data['penerima_index'] ?? 0);
        $penerima = $tim[$index] ?? ($tim[0] ?? []);

        // Mata anggaran menentukan frasa belanjanya: Dalam Kota atau Biasa.
        $anggaran = isset($data['master_anggaran_id']) ? MasterAnggaran::find($data['master_anggaran_id']) : null;

        return KeteranganLampiranService::pd([
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'tanggal_pulang' => $data['tanggal_pulang'] ?? null,
            'uraian_sp' => $data['uraian_sp'] ?? '',
            'nomor_sp' => $sp?->nomor_sp ?? '',
            'tanggal_sp' => $sp?->tanggal_sp?->format('Y-m-d'),
        ], $tim, trim((string) ($penerima['nama'] ?? '')), $anggaran?->kode_rekening_bersih);
    }

    /** @param  array<string, mixed>  $data */
    private function teksKd(array $data): string
    {
        $peserta = array_values($data['peserta'] ?? []);
        $index = (int) ($data['penerima_index'] ?? 0);
        $tunggal = $peserta[$index] ?? ($peserta[0] ?? []);

        // Daftar Tujuan Transfer berlaku pada kedua mode - sama seperti
        // aturan validasinya di StoreNpdKontribusiDiklatRequest.
        $penerimaTransfer = array_values($data['penerima_transfer'] ?? []);

        // "PPTK Sebagai Penerima Transfer": namanya dari pelimpahan sub
        // kegiatan, persis seperti saat dicetak (App\Support\PptkPenerima).
        if ($data['pptk_penerima'] ?? false) {
            $anggaran = isset($data['master_anggaran_id']) ? MasterAnggaran::find($data['master_anggaran_id']) : null;
            $penerimaTransfer = [];
            $tunggal = ['nama' => $anggaran ? PptkPenerima::nama($anggaran, isset($data['tahun']) ? (int) $data['tahun'] : null) : ''];
        }

        return KeteranganLampiranService::kd(
            [
                'nama_pelatihan' => $data['nama_pelatihan'] ?? '',
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            ],
            $data['mode'] ?? null,
            // Tiap penerima transfer mendapat baris Lampiran sendiri yang
            // hanya menyebut namanya; pratinjau menampilkan baris PERTAMA.
            KeteranganLampiranService::atasNamaKd($penerimaTransfer, trim((string) ($tunggal['nama'] ?? '')))[0],
        );
    }
}
