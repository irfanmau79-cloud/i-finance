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

    /** Bunyi pesan yang akan dikirim, sesuai template di config/whatsapp.php. */
    public function pesan(Npd $npd): string
    {
        $nomorSp = trim((string) ($npd->suratPerintah?->nomor_sp ?? ''));

        return strtr((string) config('whatsapp.template_npd_selesai'), [
            ':nomor_npd' => $npd->nomor_lengkap ?: '-',
            ':frasa_sp' => $nomorSp === ''
                ? ''
                : str_replace(':nomor_sp', $nomorSp, (string) config('whatsapp.frasa_sp')),
            ':nominal' => number_format((float) $npd->nominal, 2, ',', '.'),
            ':aplikasi' => (string) config('whatsapp.tautan_aplikasi'),
        ]);
    }

    /** Tautan wa.me siap klik, atau null bila nomor tujuannya belum ada. */
    public function tautan(Npd $npd): ?string
    {
        $nomor = $this->tujuan($npd)['nomor_wa'];

        return $nomor === null
            ? null
            : 'https://wa.me/'.$nomor.'?text='.rawurlencode($this->pesan($npd));
    }

    /** Catat satu kali pembukaan WhatsApp sebagai jejak pengiriman. */
    public function catat(Npd $npd, ?User $user): NpdNotifikasi
    {
        $tujuan = $this->tujuan($npd);

        return NpdNotifikasi::create([
            'npd_id' => $npd->id,
            'user_id' => $user?->id,
            'kanal' => NpdNotifikasi::KANAL_DEEP_LINK,
            'tujuan_nama' => $tujuan['nama'],
            'tujuan_nomor' => (string) $tujuan['nomor_wa'],
            'pesan' => $this->pesan($npd),
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
