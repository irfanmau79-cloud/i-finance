<?php

namespace App\Support;

/**
 * Peta kolom berkas SIPD -> kolom tabel, untuk modul Data Gaji & Tunjangan.
 *
 * PEMETAAN BERDASARKAN POSISI, BUKAN NAMA HEADER. Dua alasan:
 *
 *  1. Berkas TPP memuat header "zakat" DUA KALI - kolom AB (zakat potongan
 *     SIPD) dan kolom AI (zakat yang dipotong bersama Koperasi Praja).
 *     Pemetaan berbasis nama akan menabrakkan keduanya.
 *  2. GAS pun membaca berkasnya lewat indeks kolom (GI dan TI di
 *     CodeGajiTunjangan.gs), jadi urutan kolomlah yang selama ini menjadi
 *     kontrak sesungguhnya dengan SIPD.
 *
 * Nama header tetap dicocokkan, tetapi HANYA sebagai penjaga: kalau SIPD
 * mengubah urutan kolom, import ditolak di tahap preview dengan pesan yang
 * menyebut kolom mana yang bergeser - bukan diam-diam menyimpan angka yang
 * keliru ke kolom yang salah.
 */
class GajiTunjanganKolom
{
    /** Jenis penghasilan yang bisa diimpor, beserta labelnya di antarmuka. */
    public const JENIS = [
        'gaji' => 'Gaji Induk',
        'beban' => 'TPP Beban Kerja',
        'kondisi' => 'TPP Kondisi Kerja',
    ];

    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Berkas "TemplateSIPD-Gaji-Induk": 44 kolom, A sampai AR.
     * Kunci = header di baris 1, nilai = [kolom tabel, tipe].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const GAJI = [
        'nama_pegawai' => ['nama_pegawai', 'teks'],
        'nip' => ['nip', 'teks'],
        'nik' => ['nik', 'teks'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal'],
        'alamat' => ['alamat', 'teks'],
        'tipe_jabatan' => ['tipe_jabatan', 'teks'],
        'eselon' => ['eselon', 'teks'],
        'golongan' => ['golongan', 'teks'],
        'pppk_pns' => ['pppk_pns', 'teks'],
        'nama_jabatan' => ['nama_jabatan', 'teks'],
        'status_pernikahan' => ['status_pernikahan', 'teks'],
        'nip_pasangan' => ['nip_pasangan', 'teks'],
        'is_pasangan_pns' => ['is_pasangan_pns', 'teks'],
        'kode_bank' => ['kode_bank', 'teks'],
        'nama_bank' => ['nama_bank', 'teks'],
        'npwp' => ['npwp', 'teks'],
        'nomor_rekening_bank_pegawai' => ['nomor_rekening_bank_pegawai', 'teks'],
        'tipe_k' => ['tipe_k', 'teks'],
        'jumlah_anak' => ['jumlah_anak', 'cacah'],
        'jumlah_istri_suami' => ['jumlah_istri_suami', 'cacah'],
        'jumlah_tanggungan' => ['jumlah_tanggungan', 'cacah'],
        'belanja_gaji_pokok' => ['belanja_gaji_pokok', 'uang'],
        'perhitungan_suami_istri' => ['perhitungan_suami_istri', 'uang'],
        'perhitungan_anak' => ['perhitungan_anak', 'uang'],
        'belanja_tunjangan_keluarga' => ['belanja_tunjangan_keluarga', 'uang'],
        'belanja_tunjangan_jabatan' => ['belanja_tunjangan_jabatan', 'uang'],
        'belanja_tunjangan_fungsional' => ['belanja_tunjangan_fungsional', 'uang'],
        'jumlah_gaji_tunjangan' => ['jumlah_gaji_tunjangan', 'uang'],
        'belanja_tunjangan_fungsional_umum' => ['belanja_tunjangan_fungsional_umum', 'uang'],
        'belanja_tunjangan_beras' => ['belanja_tunjangan_beras', 'uang'],
        'belanja_tunjangan_pph' => ['belanja_tunjangan_pph', 'uang'],
        'belanja_pembulatan_gaji' => ['belanja_pembulatan_gaji', 'uang'],
        'belanja_iuran_jaminan_kesehatan' => ['belanja_iuran_jaminan_kesehatan', 'uang'],
        'belanja_iuran_jaminan_kecelakaan_kerja' => ['belanja_iuran_jaminan_kecelakaan_kerja', 'uang'],
        'belanja_iuran_jaminan_kematian' => ['belanja_iuran_jaminan_kematian', 'uang'],
        'tunjangan_jaminan_hari_tua' => ['tunjangan_jaminan_hari_tua', 'uang'],
        'tunjangan_jaminan_pensiun' => ['tunjangan_jaminan_pensiun', 'uang'],
        'iwp_1%' => ['iwp_1_persen', 'uang'],
        'belanja_iuran_simpanan_tapera' => ['belanja_iuran_simpanan_tapera', 'uang'],
        'tunjangan_khusus_papua' => ['tunjangan_khusus_papua', 'uang'],
        'jumlah_potongan' => ['jumlah_potongan', 'uang'],
        'jumlah_ditransfer' => ['jumlah_ditransfer', 'uang'],
        'mkg' => ['mkg', 'cacah'],
        'pph_21' => ['pph_21', 'uang'],
    ];

    /**
     * Berkas "TemplateSIPD-TPP-*": 35 kolom, A sampai AI. Berlaku untuk Beban
     * Kerja maupun Kondisi Kerja - strukturnya identik.
     *
     * Empat kolom terakhir (AF-AI) diisi sendiri sebelum berkas diunggah;
     * tidak datang dari SIPD. Di GAS posisi AH & AI ditempati bulan & tahun,
     * di sini keduanya dari dropdown sehingga slotnya jatuh ke koperasi
     * praja & zakat.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const TPP = [
        'nama_pegawai' => ['nama_pegawai', 'teks'],
        'nip' => ['nip', 'teks'],
        'nik' => ['nik', 'teks'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal'],
        'alamat' => ['alamat', 'teks'],
        'tipe_jabatan' => ['tipe_jabatan', 'teks'],
        'eselon' => ['eselon', 'teks'],
        'golongan' => ['golongan', 'teks'],
        'pns_pppk' => ['pns_pppk', 'teks'],
        'nama_jabatan' => ['nama_jabatan', 'teks'],
        'kode_bank' => ['kode_bank', 'teks'],
        'nama_bank' => ['nama_bank', 'teks'],
        'npwp' => ['npwp', 'teks'],
        'nomor_rekening_bank_pegawai' => ['nomor_rekening_bank_pegawai', 'teks'],
        'belanja_tpp_beban_kerja' => ['belanja_tpp_beban_kerja', 'uang'],
        'belanja_tpp_tempat_bertugas' => ['belanja_tpp_tempat_bertugas', 'uang'],
        'belanja_tpp_kondisi_kerja' => ['belanja_tpp_kondisi_kerja', 'uang'],
        'belanja_tpp_kelangkaan_profesi' => ['belanja_tpp_kelangkaan_profesi', 'uang'],
        'belanja_tpp_prestasi_kerja' => ['belanja_tpp_prestasi_kerja', 'uang'],
        'tunjangan_iuran_jaminan_kesehatan' => ['tunjangan_iuran_jaminan_kesehatan', 'uang'],
        'tunjangan_iuran_jaminan_kecelakaan_kerja' => ['tunjangan_iuran_jaminan_kecelakaan_kerja', 'uang'],
        'tunjangan_iuran_jaminan_kematian' => ['tunjangan_iuran_jaminan_kematian', 'uang'],
        'tunjangan_jaminan_hari_tua' => ['tunjangan_jaminan_hari_tua', 'uang'],
        'tunjangan_jaminan_pensiun' => ['tunjangan_jaminan_pensiun', 'uang'],
        'iwp_1%' => ['iwp_1_persen', 'uang'],
        'tunjangan_iuran_simpanan_tapera' => ['tunjangan_iuran_simpanan_tapera', 'uang'],
        'pph_21' => ['pph_21', 'uang'],
        'zakat' => ['zakat', 'uang'],
        'bulog' => ['bulog', 'uang'],
        'jumlah_ditransfer' => ['jumlah_ditransfer', 'uang'],
        'jumlah_potongan' => ['jumlah_potongan', 'uang'],
        'nilai kinerja' => ['nilai_kinerja', 'persen'],
        'tpp maksimum' => ['tpp_maksimum', 'uang'],
        'simpanan koperasi praja' => ['koperasi_praja', 'uang'],
        // Header "zakat" yang KEDUA. Justru inilah alasan pemetaan tidak
        // boleh berbasis nama - lihat catatan kelas.
        'zakat_praja' => ['zakat_praja', 'uang'],
    ];

    /**
     * Kolom TPP yang hanya berlaku untuk jenis 'beban'. Pada berkas Kondisi
     * Kerja kolomnya tetap ada tetapi nilainya diabaikan (disimpan null),
     * mengikuti GAS yang membaca Koperasi Praja & Zakat dari TPP_Beban saja.
     *
     * @var array<int, string>
     */
    public const KOLOM_KHUSUS_BEBAN = ['koperasi_praja', 'zakat_praja'];

    /**
     * Header baris 1 yang diharapkan, urut sesuai posisi kolom.
     *
     * Header kedua "zakat" pada berkas TPP dituliskan sebagai 'zakat_praja'
     * di konstanta TPP supaya kuncinya tidak bentrok; di berkas aslinya
     * tertulis 'zakat', jadi dipulihkan di sini.
     *
     * @return array<int, string>
     */
    public static function header(string $jenis): array
    {
        $kunci = array_keys($jenis === 'gaji' ? self::GAJI : self::TPP);

        return array_map(fn (string $h) => $h === 'zakat_praja' ? 'zakat' : $h, $kunci);
    }

    /**
     * Definisi kolom urut posisi: [kolom tabel, tipe] per indeks.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function definisi(string $jenis): array
    {
        return array_values($jenis === 'gaji' ? self::GAJI : self::TPP);
    }

    public static function jumlahKolom(string $jenis): int
    {
        return count(self::definisi($jenis));
    }

    /** Tabel tujuan untuk sebuah jenis penghasilan. */
    public static function tabel(string $jenis): string
    {
        return $jenis === 'gaji' ? 'gaji_induk' : 'tpp';
    }

    /**
     * Normalisasi header untuk pembandingan: huruf kecil, spasi/garis bawah
     * dianggap sama, spasi ganda dirapatkan. Membuat "Nilai Kinerja",
     * "nilai kinerja", dan "nilai_kinerja" dianggap kolom yang sama.
     */
    public static function normalHeader(mixed $nilai): string
    {
        $teks = mb_strtolower(trim((string) $nilai));
        $teks = preg_replace('/[\s_]+/u', ' ', $teks) ?? '';

        return trim($teks);
    }
}
