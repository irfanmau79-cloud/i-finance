<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Fillable([
    'jenis_spm',
    'tanggal_dokumen',
    'nomor_dokumen',
    'tanggal_sp2d',
    'nomor_sp2d',
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

    /** Baris mata anggaran (HANYA untuk jenis_spm 'ls' - lihat Spm::buatLs()). */
    public function detail(): HasMany
    {
        return $this->hasMany(SpmDetail::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Total nominal dokumen. Untuk 'up_gu' langsung dari kolom nominal
     * header (satu angka, tanpa mata anggaran). Untuk 'ls' kolom nominal
     * header tidak pernah diisi - totalnya SELALU SUM(spm_detail.nominal),
     * karena satu dokumen LS bisa mencakup beberapa mata anggaran.
     */
    public function totalNominal(): float
    {
        if ($this->jenis_spm !== 'ls') {
            return (float) $this->nominal;
        }

        return (float) ($this->relationLoaded('detail') ? $this->detail->sum('nominal') : $this->detail()->sum('nominal'));
    }

    /**
     * Simpan SPM jalur LS (dicairkan langsung di BPKAD ke pihak ketiga,
     * langsung mengurangi pagu — lihat MasterAnggaran::sisaTersedia()). Satu
     * dokumen LS bisa mencakup BEBERAPA mata anggaran sekaligus lewat
     * $data['baris'] (array of ['master_anggaran_id' => int, 'nominal' =>
     * float]) - PPN/PPh/penerima/uraian tetap satu angka untuk seluruh
     * dokumen (kolom header biasa di $data). Dipakai baik dari form input
     * maupun proses impor, makanya validasinya ditaruh di sini (satu pintu),
     * bukan di controller/request.
     *
     * Semua baris divalidasi lebih dulu (di dalam transaction, sebelum
     * apa pun disimpan) - kalau SATU baris saja gagal (mata anggaran tidak
     * ada atau melebihi sisa tersedia mata anggaran itu), SELURUH dokumen
     * ditolak (all-or-nothing). Alasan: satu SPM adalah satu dokumen fisik
     * dengan satu nomor dokumen - menyimpan sebagian baris yang diminta
     * pengguna akan menghasilkan dokumen yang tidak sesuai dengan yang
     * sebenarnya diajukan.
     *
     * @throws RuntimeException kalau baris kosong/duplikat mata anggaran, mata anggaran tidak ada, atau nominal baris manapun melebihi sisa tersedia mata anggaran itu.
     */
    public static function buatLs(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $baris = self::validasiBaris($data['baris'] ?? []);

            $spm = self::create($data + ['jenis_spm' => 'ls']);

            foreach ($baris as $item) {
                $spm->detail()->create($item);
            }

            return $spm;
        });
    }

    /**
     * Simpan SPM jalur UP/GU (isi ulang kas BPP, BUKAN realisasi — realisasi
     * sudah tercatat lewat NPD saat transaksi dibayar). Tidak pernah punya
     * baris spm_detail.
     */
    public static function buatUpGu(array $data): self
    {
        $data['jenis_spm'] = 'up_gu';

        return self::create($data);
    }

    /**
     * Perbarui SPM LS yang sudah ada. Baris lama diganti seluruhnya dengan
     * baris baru (form selalu mengirim keadaan akhir yang diinginkan, bukan
     * diff) - nominal lama tiap mata anggaran dikreditkan kembali ke
     * sisaTersedia() mata anggaran itu sebelum divalidasi ulang, supaya
     * baris yang mata anggarannya tidak berubah tidak terhitung dobel.
     * All-or-nothing, sama seperti buatLs().
     *
     * @throws RuntimeException kalau baris kosong/duplikat mata anggaran, mata anggaran tidak ada, atau nominal baris manapun melebihi sisa tersedia mata anggaran itu.
     */
    public function updateLs(array $data): void
    {
        DB::transaction(function () use ($data) {
            $nominalLama = $this->detail()
                ->lockForUpdate()
                ->get()
                ->mapWithKeys(fn (SpmDetail $d) => [(int) $d->master_anggaran_id => (float) $d->nominal])
                ->all();

            $baris = self::validasiBaris($data['baris'] ?? [], $nominalLama);

            $this->update($data + ['jenis_spm' => 'ls']);
            $this->detail()->delete();

            foreach ($baris as $item) {
                $this->detail()->create($item);
            }
        });
    }

    /** Perbarui SPM UP/GU. */
    public function updateUpGu(array $data): void
    {
        $data['jenis_spm'] = 'up_gu';
        $this->update($data);
    }

    /**
     * Validasi baris mata anggaran SPM LS: minimal 1 baris, nominal semua
     * baris > 0, mata anggaran tidak boleh duplikat dalam satu dokumen, dan
     * sisa_tersedia PER mata anggaran (dihitung independen - baris pada
     * mata anggaran A tidak memengaruhi validasi baris pada mata anggaran
     * B). Mengunci baris master_anggaran yang dipakai (lockForUpdate) supaya
     * aman dari race condition dengan SPM LS lain yang disimpan bersamaan.
     *
     * @param  array<int, array{master_anggaran_id?: mixed, nominal?: mixed}>  $baris
     * @param  array<int, float>  $nominalLama  nominal lama per master_anggaran_id (dikreditkan kembali saat edit, lihat updateLs())
     * @return array<int, array{master_anggaran_id: int, nominal: float}>
     *
     * @throws RuntimeException
     */
    private static function validasiBaris(array $baris, array $nominalLama = []): array
    {
        if (empty($baris)) {
            throw new RuntimeException('SPM LS wajib memiliki minimal 1 baris mata anggaran.');
        }

        $dipakai = [];
        $hasil = [];

        foreach ($baris as $item) {
            $masterAnggaranId = (int) ($item['master_anggaran_id'] ?? 0);
            $nominal = (float) ($item['nominal'] ?? 0);

            if ($nominal <= 0) {
                throw new RuntimeException('Nominal setiap baris mata anggaran harus lebih dari 0.');
            }

            if (in_array($masterAnggaranId, $dipakai, true)) {
                throw new RuntimeException('Mata anggaran tidak boleh dipilih lebih dari satu kali dalam satu SPM LS.');
            }
            $dipakai[] = $masterAnggaranId;

            $masterAnggaran = MasterAnggaran::query()->lockForUpdate()->find($masterAnggaranId);

            if (! $masterAnggaran) {
                throw new RuntimeException('Mata anggaran tidak ditemukan.');
            }

            $sisaTersedia = $masterAnggaran->sisaTersedia() + ($nominalLama[$masterAnggaranId] ?? 0.0);

            if ($nominal > $sisaTersedia) {
                $labelAnggaran = $masterAnggaran->rekening_lengkap;

                throw new RuntimeException(sprintf(
                    'SPM LS sebesar %s pada mata anggaran %s melebihi sisa tersedia %s.',
                    fmt_rupiah($nominal),
                    $labelAnggaran,
                    fmt_rupiah($sisaTersedia)
                ));
            }

            $hasil[] = ['master_anggaran_id' => $masterAnggaranId, 'nominal' => $nominal];
        }

        return $hasil;
    }
}
