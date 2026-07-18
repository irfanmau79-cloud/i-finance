<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdBjRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class NpdBjController extends Controller
{
    public function create()
    {
        $masterAnggaran = MasterAnggaran::with('tagging')
            ->where('aktif', true)
            ->orderBy('sub_kegiatan')
            ->get();

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

        $penerima = collect($data['penerima'])->map(function (array $p) {
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

        // Nominal NPD = TOTAL BRUTO seluruh penerima (persis logika GAS, bukan netto).
        // Karena nominal diturunkan langsung dari bruto (tak ada input nominal terpisah),
        // "total bruto harus sama dengan nominal" selalu terpenuhi by construction.
        $nominal = round((float) $penerima->sum('bruto'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors([
                'penerima' => 'Total Bruto seluruh penerima harus lebih dari 0.',
            ]);
        }

        $sisa = $masterAnggaran->sisaAnggaran();

        if ($nominal > $sisa) {
            return back()->withInput()->withErrors([
                'penerima' => 'Total Bruto (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Anggaran sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
            ]);
        }

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $nominal, $penerima, $request) {
            $npd = Npd::create([
                'jenis' => 'bj',
                'master_anggaran_id' => $masterAnggaran->id,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'terbilang' => Terbilang::rupiah($nominal),
                'status' => 'Draft NPD - PPTK',
                'dibuat_oleh' => $request->user()->id,
            ]);

            foreach ($penerima as $p) {
                $pphList = $p['pph_list'];
                unset($p['pph_list']);

                $npd->penerima()->create($p)->pphList()->createMany($pphList->all());
            }

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Barang/Jasa, Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Barang/Jasa berhasil disimpan sebagai draft.');
    }
}
