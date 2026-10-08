<?php

namespace App\Http\Controllers;

use App\Services\NpdRevisiService;
use App\Services\SpjBerkasService;
use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdKontribusiDiklatRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Support\KeteranganLampiranIsian;
use App\Models\Pegawai;
use App\Support\AnggaranNpd;
use App\Support\PptkPenerima;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NpdKontribusiDiklatController extends Controller
{
    public function create()
    {
        return $this->form();
    }

    public function store(StoreNpdKontribusiDiklatRequest $request, SpjBerkasService $spj)
    {
        $data = $request->validated();
        $mode = $data['mode'];

        $masterAnggaran = MasterAnggaran::findOrFail($data['master_anggaran_id']);
        $keu = $masterAnggaran->tentukanKeu();

        if ($keu === null) {
            return back()->withInput()->withErrors([
                'master_anggaran_id' => 'Sub kegiatan pada sumber dana ini tidak dapat dipetakan ke KEU (harus diawali 6.01.01, 6.01.02, atau 6.01.03).',
            ]);
        }

        // Referensi SP (opsional) hanya berlaku untuk mode Perjalanan Dinas;
        // kelayakan SP-nya sudah diperiksa StoreNpdKontribusiDiklatRequest.
        $suratPerintahId = $mode === 'perjalanan' ? ($data['surat_perintah_id'] ?? null) : null;

        if ((int) $data['penerima_index'] >= count($data['peserta'])) {
            return back()->withInput()->withErrors(['penerima_index' => 'Penerima dana harus salah satu peserta yang diinput.']);
        }

        $peserta = $this->siapkanPeserta($data['peserta']);

        // Nominal NPD = subtotal bagian sesuai mode saja (bukan gabungan kontribusi+perjalanan).
        // Port dari buatNPDKontribusiDiklat() di gas-lama/CodeKontribusiDiklat.gs.
        $nominal = round((float) $peserta->sum($mode === 'perjalanan' ? 'sub_perjalanan' : 'sub_kontribusi'), 2);

        if ($nominal <= 0) {
            $label = $mode === 'perjalanan' ? 'Total perjalanan dinas' : 'Total kontribusi diklat';

            return back()->withInput()->withErrors(['peserta' => "{$label} harus lebih dari 0."]);
        }

        if ($galat = $this->periksaPenerimaTransfer($data, $nominal)) {
            return back()->withInput()->withErrors($galat);
        }

        $detailJson = $this->buatDetailJson($data);

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $mode, $suratPerintahId, $nominal, $peserta, $detailJson, $request) {
            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($masterAnggaran->id);
            $sisa = $masterAnggaran->sisaTersedia();

            if ($nominal > $sisa) {
                $label = $mode === 'perjalanan' ? 'Total perjalanan dinas' : 'Total kontribusi diklat';

                throw ValidationException::withMessages([
                    'peserta' => "{$label} (Rp ".number_format($nominal, 2, ',', '.').') melebihi Sisa Tersedia sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
                ]);
            }

            $npd = Npd::create([
                'jenis' => 'kd',
                'mode_kd' => $mode,
                'surat_perintah_id' => $suratPerintahId,
                'master_anggaran_id' => $masterAnggaran->id,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'sisa_anggaran_manual' => Npd::sisaManualDariInput($data),
                'terbilang' => Terbilang::rupiah($nominal),
                'status' => 'Draft NPD - PPTK',
                'detail_json' => $detailJson,
                'dibuat_oleh' => $request->user()->id,
            ]);

            $this->simpanPeserta($npd, $peserta);
            // Status SP mengikuti NPD yang menautnya, sama seperti NPD
            // Perjalanan Dinas. No-op bila tanpa Referensi SP.
            $npd->mirrorStatusKeSuratPerintah();
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        $labelModul = $mode === 'perjalanan' ? 'Kontribusi Diklat (Perjalanan)' : 'Kontribusi Diklat (Kontribusi)';
        AuditLog::catat('Buat NPD', "Jenis: {$labelModul}, Nominal: Rp ".number_format((float) $nominal, 2, ',', '.'));

        // Berkas SPJ (opsional) disimpan SESUDAH NPD-nya tersimpan: berkas
        // yang sudah tertulis ke disk tidak ikut ter-rollback kalau
        // transaksi penyimpanan NPD gagal.
        $spj->simpan($npd, $request->file('spj') ?? [], $request->user());

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Kontribusi Diklat berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'kd', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $npd->load('peserta');

        $detail = $npd->detail_json ?? [];
        $pesertaAwal = $npd->peserta->map(fn ($p) => [
            'pegawai_id' => $p->pegawai_id,
            'nama' => $p->nama,
            'pangkat' => $p->pangkat,
            'nip' => $p->nip,
            'rekening' => $p->rekening,
            'volume_kontribusi' => $p->volume_kontribusi,
            'tarif_kontribusi' => $p->tarif_kontribusi,
            'volume_mooc' => $p->volume_mooc,
            'tarif_mooc' => $p->tarif_mooc,
            'hari_uh' => $p->hari_uh,
            'tarif_uh' => $p->tarif_uh,
            'volume_akomodasi' => $p->volume_akomodasi,
            'tarif_akomodasi' => $p->tarif_akomodasi,
            'hari_saku' => $p->hari_saku,
            'tarif_saku' => $p->tarif_saku,
            'transport' => $p->transport,
        ])->all();

        return $this->form($npd, $pesertaAwal, $detail);
    }

    public function update(StoreNpdKontribusiDiklatRequest $request, Npd $npd, SpjBerkasService $spj)
    {
        abort_unless($npd->jenis === 'kd', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();
        $mode = $data['mode'];

        $suratPerintahId = $mode === 'perjalanan' ? ($data['surat_perintah_id'] ?? null) : null;

        if ((int) $data['penerima_index'] >= count($data['peserta'])) {
            return back()->withInput()->withErrors(['penerima_index' => 'Penerima dana harus salah satu peserta yang diinput.']);
        }

        $peserta = $this->siapkanPeserta($data['peserta']);
        $nominal = round((float) $peserta->sum($mode === 'perjalanan' ? 'sub_perjalanan' : 'sub_kontribusi'), 2);

        if ($nominal <= 0) {
            $label = $mode === 'perjalanan' ? 'Total perjalanan dinas' : 'Total kontribusi diklat';

            return back()->withInput()->withErrors(['peserta' => "{$label} harus lebih dari 0."]);
        }

        if ($galat = $this->periksaPenerimaTransfer($data, $nominal)) {
            return back()->withInput()->withErrors($galat);
        }

        $detailJson = $this->buatDetailJson($data);

        DB::transaction(function () use ($request, $npd, $data, $mode, $suratPerintahId, $peserta, $nominal, $detailJson) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->dapatDieditOleh($request->user()), 403);
            $sebelum = app(NpdRevisiService::class)->potret($npd);

            $anggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($data['master_anggaran_id']);
            $keu = $anggaran->tentukanKeu();
            if ($keu === null) {
                throw ValidationException::withMessages(['master_anggaran_id' => 'Sub kegiatan tidak dapat dipetakan ke KEU.']);
            }

            $tersedia = $anggaran->sisaTersedia();
            if ($npd->master_anggaran_id === $anggaran->id) {
                $tersedia += (float) $npd->nominal;
            }
            if ($nominal > $tersedia) {
                throw ValidationException::withMessages([
                    'peserta' => 'Total melebihi Sisa Tersedia setelah memperhitungkan nilai NPD saat ini.',
                ]);
            }

            $suratPerintahLama = $npd->surat_perintah_id;
            $npd->update([
                'mode_kd' => $mode,
                // Referensi NPD Kontribusi sudah digantikan Referensi SP dan
                // tidak lagi bisa dipilih. Tautan lama pada NPD yang dibuat
                // sebelum itu dibiarkan, kecuali modenya pindah ke Kontribusi.
                'npd_referensi_id' => $mode === 'perjalanan' ? $npd->npd_referensi_id : null,
                'surat_perintah_id' => $suratPerintahId,
                'master_anggaran_id' => $anggaran->id,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'sisa_anggaran_manual' => Npd::sisaManualDariInput($data, $npd),
                'terbilang' => Terbilang::rupiah($nominal),
                'detail_json' => $detailJson,
            ]);

            $npd->peserta()->delete();
            $this->simpanPeserta($npd, $peserta);
            // SP yang dilepas kembali "Diterima PPTK"; SP yang kini ditaut
            // mengikuti status NPD ini - pola yang sama dengan NpdPdController.
            if ($suratPerintahLama && $suratPerintahLama !== $npd->surat_perintah_id) {
                $npdLama = clone $npd;
                $npdLama->surat_perintah_id = $suratPerintahLama;
                $npdLama->lepaskanSuratPerintah();
            }
            $npd->mirrorStatusKeSuratPerintah();
            app(NpdRevisiService::class)->catatEdit($npd, $request->user(), $sebelum, 'Data Kontribusi Diklat diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Kontribusi Diklat, NPD #'.$npd->id);

        // Berkas SPJ (opsional) disimpan SESUDAH NPD-nya tersimpan: berkas
        // yang sudah tertulis ke disk tidak ikut ter-rollback kalau
        // transaksi penyimpanan NPD gagal.
        $spj->simpan($npd, $request->file('spj') ?? [], $request->user());

        return redirect($npd->fresh()->urlSetelahEdit($request->user()))->with('success', 'NPD Kontribusi Diklat berhasil diperbarui.');
    }

    private function form(?Npd $npd = null, ?array $pesertaAwal = null, array $detailAwal = [])
    {
        $masterAnggaran = AnggaranNpd::daftar(auth()->user(), $npd);
        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'golongan', 'nip', 'rekening']);
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Referensi SP untuk mode 'perjalanan': SP dari modul Input SP yang
        // masih layak jadi sumber NPD (lihat SuratPerintah::scopeSumberNpdPerjalanan),
        // ditambah SP yang sudah tertaut ke NPD ini supaya tetap terpilih saat disunting.
        $suratPerintahList = SuratPerintah::query()
            ->with('anggota')
            ->where(fn ($query) => $query->sumberNpdPerjalanan()
                ->when($npd?->surat_perintah_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->orderBy('tanggal_sp', 'desc')
            ->orderByDesc('id')
            ->get();

        return view('npd.kd.create', compact('masterAnggaran', 'pegawai', 'bulanList', 'npd', 'pesertaAwal', 'detailAwal', 'suratPerintahList'));
    }

    /**
     * Ke mana dananya ditransfer - berlaku untuk KEDUA mode.
     *
     * Dulu hanya mode Perjalanan Dinas yang punya daftar Tujuan Transfer;
     * mode Kontribusi memilih satu peserta lewat radio "Penerima Dana" di
     * kartu peserta. Kini keduanya memakai skema yang sama, dengan dua
     * pilihan:
     *
     * - "PPTK Sebagai Penerima Transfer": seluruh dana ke PPTK sub kegiatan.
     *   Namanya diresolusi di server (App\Support\PptkPenerima), jadi yang
     *   diperiksa hanya PPTK-nya memang sudah diset dan punya rekening.
     * - Daftar Tujuan Transfer: jumlahnya harus menghabiskan Total Bruto.
     *   Selain mencegah dana bocor, aturan ini otomatis menutup baris
     *   penerima bernominal 0, yang dulu ikut tercetak di Lampiran sebagai
     *   penerima yang tidak menerima apa pun. Toleransi Rp1 untuk pembulatan.
     *
     * @return array<string, string>|null galat siap dikirim ke withErrors()
     */
    private function periksaPenerimaTransfer(array $data, float $nominal): ?array
    {
        if ($data['pptk_penerima'] ?? false) {
            $masterAnggaran = MasterAnggaran::find($data['master_anggaran_id']);
            $nama = $masterAnggaran ? PptkPenerima::nama($masterAnggaran, (int) $data['tahun']) : '';

            if ($nama === '') {
                return ['pptk_penerima' => 'PPTK untuk sub kegiatan ini belum diset di Pelimpahan maupun Data Tambahan, jadi tidak bisa dijadikan penerima.'];
            }

            if (PptkPenerima::rekening($nama, $data['pptk_rekening'] ?? null) === '') {
                return ['pptk_rekening' => 'No. rekening PPTK belum diisi.'];
            }

            return null;
        }

        $penerima = $this->siapkanPenerimaTransfer($data);

        if ($penerima === []) {
            return ['penerima_transfer' => 'Isi minimal satu Tujuan Transfer, atau centang PPTK Sebagai Penerima Transfer.'];
        }

        $jumlah = round(array_sum(array_column($penerima, 'nominal')), 2);

        if (abs($jumlah - $nominal) > 1) {
            return ['penerima_transfer' => 'Total Tujuan Transfer (Rp '.number_format($jumlah, 2, ',', '.')
                .') harus sama dengan Total Bruto (Rp '.number_format($nominal, 2, ',', '.').').'];
        }

        return null;
    }

    /**
     * Penerima transfer yang benar-benar terisi. Baris kosong sepenuhnya
     * (nama maupun nominal) dibuang lebih dulu supaya baris sisa yang tidak
     * jadi dipakai tidak menggagalkan penyimpanan.
     *
     * @return array<int, array{nama: string, rekening: string|null, nominal: float}>
     */
    private function siapkanPenerimaTransfer(array $data): array
    {
        return array_values(array_filter(
            array_map(fn (array $p) => [
                'nama' => trim((string) ($p['nama'] ?? '')),
                'rekening' => trim((string) ($p['rekening'] ?? '')) ?: null,
                'nominal' => round((float) ($p['nominal'] ?? 0), 2),
            ], $data['penerima_transfer'] ?? []),
            fn (array $p) => $p['nama'] !== '' || $p['nominal'] > 0
        ));
    }

    private function buatDetailJson(array $data): array
    {
        $pptk = (bool) ($data['pptk_penerima'] ?? false);

        return [
            'nama_pelatihan' => $data['nama_pelatihan'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            // Warisan skema lama (radio "Penerima Dana" per peserta). Tetap
            // disimpan - selalu 0 dari formulir sekarang - karena NPD lama
            // dan beberapa ringkasan masih membacanya.
            'penerima_index' => (int) $data['penerima_index'],
            // Daftar Tujuan Transfer berlaku untuk kedua mode; kosong (null)
            // bila seluruh dananya dialihkan ke PPTK.
            'penerima_transfer' => $pptk ? null : $this->siapkanPenerimaTransfer($data),
            'pptk_penerima' => $pptk,
            'pptk_rekening' => $pptk ? ($data['pptk_rekening'] ?? null) : null,
            'keterangan_lampiran' => KeteranganLampiranIsian::dari($data),
            'ppn' => (float) ($data['ppn'] ?? 0),
            'pph_jenis' => $data['pph_jenis'] ?? null,
            'pph_nilai' => (float) ($data['pph_nilai'] ?? 0),
            'biaya_lain' => (float) ($data['biaya_lain'] ?? 0),
        ];
    }

    private function siapkanPeserta(array $data)
    {
        return collect($data)->map(function (array $p) {
            $volumeKontribusi = (int) ($p['volume_kontribusi'] ?? 0);
            $tarifKontribusi = (float) ($p['tarif_kontribusi'] ?? 0);
            $volumeMooc = (int) ($p['volume_mooc'] ?? 0);
            $tarifMooc = (float) ($p['tarif_mooc'] ?? 0);
            $hariUh = (int) ($p['hari_uh'] ?? 0);
            $tarifUh = (float) ($p['tarif_uh'] ?? 0);
            $volumeAkomodasi = (int) ($p['volume_akomodasi'] ?? 0);
            $tarifAkomodasi = (float) ($p['tarif_akomodasi'] ?? 0);
            $hariSaku = (int) ($p['hari_saku'] ?? 0);
            $tarifSaku = (float) ($p['tarif_saku'] ?? 0);
            $transport = (float) ($p['transport'] ?? 0);

            $subKontribusi = ($volumeKontribusi * $tarifKontribusi) + ($volumeMooc * $tarifMooc);
            $subPerjalanan = ($hariUh * $tarifUh) + ($volumeAkomodasi * $tarifAkomodasi) + ($hariSaku * $tarifSaku) + $transport;

            $pegawaiId = $p['pegawai_id'] ?? null;
            $nama = $p['nama'];
            $pangkat = $p['pangkat'] ?? null;
            $nip = $p['nip'] ?? null;
            $rekening = $p['rekening'] ?? null;

            if ($pegawaiId && ($pegawai = Pegawai::find($pegawaiId))) {
                $nama = $pegawai->nama;
                // Kolom `pangkat` peserta berisi GOLONGAN ("III/a") - isian
                // "Golongan" di formulir, dan yang tercetak di kolom Gol.
                // Daftar Pembayaran. Nama kolomnya warisan lama.
                $pangkat = $pangkat ?: $pegawai->golongan;
                $nip = $nip ?: $pegawai->nip;
                $rekening = $rekening ?: $pegawai->rekening;
            }

            return [
                'pegawai_id' => $pegawaiId,
                'nama' => $nama,
                'pangkat' => $pangkat,
                'nip' => $nip,
                'rekening' => $rekening,
                'volume_kontribusi' => $volumeKontribusi,
                'tarif_kontribusi' => $tarifKontribusi,
                'volume_mooc' => $volumeMooc,
                'tarif_mooc' => $tarifMooc,
                'hari_uh' => $hariUh,
                'tarif_uh' => $tarifUh,
                'volume_akomodasi' => $volumeAkomodasi,
                'tarif_akomodasi' => $tarifAkomodasi,
                'hari_saku' => $hariSaku,
                'tarif_saku' => $tarifSaku,
                'transport' => $transport,
                'sub_kontribusi' => $subKontribusi,
                'sub_perjalanan' => $subPerjalanan,
            ];
        });
    }

    private function simpanPeserta(Npd $npd, $peserta): void
    {
        foreach ($peserta as $p) {
            unset($p['sub_kontribusi'], $p['sub_perjalanan']);
            $npd->peserta()->create($p);
        }
    }
}
