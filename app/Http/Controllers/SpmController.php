<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\StoreSpmLsRequest;
use App\Http\Requests\StoreSpmUpGuRequest;
use App\Models\MasterAnggaran;
use App\Models\Pegawai;
use App\Models\Spm;
use App\Models\Vendor;
use Illuminate\Http\Request;
use RuntimeException;

class SpmController extends Controller
{
    public function indexUpGu(Request $request)
    {
        $spms = $this->daftarSpm($request, 'up_gu');

        return view('spm.up-gu.index', ['spms' => $spms]);
    }

    public function createUpGu()
    {
        return view('spm.up-gu.create');
    }

    public function storeUpGu(StoreSpmUpGuRequest $request)
    {
        $spm = Spm::buatUpGu($request->validated());

        AuditLog::catat('Buat SPM', 'Jenis: UP/GU, Nomor: '.$spm->nomor_dokumen.', Nominal: Rp '.number_format((float) $spm->nominal, 2, ',', '.'));

        return redirect()->route('spm.up-gu.index')->with('success', 'SPM UP/GU berhasil disimpan.');
    }

    public function editUpGu(Spm $spm)
    {
        abort_unless($spm->jenis_spm === 'up_gu', 404);

        return view('spm.up-gu.create', ['spm' => $spm]);
    }

    public function updateUpGu(StoreSpmUpGuRequest $request, Spm $spm)
    {
        abort_unless($spm->jenis_spm === 'up_gu', 404);

        $spm->updateUpGu($request->validated());

        AuditLog::catat('Edit SPM', 'Jenis: UP/GU, SPM #'.$spm->id.' ('.$spm->nomor_dokumen.')');

        return redirect()->route('spm.up-gu.index')->with('success', 'SPM UP/GU berhasil diperbarui.');
    }

    public function indexLs(Request $request)
    {
        $spms = $this->daftarSpm($request, 'ls');

        return view('spm.ls.index', ['spms' => $spms]);
    }

    public function createLs()
    {
        $masterAnggaran = MasterAnggaran::with('tagging')->where('aktif', true)->orderBy('sub_kegiatan')->get();

        return view('spm.ls.create', ['masterAnggaran' => $masterAnggaran] + $this->pilihanPenerima());
    }

    /**
     * Isi dropdown Penerima: seluruh Data Pegawai dan Data Vendor yang aktif.
     * Yang tidak aktif lagi tetap disertakan bila sedang dipakai SPM yang
     * disunting, supaya tautannya tidak lepas saat disimpan ulang.
     */
    private function pilihanPenerima(?Spm $spm = null): array
    {
        return [
            'pegawaiList' => Pegawai::query()
                ->where(fn ($query) => $query->where('aktif', true)->orWhere('id', $spm?->penerima_pegawai_id))
                ->orderBy('nama')
                ->get(['id', 'nama', 'nip', 'jabatan', 'bidang', 'rekening']),
            'vendorList' => Vendor::query()
                ->where(fn ($query) => $query->where('aktif', true)->orWhere('id', $spm?->penerima_vendor_id))
                ->orderBy('nama')
                ->get(['id', 'nama', 'jenis_usaha', 'rekening']),
        ];
    }

    public function storeLs(StoreSpmLsRequest $request)
    {
        try {
            $data = $request->validated();
            $spm = Spm::buatLs(array_replace($data, Spm::penerimaDariInput($data)));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['baris' => $e->getMessage()]);
        }

        AuditLog::catat('Buat SPM', 'Jenis: LS, Nomor: '.$spm->nomor_dokumen.', Nominal: Rp '.number_format($spm->totalNominal(), 2, ',', '.'));

        return redirect()->route('spm.ls.index')->with('success', 'SPM LS berhasil disimpan.');
    }

    public function editLs(Spm $spm)
    {
        abort_unless($spm->jenis_spm === 'ls', 404);

        $spm->load('detail');
        $mataAnggaranTerpakai = $spm->detail->pluck('master_anggaran_id');

        $masterAnggaran = MasterAnggaran::with('tagging')
            ->where(fn ($query) => $query->where('aktif', true)->orWhereIn('id', $mataAnggaranTerpakai))
            ->orderBy('sub_kegiatan')
            ->get();

        return view('spm.ls.create', ['masterAnggaran' => $masterAnggaran, 'spm' => $spm] + $this->pilihanPenerima($spm));
    }

    public function updateLs(StoreSpmLsRequest $request, Spm $spm)
    {
        abort_unless($spm->jenis_spm === 'ls', 404);

        try {
            $data = $request->validated();
            $spm->updateLs(array_replace($data, Spm::penerimaDariInput($data)));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['baris' => $e->getMessage()]);
        }

        AuditLog::catat('Edit SPM', 'Jenis: LS, SPM #'.$spm->id.' ('.$spm->nomor_dokumen.')');

        return redirect()->route('spm.ls.index')->with('success', 'SPM LS berhasil diperbarui.');
    }

    public function destroy(Request $request, Spm $spm)
    {
        $jenis = $spm->jenis_spm;
        $nomor = $spm->nomor_dokumen;
        $routeIndex = $jenis === 'ls' ? 'spm.ls.index' : 'spm.up-gu.index';

        $spm->delete();

        AuditLog::catat('Hapus SPM', 'Jenis: '.strtoupper($jenis).', Nomor: '.$nomor);

        return redirect()->route($routeIndex)->with('success', 'SPM berhasil dihapus.');
    }

    private function daftarSpm(Request $request, string $jenis)
    {
        $query = Spm::where('jenis_spm', $jenis)->orderBy('tanggal_dokumen', 'desc');

        if ($jenis === 'ls') {
            $query->with('detail.masterAnggaran');
        }

        if ($request->filled('cari')) {
            $cari = $request->string('cari');
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_dokumen', 'like', "%{$cari}%")
                    ->orWhere('nomor_sp2d', 'like', "%{$cari}%")
                    ->orWhere('penerima', 'like', "%{$cari}%")
                    ->orWhere('uraian', 'like', "%{$cari}%");
            });
        }

        return $query->paginate(25)->withQueryString();
    }
}
