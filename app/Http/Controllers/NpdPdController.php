<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\NpdPerjalananHitung;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdPdRequest;
use App\Models\ClusterUh;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use Illuminate\Support\Facades\DB;

class NpdPdController extends Controller
{
    public function create()
    {
        $masterAnggaran = MasterAnggaran::with('tagging')
            ->where('aktif', true)
            ->orderBy('sub_kegiatan')
            ->get();

        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'nip', 'rekening']);

        $clusterList = ClusterUh::with(['wilayah' => fn ($q) => $q->orderBy('nama_wilayah')])
            ->where('aktif', true)
            ->orderBy('kode')
            ->get();

        $suratPerintahList = SuratPerintah::where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
            ->orderBy('tanggal_sp', 'desc')
            ->get(['id', 'nomor_sp', 'tanggal_sp', 'keterangan', 'lokasi', 'unit_kerja']);

        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('npd.pd.create', compact('masterAnggaran', 'pegawai', 'clusterList', 'suratPerintahList', 'bulanList'));
    }

    public function store(StoreNpdPdRequest $request)
    {
        $data = $request->validated();

        $masterAnggaran = MasterAnggaran::findOrFail($data['master_anggaran_id']);
        $keu = $masterAnggaran->tentukanKeu();

        if ($keu === null) {
            return back()->withInput()->withErrors([
                'master_anggaran_id' => 'Sub kegiatan pada sumber dana ini tidak dapat dipetakan ke KEU (harus diawali 6.01.01, 6.01.02, atau 6.01.03).',
            ]);
        }

        $tim = array_values($data['tim']);

        $penerimaIndex = (int) $data['penerima_index'];
        if (! isset($tim[$penerimaIndex])) {
            $penerimaIndex = 0;
        }

        // Nominal NPD = total jumlah seluruh anggota tim (persis _hitungAnggota / buatNPDPerjalanan).
        $nominal = round(NpdPerjalananHitung::totalTim($tim), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors([
                'tim' => 'Total perjalanan dinas seluruh tim harus lebih dari 0.',
            ]);
        }

        $sisa = $masterAnggaran->sisaAnggaran();

        if ($nominal > $sisa) {
            return back()->withInput()->withErrors([
                'tim' => 'Total (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Anggaran sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
            ]);
        }

        $suratPerintahId = $data['surat_perintah_id'] ?? null;

        $detailJson = [
            'nomor_sp' => $data['nomor_sp'],
            'tanggal_sp' => $data['tanggal_sp'],
            'uraian_sp' => $data['uraian_sp'],
            'berangkat_dari' => $data['berangkat_dari'],
            'tujuan' => $data['tujuan'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_pulang' => $data['tanggal_pulang'],
            'keterangan_lampiran' => $data['keterangan_lampiran'] ?? null,
        ];

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $nominal, $tim, $penerimaIndex, $suratPerintahId, $detailJson, $request) {
            $npd = Npd::create([
                'jenis' => 'pd',
                'master_anggaran_id' => $masterAnggaran->id,
                'surat_perintah_id' => $suratPerintahId,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'terbilang' => Terbilang::rupiah($nominal),
                'status' => 'Draft NPD - PPTK',
                'detail_json' => $detailJson,
                'dibuat_oleh' => $request->user()->id,
            ]);

            foreach ($tim as $i => $anggota) {
                $pegawaiId = $anggota['pegawai_id'] ?? null;
                $nama = $anggota['nama'];

                if ($pegawaiId && ($pegawai = Pegawai::find($pegawaiId))) {
                    $nama = $pegawai->nama;
                }

                $npdTim = $npd->tim()->create([
                    'pegawai_id' => $pegawaiId,
                    'nama' => $nama,
                    'jabatan' => $anggota['jabatan'] ?? null,
                    'nip' => $anggota['nip'] ?? null,
                    'rekening' => $anggota['rekening'] ?? null,
                    'bbm_liter' => $anggota['bbm_liter'] ?? 0,
                    'bbm_tarif' => $anggota['bbm_tarif'] ?? 0,
                    'tol' => $anggota['tol'] ?? 0,
                    'tiket' => $anggota['tiket'] ?? 0,
                    'representatif' => $anggota['representatif'] ?? 0,
                    'is_penerima' => $i === $penerimaIndex,
                ]);

                foreach ($anggota['paket'] as $paket) {
                    $npdTim->paket()->create([
                        'cluster' => $paket['cluster'],
                        'wilayah' => $paket['wilayah'],
                        'lama_hari' => $paket['lama_hari'],
                        'tarif_uh' => $paket['tarif_uh'],
                        'malam' => $paket['malam'] ?? 0,
                        'tarif_akom' => $paket['tarif_akom'] ?? 0,
                    ]);
                }
            }

            // Aman dipanggil selalu — method ini sendiri no-op kalau surat_perintah_id kosong.
            $npd->mirrorStatusKeSuratPerintah();

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Perjalanan Dinas, Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Perjalanan Dinas berhasil disimpan sebagai draft.');
    }
}
