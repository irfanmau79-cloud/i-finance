<?php

namespace App\Services;

use App\Helpers\NomorWhatsapp;
use App\Models\Npd;
use App\Models\NpdNotifikasi;
use App\Models\Pegawai;
use App\Models\User;
use App\Models\Vendor;

/**
 * Notifikasi WhatsApp "pencairan NPD selesai" untuk satu penerima tujuan
 * transfer. Kanalnya deep link wa.me: aplikasi menyiapkan nomor + teks,
 * petugas menekan Kirim di WhatsApp miliknya sendiri (lihat config/whatsapp.php).
 *
 * Semua aturannya terpusat di sini - siapa yang boleh mengirim, siapa yang
 * dituju, dan apa bunyi pesannya - supaya controller/tampilan tidak pernah
 * menyusun ulang teks maupun menebak nomor tujuan sendiri.
 */
class NotifikasiNpdService
{
    /** Hanya NPD yang uangnya benar-benar sudah cair yang boleh dinotifikasi. */
    public const STATUS_BOLEH = 'Selesai';

    /**
     * BPP menjalankan aksi "Tandai Selesai", BP memantau seluruh OPD, dan
     * superadmin boleh apa saja. PPTK & Verifikator tidak berurusan dengan
     * pencairan, jadi tidak diberi tombol ini.
     */
    public const ROLE_BOLEH = [
        User::ROLE_SUPERADMIN,
        User::ROLE_BENDAHARA_PENGELUARAN,
        User::ROLE_BPP,
    ];

    public function bolehKirim(?User $user, Npd $npd): bool
    {
        return $user !== null
            && in_array($user->role, self::ROLE_BOLEH, true)
            && $npd->status === self::STATUS_BOLEH;
    }

    /**
     * Penerima tujuan transfer NPD ini, berikut nomor WhatsApp-nya.
     *
     * Urutan penelusuran mengikuti cara kantor membaca dokumen: kalau NPD
     * lahir dari Surat Perintah, yang berhak diberi tahu adalah Tujuan
     * Transfer yang tertulis di SP itu (kolom teks bebas, dicocokkan ke Data
     * Pegawai lewat Pegawai::cariByNama). Kalau tidak ada SP, jatuh ke
     * penerima utama pada NPD - beda tabel per jenis NPD.
     *
     * @return array{nama: string, sumber: string, nomor: ?string, nomor_wa: ?string, nomor_tampil: ?string, jenis_kontak: ?string, pegawai_id: ?int}
     */
    public function tujuan(Npd $npd): array
    {
        $sp = $npd->suratPerintah;
        $tujuanSp = trim((string) ($sp?->tujuan_transfer ?? ''));

        if ($tujuanSp !== '') {
            return $this->rakit(
                nama: $tujuanSp,
                sumber: 'Tujuan Transfer pada SP '.($sp->nomor_sp ?: '-'),
                pegawai: Pegawai::cariByNama($tujuanSp),
            );
        }

        $baris = $this->penerimaUtama($npd);
        $nama = trim((string) ($baris?->nama ?? ''));

        if ($baris === null || $nama === '') {
            return $this->rakit(nama: '', sumber: 'Penerima pada NPD', pegawai: null);
        }

        // Vendor hanya mungkin pada NPD Barang/Jasa dan Narasumber.
        if (($baris->vendor_id ?? null) !== null) {
            return $this->rakit(
                nama: $nama,
                sumber: 'Penerima pada NPD (vendor)',
                vendor: Vendor::find($baris->vendor_id),
            );
        }

        return $this->rakit(
            nama: $nama,
            sumber: 'Penerima pada NPD',
            // Penerima hasil ketik manual tidak menyimpan pegawai_id; nama
            // bebasnya masih bisa dicocokkan seperti Tujuan Transfer di SP.
            pegawai: ($baris->pegawai_id ?? null) !== null
                ? Pegawai::find($baris->pegawai_id)
                : Pegawai::cariByNama($nama),
        );
    }

    /**
     * Bunyi pesan untuk SATU penerima, sesuai template di config/whatsapp.php.
     *
     * $tujuan menentukan siapa yang disapa di pembuka; bila tidak disebut,
     * penerima pertama yang dipakai. Nominal pembuka selalu TOTAL seluruh
     * penerima, dan rinciannya menyebut jatah masing-masing - itulah yang
     * membuat satu pesan tetap bisa dibaca utuh oleh siapa pun penerimanya.
     *
     * @param  array<string, mixed>|null  $tujuan
     */
    public function pesan(Npd $npd, ?array $tujuan = null): string
    {
        $daftar = $this->daftarTujuan($npd);
        $tujuan ??= $daftar[0];
        $nomorSp = trim((string) ($npd->suratPerintah?->nomor_sp ?? ''));

        return strtr((string) config('whatsapp.template_npd_selesai'), [
            ':penerima' => trim((string) ($tujuan['nama'] ?? '')) ?: 'Bapak/Ibu',
            ':nomor_npd' => $npd->nomor_lengkap ?: '-',
            ':frasa_sp' => $nomorSp === ''
                ? ''
                : str_replace(':nomor_sp', $nomorSp, (string) config('whatsapp.frasa_sp')),
            ':nominal' => number_format(array_sum(array_column($daftar, 'nominal')), 2, ',', '.'),
            ':rincian' => $this->rincian($daftar),
            ':aplikasi' => (string) config('whatsapp.tautan_aplikasi'),
        ]);
    }

    /**
     * Daftar bernomor "1. Nama sebesar Rp...". Dikosongkan saat penerimanya
     * tunggal - merinci satu baris cuma mengulang total yang baru disebut.
     *
     * @param  array<int, array<string, mixed>>  $daftar
     */
    private function rincian(array $daftar): string
    {
        if (count($daftar) < 2) {
            return '';
        }

        $baris = '';

        foreach (array_values($daftar) as $i => $t) {
            $baris .= "\n".($i + 1).'. '.$t['nama'].' sebesar Rp'.number_format((float) $t['nominal'], 2, ',', '.');
        }

        return (string) config('whatsapp.judul_rincian').$baris;
    }

    /**
     * Tautan wa.me siap klik, atau null bila nomor tujuannya belum ada.
     *
     * @param  array<string, mixed>|null  $tujuan
     */
    public function tautan(Npd $npd, ?array $tujuan = null): ?string
    {
        $tujuan ??= $this->daftarTujuan($npd)[0];
        $nomor = $tujuan['nomor_wa'] ?? null;

        return $nomor === null
            ? null
            : 'https://wa.me/'.$nomor.'?text='.rawurlencode($this->pesan($npd, $tujuan));
    }

    /**
     * SELURUH penerima transfer NPD ini, beserta nominal yang benar-benar
     * diterima masing-masing (adopsi GAS #68 & #74).
     *
     * tujuan() di atas menjawab "satu orang yang diberi tahu" dan tetap
     * dipakai apa adanya. Method ini menjawab pertanyaan yang berbeda: SIAPA
     * SAJA yang uangnya masuk. Pada Perjalanan Dinas, uang mendarat di
     * rekening tiap anggota - Daftar Pembayaran memang memerincinya - jadi
     * yang pantas diberi tahu bukan hanya koordinatornya.
     *
     * Penerima bernilai 0 DILEWATI: ia tidak menerima apa pun, jadi
     * mengiriminya pesan pencairan hanya membingungkan.
     *
     * Bila tidak ada rincian per orang (Barang/Jasa, Narasumber, atau
     * Perjalanan Dinas yang timnya belum berisi nominal), hasilnya jatuh ke
     * satu penerima dari tujuan() - perilaku lama, tidak berubah.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarTujuan(Npd $npd): array
    {
        $daftar = match ($npd->jenis) {
            'pd', 'tr' => $this->dariTim($npd),
            'kd' => $this->dariPenerimaTransfer($npd),
            default => [],
        };

        if ($daftar !== []) {
            return $daftar;
        }

        return [$this->tujuan($npd) + ['nominal' => (float) $npd->nominal]];
    }

    /** @return array<int, array<string, mixed>> */
    private function dariTim(Npd $npd): array
    {
        $daftar = [];

        foreach ($npd->tim as $anggota) {
            $nominal = (float) $anggota->hitung()['jumlah'];
            $nama = trim((string) $anggota->nama);

            if ($nominal <= 0 || $nama === '') {
                continue;
            }

            $daftar[] = $this->rakit(
                nama: $nama,
                sumber: 'Anggota tim pada NPD',
                pegawai: $anggota->pegawai_id !== null
                    ? Pegawai::find($anggota->pegawai_id)
                    : Pegawai::cariByNama($nama),
            ) + ['nominal' => $nominal];
        }

        return $daftar;
    }

    /** @return array<int, array<string, mixed>> */
    private function dariPenerimaTransfer(Npd $npd): array
    {
        $daftar = [];

        foreach (($npd->detail_json['penerima_transfer'] ?? []) as $baris) {
            $nominal = (float) ($baris['nominal'] ?? 0);
            $nama = trim((string) ($baris['nama'] ?? ''));

            if ($nominal <= 0 || $nama === '') {
                continue;
            }

            $daftar[] = $this->rakit(
                nama: $nama,
                sumber: 'Tujuan Transfer pada NPD',
                pegawai: Pegawai::cariByNama($nama),
            ) + ['nominal' => $nominal];
        }

        return $daftar;
    }

    /** Jumlah yang benar-benar ditransfer - tanpa penerima bernilai 0. */
    public function totalTujuan(Npd $npd): float
    {
        return array_sum(array_column($this->daftarTujuan($npd), 'nominal'));
    }

    /**
     * Catat satu kali pembukaan WhatsApp sebagai jejak pengiriman.
     *
     * @param  array<string, mixed>|null  $tujuan
     */
    public function catat(Npd $npd, ?User $user, ?array $tujuan = null): NpdNotifikasi
    {
        $tujuan ??= $this->daftarTujuan($npd)[0];

        return NpdNotifikasi::create([
            'npd_id' => $npd->id,
            'user_id' => $user?->id,
            'kanal' => NpdNotifikasi::KANAL_DEEP_LINK,
            'tujuan_nama' => $tujuan['nama'],
            'tujuan_nomor' => (string) $tujuan['nomor_wa'],
            'pesan' => $this->pesan($npd, $tujuan),
        ]);
    }

    /**
     * Baris penerima utama per jenis NPD. Untuk perjalanan dinas, penerima
     * dana ditandai eksplisit lewat is_penerima; bila belum ditandai, anggota
     * pertama yang dipakai - sama seperti ringkasanPenerima().
     */
    private function penerimaUtama(Npd $npd): ?object
    {
        return match ($npd->jenis) {
            'bj' => $npd->penerima->first(),
            'pd', 'tr' => $npd->tim->firstWhere('is_penerima', true) ?? $npd->tim->first(),
            'ns' => $npd->narasumber->first(),
            'kd' => $npd->peserta->first(),
            default => null,
        };
    }

    /**
     * @return array{nama: string, sumber: string, nomor: ?string, nomor_wa: ?string, nomor_tampil: ?string, jenis_kontak: ?string, pegawai_id: ?int}
     */
    private function rakit(string $nama, string $sumber, ?Pegawai $pegawai = null, ?Vendor $vendor = null): array
    {
        $kontak = $pegawai ?? $vendor;
        $nomor = trim((string) ($kontak->nomor_handphone ?? ''));

        return [
            'nama' => $kontak->nama ?? $nama,
            'sumber' => $sumber,
            'nomor' => $nomor !== '' ? $nomor : null,
            'nomor_wa' => NomorWhatsapp::normalisasi($nomor),
            'nomor_tampil' => NomorWhatsapp::tampilan($nomor),
            'jenis_kontak' => match (true) {
                $pegawai !== null => 'pegawai',
                $vendor !== null => 'vendor',
                default => null,
            },
            'pegawai_id' => $pegawai?->id,
        ];
    }
}
