<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Keterangan Penghasilan (menu "Cetak Rincian Penghasilan" & "Daftar
 * Rincian Penghasilan") - port sheet "RincianPenghasilan" di GAS.
 *
 * GAS menyimpan PDF-nya ke Google Drive lalu mencatat url_pdf + file_id. Di
 * sini PDF TIDAK disimpan: yang dicatat adalah seluruh masukan yang
 * menentukan isi dokumen, dan PDF-nya digenerate ulang on-demand tiap kali
 * tombol Cetak ditekan (lihat CLAUDE.md: "PDF digenerate ON-DEMAND").
 *
 * Karena itu nomor surat, tanggal dokumen, dan identitas penandatangan
 * DIBEKUKAN sebagai snapshot di baris ini. Kalau daftar penandatangan di
 * config/gaji_tunjangan.php berganti orang, dokumen lama tetap tercetak
 * dengan pejabat yang menandatanganinya dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rincian_penghasilan', function (Blueprint $table) {
            $table->id();

            // Nomor urut GLOBAL lintas bulan & tahun, tidak pernah reset -
            // lihat perubahan 17 di README_PERUBAHAN.txt GAS. mm/yyyy pada
            // string nomor hanyalah penanda bulan & tahun PEMBUATAN dokumen,
            // bukan periode penghasilannya.
            $table->unsignedInteger('nomor_urut');
            $table->string('nomor', 100);

            $table->string('nip', 30);
            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();

            $table->unsignedSmallInteger('tahun');
            /** @var array<int, int> daftar bulan penghasilan, urut menaik */
            $table->json('periode');

            $table->boolean('ada_pd')->default(false);
            /** @var array<int, float> nominal uang harian PD per bulan */
            $table->json('nominal_pd')->nullable();
            $table->decimal('total_pd', 18, 2)->default(0);

            // Snapshot penandatangan - lihat catatan kelas di atas.
            $table->string('penandatangan_kunci', 40);
            $table->string('penandatangan_nama', 255);
            $table->string('penandatangan_jabatan', 255);
            $table->string('penandatangan_pangkat', 100);

            $table->date('tanggal_dokumen');

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dibuat_oleh_nama', 100)->nullable();

            $table->timestamps();

            $table->unique('nomor_urut');
            $table->index(['nip', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rincian_penghasilan');
    }
};
