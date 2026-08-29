<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\SpmDetail;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sejak Prompt 22, satu SPM LS bisa mencakup beberapa mata anggaran. Sumber
 * query di sini adalah spm_detail (bukan spm) - SATU BARIS FILE per
 * kombinasi SPM + mata anggaran, dengan kolom header dokumen (Nomor/Tanggal
 * Dokumen, SP2D, PPN, PPh, Penerima, Uraian, Dibuat Oleh/Pada) DIULANG persis
 * sama di setiap baris milik SPM yang sama - lihat App\Models\SpmImport untuk
 * penjelasan lengkap kenapa format ini dipilih (harus tetap sinkron dengan
 * SpmUploadImport yang membacanya kembali).
 */
class SpmLsExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return SpmLsTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return SpmLsTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return SpmDetail::query()
            ->join('spm', 'spm.id', '=', 'spm_detail.spm_id')
            ->where('spm.jenis_spm', 'ls')
            ->with(['spm', 'masterAnggaran.tagging'])
            ->orderByDesc('spm.tanggal_dokumen')
            ->orderByDesc('spm.id')
            ->orderBy('spm_detail.id')
            ->select('spm_detail.*');
    }

    public function headings(): array
    {
        return SpmLsTemplateExport::HEADERS;
    }

    /** @param  SpmDetail  $row */
    public function map($row): array
    {
        $spm = $row->spm;

        return [
            optional($spm->tanggal_dokumen)->format('Y-m-d'),
            $spm->nomor_dokumen,
            optional($spm->tanggal_sp2d)->format('Y-m-d'),
            $spm->nomor_sp2d,
            $row->masterAnggaran?->kode_sub_kegiatan,
            $row->masterAnggaran?->sub_kegiatan,
            $row->masterAnggaran?->kode_rekening,
            $row->masterAnggaran?->rekening,
            $row->masterAnggaran?->tagging?->nama,
            (float) $row->nominal,
            (float) $spm->ppn,
            $spm->jenis_pph1,
            (float) $spm->pph1,
            $spm->jenis_pph2,
            (float) $spm->pph2,
            $spm->penerima,
            $spm->uraian,
        ];
    }
}
