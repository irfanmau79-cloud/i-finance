<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\VersiPagu;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Kelola tahapan dokumen pagu (DPA Murni, DPA Pergeseran 1, ...). Import
 * hanya menghasilkan tahapan berstatus draf; halaman inilah yang
 * memberlakukan sebuah tahapan lewat VersiPagu::aktifkan(), sekaligus
 * tempat melengkapi Nomor DPA-nya.
 *
 * Nama kelas & tabel tetap "versi pagu" — yang berubah hanya istilah yang
 * dilihat pengguna.
 */
class VersiPaguController extends Controller
{
    public function index()
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        $versi = VersiPagu::where('tahun', $tahun)
            ->with(['user:id,nama', 'diaktifkanOleh:id,nama'])
            ->orderByRaw("CASE status WHEN 'aktif' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get();

        return view('versi-pagu.index', compact('versi', 'tahun'));
    }

    public function show(VersiPagu $versiPagu)
    {
        $baris = $versiPagu->detail()
            ->with(['masterAnggaran' => fn ($q) => $q->with('tagging:id,nama')])
            ->join('master_anggaran', 'master_anggaran.id', '=', 'versi_pagu_detail.master_anggaran_id')
            ->orderBy('master_anggaran.kode_sub_kegiatan')
            ->orderBy('master_anggaran.kode_rekening')
            ->select('versi_pagu_detail.*')
            ->paginate(50);

        // Versi aktif dipakai sebagai pembanding "pagu berlaku sekarang".
        $pembanding = VersiPagu::aktifTahun($versiPagu->tahun);
        $paguPembanding = $pembanding !== null && $pembanding->isNot($versiPagu)
            ? $pembanding->detail()->pluck('pagu', 'master_anggaran_id')
            : collect();

        return view('versi-pagu.show', [
            'versi' => $versiPagu,
            'baris' => $baris,
            'pembanding' => $pembanding !== null && $pembanding->isNot($versiPagu) ? $pembanding : null,
            'paguPembanding' => $paguPembanding,
        ]);
    }

    public function aktifkan(Request $request, VersiPagu $versiPagu)
    {
        $sebelumnya = VersiPagu::aktifTahun($versiPagu->tahun);

        try {
            $versiPagu->aktifkan($request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('versi-pagu.index')->withErrors(['aktivasi' => $e->getMessage()]);
        }

        AuditLog::catat('Aktivasi Tahapan Pagu', sprintf(
            'Tahapan "%s" (TA %d) diberlakukan. Tahapan sebelumnya: %s. Total pagu: Rp %s.',
            $versiPagu->nama,
            $versiPagu->tahun,
            $sebelumnya?->nama ?? 'belum ada',
            fmt_rupiah((float) $versiPagu->fresh()->total_pagu)
        ));

        return redirect()->route('versi-pagu.index')->with('success', sprintf(
            'Tahapan pagu "%s" sekarang berlaku. Seluruh pagu, sisa tersedia, dashboard, dan Nomor DPA pada cetakan NPD sudah memakai tahapan ini.',
            $versiPagu->nama
        ));
    }

    /**
     * Isi atau perbarui Nomor DPA sebuah tahapan.
     *
     * Nomor DPA kerap baru terbit setelah angka pagunya diimpor, jadi
     * melengkapinya tidak boleh menuntut impor ulang seluruh dokumen. Boleh
     * pada tahapan berstatus apa pun — termasuk arsip, yang nomornya bisa
     * saja baru dicatat belakangan.
     */
    public function nomorDpa(Request $request, VersiPagu $versiPagu)
    {
        $data = $request->validate(
            ['nomor_dpa' => ['nullable', 'string', 'max:100']],
            [],
            ['nomor_dpa' => 'Nomor DPA'],
        );

        $sebelumnya = $versiPagu->nomor_dpa;
        $versiPagu->update(['nomor_dpa' => trim((string) ($data['nomor_dpa'] ?? '')) ?: null]);

        AuditLog::catat('Ubah Nomor DPA Tahapan Pagu', sprintf(
            'Tahapan "%s" (TA %d): %s -> %s.',
            $versiPagu->nama,
            $versiPagu->tahun,
            $sebelumnya ?: 'kosong',
            $versiPagu->nomor_dpa ?: 'kosong',
        ));

        return redirect()->route('versi-pagu.index')->with('success', sprintf(
            $versiPagu->berlaku()
                ? 'Nomor DPA tahapan "%s" tersimpan dan langsung dipakai pada cetakan NPD.'
                : 'Nomor DPA tahapan "%s" tersimpan.',
            $versiPagu->nama
        ));
    }

    /**
     * Hanya versi draft yang boleh dihapus. Versi aktif adalah pagu yang
     * sedang dipakai, dan versi arsip adalah jejak riwayat pergeseran yang
     * harus tetap bisa ditelusuri.
     */
    public function destroy(VersiPagu $versiPagu)
    {
        abort_if($versiPagu->status !== VersiPagu::STATUS_DRAFT, 403, 'Hanya tahapan pagu berstatus draf yang bisa dihapus.');

        $nama = $versiPagu->nama;
        $versiPagu->delete();

        AuditLog::catat('Hapus Tahapan Pagu', sprintf('Tahapan draf "%s" dihapus.', $nama));

        return redirect()->route('versi-pagu.index')->with('success', sprintf('Tahapan pagu draf "%s" dihapus.', $nama));
    }
}
