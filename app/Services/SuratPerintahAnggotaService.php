<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\SuratPerintah;
use App\Models\SuratPerintahAnggota;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validasi + pembentukan snapshot anggota Surat Perintah.
 *
 * Port dari _spNormalisasiAnggota() di CodeSuratPerintah.gs, dengan aturan
 * yang sama persis:
 *
 * - Nama wajib; kartu yang benar-benar kosong (nama & jabatan tim kosong)
 *   diabaikan, bukan ditolak.
 * - Jabatan Dalam Tim OPSIONAL, tapi kalau diisi harus dari daftar yang sah.
 * - Nama ganda dalam satu SP ditolak.
 * - Nama di luar master Pegawai HANYA boleh lewat mode "Isi Manual".
 * - Identitas diambil dari master untuk input biasa, dari ketikan pengguna
 *   untuk mode manual, dan dari snapshot lama saat mengedit SP - supaya
 *   perubahan master tidak mengubah dokumen historis.
 */
class SuratPerintahAnggotaService
{
    /**
     * @param  array<int, array<string, mixed>>|null  $anggota
     * @param  bool  $gunakanSnapshot  true saat edit: identitas lama dipertahankan
     * @param  bool  $wajib  true bila SP wajib punya minimal satu anggota
     * @return array<int, array<string, mixed>> siap disimpan ke surat_perintah_anggota
     *
     * @throws ValidationException
     */
    public function normalisasi(?array $anggota, bool $gunakanSnapshot = false, bool $wajib = true): array
    {
        $anggota ??= [];

        if (count($anggota) > SuratPerintah::MAKS_ANGGOTA) {
            $this->tolak(sprintf('Jumlah anggota SP maksimal %d orang.', SuratPerintah::MAKS_ANGGOTA));
        }

        $master = $this->masterPegawai();
        $terlihat = [];
        $hasil = [];

        foreach (array_values($anggota) as $indeks => $baris) {
            $baris = is_array($baris) ? $baris : [];
            $nama = trim((string) ($baris['nama'] ?? ''));
            $jabatanSp = trim((string) ($baris['jabatan_sp'] ?? ''));

            // Kartu kosong diabaikan - pengguna menambah baris lalu tidak mengisinya.
            if ($nama === '' && $jabatanSp === '') {
                continue;
            }

            if ($nama === '') {
                $this->tolak(sprintf('Nama Anggota %d wajib diisi.', $indeks + 1));
            }

            if ($jabatanSp !== '' && ! in_array($jabatanSp, SuratPerintah::JABATAN_ANGGOTA, true)) {
                $this->tolak(sprintf('Jabatan Dalam Tim untuk %s tidak valid.', $nama));
            }

            $kunci = $this->kunci($nama);

            if (isset($terlihat[$kunci])) {
                $this->tolak(sprintf('Nama anggota "%s" terduplikasi.', $nama));
            }

            $terlihat[$kunci] = true;

            $pegawai = $master->get($kunci);
            $manual = filter_var($baris['manual'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $pegawai && ! $manual && ! $gunakanSnapshot) {
                $this->tolak(sprintf(
                    'Pegawai "%s" tidak ditemukan pada master Pegawai. Pilih dari daftar atau gunakan Isi Manual.',
                    $nama
                ));
            }

            $hasil[] = $this->snapshot($baris, $pegawai, $nama, $jabatanSp, $manual, $gunakanSnapshot);
        }

        if ($wajib && $hasil === []) {
            $this->tolak('Anggota SP wajib diisi minimal 1 orang.');
        }

        return $hasil;
    }

    /**
     * Salin anggota SP induk untuk entri Reimburse Transportasi. Semua
     * diperlakukan sebagai snapshot manual supaya identitasnya persis sama
     * dengan induk, tidak ikut berubah bila master berubah.
     *
     * @param  iterable<int, SuratPerintahAnggota>  $anggotaInduk
     * @return array<int, array<string, mixed>>
     */
    public function salinDariInduk(iterable $anggotaInduk): array
    {
        $input = [];

        foreach ($anggotaInduk as $anggota) {
            $input[] = $anggota->sebagaiInput() + ['manual' => true];
        }

        if ($input === []) {
            $this->tolak('SP induk belum memiliki anggota; lengkapi anggota SP induk lebih dulu.');
        }

        return $this->normalisasi($input, false, true);
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    private function snapshot(
        array $baris,
        ?Pegawai $pegawai,
        string $nama,
        string $jabatanSp,
        bool $manual,
        bool $gunakanSnapshot,
    ): array {
        // Sumber identitas: snapshot lama saat edit, ketikan pengguna saat
        // manual, master pegawai untuk input biasa.
        if ($gunakanSnapshot) {
            $sumber = $baris;

            // Snapshot kosong (data lama) tetap boleh dilengkapi dari master.
            $adaIsi = collect(['nip', 'golongan', 'pangkat', 'jabatan'])
                ->contains(fn (string $key) => trim((string) ($baris[$key] ?? '')) !== '');

            if (! $adaIsi && $pegawai) {
                $sumber = $this->dariPegawai($pegawai);
            }
        } elseif ($manual) {
            $sumber = $baris;
        } else {
            $sumber = $this->dariPegawai($pegawai);
        }

        return [
            // Nama resmi master dipakai bila anggota memang dipilih dari master.
            'pegawai_id' => (! $manual && $pegawai) ? $pegawai->id : null,
            'nama' => (! $gunakanSnapshot && ! $manual && $pegawai) ? trim((string) $pegawai->nama) : $nama,
            'nip' => trim((string) ($sumber['nip'] ?? '')),
            'golongan' => trim((string) ($sumber['golongan'] ?? '')),
            'pangkat' => trim((string) ($sumber['pangkat'] ?? '')),
            'jabatan' => trim((string) ($sumber['jabatan'] ?? '')),
            'rekening' => trim((string) ($sumber['rekening'] ?? '')),
            'manual' => $manual,
            'jabatan_sp' => $jabatanSp !== '' ? $jabatanSp : null,
        ];
    }

    /** @return array<string, string> */
    private function dariPegawai(?Pegawai $pegawai): array
    {
        if (! $pegawai) {
            return [];
        }

        return [
            'nip' => (string) $pegawai->nip,
            'golongan' => (string) $pegawai->golongan,
            'pangkat' => (string) $pegawai->pangkat,
            'jabatan' => (string) $pegawai->jabatan,
            'rekening' => (string) $pegawai->rekening,
        ];
    }

    /** @return Collection<string, Pegawai> */
    private function masterPegawai(): Collection
    {
        return Pegawai::query()->get()->keyBy(fn (Pegawai $p) => $this->kunci((string) $p->nama));
    }

    /** Normalisasi nama untuk pembandingan: whitespace dirapikan, huruf kecil. */
    private function kunci(string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $nama) ?? ''));
    }

    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['anggota' => $pesan]);
    }
}
