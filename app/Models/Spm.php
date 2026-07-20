<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable([
    'jenis_spm',
    'tanggal_dokumen',
    'nomor_dokumen',
    'tanggal_sp2d',
    'nomor_sp2d',
    'master_anggaran_id',
    'nominal',
    'ppn',
    'pph1',
    'jenis_pph1',
    'pph2',
    'jenis_pph2',
    'penerima',
    'uraian',
    'dibuat_oleh',
])]
class Spm extends Model
{
    protected $table = 'spm';

    public const JENIS_LIST = ['up_gu', 'ls'];

    protected function casts(): array
    {
        return [
            'tanggal_dokumen' => 'date',
            'tanggal_sp2d' => 'date',
            'nominal' => 'decimal:2',
            'ppn' => 'decimal:2',
            'pph1' => 'decimal:2',
            'pph2' => 'decimal:2',
        ];
    }

    public function masterAnggaran(): BelongsTo
    {
        return $this->belongsTo(MasterAnggaran::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Simpan SPM jalur LS (dicairkan langsung di BPKAD ke pihak ketiga,
     * langsung mengurangi pagu — lihat MasterAnggaran::totalRealisasi()).
     * Dipakai baik dari form input maupun proses impor, makanya validasinya
     * ditaruh di sini (satu pintu), bukan di controller/request.
     *
     * @throws RuntimeException  kalau mata anggaran tidak ada atau nominalnya membuat realisasi melebihi pagu.
     */
    public static function buatLs(array $data): self
    {
        $masterAnggaran = MasterAnggaran::find($data['master_anggaran_id'] ?? null);

        if (! $masterAnggaran) {
            throw new RuntimeException('Mata anggaran tidak ditemukan.');
        }

        $nominal = (float) ($data['nominal'] ?? 0);
        $realisasiBaru = $masterAnggaran->totalRealisasi() + $nominal;
        $pagu = (float) $masterAnggaran->pagu;

        if ($realisasiBaru > $pagu) {
            $labelAnggaran = trim($masterAnggaran->kode_rekening.' '.$masterAnggaran->uraian_rekening);

            throw new RuntimeException(sprintf(
                'SPM LS sebesar %s membuat realisasi %s melebihi pagu %s pada %s.',
                fmt_rupiah($nominal),
                fmt_rupiah($realisasiBaru),
                fmt_rupiah($pagu),
                $labelAnggaran
            ));
        }

        $data['jenis_spm'] = 'ls';

        return self::create($data);
    }

    /**
     * Simpan SPM jalur UP/GU (isi ulang kas BPP, BUKAN realisasi — realisasi
     * sudah tercatat lewat NPD saat transaksi dibayar). master_anggaran_id
     * selalu NULL untuk jenis ini, apa pun yang dikirim di $data.
     */
    public static function buatUpGu(array $data): self
    {
        $data['jenis_spm'] = 'up_gu';
        $data['master_anggaran_id'] = null;

        return self::create($data);
    }
}
