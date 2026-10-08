<?php

namespace App\Http\Controllers;

use App\Services\NpdRevisiService;
use App\Services\SpjBerkasService;
use App\Helpers\AuditLog;
use App\Helpers\NpdPerjalananHitung;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdTransportRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Support\KeteranganLampiranIsian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NPD Transport ('tr') - turunan NPD Perjalanan Dinas yang hanya memuat
 * komponen transport (BBM, tol, tiket, representatif).
 *
 * PEMBUATANNYA SUDAH DIHAPUS (keputusan Irfan, Oktober 2026): transport kini
 * dibayar lewat NPD Perjalanan Dinas itu sendiri, yang formulirnya memuat
 * BBM/tol/tiket per anggota. Controller ini tinggal melayani PENYUNTINGAN
 * NPD Transport yang sudah telanjur ada, supaya dokumen yang masih berjalan
 * bisa diselesaikan. Melihat, mencetak, dan alur persetujuannya tetap lewat
 * NpdController seperti jenis NPD lain.
 */
class NpdTransportController extends Controller
{
    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'tr', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $npd->load(['tim', 'induk.tim']);

        $timAwal = $npd->tim->map(fn ($t) => [
            // NPD lama (liter x tarif) dibuka dengan nominal hasil kalinya,
            // supaya formulirnya langsung terisi Total Nominal BBM.
            'bbm_nominal' => NpdPerjalananHitung::bbm($t->toHitungArray()) ?: null,
            'bbm_tarif' => $t->bbm_tarif,
            'tol' => $t->tol,
            'tiket' => $t->tiket,
            'representatif' => $t->representatif,
        ])->all();

        return $this->form($npd, $timAwal);
    }

    public function update(StoreNpdTransportRequest $request, Npd $npd, SpjBerkasService $spj)
    {
        abort_unless($npd->jenis === 'tr', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $data = $request->validated();

        // Induk tidak dapat diganti setelah Transport dibuat.
        if ((int) $data['npd_induk_id'] !== $npd->npd_induk_id) {
            return back()->withInput()->withErrors(['npd_induk_id' => 'Induk NPD Transport tidak dapat diganti — batalkan dan buat NPD baru bila perlu.']);
        }

        $induk = Npd::with('tim')->findOrFail($npd->npd_induk_id);

        if (count($data['tim']) !== $induk->tim->count()) {
            return back()->withInput()->withErrors([
                'tim' => 'Jumlah anggota harus sama dengan anggota NPD Perjalanan Dinas induk ('.$induk->tim->count().' orang).',
            ]);
        }

        if ((int) $data['penerima_index'] >= count($data['tim'])) {
            return back()->withInput()->withErrors(['penerima_index' => 'Penerima dana harus salah satu anggota tim.']);
        }

        $tim = $this->siapkanTim($data['tim'], $induk);
        $nominal = round((float) $tim->sum('jumlah'), 2);

        if ($nominal <= 0) {
            return back()->withInput()->withErrors(['tim' => 'Total transport seluruh anggota harus lebih dari 0.']);
        }

        $detailJson = $this->snapshotDetailJson($induk, KeteranganLampiranIsian::dari($data));
        $penerimaIndex = (int) $data['penerima_index'];

        DB::transaction(function () use ($request, $npd, $data, $tim, $nominal, $detailJson, $penerimaIndex) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->dapatDieditOleh($request->user()), 403);
            $sebelum = app(NpdRevisiService::class)->potret($npd);

            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($npd->master_anggaran_id);
            $tersedia = $masterAnggaran->sisaTersedia() + (float) $npd->nominal;

            if ($nominal > $tersedia) {
                throw ValidationException::withMessages([
                    'tim' => 'Total transport melebihi Sisa Tersedia setelah memperhitungkan nilai NPD saat ini.',
                ]);
            }

            $npd->update([
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'tanggal_npd' => $data['tanggal_npd'],
                'jenis_panjar' => $data['jenis_panjar'],
                'nominal' => $nominal,
                'sisa_anggaran_manual' => Npd::sisaManualDariInput($data, $npd),
                'terbilang' => Terbilang::rupiah($nominal),
                'detail_json' => $detailJson,
            ]);

            $npd->tim()->delete();
            foreach ($tim as $i => $anggota) {
                unset($anggota['jumlah']);
                $anggota['is_penerima'] = $i === $penerimaIndex;
                $npd->tim()->create($anggota);
            }

            app(NpdRevisiService::class)->catatEdit($npd, $request->user(), $sebelum, 'Data Transport diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Transport, NPD #'.$npd->id);

        // Berkas SPJ (opsional) disimpan SESUDAH NPD-nya tersimpan: berkas
        // yang sudah tertulis ke disk tidak ikut ter-rollback kalau
        // transaksi penyimpanan NPD gagal.
        $spj->simpan($npd, $request->file('spj') ?? [], $request->user());

        return redirect($npd->fresh()->urlSetelahEdit($request->user()))->with('success', 'NPD Transport berhasil diperbarui.');
    }

    private function form(Npd $npd, array $timAwal)
    {
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Induk sudah tetap, hanya ditampilkan (read-only di form).
        $indukList = Npd::with('tim')->whereKey($npd->npd_induk_id)->get();

        return view('npd.tr.edit', compact('bulanList', 'npd', 'timAwal', 'indukList'));
    }

    /**
     * Salin detail_json induk (data SP/perjalanan) apa adanya — snapshot, bukan
     * referensi dinamis. Hanya keterangan_lampiran yang boleh dioverride.
     */
    private function snapshotDetailJson(Npd $induk, ?string $keteranganOverride): array
    {
        $detail = $induk->detail_json ?? [];
        $detail['keterangan_lampiran'] = $keteranganOverride ?: ($detail['keterangan_lampiran'] ?? null);

        return $detail;
    }

    /**
     * Identitas anggota (nama/jabatan/nip/rekening/pegawai_id) SELALU disalin dari
     * anggota induk berdasarkan urutan indeks — tidak pernah dari input klien — supaya
     * Transport benar-benar snapshot induk. Paket perjalanan sengaja tidak disalin
     * (kosong), sehingga uang harian & akomodasi otomatis nol lewat NpdPerjalananHitung.
     */
    private function siapkanTim(array $timData, Npd $induk)
    {
        $indukTim = $induk->tim->values();

        return collect($timData)->map(function (array $t, int $i) use ($indukTim) {
            $sumber = $indukTim->get($i);

            $anggota = [
                'pegawai_id' => $sumber?->pegawai_id,
                'nama' => $sumber?->nama ?? '',
                'jabatan' => $sumber?->jabatan,
                'bidang_snapshot' => $sumber?->bidang_snapshot ?: $sumber?->pegawai?->bidang,
                'nip' => $sumber?->nip,
                'rekening' => $sumber?->rekening,
                'bbm_nominal' => NpdPerjalananHitung::memakaiNominalBbm($t) ? (float) $t['bbm_nominal'] : null,
                'bbm_liter' => NpdPerjalananHitung::literBbm($t),
                'bbm_tarif' => (float) ($t['bbm_tarif'] ?? 0),
                'tol' => (float) ($t['tol'] ?? 0),
                'tiket' => (float) ($t['tiket'] ?? 0),
                'representatif' => (float) ($t['representatif'] ?? 0),
            ];

            $anggota['jumlah'] = NpdPerjalananHitung::hitungAnggota($anggota)['jumlah'];

            return $anggota;
        });
    }
}
