<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\PejabatResolver;
use App\Models\Npd;
use App\Models\NpdPenerima;
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
        $npd->load(['masterAnggaran.tagging', 'penerima.pphList', 'tim.paket', 'dibuatOleh']);

        $role = $request->user()->role;
        $aksiTersedia = $npd->aksiTersedia($role);

        [$ruteDaftar, $activeNav] = match ($role) {
            'bpp' => ['npd.persetujuan', 'persetujuan'],
            'verifikator' => ['npd.verifikasi', 'verifikasi'],
            default => ['npd.index', 'npd'],
        };

        $peringatanPelimpahan = PejabatResolver::untukNpd($npd)['peringatan'];

        return view('npd.show', compact('npd', 'aksiTersedia', 'ruteDaftar', 'activeNav', 'peringatanPelimpahan'));
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
        $npd->load($npd->jenis === 'pd' ? ['masterAnggaran', 'tim.paket'] : ['masterAnggaran', 'penerima.pphList']);

        $pptk = PejabatResolver::untukNpd($npd)['pptk'];

        if ($npd->jenis === 'pd') {
            $html = view('npd.pdf.pd-lampiran', array_merge([
                'npd' => $npd,
                'pptk' => $pptk,
            ], $this->bangunLampiranPd($npd)))->render();
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
        abort_unless($npd->jenis === 'pd', 404);

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
     * Cetak SPD Rampung NPD Perjalanan Dinas. Port dari tpl_pd_spd.html +
     * _rowsSPDRampung() di gas-lama/CodePerjalanan.gs.
     */
    public function cetakSpd(Npd $npd)
    {
        abort_unless($npd->jenis === 'pd', 404);

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
        return $tanggal ? \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('d F Y') : '';
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
     * @param  Collection<int, \App\Models\NpdTim>  $tim
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
     * @param  Collection<int, \App\Models\NpdTim>  $tim
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
}
