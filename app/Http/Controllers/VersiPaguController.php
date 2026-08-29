<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\VersiPagu;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Kelola versi dokumen pagu (DPA Murni, DPA Pergeseran 1, ...). Import
 * hanya menghasilkan versi berstatus draft; halaman inilah yang
 * memberlakukan sebuah versi lewat VersiPagu::aktifkan().
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

        AuditLog::catat('Aktivasi Versi Pagu', sprintf(
            'Versi "%s" (TA %d) diberlakukan. Versi sebelumnya: %s. Total pagu: Rp %s.',
            $versiPagu->nama,
            $versiPagu->tahun,
            $sebelumnya?->nama ?? 'belum ada',
            fmt_rupiah((float) $versiPagu->fresh()->total_pagu)
        ));

        return redirect()->route('versi-pagu.index')->with('success', sprintf(
            'Versi pagu "%s" sekarang berlaku. Seluruh pagu, sisa tersedia, dan dashboard sudah memakai angka versi ini.',
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
        abort_if($versiPagu->status !== VersiPagu::STATUS_DRAFT, 403, 'Hanya versi pagu berstatus draft yang bisa dihapus.');

        $nama = $versiPagu->nama;
        $versiPagu->delete();

        AuditLog::catat('Hapus Versi Pagu', sprintf('Versi draft "%s" dihapus.', $nama));

        return redirect()->route('versi-pagu.index')->with('success', sprintf('Versi pagu draft "%s" dihapus.', $nama));
    }
}
