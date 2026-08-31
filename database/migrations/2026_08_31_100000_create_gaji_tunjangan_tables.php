<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Gaji & Tunjangan - port modul CodeGajiTunjangan.gs.
 *
 * Di GAS sumbernya 3 sheet yang di-paste manual tiap bulan: "Gaji" (44 kolom
 * induk SIPD), "TPP_Beban" dan "TPP_Kondisi" (31 kolom induk + kolom manual).
 * Di sini TPP_Beban & TPP_Kondisi digabung ke SATU tabel dengan pembeda
 * kolom `jenis` karena struktur keduanya identik - berkas SIPD-nya pun sama
 * persis, cuma beda isi.
 *
 * Kolom `bulan` & `tahun` TIDAK ada di berkas SIPD (sudah diverifikasi pada
 * ketiga template): di GAS keduanya diketik manual ke sheet, di sini diambil
 * dari dropdown saat unggah. Karena itu berkasnya bisa diunggah apa adanya
 * tanpa disunting lebih dulu.
 *
 * Tidak ada foreign key ke tabel `pegawai`: data SIPD memuat orang yang tidak
 * harus sama persis dengan master pegawai (union NIP ketiga berkas Agustus
 * 2026 = 153 orang, sementara masing-masing berkas 152/152/145), dan modul
 * GAS-nya pun berdiri sendiri. NIP disimpan apa adanya sebagai kunci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gaji_induk', function (Blueprint $table) {
            $table->id();

            // Periode - dari dropdown saat import, bukan dari berkas.
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // --- Identitas (kolom A-U berkas SIPD Gaji Induk) ---
            $table->string('nama_pegawai', 255);
            $table->string('nip', 30);
            $table->string('nik', 30)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('tipe_jabatan', 50)->nullable();
            $table->string('eselon', 50)->nullable();
            $table->string('golongan', 20)->nullable();
            $table->string('pppk_pns', 20)->nullable();
            $table->string('nama_jabatan', 255)->nullable();
            $table->string('status_pernikahan', 20)->nullable();
            $table->string('nip_pasangan', 30)->nullable();
            $table->string('is_pasangan_pns', 20)->nullable();
            $table->string('kode_bank', 20)->nullable();
            $table->string('nama_bank', 100)->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('nomor_rekening_bank_pegawai', 50)->nullable();
            $table->string('tipe_k', 10)->nullable();
            $table->unsignedSmallInteger('jumlah_anak')->default(0);
            $table->unsignedSmallInteger('jumlah_istri_suami')->default(0);
            $table->unsignedSmallInteger('jumlah_tanggungan')->default(0);

            // --- Penghasilan & potongan (kolom V-AR) ---
            $table->decimal('belanja_gaji_pokok', 18, 2)->default(0);
            $table->decimal('perhitungan_suami_istri', 18, 2)->default(0);
            $table->decimal('perhitungan_anak', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_keluarga', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_jabatan', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_fungsional', 18, 2)->default(0);
            // AB. Total bruto gaji. Meski posisinya di tengah berkas, isinya
            // sudah menjumlah seluruh komponen termasuk beras, PPh, dan
            // pembulatan yang kolomnya berada SESUDAHNYA - terverifikasi pada
            // data Agustus 2026. Dipakai sebagai "Jumlah Gaji Bruto" di surat.
            $table->decimal('jumlah_gaji_tunjangan', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_fungsional_umum', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_beras', 18, 2)->default(0);
            $table->decimal('belanja_tunjangan_pph', 18, 2)->default(0);
            $table->decimal('belanja_pembulatan_gaji', 18, 2)->default(0);
            $table->decimal('belanja_iuran_jaminan_kesehatan', 18, 2)->default(0);
            $table->decimal('belanja_iuran_jaminan_kecelakaan_kerja', 18, 2)->default(0);
            $table->decimal('belanja_iuran_jaminan_kematian', 18, 2)->default(0);
            // AJ. Di GAS dibaca sebagai "Iuran 8%" (GI.pot_iwp8 = index 35).
            $table->decimal('tunjangan_jaminan_hari_tua', 18, 2)->default(0);
            $table->decimal('tunjangan_jaminan_pensiun', 18, 2)->default(0);
            // AL. "Iuran 1%" (GI.pot_iwp1 = index 37). Nama kolom di berkas
            // memakai tanda persen ("iwp_1%") yang tidak dipakai di sini.
            $table->decimal('iwp_1_persen', 18, 2)->default(0);
            $table->decimal('belanja_iuran_simpanan_tapera', 18, 2)->default(0);
            $table->decimal('tunjangan_khusus_papua', 18, 2)->default(0);
            $table->decimal('jumlah_potongan', 18, 2)->default(0);
            $table->decimal('jumlah_ditransfer', 18, 2)->default(0);
            $table->unsignedSmallInteger('mkg')->default(0);
            $table->decimal('pph_21', 18, 2)->default(0);

            $table->timestamps();

            $table->unique(['nip', 'bulan', 'tahun']);
            $table->index(['tahun', 'bulan']);
        });

        Schema::create('tpp', function (Blueprint $table) {
            $table->id();

            // 'beban'   = TPP Beban Kerja
            // 'kondisi' = TPP Kondisi Kerja (TOL)
            $table->enum('jenis', ['beban', 'kondisi']);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // --- Identitas (kolom A-N) ---
            $table->string('nama_pegawai', 255);
            $table->string('nip', 30);
            $table->string('nik', 30)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('tipe_jabatan', 50)->nullable();
            $table->string('eselon', 50)->nullable();
            $table->string('golongan', 20)->nullable();
            $table->string('pns_pppk', 20)->nullable();
            $table->string('nama_jabatan', 255)->nullable();
            $table->string('kode_bank', 20)->nullable();
            $table->string('nama_bank', 100)->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('nomor_rekening_bank_pegawai', 50)->nullable();

            // --- Finansial (kolom O-AE) ---
            $table->decimal('belanja_tpp_beban_kerja', 18, 2)->default(0);
            $table->decimal('belanja_tpp_tempat_bertugas', 18, 2)->default(0);
            $table->decimal('belanja_tpp_kondisi_kerja', 18, 2)->default(0);
            $table->decimal('belanja_tpp_kelangkaan_profesi', 18, 2)->default(0);
            $table->decimal('belanja_tpp_prestasi_kerja', 18, 2)->default(0);
            $table->decimal('tunjangan_iuran_jaminan_kesehatan', 18, 2)->default(0);
            $table->decimal('tunjangan_iuran_jaminan_kecelakaan_kerja', 18, 2)->default(0);
            $table->decimal('tunjangan_iuran_jaminan_kematian', 18, 2)->default(0);
            $table->decimal('tunjangan_jaminan_hari_tua', 18, 2)->default(0);
            $table->decimal('tunjangan_jaminan_pensiun', 18, 2)->default(0);
            $table->decimal('iwp_1_persen', 18, 2)->default(0);
            $table->decimal('tunjangan_iuran_simpanan_tapera', 18, 2)->default(0);
            $table->decimal('pph_21', 18, 2)->default(0);
            $table->decimal('zakat', 18, 2)->default(0);
            $table->decimal('bulog', 18, 2)->default(0);
            // AD. Satu-satunya angka yang dipakai untuk menampilkan nilai TPP:
            // Penilaian = Bruto = Netto. TPP & TOL tidak punya potongan.
            $table->decimal('jumlah_ditransfer', 18, 2)->default(0);
            $table->decimal('jumlah_potongan', 18, 2)->default(0);

            // --- Kolom manual, diisi sendiri sebelum berkas diunggah ---
            // AF. Persen apa adanya, mis. 98.74 berarti "98,74%". Wajib diisi.
            $table->decimal('nilai_kinerja', 8, 2)->nullable();
            // AG. Besaran TPP 100%.
            $table->decimal('tpp_maksimum', 18, 2)->default(0);
            // AH & AI. HANYA untuk jenis 'beban'. Berkas Kondisi Kerja punya
            // kedua kolom ini juga, tetapi di kantor potongan Koperasi Praja
            // dan Zakat memang tidak pernah ada di TOL, jadi nilainya tidak
            // dibaca - mengikuti GAS yang membacanya dari TPP_Beban saja.
            // Kolomnya null untuk jenis 'kondisi'.
            $table->decimal('koperasi_praja', 18, 2)->nullable();
            $table->decimal('zakat_praja', 18, 2)->nullable();

            $table->timestamps();

            $table->unique(['jenis', 'nip', 'bulan', 'tahun']);
            $table->index(['jenis', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpp');
        Schema::dropIfExists('gaji_induk');
    }
};
