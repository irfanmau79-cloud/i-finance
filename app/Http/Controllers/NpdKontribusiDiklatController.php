<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdKontribusiDiklatRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Support\AnggaranNpd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NpdKontribusiDiklatController extends Controller
{
    public function create()
    {
        return $this->form();
    }

    public function store(StoreNpdKontribusiDiklatRequest $request)
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

        $referensiId = $mode === 'perjalanan' ? ($data['npd_referensi_id'] ?? null) : null;

        if ($referensiId !== null) {
            $referensiValid = Npd::where('jenis', 'kd')
                ->where('mode_kd', 'kontribusi')
                ->where('status', '!=', 'Dibatalkan')
                ->whereKey($referensiId)
                ->exists();

            if (! $referensiValid) {
                return back()->withInput()->withErrors([
                    'npd_referensi_id' => 'Referensi harus NPD Kontribusi Diklat mode Kontribusi yang belum dibatalkan.',
                ]);
            }
        }

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

        $detailJson = $this->buatDetailJson($data);

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $mode, $referensiId, $nominal, $peserta, $detailJson, $request) {
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
                'npd_referensi_id' => $referensiId,
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
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        $labelModul = $mode === 'perjalanan' ? 'Kontribusi Diklat (Perjalanan)' : 'Kontribusi Diklat (Kontribusi)';
        AuditLog::catat('Buat NPD', "Jenis: {$labelModul}, Nominal: Rp ".number_format((float) $nominal, 2, ',', '.'));

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

    public function update(StoreNpdKontribusiDiklatRequest $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'kd', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();
        $mode = $data['mode'];

        $referensiId = $mode === 'perjalanan' ? ($data['npd_referensi_id'] ?? null) : null;

        if ($referensiId !== null) {
            $referensiValid = Npd::where('jenis', 'kd')
                ->where('mode_kd', 'kontribusi')
                ->where('status', '!=', 'Dibatalkan')
                ->whereKeyNot($npd->id)
                ->whereKey($referensiId)
                ->exists();

            if (! $referensiValid) {
                return back()->withInput()->withErrors([
                    'npd_referensi_id' => 'Referensi harus NPD Kontribusi Diklat mode Kontribusi yang belum dibatalkan.',
                ]);
            }
        }

        if ((int) $data['penerima_index'] >= count($data['peserta'])) {
            return back()->withInput()->withErrors(['penerima_index' => 'Penerima dana harus salah satu peserta yang diinput.']);
        }

        $peserta = $this->siapkanPeserta($data['peserta']);
        $nominal = round((float) $peserta->sum($mode === 'perjalanan' ? 'sub_perjalanan' : 'sub_kontribusi'), 2);

        if ($nominal <= 0) {
            $label = $mode === 'perjalanan' ? 'Total perjalanan dinas' : 'Total kontribusi diklat';

            return back()->withInput()->withErrors(['peserta' => "{$label} harus lebih dari 0."]);
        }

        $detailJson = $this->buatDetailJson($data);

        DB::transaction(function () use ($request, $npd, $data, $mode, $referensiId, $peserta, $nominal, $detailJson) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->dapatDieditOleh($request->user()), 403);

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

            $npd->update([
                'mode_kd' => $mode,
                'npd_referensi_id' => $referensiId,
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
            $npd->catatHistoriStatus($request->user(), 'edit', $npd->status, $npd->status, 'Data Kontribusi Diklat diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Kontribusi Diklat, NPD #'.$npd->id);

        return redirect()->route('npd.show', $npd)->with('success', 'Draft NPD Kontribusi Diklat berhasil diperbarui.');
    }

    private function form(?Npd $npd = null, ?array $pesertaAwal = null, array $detailAwal = [])
    {
        $masterAnggaran = AnggaranNpd::daftar(auth()->user(), $npd);
        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'nip', 'rekening']);
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Hanya NPD Kontribusi Diklat mode 'kontribusi' yang boleh jadi referensi mode 'perjalanan'.
        $referensiList = Npd::with('peserta')
            ->where('jenis', 'kd')
            ->where('mode_kd', 'kontribusi')
            ->where('status', '!=', 'Dibatalkan')
            ->when($npd, fn ($query) => $query->whereKeyNot($npd->id))
            ->orderBy('tanggal_npd', 'desc')
            ->get();

        return view('npd.kd.create', compact('masterAnggaran', 'pegawai', 'bulanList', 'npd', 'pesertaAwal', 'detailAwal', 'referensiList'));
    }

    private function buatDetailJson(array $data): array
    {
        return [
            'nama_pelatihan' => $data['nama_pelatihan'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'penerima_index' => (int) $data['penerima_index'],
            'keterangan_lampiran' => $data['keterangan_lampiran'] ?? null,
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
                $pangkat = $pangkat ?: $pegawai->pangkat;
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
