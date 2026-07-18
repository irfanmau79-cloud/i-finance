<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\DataTambahan;
use App\Models\Npd;
use App\Models\NpdPenerima;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class NpdController extends Controller
{
    public function index(Request $request)
    {
        $npds = $this->daftarNpd($request);

        return view('npd.index', compact('npds'));
    }

    /**
     * Antrean Persetujuan NPD untuk BPP. Port dari getNPDuntukBPP di
     * gas-lama/CodeRevisi.gs: seluruh NPD ditampilkan, tombol aksi di
     * halaman detail aktif hanya untuk status di tahap BPP.
     */
    public function persetujuan(Request $request)
    {
        $npds = $this->daftarNpd($request);

        return view('npd.persetujuan', compact('npds'));
    }

    /**
     * Antrean Verifikasi NPD untuk Verifikator. Port dari
     * getNPDuntukVerifikator di gas-lama/CodeRevisi.gs.
     */
    public function verifikasi(Request $request)
    {
        $npds = $this->daftarNpd($request);

        return view('npd.verifikasi', compact('npds'));
    }

    private function daftarNpd(Request $request)
    {
        $query = Npd::with('masterAnggaran')->orderBy('tanggal_npd', 'desc');

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query->paginate(30)->withQueryString();
    }

    public function show(Request $request, Npd $npd)
    {
        $npd->load(['masterAnggaran.tagging', 'penerima.pphList', 'dibuatOleh']);

        $role = $request->user()->role;
        $aksiTersedia = $npd->aksiTersedia($role);

        [$ruteDaftar, $activeNav] = match ($role) {
            'bpp' => ['npd.persetujuan', 'persetujuan'],
            'verifikator' => ['npd.verifikasi', 'verifikasi'],
            default => ['npd.index', 'npd'],
        };

        return view('npd.show', compact('npd', 'aksiTersedia', 'ruteDaftar', 'activeNav'));
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

    /**
     * Cetak dokumen NPD utama (F4). Port dari tpl_npd.html + buatNPD() di
     * gas-lama/Code.gs. Di-generate on-demand, tidak disimpan ke disk.
     */
    public function cetakNpd(Npd $npd)
    {
        $npd->load('masterAnggaran.tagging');

        $dataTambahan = DataTambahan::untukProgram($npd->masterAnggaran->program);

        $html = view('npd.pdf.npd', [
            'npd' => $npd,
            'kpa' => $this->resolvePegawai($dataTambahan?->kpa),
            'pptk' => $this->resolvePegawai($dataTambahan?->pptk),
            'noDpa' => $dataTambahan?->no_dpa ?? '',
            'sisaSebelum' => $npd->masterAnggaran->sisaAnggaranSebelum($npd),
            'logoPath' => $this->logoKopPath(),
        ])->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak NPD', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'npd-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Cetak Lampiran NPD (daftar penerima, kolom PPh adaptif). Port dari
     * tpl_lampiran.html + _rowsLampiran() di gas-lama/Code.gs.
     */
    public function cetakLampiran(Npd $npd)
    {
        $npd->load(['masterAnggaran', 'penerima.pphList']);

        $dataTambahan = DataTambahan::untukProgram($npd->masterAnggaran->program);

        $html = view('npd.pdf.lampiran', array_merge([
            'npd' => $npd,
            'pptk' => $this->resolvePegawai($dataTambahan?->pptk),
        ], $this->bangunLampiranPph($npd->penerima)))->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak Lampiran NPD', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'lampiran-npd-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Resolusi nama bebas (data_tambahan.kpa/pptk) ke data pegawai (NIP,
     * pangkat). Kalau tidak ketemu, nama tetap ditampilkan apa adanya
     * (persis fallback "kosong" di _cariPegawai gas-lama), hanya NIP/pangkat
     * yang kosong.
     */
    private function resolvePegawai(?string $nama): object
    {
        $match = Pegawai::cariByNama($nama);

        return (object) [
            'nama' => $match->nama ?? trim((string) $nama),
            'pangkat' => $match->pangkat ?? '',
            'nip' => $match->nip ?? '',
        ];
    }

    private function logoKopPath(): ?string
    {
        $path = storage_path('app/import/Coat_of_arms_of_West_Java.svg');

        return file_exists($path) ? $path : null;
    }

    /**
     * Bangun kolom PPh adaptif + baris tabel Lampiran. Port 1:1 dari
     * _rowsLampiran() di gas-lama/Code.gs: kumpulkan jenis PPh yang benar-
     * benar dipakai (nilai > 0) di antara penerima ini; kalau tidak ada,
     * jatuh ke satu kolom generik "PPh".
     *
     * @param  Collection<int, NpdPenerima>  $penerima
     */
    private function bangunLampiranPph(Collection $penerima): array
    {
        $kolomPph = [];

        foreach ($penerima as $p) {
            foreach ($p->pphList as $pph) {
                if ((float) $pph->nilai > 0 && $pph->jenis && ! in_array($pph->jenis, $kolomPph, true)) {
                    $kolomPph[] = $pph->jenis;
                }
            }
        }

        if ($kolomPph === []) {
            $kolomPph = ['PPh'];
        }

        $totals = ['bruto' => 0.0, 'ppn' => 0.0, 'biaya' => 0.0, 'transfer' => 0.0];
        $totalsPph = array_fill_keys($kolomPph, 0.0);
        $rows = [];

        foreach ($penerima as $p) {
            $bruto = (float) $p->bruto;
            $ppn = (float) $p->ppn;
            $biaya = (float) $p->biaya_ku_rtgs;
            $transfer = $p->netto;

            $totals['bruto'] += $bruto;
            $totals['ppn'] += $ppn;
            $totals['biaya'] += $biaya;
            $totals['transfer'] += $transfer;

            $pphCells = [];

            foreach ($kolomPph as $jenis) {
                $v = 0.0;

                foreach ($p->pphList as $pph) {
                    if ($pph->jenis === $jenis || ($jenis === 'PPh' && ! $pph->jenis)) {
                        $v += (float) $pph->nilai;
                    }
                }

                $totalsPph[$jenis] += $v;
                $pphCells[$jenis] = $v;
            }

            $rows[] = [
                'nama' => $p->nama,
                'rekening' => $p->rekening,
                'bruto' => $bruto,
                'ppn' => $ppn,
                'pph' => $pphCells,
                'biaya' => $biaya,
                'transfer' => $transfer,
                'keterangan' => $p->keterangan,
            ];
        }

        return [
            'kolomPph' => $kolomPph,
            'rows' => $rows,
            'totals' => $totals,
            'totalsPph' => $totalsPph,
            'nominalColspan' => 4 + count($kolomPph),
        ];
    }
}
