<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Helpers\GuestSession;
use App\Http\Requests\StoreSuratPerintahRequest;
use App\Http\Requests\UpdateSuratPerintahRequest;
use App\Models\Pegawai;
use App\Models\SuratPerintah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SuratPerintahController extends Controller
{
    public function index()
    {
        $suratPerintahs = SuratPerintah::orderBy('tanggal_sp', 'desc')->get();

        return view('surat-perintah.index', compact('suratPerintahs'));
    }

    /** Monitoring SP: hanya orderan yang masih dipantau. Port dari getSPMonitoringAktif di gas-lama/CodeSuratPerintah.gs. */
    public function monitoring()
    {
        GuestSession::login();

        $suratPerintahs = SuratPerintah::where('dipantau', true)->orderBy('tanggal_sp', 'desc')->get();

        return view('surat-perintah.monitoring', compact('suratPerintahs'));
    }

    /** Nyalakan/matikan pemantauan SP (toggle di halaman Data SP). Port dari setPantauSP. */
    public function togglePantau(SuratPerintah $suratPerintah)
    {
        $suratPerintah->update(['dipantau' => ! $suratPerintah->dipantau]);

        AuditLog::catat('Toggle Monitoring SP', 'Nomor SP: '.$suratPerintah->nomor_sp.' — '.($suratPerintah->dipantau ? 'aktif' : 'nonaktif'));

        return response()->json(['dipantau' => $suratPerintah->dipantau]);
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
        return view('surat-perintah.create', ['pegawaiList' => $this->pegawaiList()]);
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

        return view('surat-perintah.create', [
            'isPublicForm' => true,
            'pegawaiList' => $this->pegawaiList(),
        ]);
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

    private function simpanSuratPerintah(StoreSuratPerintahRequest $request): SuratPerintah
    {
        $data = $request->validated();
        $anggota = $data['anggota'] ?? [];
        unset($data['anggota']);
        unset($data['website']);
        $path = 'sp/'.Str::uuid().'.pdf';
        $stored = $request->file('file_url')->storeAs('sp', basename($path), 'local');
        if (! $stored) {
            throw new \RuntimeException('File SP gagal disimpan pada penyimpanan private.');
        }
        $data['file_url'] = 'private:'.$stored;
        $data['status'] = SuratPerintah::STATUS_DITERIMA_PPTK;
        $data['dipantau'] = true;

        try {
            $suratPerintah = SuratPerintah::create($data);
            $this->simpanAnggota($suratPerintah, $anggota);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }

        AuditLog::catat('Buat SP', 'Nomor SP: '.$suratPerintah->nomor_sp);

        return $suratPerintah;
    }

    public function edit(SuratPerintah $suratPerintah)
    {
        $suratPerintah->load('anggota.pegawai');
        $pegawaiList = $this->pegawaiList();

        return view('surat-perintah.edit', compact('suratPerintah', 'pegawaiList'));
    }

    public function update(UpdateSuratPerintahRequest $request, SuratPerintah $suratPerintah)
    {
        $data = $request->validated();
        $anggota = $data['anggota'] ?? [];
        unset($data['anggota']);
        unset($data['website']);

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

        Storage::disk($suratPerintah->fileDisk())->delete($suratPerintah->filePath());
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
        return Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'jabatan', 'bidang']);
    }

    private function simpanAnggota(SuratPerintah $suratPerintah, array $anggota): void
    {
        $suratPerintah->anggota()->delete();

        foreach (array_values($anggota) as $urutan => $item) {
            $suratPerintah->anggota()->create([
                'pegawai_id' => $item['pegawai_id'],
                'jabatan_sp' => $item['jabatan_sp'],
                'urutan' => $urutan,
            ]);
        }
    }
}
