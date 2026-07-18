<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Npd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NpdController extends Controller
{
    public function index(Request $request)
    {
        $query = Npd::with('masterAnggaran')->orderBy('tanggal_npd', 'desc');

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $npds = $query->paginate(30)->withQueryString();

        return view('npd.index', compact('npds'));
    }

    public function show(Request $request, Npd $npd)
    {
        $npd->load(['masterAnggaran.tagging', 'penerima.pphList', 'dibuatOleh']);

        $aksiTersedia = $npd->aksiTersedia($request->user()->role);

        return view('npd.show', compact('npd', 'aksiTersedia'));
    }

    /**
     * Transisi status workflow NPD (semua jenis). Port dari transisiNPD di
     * gas-lama/CodeRevisi.gs: bendahara boleh aksi apa pun, role lain hanya
     * aksi yang dipetakan di Npd::TRANSISI.
     */
    public function transisi(Request $request, Npd $npd)
    {
        $aksi = (string) $request->input('aksi');
        $rule = Npd::TRANSISI[$aksi] ?? null;

        if ($rule === null) {
            return back()->withErrors(['aksi' => "Aksi tidak dikenal: {$aksi}."]);
        }

        $role = $request->user()->role;

        if (! Npd::bolehAksi($aksi, $role)) {
            $labelRole = config('akses.role_label')[$rule['roles'][0]] ?? $rule['roles'][0];

            return back()->withErrors(['aksi' => "Aksi ini khusus {$labelRole}."]);
        }

        if (trim((string) $npd->status) !== $rule['from']) {
            return back()->withErrors([
                'aksi' => "NPD berstatus \"{$npd->status}\", aksi \"{$rule['label']}\" hanya berlaku untuk status \"{$rule['from']}\".",
            ]);
        }

        $catatanInput = trim((string) $request->input('catatan', ''));

        if (in_array($aksi, Npd::AKSI_WAJIB_CATATAN, true) && $catatanInput === '') {
            $pesan = $aksi === 'batal_selesai' ? 'Alasan pembatalan wajib diisi.' : 'Catatan revisi wajib diisi.';

            return back()->withErrors(['catatan' => $pesan]);
        }

        $nomorUrut = null;

        if ($aksi === 'verifikasi') {
            $nomorUrut = (int) $request->input('nomor_urut');

            if ($nomorUrut < 1 || $nomorUrut > 999) {
                return back()->withErrors(['nomor_urut' => 'Nomor NPD harus antara 1 dan 999.']);
            }

            $bentrok = Npd::where('id', '!=', $npd->id)
                ->where('keu', $npd->keu)
                ->where('nomor_urut', $nomorUrut)
                ->where('status', 'not like', '%batal%')
                ->first();

            if ($bentrok) {
                return back()->withErrors([
                    'nomor_urut' => "Nomor {$nomorUrut} sudah dipakai pada Keu.{$npd->keu} (NPD: {$bentrok->nomor_lengkap}).",
                ]);
            }
        }

        $catatanBaru = match (true) {
            $aksi === 'verifikasi' => '[Terverifikasi]'.($catatanInput !== '' ? ' '.$catatanInput : ''),
            in_array($aksi, ['kembali_bpp', 'kembali_pptk'], true) => '[Perlu Revisi] '.$catatanInput,
            $aksi === 'batal_selesai' => '[Pembatalan Selesai] '.$catatanInput,
            default => null,
        };

        DB::transaction(function () use ($npd, $rule, $catatanBaru, $nomorUrut, $aksi) {
            if ($aksi === 'verifikasi') {
                $npd->nomor_urut = $nomorUrut;
                $npd->nomor_lengkap = Npd::buatNomorLengkap($nomorUrut, $npd->keu, $npd->bulan, $npd->tahun);
            }

            $npd->status = $rule['to'];
            $npd->catatan = $catatanBaru;
            $npd->save();

            $npd->mirrorStatusKeSuratPerintah();
        });

        $nomorForLog = $npd->nomor_lengkap ?? "NPD #{$npd->id}";
        AuditLog::catat($rule['label'], "NPD: {$nomorForLog}".($catatanBaru ? " | {$catatanBaru}" : ''));

        return back()->with('success', "Status NPD diperbarui: {$rule['label']}.");
    }
}
