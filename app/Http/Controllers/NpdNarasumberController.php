<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdNarasumberRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Vendor;
use App\Support\AnggaranNpd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NpdNarasumberController extends Controller
{
    public function create()
    {
        return $this->form();
    }

    public function store(StoreNpdNarasumberRequest $request)
    {
        $data = $request->validated();

        $masterAnggaran = MasterAnggaran::findOrFail($data['master_anggaran_id']);
        $keu = $masterAnggaran->tentukanKeu();

        if ($keu === null) {
            return back()->withInput()->withErrors([
                'master_anggaran_id' => 'Sub kegiatan pada sumber dana ini tidak dapat dipetakan ke KEU (harus diawali 6.01.01, 6.01.02, atau 6.01.03).',
            ]);
        }

        $narasumber = $this->siapkanNarasumber($data['narasumber']);

        // Nominal NPD = TOTAL BRUTO seluruh narasumber (honor + transport), bukan netto.
        // Port dari buatNPDNarasumber() di gas-lama/CodeNarasumber.gs.
        $nominal = round((float) $narasumber->sum('bruto'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors([
                'narasumber' => 'Total honorarium seluruh narasumber harus lebih dari 0.',
            ]);
        }

        $detailJson = [
            'uraian_kegiatan' => $data['uraian_kegiatan'],
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
        ];

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $nominal, $narasumber, $detailJson, $request) {
            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($masterAnggaran->id);
            $sisa = $masterAnggaran->sisaTersedia();

            if ($nominal > $sisa) {
                throw ValidationException::withMessages([
                    'narasumber' => 'Total honorarium (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Tersedia sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
                ]);
            }

            $npd = Npd::create([
                'jenis' => 'ns',
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

            $this->simpanNarasumber($npd, $narasumber);
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Narasumber, Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Narasumber berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'ns', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $npd->load('narasumber');

        $narasumberAwal = $npd->narasumber->map(fn ($n) => [
            'pegawai_id' => $n->pegawai_id,
            'vendor_id' => $n->vendor_id,
            'nama' => $n->nama,
            'jabatan' => $n->jabatan,
            'rekening' => $n->rekening,
            'jumlah_jp' => $n->jumlah_jp,
            'tarif_jp' => $n->tarif_jp,
            'transport' => $n->transport,
            'pph21' => $n->pph21,
            'uraian' => $n->uraian,
        ])->all();

        return $this->form($npd, $narasumberAwal);
    }

    public function update(StoreNpdNarasumberRequest $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'ns', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();
        $narasumber = $this->siapkanNarasumber($data['narasumber']);
        $nominal = round((float) $narasumber->sum('bruto'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors(['narasumber' => 'Total honorarium seluruh narasumber harus lebih dari 0.']);
        }

        $detailJson = [
            'uraian_kegiatan' => $data['uraian_kegiatan'],
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
        ];

        DB::transaction(function () use ($request, $npd, $data, $narasumber, $nominal, $detailJson) {
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
                    'narasumber' => 'Total honorarium melebihi Sisa Tersedia setelah memperhitungkan nilai NPD saat ini.',
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
                'detail_json' => $detailJson,
            ]);

            $npd->narasumber()->delete();
            $this->simpanNarasumber($npd, $narasumber);
            $npd->catatHistoriStatus($request->user(), 'edit', $npd->status, $npd->status, 'Data Narasumber diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Narasumber, NPD #'.$npd->id);

        return redirect()->route('npd.show', $npd)->with('success', 'Draft NPD Narasumber berhasil diperbarui.');
    }

    private function form(?Npd $npd = null, ?array $narasumberAwal = null)
    {
        $masterAnggaran = AnggaranNpd::daftar(auth()->user(), $npd);
        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'rekening']);
        $vendor = Vendor::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'rekening']);
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('npd.ns.create', compact('masterAnggaran', 'pegawai', 'vendor', 'bulanList', 'npd', 'narasumberAwal'));
    }

    private function siapkanNarasumber(array $data)
    {
        return collect($data)->map(function (array $n) {
            $jumlahJp = (int) $n['jumlah_jp'];
            $tarifJp = (float) $n['tarif_jp'];
            $transport = (float) ($n['transport'] ?? 0);
            $pph21 = (float) ($n['pph21'] ?? 0);
            $bruto = ($jumlahJp * $tarifJp) + $transport;

            $pegawaiId = $n['pegawai_id'] ?? null;
            $vendorId = $n['vendor_id'] ?? null;

            $nama = $n['nama'];
            if ($pegawaiId) {
                $nama = Pegawai::findOrFail($pegawaiId)->nama;
            } elseif ($vendorId) {
                $nama = Vendor::findOrFail($vendorId)->nama;
            }

            return [
                'pegawai_id' => $pegawaiId,
                'vendor_id' => $vendorId,
                'nama' => $nama,
                'jabatan' => $n['jabatan'] ?? null,
                'rekening' => $n['rekening'] ?? null,
                'jumlah_jp' => $jumlahJp,
                'tarif_jp' => $tarifJp,
                'transport' => $transport,
                'pph21' => $pph21,
                'uraian' => $n['uraian'] ?? null,
                'bruto' => $bruto,
            ];
        });
    }

    private function simpanNarasumber(Npd $npd, $narasumber): void
    {
        foreach ($narasumber as $n) {
            unset($n['bruto']);
            $npd->narasumber()->create($n);
        }
    }
}
