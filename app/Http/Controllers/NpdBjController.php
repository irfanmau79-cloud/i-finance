<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdBjRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Vendor;
use App\Support\AnggaranNpd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NpdBjController extends Controller
{
    public function create()
    {
        $masterAnggaran = AnggaranNpd::daftar(auth()->user());

        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'rekening']);
        $vendor = Vendor::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'rekening']);

        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('npd.bj.create', compact('masterAnggaran', 'pegawai', 'vendor', 'bulanList'));
    }

    public function store(StoreNpdBjRequest $request)
    {
        $data = $request->validated();

        $masterAnggaran = MasterAnggaran::findOrFail($data['master_anggaran_id']);
        $keu = $masterAnggaran->tentukanKeu();

        if ($keu === null) {
            return back()->withInput()->withErrors([
                'master_anggaran_id' => 'Sub kegiatan pada sumber dana ini tidak dapat dipetakan ke KEU (harus diawali 6.01.01, 6.01.02, atau 6.01.03).',
            ]);
        }

        $penerima = $this->siapkanPenerima($data['penerima']);

        // Nominal NPD = TOTAL BRUTO seluruh penerima (persis logika GAS, bukan netto).
        $nominal = round((float) $penerima->sum('bruto'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors([
                'penerima' => 'Total Bruto seluruh penerima harus lebih dari 0.',
            ]);
        }

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $nominal, $penerima, $request) {
            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($masterAnggaran->id);
            $sisa = $masterAnggaran->sisaTersedia();

            if ($nominal > $sisa) {
                throw ValidationException::withMessages([
                    'penerima' => 'Total Bruto (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Tersedia sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
                ]);
            }

            $npd = Npd::create([
                'jenis' => 'bj',
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
                'dibuat_oleh' => $request->user()->id,
            ]);

            $this->simpanPenerima($npd, $penerima);
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Barang/Jasa, Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Barang/Jasa berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'bj', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $npd->load('penerima.pphList');

        $penerimaAwal = $npd->penerima->map(fn ($p) => [
            'pegawai_id' => $p->pegawai_id,
            'vendor_id' => $p->vendor_id,
            'nama' => $p->nama,
            'rekening' => $p->rekening,
            'bruto' => $p->bruto,
            'ppn' => $p->ppn,
            'biaya_ku_rtgs' => $p->biaya_ku_rtgs,
            'keterangan' => $p->keterangan,
            'pph_list' => $p->pphList->map(fn ($pph) => ['jenis' => $pph->jenis, 'nilai' => $pph->nilai])->all(),
        ])->all();

        return $this->form($npd, $penerimaAwal);
    }

    public function update(StoreNpdBjRequest $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'bj', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();
        $penerima = $this->siapkanPenerima($data['penerima']);
        $nominal = round((float) $penerima->sum('bruto'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors(['penerima' => 'Total Bruto seluruh penerima harus lebih dari 0.']);
        }

        DB::transaction(function () use ($request, $npd, $data, $penerima, $nominal) {
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
                    'penerima' => 'Total Bruto melebihi Sisa Tersedia setelah memperhitungkan nilai NPD saat ini.',
                ]);
            }

            $npd->update([
                'master_anggaran_id' => $anggaran->id,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'sisa_anggaran_manual' => Npd::sisaManualDariInput($data, $npd),
                'terbilang' => Terbilang::rupiah($nominal),
            ]);

            $npd->penerima()->delete();
            $this->simpanPenerima($npd, $penerima);
            $npd->catatHistoriStatus($request->user(), 'edit', $npd->status, $npd->status, 'Data Barang/Jasa diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Barang/Jasa, NPD #'.$npd->id);

        return redirect()->route('npd.show', $npd)->with('success', 'Draft NPD Barang/Jasa berhasil diperbarui.');
    }

    private function form(?Npd $npd = null, ?array $penerimaAwal = null)
    {
        $masterAnggaran = AnggaranNpd::daftar(auth()->user(), $npd);
        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'rekening']);
        $vendor = Vendor::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'rekening']);
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('npd.bj.create', compact('masterAnggaran', 'pegawai', 'vendor', 'bulanList', 'npd', 'penerimaAwal'));
    }

    private function siapkanPenerima(array $data)
    {
        return collect($data)->map(function (array $p) {
            $bruto = (float) $p['bruto'];
            $ppn = (float) ($p['ppn'] ?? 0);
            $biayaKuRtgs = (float) ($p['biaya_ku_rtgs'] ?? 0);

            // Hanya baris PPh dengan nilai > 0 yang disimpan, sama seperti GAS (collectPenerima).
            $pphList = collect($p['pph_list'] ?? [])
                ->filter(fn (array $pp) => (float) ($pp['nilai'] ?? 0) > 0)
                ->map(fn (array $pp) => [
                    'jenis' => $pp['jenis'] ?: 'PPh',
                    'nilai' => (float) $pp['nilai'],
                ])->values();

            $pegawaiId = $p['pegawai_id'] ?? null;
            $vendorId = $p['vendor_id'] ?? null;

            $nama = $p['nama'];
            if ($pegawaiId) {
                $nama = Pegawai::findOrFail($pegawaiId)->nama;
            } elseif ($vendorId) {
                $nama = Vendor::findOrFail($vendorId)->nama;
            }

            return [
                'pegawai_id' => $pegawaiId,
                'vendor_id' => $vendorId,
                'nama' => $nama,
                'rekening' => $p['rekening'] ?? null,
                'bruto' => $bruto,
                'ppn' => $ppn,
                'biaya_ku_rtgs' => $biayaKuRtgs,
                'keterangan' => $p['keterangan'] ?? null,
                'pph_list' => $pphList,
            ];
        });
    }

    private function simpanPenerima(Npd $npd, $penerima): void
    {
        foreach ($penerima as $p) {
            $pphList = $p['pph_list'];
            unset($p['pph_list']);
            $npd->penerima()->create($p)->pphList()->createMany($pphList->all());
        }
    }
}
