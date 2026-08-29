<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\NpdPerjalananHitung;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdPdRequest;
use App\Http\Requests\UpdateNpdPdRequest;
use App\Models\ClusterUh;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        // Hanya SP yang masih "Diterima PPTK" DAN flag Sumber NPD-nya menyala.
        // Toggle Sumber NPD di halaman Data SP-lah yang menyembunyikan sebuah
        // SP dari sini tanpa menghapusnya dari Monitoring - lihat
        // SuratPerintah::scopeSumberNpdAktif (port getSPTerinput di GAS).
        $suratPerintahList = SuratPerintah::query()
            ->with('anggota')
            ->sumberNpdPerjalanan()
            ->orderBy('tanggal_sp', 'desc')
            ->get(['id', 'nomor_sp', 'tanggal_sp', 'keterangan', 'lokasi', 'unit_kerja', 'tujuan_transfer', 'jenis_permintaan']);

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

        $suratPerintahId = $data['surat_perintah_id'] ?? null;
        $suratPerintah = SuratPerintah::findOrFail($suratPerintahId);

        $detailJson = [
            // Diambil dari Surat Perintah-nya, bukan dari isian formulir:
            // NPD Perjalanan Dinas selalu berangkat dari SP, jadi nomor dan
            // tanggalnya harus selalu sama persis dengan SP tersebut.
            'nomor_sp' => $suratPerintah->nomor_sp,
            'tanggal_sp' => $suratPerintah->tanggal_sp->format('Y-m-d'),
            'uraian_sp' => $data['uraian_sp'],
            'berangkat_dari' => $data['berangkat_dari'],
            'tujuan' => $data['tujuan'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_pulang' => $data['tanggal_pulang'],
            'keterangan_lampiran' => $data['keterangan_lampiran'] ?? null,
        ];

        $npd = DB::transaction(function () use ($data, $masterAnggaran, $keu, $nominal, $tim, $penerimaIndex, $suratPerintahId, $detailJson, $request) {
            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($masterAnggaran->id);
            $sisa = $masterAnggaran->sisaTersedia();

            if ($nominal > $sisa) {
                throw ValidationException::withMessages([
                    'tim' => 'Total (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Tersedia sumber dana ini (Rp '.number_format($sisa, 2, ',', '.').').',
                ]);
            }

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

                $pegawai = $pegawaiId ? Pegawai::find($pegawaiId) : null;
                if ($pegawai) {
                    $nama = $pegawai->nama;
                }

                $npdTim = $npd->tim()->create([
                    'pegawai_id' => $pegawaiId,
                    'nama' => $nama,
                    'jabatan' => $anggota['jabatan'] ?? null,
                    'bidang_snapshot' => $pegawai?->bidang,
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
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Perjalanan Dinas, Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Perjalanan Dinas berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'pd', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);
        $npd->load('tim.paket');

        $masterAnggaran = MasterAnggaran::with('tagging')->where('aktif', true)->orderBy('sub_kegiatan')->get();
        $pegawai = Pegawai::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'jabatan', 'bidang', 'nip', 'rekening']);
        $clusterList = ClusterUh::with(['wilayah' => fn ($q) => $q->orderBy('nama_wilayah')])->where('aktif', true)->orderBy('kode')->get();
        // SP yang sedang tertaut tetap ikut ditampilkan walau flag Sumber NPD
        // sudah dimatikan, supaya NPD lama masih bisa disunting.
        $suratPerintahList = SuratPerintah::query()
            ->with('anggota')
            // orWhereKey() tidak ada pada Eloquent\Builder - pemakaiannya membuat
            // halaman Edit NPD Perjalanan Dinas balas 500. Dipakai orWhere('id')
            // yang setara, dan hanya bila NPD-nya memang sudah tertaut SP.
            ->where(fn ($query) => $query->sumberNpdPerjalanan()
                ->when($npd->surat_perintah_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->orderBy('tanggal_sp', 'desc')
            ->get(['id', 'nomor_sp', 'tanggal_sp', 'keterangan', 'lokasi', 'unit_kerja', 'tujuan_transfer', 'jenis_permintaan']);
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('npd.pd.create', compact('masterAnggaran', 'pegawai', 'clusterList', 'suratPerintahList', 'bulanList', 'npd'));
    }

    public function update(UpdateNpdPdRequest $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'pd', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();
        $tim = array_values($data['tim']);
        $penerimaIndex = isset($tim[(int) $data['penerima_index']]) ? (int) $data['penerima_index'] : 0;
        $nominal = round(NpdPerjalananHitung::totalTim($tim), 2);
        if ($nominal <= 0) {
            return back()->withInput()->withErrors(['tim' => 'Total perjalanan dinas seluruh tim harus lebih dari 0.']);
        }

        $suratPerintah = SuratPerintah::findOrFail($data['surat_perintah_id']);

        $detailJson = [
            // Diambil dari Surat Perintah-nya, bukan dari isian formulir:
            // NPD Perjalanan Dinas selalu berangkat dari SP, jadi nomor dan
            // tanggalnya harus selalu sama persis dengan SP tersebut.
            'nomor_sp' => $suratPerintah->nomor_sp,
            'tanggal_sp' => $suratPerintah->tanggal_sp->format('Y-m-d'),
            'uraian_sp' => $data['uraian_sp'],
            'berangkat_dari' => $data['berangkat_dari'],
            'tujuan' => $data['tujuan'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_pulang' => $data['tanggal_pulang'],
            'keterangan_lampiran' => $data['keterangan_lampiran'] ?? null,
        ];

        DB::transaction(function () use ($request, $npd, $data, $tim, $penerimaIndex, $nominal, $detailJson) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->dapatDieditOleh($request->user()), 403);
            $anggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($data['master_anggaran_id']);
            $keu = $anggaran->tentukanKeu();
            if ($keu === null) {
                throw ValidationException::withMessages(['master_anggaran_id' => 'Sub kegiatan tidak dapat dipetakan ke KEU.']);
            }

            $tersedia = $anggaran->sisaTersedia() + ($npd->master_anggaran_id === $anggaran->id ? (float) $npd->nominal : 0);
            if ($nominal > $tersedia) {
                throw ValidationException::withMessages(['tim' => 'Total perjalanan dinas melebihi Sisa Tersedia setelah memperhitungkan nilai NPD saat ini.']);
            }

            $suratPerintahLama = $npd->surat_perintah_id;
            $npd->update([
                'master_anggaran_id' => $anggaran->id,
                'surat_perintah_id' => $data['surat_perintah_id'] ?? null,
                'keu' => $keu,
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'terbilang' => Terbilang::rupiah($nominal),
                'detail_json' => $detailJson,
            ]);

            $npd->tim()->delete();
            $this->simpanTim($npd, $tim, $penerimaIndex);
            if ($suratPerintahLama && $suratPerintahLama !== $npd->surat_perintah_id) {
                $npdLama = clone $npd;
                $npdLama->surat_perintah_id = $suratPerintahLama;
                $npdLama->lepaskanSuratPerintah();
            }
            $npd->mirrorStatusKeSuratPerintah();
            $npd->catatHistoriStatus($request->user(), 'edit', $npd->status, $npd->status, 'Data Perjalanan Dinas diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Perjalanan Dinas, NPD #'.$npd->id);

        return redirect()->route('npd.show', $npd)->with('success', 'Draft NPD Perjalanan Dinas berhasil diperbarui.');
    }

    private function simpanTim(Npd $npd, array $tim, int $penerimaIndex): void
    {
        foreach ($tim as $i => $anggota) {
            $pegawaiId = $anggota['pegawai_id'] ?? null;
            $pegawai = $pegawaiId ? Pegawai::find($pegawaiId) : null;
            $nama = $pegawai ? $pegawai->nama : $anggota['nama'];
            $npdTim = $npd->tim()->create([
                'pegawai_id' => $pegawaiId, 'nama' => $nama, 'jabatan' => $anggota['jabatan'] ?? null,
                'bidang_snapshot' => $pegawai?->bidang,
                'nip' => $anggota['nip'] ?? null, 'rekening' => $anggota['rekening'] ?? null,
                'bbm_liter' => $anggota['bbm_liter'] ?? 0, 'bbm_tarif' => $anggota['bbm_tarif'] ?? 0,
                'tol' => $anggota['tol'] ?? 0, 'tiket' => $anggota['tiket'] ?? 0,
                'representatif' => $anggota['representatif'] ?? 0, 'is_penerima' => $i === $penerimaIndex,
            ]);
            foreach ($anggota['paket'] as $paket) {
                $npdTim->paket()->create([
                    'cluster' => $paket['cluster'], 'wilayah' => $paket['wilayah'],
                    'lama_hari' => $paket['lama_hari'], 'tarif_uh' => $paket['tarif_uh'],
                    'malam' => $paket['malam'] ?? 0, 'tarif_akom' => $paket['tarif_akom'] ?? 0,
                ]);
            }
        }
    }
}
