<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Http\Requests\StoreSuratPerintahRequest;
use App\Http\Requests\UpdateSuratPerintahRequest;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Services\SuratPerintahAnggotaService;
use App\Services\SuratPerintahTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SuratPerintahController extends Controller
{
    public function __construct(
        private readonly SuratPerintahAnggotaService $anggotaService,
        private readonly SuratPerintahTimelineService $timelineService,
    ) {}

    public function index()
    {
        $suratPerintahs = SuratPerintah::query()
            ->with('induk:id,nomor_sp')
            ->orderByDesc('tanggal_sp')
            ->orderByDesc('id')
            ->get();

        return view('surat-perintah.index', compact('suratPerintahs'));
    }

    /** Monitoring SP: hanya orderan yang masih dipantau. Port dari getSPMonitoringAktif di gas-lama/CodeSuratPerintah.gs. */
    public function monitoring()
    {
        GuestSession::login();

        // getSPMonitoringAktif() di GAS mengurutkan SP input TERBARU di atas.
        $suratPerintahs = SuratPerintah::query()
            ->dipantau()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $timeline = $this->timelineService->untukBanyak($suratPerintahs);

        return view('surat-perintah.monitoring', compact('suratPerintahs', 'timeline'));
    }

    /** Nyalakan/matikan pemantauan SP (toggle di halaman Data SP). Port dari setPantauSP. */
    public function togglePantau(SuratPerintah $suratPerintah)
    {
        $suratPerintah->update(['dipantau' => ! $suratPerintah->dipantau]);

        AuditLog::catat('Toggle Monitoring SP', 'Nomor SP: '.$suratPerintah->nomor_sp.' — '.($suratPerintah->dipantau ? 'aktif' : 'nonaktif'));

        return response()->json(['dipantau' => $suratPerintah->dipantau]);
    }

    /**
     * Nyalakan/matikan flag Sumber NPD. Port dari setSumberNPD.
     * OFF berarti SP tidak lagi muncul sebagai sumber data di Pembuatan NPD
     * Perjalanan Dinas maupun daftar Reimburse Transportasi - tanpa
     * menghapusnya dari Monitoring SP.
     */
    public function toggleSumberNpd(SuratPerintah $suratPerintah)
    {
        $suratPerintah->update(['sumber_npd' => ! $suratPerintah->sumber_npd]);

        AuditLog::catat('Toggle Sumber NPD', 'Nomor SP: '.$suratPerintah->nomor_sp.' — '.($suratPerintah->sumber_npd ? 'aktif' : 'nonaktif'));

        return response()->json(['sumber_npd' => $suratPerintah->sumber_npd]);
    }

    /** Ubah kolom Pengajuan (checkbox multiple) dari halaman Monitoring SP. Port dari updatePengajuanSP. */
    public function updatePengajuan(Request $request, SuratPerintah $suratPerintah)
    {
        $validated = $request->validate([
            'pengajuan' => ['array'],
            'pengajuan.*' => ['string', 'in:'.implode(',', SuratPerintah::PENGAJUAN_OPTIONS)],
        ]);

        $pengajuan = implode(', ', $validated['pengajuan'] ?? []);
        $suratPerintah->update(['pengajuan' => $pengajuan]);

        AuditLog::catat('Ubah Pengajuan SP', 'Nomor SP: '.$suratPerintah->nomor_sp.' — '.($pengajuan !== '' ? $pengajuan : '(kosong)'));

        return response()->json(['pengajuan' => $pengajuan]);
    }

    public function exportPdf()
    {
        $suratPerintahs = SuratPerintah::orderBy('tanggal_sp', 'desc')->get();

        $html = view('surat-perintah.pdf', compact('suratPerintahs'))->render();

        $mpdf = new Mpdf([
            'format' => [215, 330],
            'orientation' => 'L',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'default_font' => 'arial',
        ]);

        $mpdf->WriteHTML($html);

        AuditLog::catat('Export PDF SP', 'Jumlah data: '.$suratPerintahs->count());

        $fileName = 'daftar-sp-'.now()->format('Ymd').'.pdf';

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function create()
    {
        return view('surat-perintah.create', $this->dataForm());
    }

    /** @return array<string, mixed> */
    private function dataForm(): array
    {
        return [
            'pegawaiList' => $this->pegawaiList(),
            'indukList' => SuratPerintah::calonIndukReimburse(),
        ];
    }

    public function store(StoreSuratPerintahRequest $request)
    {
        $this->simpanSuratPerintah($request);

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil disimpan.');
    }

    /** Form input publik (tanpa login) untuk role layanan. */
    public function publicCreate()
    {
        GuestSession::login();

        return view('surat-perintah.create', $this->dataForm() + ['isPublicForm' => true]);
    }

    /**
     * Simpan dari form publik. Validasi & penyimpanan sama seperti store().
     * Diarahkan ke Monitoring SP (di dalam shell tamu), bukan halaman
     * "terima kasih" berdiri sendiri — port dari switchTab('sp-monitor')
     * di gas-lama/index.html sesudah prosesInputSP() sukses.
     */
    public function publicStore(StoreSuratPerintahRequest $request)
    {
        GuestSession::login();

        $suratPerintah = $this->simpanSuratPerintah($request);

        return redirect()
            ->route('surat-perintah.monitoring')
            ->with('success', "Surat Perintah {$suratPerintah->nomor_sp} berhasil dikirim dan akan segera diproses.");
    }

    /**
     * Port dari prosesInputSP(). Dua bentuk berkas ditangani di sini:
     *
     * - Uang Harian/Akomodasi: identitas diisi pengguna, PDF wajib.
     * - Reimburse Transportasi: identitas & anggota DISALIN dari SP induk di
     *   sisi server (bukan dari kiriman client, supaya tidak bisa dipalsukan),
     *   nomornya "{nomor induk} (Reimburse)", komponen dipaksa Transport, dan
     *   PDF tidak wajib.
     *
     * Seluruhnya dalam satu transaksi: bila anggota gagal disimpan, baris SP
     * ikut dibatalkan dan berkas yang terlanjur terunggah dihapus.
     */
    private function simpanSuratPerintah(StoreSuratPerintahRequest $request): SuratPerintah
    {
        $data = $request->validated();
        $anggotaInput = $data['anggota'] ?? [];
        $komponen = $data['komponen'] ?? [];
        unset($data['anggota'], $data['komponen'], $data['website']);

        $stored = null;

        if ($request->hasFile('file_url')) {
            $stored = $request->file('file_url')->storeAs('sp', Str::uuid().'.pdf', 'local');

            if (! $stored) {
                throw new \RuntimeException('File SP gagal disimpan pada penyimpanan private.');
            }
        }

        $data['file_url'] = $stored ? 'private:'.$stored : null;
        $data['status'] = SuratPerintah::STATUS_DITERIMA_PPTK;
        $data['dipantau'] = true;
        $data['sumber_npd'] = true;

        try {
            $suratPerintah = DB::transaction(function () use ($data, $anggotaInput, $komponen, $request) {
                if ($request->reimburse()) {
                    $induk = SuratPerintah::query()->lockForUpdate()->findOrFail($data['sp_induk_id']);

                    $data = $this->salinDariInduk($data, $induk);
                    $anggota = $this->anggotaService->salinDariInduk($induk->anggota);
                } else {
                    $data['pengajuan'] = implode(', ', $komponen);
                    $anggota = $this->anggotaService->normalisasi($anggotaInput, false, true);
                }

                $suratPerintah = SuratPerintah::create($data);
                $this->simpanAnggota($suratPerintah, $anggota);

                return $suratPerintah;
            });
        } catch (Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }

            throw $e;
        }

        AuditLog::catat('Buat SP', sprintf(
            'Nomor SP: %s, jenis: %s, anggota: %d',
            $suratPerintah->nomor_sp,
            $suratPerintah->jenis_permintaan,
            $suratPerintah->anggota()->count()
        ));

        return $suratPerintah;
    }

    /**
     * Salin identitas SP induk untuk entri Reimburse Transportasi. Nomor
     * memakai suffix, komponen dipaksa Transport, dan seluruh field identitas
     * diambil dari induk - bukan dari kiriman client.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function salinDariInduk(array $data, SuratPerintah $induk): array
    {
        $nomor = $induk->nomor_sp.SuratPerintah::SUFFIX_REIMBURSE;

        if (SuratPerintah::where('nomor_sp', $nomor)->exists()) {
            throw ValidationException::withMessages([
                'sp_induk_id' => 'SP induk "'.$induk->nomor_sp.'" sudah memiliki entri Reimburse Transportasi.',
            ]);
        }

        return array_replace($data, [
            'nomor_sp' => $nomor,
            'tanggal_sp' => $induk->tanggal_sp,
            'unit_kerja' => $induk->unit_kerja,
            'lokasi' => $induk->lokasi,
            'nama_pengirim' => $induk->nama_pengirim,
            'tujuan_transfer' => $induk->tujuan_transfer,
            'irban_dibayar' => $induk->irban_dibayar,
            'rincian_tgl_bayar' => $induk->rincian_tgl_bayar,
            'keterangan' => $induk->keterangan,
            'pengajuan' => 'Transport',
        ]);
    }

    public function edit(SuratPerintah $suratPerintah)
    {
        $suratPerintah->load('anggota', 'induk:id,nomor_sp');

        return view('surat-perintah.edit', $this->dataForm() + compact('suratPerintah'));
    }

    public function update(UpdateSuratPerintahRequest $request, SuratPerintah $suratPerintah)
    {
        $data = $request->validated();
        $anggotaInput = $data['anggota'] ?? [];
        $komponen = $data['komponen'] ?? null;
        unset($data['anggota'], $data['komponen'], $data['website']);

        if ($komponen !== null) {
            $data['pengajuan'] = implode(', ', $komponen);
        }

        // Snapshot lama dipertahankan saat edit supaya perubahan master
        // Pegawai tidak mengubah dokumen yang sudah ditandatangani.
        $anggota = $this->anggotaService->normalisasi($anggotaInput, true, false);

        if ($request->hasFile('file_url')) {
            $pathLama = $suratPerintah->filePath();
            $diskLama = $suratPerintah->fileDisk();
            $pathBaru = $request->file('file_url')->storeAs('sp', Str::uuid().'.pdf', 'local');
            if (! $pathBaru) {
                throw new \RuntimeException('File SP pengganti gagal disimpan pada penyimpanan private.');
            }
            $data['file_url'] = 'private:'.$pathBaru;
        } else {
            unset($data['file_url']);
        }

        try {
            $suratPerintah->update($data);
            $this->simpanAnggota($suratPerintah, $anggota);
        } catch (Throwable $e) {
            if (isset($pathBaru)) {
                Storage::disk('local')->delete($pathBaru);
            }
            throw $e;
        }
        if (isset($pathBaru)) {
            Storage::disk($diskLama)->delete($pathLama);
        }

        $fieldBerubah = array_keys(array_diff_key($suratPerintah->getChanges(), array_flip(['updated_at'])));
        $keterangan = 'Nomor SP: '.$suratPerintah->nomor_sp
            .($fieldBerubah ? ' — field diubah: '.implode(', ', $fieldBerubah) : '');

        AuditLog::catat('Edit SP', $keterangan);

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil diperbarui.');
    }

    public function destroy(SuratPerintah $suratPerintah)
    {
        $nomorSp = $suratPerintah->nomor_sp;

        if (filled($suratPerintah->file_url)) {
            Storage::disk($suratPerintah->fileDisk())->delete($suratPerintah->filePath());
        }

        $suratPerintah->delete();

        AuditLog::catat('Hapus SP', 'Nomor SP: '.$nomorSp);

        return redirect()
            ->route('surat-perintah.index')
            ->with('success', 'Surat Perintah berhasil dihapus.');
    }

    public function downloadFile(SuratPerintah $suratPerintah): StreamedResponse
    {
        abort_unless($suratPerintah->fileTersedia(), 404);

        return Storage::disk($suratPerintah->fileDisk())->download(
            $suratPerintah->filePath(),
            'surat-perintah-'.$suratPerintah->id.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function pegawaiList()
    {
        // Identitas lengkap ikut dikirim supaya kartu anggota bisa terisi
        // otomatis begitu nama dipilih, sama seperti SPAnggotaUI.html di GAS.
        return Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'golongan', 'pangkat', 'jabatan', 'bidang', 'rekening']);
    }

    /**
     * Ganti seluruh snapshot anggota. Daftar kosong berarti SP tanpa anggota
     * (dibolehkan saat edit), sama seperti _spSimpanAnggota di GAS.
     *
     * @param  array<int, array<string, mixed>>  $anggota
     */
    private function simpanAnggota(SuratPerintah $suratPerintah, array $anggota): void
    {
        $suratPerintah->anggota()->delete();

        foreach (array_values($anggota) as $urutan => $item) {
            $suratPerintah->anggota()->create($item + ['urutan' => $urutan + 1]);
        }
    }
}
