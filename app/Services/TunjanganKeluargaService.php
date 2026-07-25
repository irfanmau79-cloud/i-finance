<?php

namespace App\Services;

use App\Models\AnggotaKeluarga;
use App\Models\Pegawai;
use App\Models\TunjanganKeluarga;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TunjanganKeluargaService
{
    public function dashboard(?CarbonInterface $tanggalAcuan = null): array
    {
        $tanggalAcuan ??= now();
        $keluarga = TunjanganKeluarga::with(['pegawai', 'anggota'])->get();
        $rincian = $keluarga->map(fn (TunjanganKeluarga $item) => $this->rincian($item, $tanggalAcuan));

        $anak = $keluarga->flatMap->anggota->where('hubungan', 'anak');
        $bucket = ['lt21' => 0, '21to25' => 0, 'gt25' => 0, 'invalid' => 0];
        foreach ($anak as $anggota) {
            $key = $anggota->tanggal_lahir ? $this->bucketUsia($anggota->tanggal_lahir, $tanggalAcuan) : 'invalid';
            $bucket[$key]++;
        }

        $pasangan = $keluarga->filter(fn ($item) => $item->anggota->contains('hubungan', 'pasangan'))->count();
        $anakAktif = $rincian->sum('jumlah_anak_aktif');

        return [
            'jumlah_pegawai' => $keluarga->count(), 'jumlah_pasangan' => $pasangan, 'jumlah_anak_aktif' => $anakAktif,
            'total_jiwa' => $keluarga->count() + $pasangan + $anakAktif, 'bucket' => $bucket,
            'rincian' => $rincian->sortBy('nama')->values()->all(), 'kosong' => $keluarga->isEmpty(),
        ];
    }

    public function simpanKeluarga(Pegawai $pegawai, array $payload, ?int $userId = null): TunjanganKeluarga
    {
        $anak = collect($payload['anak'] ?? [])->filter(fn ($item) => filled($item['nama'] ?? null));
        if ($anak->where('status_tunjangan', true)->count() > 2) {
            throw ValidationException::withMessages(['anak' => 'Maksimal dua anak dapat berstatus penerima tunjangan.']);
        }

        return DB::transaction(function () use ($pegawai, $payload, $userId, $anak) {
            Pegawai::query()->lockForUpdate()->findOrFail($pegawai->id);
            $keluarga = TunjanganKeluarga::updateOrCreate(['pegawai_id' => $pegawai->id], [
                'catatan' => $payload['catatan'] ?? null, 'diperbarui_oleh' => $userId,
                // Hanya disentuh kalau pemanggil eksplisit mengirim kunci ini (dipakai halaman
                // admin Data Tunjangan Keluarga) — alur pengajuan/import lama tidak pernah
                // mengirimnya, jadi dokumen yang sudah ada tidak boleh ikut terhapus diam-diam.
                ...(array_key_exists('dokumen_pendukung_path', $payload) ? [
                    'dokumen_pendukung_path' => $payload['dokumen_pendukung_path'],
                    'dokumen_pendukung_nama' => $payload['dokumen_pendukung_nama'] ?? null,
                ] : []),
            ]);
            $keluarga->anggota()->delete();

            $pasangan = $payload['pasangan'] ?? [];
            if (filled($pasangan['nama'] ?? null)) {
                $keluarga->anggota()->create($this->anggotaData('pasangan', $pasangan));
            }
            foreach ($anak as $item) {
                $keluarga->anggota()->create($this->anggotaData('anak', $item));
            }

            return $keluarga->load('anggota');
        });
    }

    public function umurRinci(CarbonInterface $tanggalLahir, ?CarbonInterface $acuan = null): ?array
    {
        $acuan ??= now();
        if ($tanggalLahir->startOfDay()->greaterThan($acuan->startOfDay())) {
            return null;
        }
        $diff = $tanggalLahir->startOfDay()->diff($acuan->startOfDay());

        return ['tahun' => $diff->y, 'bulan' => $diff->m, 'hari' => $diff->d, 'teks' => "{$diff->y} tahun {$diff->m} bulan {$diff->d} hari"];
    }

    public function bucketUsia(CarbonInterface $tanggalLahir, ?CarbonInterface $acuan = null): string
    {
        $umur = $this->umurRinci($tanggalLahir, $acuan);
        if ($umur === null) {
            return 'invalid';
        }
        if ($umur['tahun'] < 21) {
            return 'lt21';
        }
        if ($umur['tahun'] < 25 || ($umur['tahun'] === 25 && $umur['bulan'] === 0 && $umur['hari'] === 0)) {
            return '21to25';
        }

        return 'gt25';
    }

    public function kelayakan(AnggotaKeluarga $anggota, ?CarbonInterface $acuan = null): array
    {
        if (! $anggota->status_tunjangan) {
            return ['aktif' => false, 'alasan' => 'Status tunjangan tidak aktif'];
        }
        if ($anggota->hubungan !== 'anak') {
            return ['aktif' => true, 'alasan' => null];
        }
        if (! $anggota->tanggal_lahir) {
            return ['aktif' => false, 'alasan' => 'Tanggal lahir tidak valid'];
        }
        $bucket = $this->bucketUsia($anggota->tanggal_lahir, $acuan);
        if ($bucket === 'lt21') {
            return ['aktif' => true, 'alasan' => null];
        }
        if ($bucket === '21to25' && $anggota->perpanjangan_kuliah) {
            return ['aktif' => true, 'alasan' => null];
        }
        if ($bucket === '21to25') {
            return ['aktif' => false, 'alasan' => 'Perlu surat keterangan kuliah'];
        }

        return ['aktif' => false, 'alasan' => 'Melewati batas usia 25 tahun'];
    }

    public static function parseTanggal(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }
        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
                if ($date && $date->format($format) === $text) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function rincian(TunjanganKeluarga $keluarga, CarbonInterface $acuan): array
    {
        $pasangan = $keluarga->anggota->firstWhere('hubungan', 'pasangan');
        $anak = $keluarga->anggota->where('hubungan', 'anak')->map(function (AnggotaKeluarga $item) use ($acuan) {
            return ['id' => $item->id, 'nama' => $item->nama, 'tanggal_lahir' => $item->tanggal_lahir?->format('d/m/Y'),
                'umur' => $item->tanggal_lahir ? $this->umurRinci($item->tanggal_lahir, $acuan) : null,
                'status_tersimpan' => $item->status_tunjangan, 'perpanjangan_kuliah' => $item->perpanjangan_kuliah,
                'kelayakan' => $this->kelayakan($item, $acuan), 'keterangan' => $item->keterangan];
        })->values();

        return ['pegawai_id' => $keluarga->pegawai_id, 'nama' => $keluarga->pegawai->nama, 'nip' => $keluarga->pegawai->nip,
            'golongan' => $keluarga->pegawai->golongan, 'pangkat' => $keluarga->pegawai->pangkat, 'jabatan' => $keluarga->pegawai->jabatan,
            'pasangan' => $pasangan?->nama, 'anak' => $anak->all(), 'jumlah_anak_aktif' => $anak->where('kelayakan.aktif', true)->count(),
            'status' => ($pasangan ? 'K' : 'TK').'/'.$anak->where('kelayakan.aktif', true)->count()];
    }

    private function anggotaData(string $hubungan, array $data): array
    {
        return ['hubungan' => $hubungan, 'nama' => trim($data['nama']), 'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'status_tunjangan' => (bool) ($data['status_tunjangan'] ?? false), 'perpanjangan_kuliah' => (bool) ($data['perpanjangan_kuliah'] ?? false),
            'keterangan' => $data['keterangan'] ?? null];
    }
}
