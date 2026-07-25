<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\PejabatResolver;
use App\Models\Npd;
use App\Models\NpdNarasumber;
use App\Models\NpdPenerima;
use App\Models\NpdPeserta;
use App\Models\NpdTim;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class NpdController extends Controller
{
    /**
     * Pembuatan NPD: satu halaman gabungan pemilih jenis + daftar NPD, port
     * 1:1 dari #page-npd di gas-lama/index.html (bukan dua halaman terpisah
     * seperti sebelumnya). Pemilih jenis hanya untuk role yang boleh
     * membuat NPD; Bendahara Pengeluaran cuma memantau daftarnya.
     */
    public function index(Request $request)
    {
        [$npds, $filters] = $this->daftarNpd($request, 'daftar', lengkap: true);
        $bolehBuat = in_array($request->user()->role, [User::ROLE_SUPERADMIN, User::ROLE_PPTK], true);

        return view('npd.index', compact('npds', 'filters', 'bolehBuat'));
    }

    /**
     * Antrean Persetujuan NPD untuk BPP. Port dari getNPDuntukBPP di
     * gas-lama/CodeRevisi.gs: seluruh NPD ditampilkan, tombol aksi di
     * halaman detail aktif hanya untuk status di tahap BPP.
     */
    public function persetujuan(Request $request)
    {
        [$npds, $filters] = $this->daftarNpd($request, 'persetujuan', lengkap: true);

        return view('npd.persetujuan', compact('npds', 'filters'));
    }

    /**
     * Antrean Verifikasi NPD untuk Verifikator. Port dari
     * getNPDuntukVerifikator di gas-lama/CodeRevisi.gs.
     */
    public function verifikasi(Request $request)
    {
        [$npds, $filters] = $this->daftarNpd($request, 'verifikasi', lengkap: true);

        return view('npd.verifikasi', compact('npds', 'filters'));
    }

    /**
     * $lengkap eager-load relasi tambahan (tagging + seluruh sumber penerima)
     * yang cuma dibutuhkan halaman Pembuatan NPD (kolom Tagging/Penerima) —
     * persetujuan/verifikasi tidak menampilkannya jadi tidak perlu query ekstra.
     */
    private function daftarNpd(Request $request, string $mode = 'daftar', bool $lengkap = false): array
    {
        $defaultStatus = match ($mode) {
            'persetujuan' => 'Draft NPD - BPP',
            'verifikasi' => 'Verifikasi - Verifikator',
            default => null,
        };
        $validated = $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(Npd::JENIS_LABEL))],
            'status' => ['nullable', Rule::in([...Npd::STATUS_LIST, 'semua'])],
        ]);
        $filters = [
            'jenis' => $validated['jenis'] ?? '',
            'status' => $request->has('status') ? (($validated['status'] ?? null) ?: 'semua') : ($defaultStatus ?? 'semua'),
        ];

        $query = Npd::query()
            ->with($lengkap
                ? ['masterAnggaran.tagging', 'penerima', 'tim', 'narasumber', 'peserta']
                : ['masterAnggaran:id,kode_rekening,sub_kegiatan'])
            ->latest('tanggal_npd')
            ->latest('id');

        if ($mode !== 'daftar') {
            $query->where('sumber_data', '!=', 'import_historis');
        }

        if ($filters['jenis'] !== '') {
            $query->where('jenis', $filters['jenis']);
        }

        if ($filters['status'] !== 'semua') {
            $query->where('status', $filters['status']);
        }

        return [$query->paginate(30)->withQueryString(), $filters];
    }

    public function show(Request $request, Npd $npd)
    {
        $npd->load(['masterAnggaran.tagging', 'penerima.pphList', 'tim.paket', 'narasumber', 'peserta', 'referensi', 'turunanPerjalanan', 'induk', 'turunanTransport', 'dibuatOleh', 'historiStatus.user', 'arsipSpj.ditetapkanOleh']);

        $role = $request->user()->role;
        $aksiTersedia = $npd->aksiTersedia($role);

        [$ruteDaftar, $activeNav] = match ($role) {
            'bpp' => ['npd.persetujuan', 'persetujuan'],
            'verifikator' => ['npd.verifikasi', 'verifikasi'],
            default => ['npd.index', 'npd'],
        };

        $peringatanPelimpahan = PejabatResolver::untukNpd($npd)['peringatan'];
        $bolehKelolaArsip = in_array($role, ['superadmin', 'bendahara_pengeluaran', 'pptk', 'bpp', 'verifikator'], true);

        return view('npd.show', compact('npd', 'aksiTersedia', 'ruteDaftar', 'activeNav', 'peringatanPelimpahan', 'bolehKelolaArsip'));
    }

    /**
     * Transisi status workflow NPD (semua jenis). Port dari transisiNPD di
     * gas-lama/CodeRevisi.gs: superadmin boleh aksi apa pun, role lain hanya
     * aksi yang dipetakan di Npd::TRANSISI.
     */
    public function transisi(Request $request, Npd $npd)
    {
        abort_if($npd->sumber_data === 'import_historis', 422, 'NPD historis adalah arsip final dan tidak mengikuti workflow persetujuan/verifikasi.');

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

        // Batalkan status Selesai berarti induk tidak lagi memenuhi syarat "induk wajib
        // Selesai" untuk NPD Transport yang sudah menjadikannya induk — cegah supaya
        // turunan tidak menjadi yatim. Lihat Npd::punyaTurunanTransportAktif().
        if ($aksi === 'batal_selesai' && $npd->punyaTurunanTransportAktif()) {
            return back()->withErrors([
                'aksi' => 'NPD ini masih menjadi induk NPD Transport yang aktif. Batalkan NPD Transport tersebut terlebih dahulu.',
            ]);
        }

        $nomorUrut = null;

        if ($aksi === 'verifikasi') {
            $nomorUrut = (int) $request->input('nomor_urut');

            if ($nomorUrut < 1 || $nomorUrut > 999) {
                return back()->withErrors(['nomor_urut' => 'Nomor NPD harus antara 1 dan 999.']);
            }

            $bentrok = Npd::where('id', '!=', $npd->id)
                ->where('keu', $npd->keu)
                ->where('tahun', $npd->tahun)
                ->where('nomor_urut', $nomorUrut)
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

        try {
            DB::transaction(function () use ($request, $npd, $rule, $catatanBaru, $nomorUrut, $aksi) {
                $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
                if ($npd->status !== $rule['from']) {
                    throw ValidationException::withMessages(['aksi' => 'Status NPD telah berubah. Muat ulang halaman sebelum melanjutkan.']);
                }

                if ($aksi === 'batal_selesai' && $npd->punyaTurunanTransportAktif()) {
                    throw ValidationException::withMessages([
                        'aksi' => 'NPD ini masih menjadi induk NPD Transport yang aktif. Batalkan NPD Transport tersebut terlebih dahulu.',
                    ]);
                }

                $statusAsal = $npd->status;
                if ($aksi === 'verifikasi') {
                    $bentrok = Npd::query()->lockForUpdate()
                        ->whereKeyNot($npd->id)
                        ->where('keu', $npd->keu)
                        ->where('tahun', $npd->tahun)
                        ->where('nomor_urut', $nomorUrut)
                        ->first();
                    if ($bentrok) {
                        throw ValidationException::withMessages([
                            'nomor_urut' => "Nomor {$nomorUrut} sudah dipakai pada KEU {$npd->keu} tahun {$npd->tahun}.",
                        ]);
                    }
                    $npd->nomor_urut = $nomorUrut;
                    $npd->nomor_lengkap = Npd::buatNomorLengkap($nomorUrut, $npd->keu, $npd->bulan, $npd->tahun);
                }

                $npd->status = $rule['to'];
                $npd->catatan = $catatanBaru;
                $npd->save();
                $npd->catatHistoriStatus($request->user(), $aksi, $statusAsal, $rule['to'], $catatanBaru);
                $npd->mirrorStatusKeSuratPerintah();
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || ($e->errorInfo[0] ?? null) === '23000') {
                return back()->withErrors(['nomor_urut' => 'Nomor NPD baru saja dipakai oleh transaksi lain. Pilih nomor lain.']);
            }
            throw $e;
        }

        $nomorForLog = $npd->nomor_lengkap ?? "NPD #{$npd->id}";
        AuditLog::catat($rule['label'], "NPD: {$nomorForLog}".($catatanBaru ? " | {$catatanBaru}" : ''));

        return back()->with('success', "Status NPD diperbarui: {$rule['label']}.");
    }

    public function destroy(Request $request, Npd $npd)
    {
        $alasan = trim((string) $request->input('alasan'));
        if ($alasan === '') {
            return back()->withErrors(['alasan' => 'Alasan pembatalan wajib diisi.']);
        }

        $user = $request->user();
        $boleh = $user->isSuperadmin()
            || ($user->role === 'pptk' && $npd->status === 'Draft NPD - PPTK');
        abort_unless($boleh, 403);

        if ($npd->punyaTurunanTransportAktif()) {
            return back()->withErrors(['alasan' => 'NPD ini masih menjadi induk NPD Transport yang aktif. Batalkan NPD Transport tersebut terlebih dahulu.']);
        }

        DB::transaction(function () use ($npd, $user, $alasan) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            $boleh = $user->isSuperadmin()
                || ($user->role === 'pptk' && $npd->status === 'Draft NPD - PPTK');
            abort_unless($boleh && $npd->status !== 'Dibatalkan', 403);

            if ($npd->punyaTurunanTransportAktif()) {
                throw ValidationException::withMessages([
                    'alasan' => 'NPD ini masih menjadi induk NPD Transport yang aktif. Batalkan NPD Transport tersebut terlebih dahulu.',
                ]);
            }

            $statusAsal = $npd->status;
            $npd->status = 'Dibatalkan';
            $npd->catatan = '[Dibatalkan] '.$alasan;
            $npd->nomor_urut = null;
            $npd->nomor_lengkap = null;
            $npd->save();
            $npd->catatHistoriStatus($user, 'batalkan', $statusAsal, 'Dibatalkan', $alasan);
            $npd->lepaskanSuratPerintah();

            if ($statusAsal === 'Draft NPD - PPTK') {
                $npd->delete();
            }
        });

        AuditLog::catat('Batalkan NPD', 'NPD #'.$npd->id.' | '.$alasan);

        return redirect()->route('npd.index')->with('success', 'NPD berhasil dibatalkan dengan aman.');
    }

    /**
     * Cetak dokumen NPD utama (F4). Port dari tpl_npd.html + buatNPD() di
     * gas-lama/Code.gs. Di-generate on-demand, tidak disimpan ke disk.
     */
    public function cetakNpd(Npd $npd)
    {
        $npd->load('masterAnggaran.tagging');

        $pejabat = PejabatResolver::untukNpd($npd);

        $html = view('npd.pdf.npd', [
            'npd' => $npd,
            'kpa' => $pejabat['kpa'],
            'pptk' => $pejabat['pptk'],
            'noDpa' => $pejabat['no_dpa'],
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
     * Cetak Lampiran NPD. Port dari tpl_lampiran.html. Untuk jenis 'bj':
     * multi-penerima dengan kolom PPh adaptif (_rowsLampiran di Code.gs).
     * Untuk jenis 'pd': 1 baris = total tim (lihat buatNPDPerjalanan di
     * CodePerjalanan.gs), tidak melalui npd_penerima sama sekali.
     */
    public function cetakLampiran(Npd $npd)
    {
        $npd->load($npd->sumber_data === 'import_historis' ? ['masterAnggaran', 'penerima.pphList'] : match ($npd->jenis) {
            'pd', 'tr' => ['masterAnggaran', 'tim.paket'],
            'ns' => ['masterAnggaran', 'narasumber'],
            'kd' => ['masterAnggaran', 'peserta'],
            default => ['masterAnggaran', 'penerima.pphList'],
        });

        $pptk = PejabatResolver::untukNpd($npd)['pptk'];

        if ($npd->sumber_data === 'import_historis') {
            $html = view('npd.pdf.lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranPph($npd->penerima)))->render();
        } elseif (in_array($npd->jenis, ['pd', 'tr'], true)) {
            $html = view('npd.pdf.pd-lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranPd($npd)))->render();
        } elseif ($npd->jenis === 'ns') {
            $html = view('npd.pdf.lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranNara($npd)))->render();
        } elseif ($npd->jenis === 'kd') {
            $html = view('npd.pdf.lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranKontribusiDiklat($npd)))->render();
        } else {
            $html = view('npd.pdf.lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranPph($npd->penerima)))->render();
        }

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
     * Cetak Daftar Pembayaran NPD Perjalanan Dinas. Port dari tpl_pd_daftar.html
     * + _rowsDaftarBayar() di gas-lama/CodePerjalanan.gs.
     */
    public function cetakDaftar(Npd $npd)
    {
        abort_unless(in_array($npd->jenis, ['pd', 'tr'], true), 404);

        $npd->load(['masterAnggaran', 'tim.paket']);

        $detail = $npd->detail_json ?? [];
        $komponen = $this->komponenBiayaPd($npd);
        $rows = $this->rowsDaftarBayar($npd->tim);
        $pejabat = PejabatResolver::untukNpd($npd);

        $html = view('npd.pdf.pd-daftar', [
            'npd' => $npd,
            'detail' => $detail,
            'uraianBiaya' => $komponen['uraian_biaya'],
            'tglBerangkat' => $this->tanggalIndo($detail['tanggal_berangkat'] ?? null),
            'tglPulang' => $this->tanggalIndo($detail['tanggal_pulang'] ?? null),
            'tglSp' => $this->tanggalIndo($detail['tanggal_sp'] ?? null),
            'rowsBody' => $rows['body'],
            'kpa' => $pejabat['kpa'],
            'bpp' => $pejabat['bpp'],
            'bulanNpd' => $npd->tanggal_npd->translatedFormat('F'),
        ])->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak Daftar Pembayaran NPD', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'daftar-pembayaran-npd-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Cetak Daftar Pembayaran Honorarium Narasumber. Port dari
     * tpl_nara_daftar.html + _rowsDaftarNara() di gas-lama/CodeNarasumber.gs.
     */
    public function cetakDaftarNarasumber(Npd $npd)
    {
        abort_unless($npd->jenis === 'ns', 404);

        $npd->load(['masterAnggaran', 'narasumber']);

        $rows = $this->rowsDaftarNara($npd->narasumber);
        $pejabat = PejabatResolver::untukNpd($npd);

        $html = view('npd.pdf.ns-daftar', [
            'npd' => $npd,
            'intro' => $this->introNarasumber($npd),
            'rowsBody' => $rows['body'],
            'kpa' => $pejabat['kpa'],
            'bpp' => $pejabat['bpp'],
            'bulanNpd' => $npd->tanggal_npd->translatedFormat('F'),
        ])->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak Daftar Pembayaran Narasumber', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'daftar-pembayaran-narasumber-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Cetak Daftar Bayar NPD Kontribusi Diklat — templatenya mengikuti mode:
     * 'kontribusi' -> tpl_kd_daftar.html, 'perjalanan' -> tpl_kd_pd_daftar.html.
     * Port dari gas-lama/CodeKontribusiDiklat.gs.
     */
    public function cetakDaftarKontribusiDiklat(Npd $npd)
    {
        abort_unless($npd->jenis === 'kd', 404);

        $npd->load(['masterAnggaran', 'peserta']);

        $detail = $npd->detail_json ?? [];
        $isPerjalanan = $npd->mode_kd === 'perjalanan';
        $pejabat = PejabatResolver::untukNpd($npd);
        $namaPelatihan = $detail['nama_pelatihan'] ?? '';

        $view = $isPerjalanan ? 'npd.pdf.kd-pd-daftar' : 'npd.pdf.kd-daftar';
        $rows = $isPerjalanan ? $this->rowsDaftarKdPerjalanan($npd->peserta) : $this->rowsDaftarKd($npd->peserta);
        $uraianBiaya = $this->uraianKontribusiDiklat($npd->peserta, $namaPelatihan, $isPerjalanan);

        $html = view($view, [
            'npd' => $npd,
            'uraianBiaya' => $uraianBiaya,
            'tglMulai' => $this->tanggalIndo($detail['tanggal_mulai'] ?? null),
            'tglSelesai' => $this->tanggalIndo($detail['tanggal_selesai'] ?? null),
            'rowsBody' => $rows['body'],
            'kpa' => $pejabat['kpa'],
            'bpp' => $pejabat['bpp'],
            'bulanNpd' => $npd->tanggal_npd->translatedFormat('F'),
        ])->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak Daftar Bayar Kontribusi Diklat', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'daftar-bayar-kontribusi-diklat-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Cetak SPD Rampung NPD Perjalanan Dinas. Port dari tpl_pd_spd.html +
     * _rowsSPDRampung() di gas-lama/CodePerjalanan.gs.
     */
    public function cetakSpd(Npd $npd)
    {
        abort_unless(in_array($npd->jenis, ['pd', 'tr'], true), 404);

        $npd->load(['masterAnggaran', 'tim.paket']);

        $detail = $npd->detail_json ?? [];
        $komponen = $this->komponenBiayaPd($npd);
        $sr = $this->rowsSpdRampung($npd->tim);
        $pejabat = PejabatResolver::untukNpd($npd);

        $penerimaTim = $npd->tim->firstWhere('is_penerima', true) ?? $npd->tim->first();

        $blokRepresentatif = '';
        if ($sr['t_rp'] > 0) {
            $blokRepresentatif = '<tr class="kat-row"><td class="center bold">IV</td><td class="kat" colspan="5">Uang Representatif</td></tr>'
                .$sr['rows_rp']
                .'<tr class="jml-row"><td colspan="5" class="bold" style="text-align:right;padding-right:8pt;">Jumlah Uang Representatif</td><td class="num bold">'.fmt_rupiah($sr['t_rp']).'</td></tr>';
        }

        $html = view('npd.pdf.pd-spd', [
            'npd' => $npd,
            'detail' => $detail,
            'uraianBiaya' => $komponen['uraian_biaya'],
            'tglBerangkat' => $this->tanggalIndo($detail['tanggal_berangkat'] ?? null),
            'tglPulang' => $this->tanggalIndo($detail['tanggal_pulang'] ?? null),
            'tglSp' => $this->tanggalIndo($detail['tanggal_sp'] ?? null),
            'sr' => $sr,
            'blokRepresentatif' => $blokRepresentatif,
            'kpa' => $pejabat['kpa'],
            'bpp' => $pejabat['bpp'],
            'penerima' => (object) ['nama' => $penerimaTim->nama ?? '', 'nip' => $penerimaTim->nip ?? ''],
            'bulanNpd' => $npd->tanggal_npd->translatedFormat('F'),
            'logoPath' => $this->logoKopPath(),
        ])->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 13,
            'margin_bottom' => 13,
            'default_font' => 'arial',
        ]);
        $mpdf->WriteHTML($html);

        AuditLog::catat('Cetak SPD Rampung NPD', 'Nomor NPD: '.($npd->nomor_lengkap ?? "#{$npd->id}"));

        $fileName = 'spd-rampung-npd-'.$npd->id.'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    private function logoKopPath(): ?string
    {
        $path = storage_path('app/import/Coat_of_arms_of_West_Java.svg');

        return file_exists($path) ? $path : null;
    }

    /** Format tanggal Indonesia dari string "Y-m-d" di detail_json. Port dari fmtTanggalIndo di gas-lama/Code.gs. */
    private function tanggalIndo(?string $tanggal): string
    {
        return $tanggal ? Carbon::parse($tanggal)->translatedFormat('d F Y') : '';
    }

    /**
     * Sel nominal gaya "Rp    [angka]" — Rp mepet kiri, angka rata kanan,
     * tanpa desimal. Nilai <=0 -> sel kosong total. Port dari _selRp/
     * _selRpSpan di gas-lama/CodePerjalanan.gs — pakai tabel bersarang asli
     * (bukan float+clear) sebab mPDF tidak konsisten merender itu.
     */
    private function selRp(float $n, ?int $rowspan = null, bool $bold = false): string
    {
        $rowspanAttr = $rowspan ? " rowspan=\"{$rowspan}\"" : '';
        $cls = 'rp'.($bold ? ' bold' : '');

        if ($n <= 0) {
            return "<td class=\"{$cls}\"{$rowspanAttr}></td>";
        }

        $angka = number_format($n, 0, ',', '.');

        return "<td class=\"{$cls}\"{$rowspanAttr}><table class=\"rpwrap\"><tr><td class=\"rp-l\">Rp</td><td class=\"rp-a\">{$angka}</td></tr></table></td>";
    }

    /**
     * Komponen biaya yang benar-benar dipakai (nilai total > 0) di antara
     * {uang harian, akomodasi, transport, uang representatif} — dipakai di
     * uraian PDF Daftar Pembayaran, SPD Rampung, & Lampiran. Port dari
     * blok "Uraian adaptif" di buatNPDPerjalanan() gas-lama/CodePerjalanan.gs.
     */
    private function komponenBiayaPd(Npd $npd): array
    {
        $totUh = 0.0;
        $totAk = 0.0;
        $totTr = 0.0;
        $totRp = 0.0;

        foreach ($npd->tim as $t) {
            $h = $t->hitung();
            $totUh += $h['jml_harian'];
            $totAk += $h['jml_akom'];
            $totTr += $h['jml_transport'];
            $totRp += $h['representatif'];
        }

        $komp = [];
        if ($totUh > 0) {
            $komp[] = 'uang harian';
        }
        if ($totAk > 0) {
            $komp[] = 'akomodasi';
        }
        if ($totTr > 0) {
            $komp[] = 'transport';
        }
        if ($totRp > 0) {
            $komp[] = 'uang representatif';
        }

        $kompStr = match (true) {
            count($komp) === 1 => $komp[0],
            count($komp) > 1 => implode(', ', array_slice($komp, 0, -1)).' dan '.end($komp),
            default => '',
        };

        $uraianBiaya = 'Pembayaran Belanja Perjalanan Dinas Biasa'.($kompStr !== '' ? " ({$kompStr})" : '');

        return ['komp_str' => $kompStr, 'uraian_biaya' => $uraianBiaya];
    }

    /**
     * Baris tabel Daftar Pembayaran (multi-baris per orang, rowspan untuk
     * kolom Representatif/Transport/Jumlah). Port 1:1 dari _rowsDaftarBayar
     * di gas-lama/CodePerjalanan.gs.
     *
     * @param  Collection<int, NpdTim>  $tim
     */
    private function rowsDaftarBayar(Collection $tim): array
    {
        $body = '';
        $tHarian = $tAkom = $tTransport = $tRepr = $tJumlah = 0.0;
        $no = 0;

        foreach ($tim as $anggota) {
            $no++;
            $h = $anggota->hitung();
            $tHarian += $h['jml_harian'];
            $tAkom += $h['jml_akom'];
            $tTransport += $h['jml_transport'];
            $tRepr += $h['representatif'];
            $tJumlah += $h['jumlah'];

            $paketList = $h['paket'] ?: [['lama_hari' => 0, 'tarif_uh' => 0, 'sub_uh' => 0, 'malam' => 0, 'tarif_akom' => 0, 'sub_akom' => 0]];
            $nPaket = max(count($paketList), 1);

            foreach (array_values($paketList) as $idx => $p) {
                $body .= '<tr class="drow'.($nPaket === 1 ? ' drow1' : '').'">';

                if ($idx === 0) {
                    $body .= '<td class="center" rowspan="'.$nPaket.'">'.$no.'</td>'
                        .'<td rowspan="'.$nPaket.'">'.e($anggota->nama).'</td>'
                        .'<td rowspan="'.$nPaket.'">'.e($anggota->jabatan).'</td>';
                }

                $body .= '<td class="center">'.(int) $p['lama_hari'].'</td>'
                    .$this->selRp((float) $p['tarif_uh'])
                    .$this->selRp((float) $p['sub_uh'])
                    .'<td class="center">'.(int) $p['malam'].'</td>'
                    .$this->selRp((float) $p['tarif_akom'])
                    .$this->selRp((float) $p['sub_akom']);

                if ($idx === 0) {
                    $body .= $this->selRp($h['representatif'], $nPaket)
                        .$this->selRp($h['jml_transport'], $nPaket)
                        .$this->selRp($h['jumlah'], $nPaket, true)
                        .'<td rowspan="'.$nPaket.'"></td>';
                }

                $body .= '</tr>';
            }
        }

        $body .= '<tr class="bold">'
            .'<td class="center" colspan="5">J U M L A H</td>'
            .$this->selRp($tHarian)
            .'<td colspan="2"></td>'
            .$this->selRp($tAkom)
            .$this->selRp($tRepr)
            .$this->selRp($tTransport)
            .$this->selRp($tJumlah)
            .'<td></td>'
            .'</tr>';

        return compact('body', 'tHarian', 'tAkom', 'tTransport', 'tRepr', 'tJumlah');
    }

    /**
     * Blok Rincian Biaya SPD Rampung, dikelompokkan per kategori (I-IV),
     * digabung per nama (rowspan). Port 1:1 dari _rowsSPDRampung di
     * gas-lama/CodePerjalanan.gs.
     *
     * @param  Collection<int, NpdTim>  $tim
     */
    private function rowsSpdRampung(Collection $tim): array
    {
        $rowsUh = $rowsAk = $rowsTr = $rowsRp = '';
        $tUh = $tAk = $tTr = $tRp = 0.0;

        // ---- I. Uang Harian ----
        $no = 0;
        foreach ($tim as $anggota) {
            $no++;
            $h = $anggota->hitung();
            $paket = $h['paket'] ?: [['lama_hari' => 0, 'tarif_uh' => 0, 'sub_uh' => 0]];
            $nP = count($paket);
            $jab = e($anggota->jabatan);

            foreach (array_values($paket) as $idx => $p) {
                $tUh += $p['sub_uh'];
                $rowsUh .= '<tr class="dat">';
                if ($idx === 0) {
                    $rowsUh .= '<td class="center v" rowspan="'.$nP.'">'.$no.'</td>'
                        .'<td class="v" rowspan="'.$nP.'">'.e($anggota->nama).'</td>'
                        .'<td class="v" rowspan="'.$nP.'">'.$jab.'</td>';
                }
                $rowsUh .= '<td class="center">'.(int) $p['lama_hari'].' hari x</td>'
                    .'<td class="num">'.fmt_rupiah($p['tarif_uh']).'</td>'
                    .'<td class="num">'.fmt_rupiah($p['sub_uh']).'</td></tr>';
            }
        }

        // ---- II. Uang Akomodasi ----
        $no = 0;
        foreach ($tim as $anggota) {
            $no++;
            $h = $anggota->hitung();
            $paket = $h['paket'] ?: [['malam' => 0, 'tarif_akom' => 0, 'sub_akom' => 0]];
            $nP = count($paket);
            $jab = e($anggota->jabatan);

            foreach (array_values($paket) as $idx => $p) {
                $tAk += $p['sub_akom'];
                $rowsAk .= '<tr class="dat">';
                if ($idx === 0) {
                    $rowsAk .= '<td class="center v" rowspan="'.$nP.'">'.$no.'</td>'
                        .'<td class="v" rowspan="'.$nP.'">'.e($anggota->nama).'</td>'
                        .'<td class="v" rowspan="'.$nP.'">'.$jab.'</td>';
                }
                $rowsAk .= '<td class="center">'.(int) $p['malam'].' malam x</td>'
                    .'<td class="num">'.fmt_rupiah($p['tarif_akom']).'</td>'
                    .'<td class="num">'.fmt_rupiah($p['sub_akom']).'</td></tr>';
            }
        }

        // ---- III. Transport: 3 baris agregat (BBM liter, e-Toll, Tiket) ----
        $totBbm = $totTol = $totTiket = $totLiter = 0.0;
        foreach ($tim as $anggota) {
            $h = $anggota->hitung();
            $totBbm += $h['bbm'];
            $totTol += $h['tol'];
            $totTiket += $h['tiket'];
            $totLiter += (float) $anggota->bbm_liter;
        }
        $tTr = $totBbm + $totTol + $totTiket;
        $literStr = str_replace('.', ',', (string) (round($totLiter * 100) / 100));
        $rowsTr =
            '<tr class="dat"><td class="center v">1</td><td class="v">BBM</td><td class="v"></td>'
                .'<td class="center">'.($totBbm > 0 ? $literStr.' liter' : '').'</td><td class="num"></td><td class="num">'.fmt_rupiah($totBbm).'</td></tr>'
            .'<tr class="dat"><td class="center v">2</td><td class="v">e-Toll</td><td class="v"></td>'
                .'<td class="center"></td><td class="num"></td><td class="num">'.fmt_rupiah($totTol).'</td></tr>'
            .'<tr class="dat"><td class="center v">3</td><td class="v">Tiket</td><td class="v"></td>'
                .'<td class="center"></td><td class="num"></td><td class="num">'.fmt_rupiah($totTiket).'</td></tr>';

        // ---- IV. Uang Representatif (hanya kalau ada) ----
        $no = 0;
        foreach ($tim as $anggota) {
            $no++;
            $h = $anggota->hitung();
            if ($h['representatif'] > 0) {
                $tRp += $h['representatif'];
                $rowsRp .= '<tr class="dat"><td class="center v">'.$no.'</td><td class="v">'.e($anggota->nama).'</td>'
                    .'<td class="v">'.e($anggota->jabatan).'</td>'
                    .'<td class="center">1 keg x</td><td class="num">'.fmt_rupiah($h['representatif']).'</td>'
                    .'<td class="num">'.fmt_rupiah($h['representatif']).'</td></tr>';
            }
        }

        return [
            'rows_uh' => $rowsUh, 'rows_ak' => $rowsAk, 'rows_tr' => $rowsTr, 'rows_rp' => $rowsRp,
            't_uh' => $tUh, 't_ak' => $tAk, 't_tr' => $tTr, 't_rp' => $tRp,
            'total' => $tUh + $tAk + $tTr + $tRp,
        ];
    }

    /**
     * Data Lampiran NPD Perjalanan Dinas: 1 baris = total tim (bukan
     * multi-penerima seperti BJ). Port dari blok "4. Lampiran" di
     * buatNPDPerjalanan() gas-lama/CodePerjalanan.gs.
     */
    private function bangunLampiranPd(Npd $npd): array
    {
        $detail = $npd->detail_json ?? [];
        $penerimaTim = $npd->tim->firstWhere('is_penerima', true) ?? $npd->tim->first();

        $penerima = (object) [
            'nama' => $penerimaTim->nama ?? '',
            'rekening' => $penerimaTim->rekening ?? '',
        ];

        $komponen = $this->komponenBiayaPd($npd);

        $ketDefault = 'Transfer Pembayaran Belanja Perjalanan Dinas Biasa'
            .($komponen['komp_str'] !== '' ? " ({$komponen['komp_str']})" : '')
            .' terhitung tanggal '.$this->tanggalIndo($detail['tanggal_berangkat'] ?? null)
            .' s.d '.$this->tanggalIndo($detail['tanggal_pulang'] ?? null)
            .' dalam rangka '.($detail['uraian_sp'] ?? '')
            .', berdasarkan Surat Perintah Nomor: '.($detail['nomor_sp'] ?? '')
            .' tanggal '.$this->tanggalIndo($detail['tanggal_sp'] ?? null)
            .' an. '.$penerima->nama;

        $keterangan = filled($detail['keterangan_lampiran'] ?? null) ? $detail['keterangan_lampiran'] : $ketDefault;

        return ['penerima' => $penerima, 'keterangan' => $keterangan];
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

    /**
     * Baris tabel Daftar Pembayaran Narasumber (NO, NAMA, JABATAN, JP, SATUAN,
     * HONOR, TRANSPORT, KOTOR, PPh 21, DITERIMA). Port 1:1 dari _rowsDaftarNara
     * di gas-lama/CodeNarasumber.gs.
     *
     * @param  Collection<int, NpdNarasumber>  $narasumber
     */
    private function rowsDaftarNara(Collection $narasumber): array
    {
        $body = '';
        $tHonor = $tTransport = $tBruto = $tPph = $tNetto = 0.0;
        $no = 0;

        foreach ($narasumber as $n) {
            $no++;
            $honor = (float) $n->honor;
            $transport = (float) $n->transport;
            $bruto = (float) $n->bruto;
            $pph21 = (float) $n->pph21;
            $netto = (float) $n->netto;

            $tHonor += $honor;
            $tTransport += $transport;
            $tBruto += $bruto;
            $tPph += $pph21;
            $tNetto += $netto;

            $body .= '<tr>'
                .'<td class="center">'.$no.'</td>'
                .'<td>'.e($n->nama).'</td>'
                .'<td>'.e($n->jabatan).'</td>'
                .'<td class="center">'.(int) $n->jumlah_jp.'</td>'
                .'<td class="num">'.fmt_rupiah($n->tarif_jp).'</td>'
                .'<td class="num">'.fmt_rupiah($honor).'</td>'
                .'<td class="num">'.($transport > 0 ? fmt_rupiah($transport) : '-').'</td>'
                .'<td class="num">'.fmt_rupiah($bruto).'</td>'
                .'<td class="num">'.fmt_rupiah($pph21).'</td>'
                .'<td class="num bold">'.fmt_rupiah($netto).'</td>'
                .'<td></td>'
                .'</tr>';
        }

        $body .= '<tr class="bold">'
            .'<td colspan="5" class="center">J U M L A H</td>'
            .'<td class="num">'.fmt_rupiah($tHonor).'</td>'
            .'<td class="num">'.($tTransport > 0 ? fmt_rupiah($tTransport) : '-').'</td>'
            .'<td class="num">'.fmt_rupiah($tBruto).'</td>'
            .'<td class="num">'.fmt_rupiah($tPph).'</td>'
            .'<td class="num">'.fmt_rupiah($tNetto).'</td>'
            .'<td></td>'
            .'</tr>';

        return compact('body', 'tHonor', 'tTransport', 'tBruto', 'tPph', 'tNetto');
    }

    /**
     * Kalimat intro Daftar Pembayaran & keterangan default Lampiran Narasumber.
     * Port dari introDaftar di buatNPDNarasumber() gas-lama/CodeNarasumber.gs.
     */
    private function introNarasumber(Npd $npd): string
    {
        $detail = $npd->detail_json ?? [];
        $uraianKeg = $detail['uraian_kegiatan'] ?? '';
        $tglMulai = $detail['tanggal_mulai'] ?? null;
        $tglSelesai = $detail['tanggal_selesai'] ?? null;

        $periode = ($tglMulai && $tglSelesai)
            ? 'pada tanggal '.$this->tanggalIndo($tglMulai).' s.d '.$this->tanggalIndo($tglSelesai)
            : '';

        return 'Pembayaran Honorarium Narasumber atau Pembahas, Moderator, Pembawa Acara dan Panitia (Narasumber) dalam rangka '.$uraianKeg.($periode !== '' ? ' '.$periode : '');
    }

    /**
     * Data Lampiran NPD Narasumber: satu baris per narasumber, kolom PPh
     * tunggal "PPh Pasal 21". PPN dan Biaya KU/RTGS selalu nol, sama seperti
     * _rowsLampiranNara di gas-lama/CodeNarasumber.gs.
     */
    private function bangunLampiranNara(Npd $npd): array
    {
        $kolomPph = ['PPh Pasal 21'];
        $totals = ['bruto' => 0.0, 'ppn' => 0.0, 'biaya' => 0.0, 'transfer' => 0.0];
        $totalsPph = ['PPh Pasal 21' => 0.0];
        $ketDefault = $this->introNarasumber($npd);
        $rows = [];

        foreach ($npd->narasumber as $n) {
            $bruto = (float) $n->bruto;
            $pph21 = (float) $n->pph21;
            $netto = (float) $n->netto;

            $totals['bruto'] += $bruto;
            $totals['transfer'] += $netto;
            $totalsPph['PPh Pasal 21'] += $pph21;

            $rows[] = [
                'nama' => $n->nama,
                'rekening' => $n->rekening,
                'bruto' => $bruto,
                'ppn' => 0.0,
                'pph' => ['PPh Pasal 21' => $pph21],
                'biaya' => 0.0,
                'transfer' => $netto,
                'keterangan' => filled($n->uraian) ? $n->uraian : $ketDefault,
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

    /**
     * Baris tabel Daftar Bayar Kontribusi (mode 'kontribusi'). Port 1:1 dari
     * _rowsDaftarKD() di gas-lama/CodeKontribusiDiklat.gs.
     *
     * @param  Collection<int, NpdPeserta>  $peserta
     */
    private function rowsDaftarKd(Collection $peserta): array
    {
        $body = '';
        $tKontribusi = $tMooc = $tJumlah = 0.0;
        $no = 0;

        foreach ($peserta as $p) {
            $no++;
            $jmlKontribusi = (float) $p->jumlah_kontribusi;
            $jmlMooc = (float) $p->jumlah_mooc;
            $subKontribusi = (float) $p->sub_kontribusi;

            $tKontribusi += $jmlKontribusi;
            $tMooc += $jmlMooc;
            $tJumlah += $subKontribusi;

            $body .= '<tr class="drow1">'
                .'<td class="center">'.$no.'</td>'
                .'<td>'.e($p->nama).'</td>'
                .'<td class="center">'.e($p->pangkat).'</td>'
                .'<td class="center">'.($p->volume_kontribusi ?: '').'</td>'
                .$this->selRp((float) $p->tarif_kontribusi)
                .$this->selRp($jmlKontribusi)
                .'<td class="center">'.($p->volume_mooc ?: '').'</td>'
                .$this->selRp((float) $p->tarif_mooc)
                .$this->selRp($jmlMooc)
                .$this->selRp($subKontribusi, null, true)
                .'<td></td>'
                .'</tr>';
        }

        $body .= '<tr class="bold">'
            .'<td class="center" colspan="5">J U M L A H</td>'
            .$this->selRp($tKontribusi)
            .'<td colspan="2"></td>'
            .$this->selRp($tMooc)
            .$this->selRp($tJumlah)
            .'<td></td>'
            .'</tr>';

        return compact('body', 'tKontribusi', 'tMooc', 'tJumlah');
    }

    /**
     * Baris tabel Daftar Bayar Perjalanan Dinas Diklat (mode 'perjalanan').
     * Port 1:1 dari _rowsDaftarKDPerjalanan() di gas-lama/CodeKontribusiDiklat.gs.
     *
     * @param  Collection<int, NpdPeserta>  $peserta
     */
    private function rowsDaftarKdPerjalanan(Collection $peserta): array
    {
        $body = '';
        $tHarian = $tAkom = $tSaku = $tTransport = $tJumlah = 0.0;
        $no = 0;

        foreach ($peserta as $p) {
            $no++;
            $jmlHarian = (float) $p->jumlah_harian;
            $jmlAkom = (float) $p->jumlah_akomodasi;
            $jmlSaku = (float) $p->jumlah_saku;
            $transport = (float) $p->transport;
            $subPerjalanan = (float) $p->sub_perjalanan;

            $tHarian += $jmlHarian;
            $tAkom += $jmlAkom;
            $tSaku += $jmlSaku;
            $tTransport += $transport;
            $tJumlah += $subPerjalanan;

            $body .= '<tr class="drow1">'
                .'<td class="center">'.$no.'</td>'
                .'<td>'.e($p->nama).'</td>'
                .'<td class="center">'.e($p->pangkat).'</td>'
                .'<td class="center">'.($p->hari_uh ?: '').'</td>'
                .$this->selRp((float) $p->tarif_uh)
                .$this->selRp($jmlHarian)
                .'<td class="center">'.($p->volume_akomodasi ?: '').'</td>'
                .$this->selRp((float) $p->tarif_akomodasi)
                .$this->selRp($jmlAkom)
                .'<td class="center">'.($p->hari_saku ?: '').'</td>'
                .$this->selRp((float) $p->tarif_saku)
                .$this->selRp($jmlSaku)
                .$this->selRp($transport)
                .$this->selRp($subPerjalanan, null, true)
                .'<td></td>'
                .'</tr>';
        }

        $body .= '<tr class="bold">'
            .'<td class="center" colspan="5">J U M L A H</td>'
            .$this->selRp($tHarian)
            .'<td colspan="2"></td>'
            .$this->selRp($tAkom)
            .'<td></td>'
            .'<td></td>'
            .$this->selRp($tSaku)
            .$this->selRp($tTransport)
            .$this->selRp($tJumlah)
            .'<td></td>'
            .'</tr>';

        return compact('body', 'tHarian', 'tAkom', 'tSaku', 'tTransport', 'tJumlah');
    }

    /**
     * Uraian biaya adaptif untuk Daftar Bayar Kontribusi Diklat, tergantung
     * komponen mana yang benar-benar terisi (>0). Port dari blok
     * "_gabungFrasa" + uraianBiaya/uraianPD di buatNPDKontribusiDiklat()
     * gas-lama/CodeKontribusiDiklat.gs.
     *
     * @param  Collection<int, NpdPeserta>  $peserta
     */
    private function uraianKontribusiDiklat(Collection $peserta, string $namaPelatihan, bool $isPerjalanan): string
    {
        $gabung = function (array $items): string {
            $items = array_values(array_filter($items));
            if ($items === []) {
                return '';
            }
            if (count($items) === 1) {
                return $items[0];
            }
            $terakhir = array_pop($items);

            return implode(', ', $items).' dan '.$terakhir;
        };

        if ($isPerjalanan) {
            $komponen = $gabung([
                $peserta->contains(fn ($p) => (float) $p->jumlah_harian > 0) ? 'uang harian' : '',
                $peserta->contains(fn ($p) => (float) $p->jumlah_akomodasi > 0) ? 'akomodasi' : '',
                $peserta->contains(fn ($p) => (float) $p->jumlah_saku > 0) ? 'uang saku diklat' : '',
                $peserta->contains(fn ($p) => (float) $p->transport > 0) ? 'transportasi' : '',
            ]);

            return 'Pembayaran Belanja Perjalanan Dinas Biasa ('.($komponen !== '' ? $komponen : 'uang harian').') dalam rangka Mengikuti '.$namaPelatihan;
        }

        $komponen = $gabung([
            $peserta->contains(fn ($p) => (float) $p->jumlah_kontribusi > 0) ? 'kontribusi' : '',
            $peserta->contains(fn ($p) => (float) $p->jumlah_mooc > 0) ? 'uang MOOC diklat' : '',
        ]);

        return 'Pembayaran Belanja Kursus Singkat/Pelatihan ('.($komponen !== '' ? $komponen : 'kontribusi diklat').') dalam rangka Mengikuti '.$namaPelatihan;
    }

    /**
     * Data Lampiran NPD Kontribusi Diklat: SATU baris transfer ke penerima
     * dana terpilih (bukan per peserta) senilai nominal NPD (subtotal mode),
     * dengan PPN/PPh/biaya lain manual. Port dari blok "3. Lampiran" di
     * buatNPDKontribusiDiklat() gas-lama/CodeKontribusiDiklat.gs.
     */
    private function bangunLampiranKontribusiDiklat(Npd $npd): array
    {
        $detail = $npd->detail_json ?? [];
        $penerimaIndex = (int) ($detail['penerima_index'] ?? 0);
        $penerima = $npd->peserta->get($penerimaIndex) ?? $npd->peserta->first();

        $ppn = (float) ($detail['ppn'] ?? 0);
        $pphJenis = filled($detail['pph_jenis'] ?? null) ? $detail['pph_jenis'] : 'PPh';
        $pphNilai = (float) ($detail['pph_nilai'] ?? 0);
        $biayaLain = (float) ($detail['biaya_lain'] ?? 0);
        $bruto = (float) $npd->nominal;
        $transfer = $bruto - $ppn - $pphNilai - $biayaLain;

        $namaPelatihan = $detail['nama_pelatihan'] ?? '';
        $isPerjalanan = $npd->mode_kd === 'perjalanan';
        $periode = 'terhitung tanggal '.$this->tanggalIndo($detail['tanggal_mulai'] ?? null).' s.d '.$this->tanggalIndo($detail['tanggal_selesai'] ?? null);
        $ketDefault = ($isPerjalanan ? 'Transfer Pembayaran Belanja Perjalanan Dinas' : 'Transfer Pembayaran Belanja Kontribusi Diklat')
            .' dalam rangka Mengikuti '.$namaPelatihan.' '.$periode.' an. '.($penerima->nama ?? '');

        $keterangan = filled($detail['keterangan_lampiran'] ?? null) ? $detail['keterangan_lampiran'] : $ketDefault;

        return [
            'kolomPph' => [$pphJenis],
            'rows' => [[
                'nama' => $penerima->nama ?? '',
                'rekening' => $penerima->rekening ?? '',
                'bruto' => $bruto,
                'ppn' => $ppn,
                'pph' => [$pphJenis => $pphNilai],
                'biaya' => $biayaLain,
                'transfer' => $transfer,
                'keterangan' => $keterangan,
            ]],
            'totals' => ['bruto' => $bruto, 'ppn' => $ppn, 'biaya' => $biayaLain, 'transfer' => $transfer],
            'totalsPph' => [$pphJenis => $pphNilai],
            'nominalColspan' => 4 + 1,
        ];
    }
}
