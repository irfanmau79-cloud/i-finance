<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\NpdPerjalananHitung;
use App\Helpers\Terbilang;
use App\Http\Requests\StoreNpdTransportRequest;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NpdTransportController extends Controller
{
    public function create()
    {
        return $this->form();
    }

    public function store(StoreNpdTransportRequest $request)
    {
        $data = $request->validated();

        $induk = Npd::with(['masterAnggaran', 'tim'])->find($data['npd_induk_id']);

        if (! $induk || $induk->jenis !== 'pd' || $induk->status !== 'Selesai') {
            return back()->withInput()->withErrors([
                'npd_induk_id' => 'Induk harus NPD Perjalanan Dinas berstatus Selesai.',
            ]);
        }

        if ($induk->punyaTurunanTransportAktif()) {
            return back()->withInput()->withErrors([
                'npd_induk_id' => 'NPD Perjalanan Dinas ini sudah memiliki NPD Transport aktif. Batalkan yang lama terlebih dahulu bila ingin membuat ulang.',
            ]);
        }

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

        $detailJson = $this->snapshotDetailJson($induk, $data['keterangan_lampiran'] ?? null);
        $penerimaIndex = (int) $data['penerima_index'];

        $npd = DB::transaction(function () use ($data, $induk, $nominal, $tim, $detailJson, $penerimaIndex, $request) {
            $induk = Npd::query()->lockForUpdate()->findOrFail($induk->id);

            if ($induk->jenis !== 'pd' || $induk->status !== 'Selesai') {
                throw ValidationException::withMessages(['npd_induk_id' => 'Induk harus NPD Perjalanan Dinas berstatus Selesai.']);
            }

            if ($induk->punyaTurunanTransportAktif()) {
                throw ValidationException::withMessages([
                    'npd_induk_id' => 'NPD Perjalanan Dinas ini baru saja mendapat NPD Transport aktif dari transaksi lain.',
                ]);
            }

            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->findOrFail($induk->master_anggaran_id);
            $sisa = $masterAnggaran->sisaTersedia();

            if ($nominal > $sisa) {
                throw ValidationException::withMessages([
                    'tim' => 'Total transport (Rp '.number_format($nominal, 2, ',', '.').') melebihi Sisa Tersedia sumber dana induk (Rp '.number_format($sisa, 2, ',', '.').').',
                ]);
            }

            $npd = Npd::create([
                'jenis' => 'tr',
                'npd_induk_id' => $induk->id,
                'master_anggaran_id' => $induk->master_anggaran_id,
                'surat_perintah_id' => $induk->surat_perintah_id,
                'keu' => $induk->keu,
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
                unset($anggota['jumlah']);
                $anggota['is_penerima'] = $i === $penerimaIndex;
                $npd->tim()->create($anggota);
            }

            $npd->mirrorStatusKeSuratPerintah();
            $npd->catatHistoriStatus($request->user(), 'buat', null, $npd->status);

            return $npd;
        });

        AuditLog::catat('Buat NPD', 'Jenis: Transport, Induk: '.($induk->nomor_lengkap ?? "#{$induk->id}").', Nominal: Rp '.number_format((float) $nominal, 2, ',', '.'));

        return redirect()->route('npd.show', $npd)->with('success', 'NPD Transport berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, Npd $npd)
    {
        abort_unless($npd->jenis === 'tr', 404);
        abort_unless($npd->dapatDieditOleh($request->user()), 403);

        $npd->load(['tim', 'induk.tim']);

        $timAwal = $npd->tim->map(fn ($t) => [
            'bbm_liter' => $t->bbm_liter,
            'bbm_tarif' => $t->bbm_tarif,
            'tol' => $t->tol,
            'tiket' => $t->tiket,
            'representatif' => $t->representatif,
        ])->all();

        return $this->form($npd, $timAwal);
    }

    public function update(StoreNpdTransportRequest $request, Npd $npd)
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

        $detailJson = $this->snapshotDetailJson($induk, $data['keterangan_lampiran'] ?? null);
        $penerimaIndex = (int) $data['penerima_index'];

        DB::transaction(function () use ($request, $npd, $data, $tim, $nominal, $detailJson, $penerimaIndex) {
            $npd = Npd::query()->lockForUpdate()->findOrFail($npd->id);
            abort_unless($npd->dapatDieditOleh($request->user()), 403);

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
                'terbilang' => Terbilang::rupiah($nominal),
                'detail_json' => $detailJson,
            ]);

            $npd->tim()->delete();
            foreach ($tim as $i => $anggota) {
                unset($anggota['jumlah']);
                $anggota['is_penerima'] = $i === $penerimaIndex;
                $npd->tim()->create($anggota);
            }

            $npd->catatHistoriStatus($request->user(), 'edit', $npd->status, $npd->status, 'Data Transport diperbarui.');
        });

        AuditLog::catat('Edit NPD', 'Jenis: Transport, NPD #'.$npd->id);

        return redirect()->route('npd.show', $npd)->with('success', 'Draft NPD Transport berhasil diperbarui.');
    }

    private function form(?Npd $npd = null, ?array $timAwal = null)
    {
        $bulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        if ($npd) {
            // Sedang mengedit: induk sudah tetap, hanya tampilkan induk ini (read-only di form).
            $indukList = Npd::with('tim')->whereKey($npd->npd_induk_id)->get();
        } else {
            // NPD PD berstatus Selesai yang belum punya turunan Transport aktif.
            $indukList = Npd::with(['tim', 'masterAnggaran'])
                ->where('jenis', 'pd')
                ->where('status', 'Selesai')
                ->whereDoesntHave('turunanTransport', fn ($query) => $query->where('status', '!=', 'Dibatalkan'))
                ->orderBy('tanggal_npd', 'desc')
                ->get();
        }

        return view('npd.tr.create', compact('bulanList', 'npd', 'timAwal', 'indukList'));
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
                'nip' => $sumber?->nip,
                'rekening' => $sumber?->rekening,
                'bbm_liter' => (float) ($t['bbm_liter'] ?? 0),
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
