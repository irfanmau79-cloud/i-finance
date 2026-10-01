<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sub menu "Daftar Rekanan" pada modul NPD.
 *
 * Rekanan adalah penyedia yang dipakai sebagai penerima pada NPD Barang/Jasa,
 * NPD Narasumber, dan SPM LS. Datanya sama dengan yang masuk lewat Manajemen
 * Data > Data Rekanan (import preview/dry-run); halaman ini menambah satu
 * pintu lagi: mengetik rekanan baru satu per satu, untuk keadaan yang tidak
 * sepadan dengan menyiapkan berkas import - mis. satu rekanan baru muncul saat
 * NPD sedang dibuat.
 *
 * Catatan penamaan: tabel, model, dan kolom relasinya tetap bernama `vendor` /
 * `vendor_id`. Yang diubah hanya sebutan di layar, karena "Rekanan" yang
 * dipakai di kantor. Mengganti nama tabel berarti menyentuh npd_penerima,
 * npd_narasumber, spm, dan seluruh tabel import - risiko yang tidak sepadan
 * dengan perubahan istilah.
 */
class RekananController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = trim((string) $request->query('status', ''));

        $rekananList = Vendor::query()
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('rekening', 'like', "%{$cari}%")
                ->orWhere('npwp', 'like', "%{$cari}%")
                ->orWhere('jenis_usaha', 'like', "%{$cari}%")))
            ->when($status === 'aktif', fn ($q) => $q->where('aktif', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('aktif', false))
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(30)
            ->withQueryString();

        return view('rekanan.index', compact('rekananList', 'cari', 'status'));
    }

    public function create(): View
    {
        return view('rekanan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $rekanan = Vendor::create($this->validasi($request));

        AuditLog::catat('Tambah Rekanan', "Rekanan: {$rekanan->nama}");

        return redirect()->route('rekanan.index')
            ->with('success', "Rekanan {$rekanan->nama} berhasil ditambahkan dan siap dipilih di Pembuatan NPD.");
    }

    public function edit(Vendor $rekanan): View
    {
        return view('rekanan.edit', compact('rekanan'));
    }

    public function update(Request $request, Vendor $rekanan): RedirectResponse
    {
        $rekanan->update($this->validasi($request, $rekanan->id));

        AuditLog::catat('Edit Rekanan', "Rekanan: {$rekanan->nama}");

        return redirect()->route('rekanan.index')
            ->with('success', "Data rekanan {$rekanan->nama} berhasil diperbarui.");
    }

    /**
     * Nama dijaga unik karena ia yang menjadi identitas baris saat import
     * (lihat VendorTemplateExport::CATATAN) sekaligus yang dicocokkan ke
     * penerima SPM LS. Dua rekanan bernama sama akan membuat pencocokan itu
     * ambigu.
     *
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('vendor', 'nama')->ignore($abaikanId)],
            'rekening' => ['nullable', 'string', 'max:100'],
            'nomor_handphone' => ['nullable', 'string', 'max:30'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'jenis_usaha' => ['nullable', 'string', 'max:100'],
        ], [
            'nama.unique' => 'Rekanan dengan nama ini sudah terdaftar.',
        ], [
            'nama' => 'Nama Rekanan',
            'npwp' => 'NPWP',
            'nomor_handphone' => 'Nomor Handphone',
            'jenis_usaha' => 'Jenis Usaha',
        ]);

        $data['pkp'] = $request->boolean('pkp');
        $data['aktif'] = $request->boolean('aktif', true);

        return $data;
    }
}
