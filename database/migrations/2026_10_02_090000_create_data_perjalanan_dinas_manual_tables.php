<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data manual Perjalanan Dinas & SPJ Perjalanan Dinas.
 *
 * LATAR: aplikasi ini mulai dipakai di pertengahan tahun anggaran. NPD
 * sebelum migrasi memang sudah masuk lewat Import NPD Historis dan nilainya
 * REAL - realisasi anggaran sudah benar karenanya. Yang tidak ikut terbawa
 * adalah rincian per anggota tim (npd_tim), padahal justru itu yang dibaca
 * Dashboard Perjalanan Dinas. Akibatnya periode Jan sampai migrasi kosong di
 * dashboard meski uangnya sudah tercatat.
 *
 * Tabel ini menambal periode itu. Ia TIDAK pernah ikut perhitungan anggaran
 * - realisasi tetap murni dari tabel npd - dan hanya dibaca dua dashboard
 * yang bersangkutan. Dengan begitu tidak ada angka pagu/sisa yang bergeser.
 *
 * Tiap baris wajib punya periode (bulan + tahun). Itu yang membuat sambungan
 * "manual sampai Juni, NPD sejak Juli" terlihat dan tidak dobel hitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Perjalanan Dinas: satu baris = satu orang pada satu bulan.
         * Orangnya DITAUTKAN ke Data Pegawai lewat pegawai_id, bukan nama
         * bebas - supaya baris manual dan baris dari NPD jatuh ke orang yang
         * sama di dashboard, bukan terpecah karena beda ejaan gelar.
         *
         * Jumlah Diterima tidak disimpan: selalu uang harian + akomodasi +
         * transport + representatif, sama rumusnya dengan NpdPerjalananHitung.
         */
        Schema::create('perjalanan_dinas_manual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            $table->decimal('hari', 10, 2)->default(0);
            $table->decimal('uang_harian', 18, 2)->default(0);
            $table->decimal('akomodasi', 18, 2)->default(0);
            $table->decimal('transport', 18, 2)->default(0);
            $table->decimal('representatif', 18, 2)->default(0);
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(['pegawai_id', 'bulan', 'tahun']);
            $table->index(['tahun', 'bulan']);
        });

        /*
         * SPJ Perjalanan Dinas: satu baris = satu DOKUMEN, bentuknya
         * mengikuti SpjDashboardService::baris(). Nomor dokumennya diketik
         * apa adanya (teks) karena dokumen periode itu tidak selalu punya
         * padanan baris di tabel npd.
         */
        Schema::create('spj_perjalanan_dinas_manual', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal');
            $table->string('nomor_npd', 100);
            $table->string('nomor_sp', 100)->nullable();
            $table->string('sub_kegiatan', 255)->nullable();
            $table->text('uraian')->nullable();
            $table->string('bidang', 100);
            $table->decimal('nominal', 18, 2)->default(0);
            $table->boolean('spj_terverifikasi')->default(false);
            $table->date('tanggal_verifikasi')->nullable();
            $table->string('diverifikasi_oleh', 255)->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(['nomor_npd', 'tahun']);
            $table->index('tahun');
        });

        // Nama indeks ditulis EKSPLISIT dan dipendekkan: nama bawaan Laravel
        // menggabungkan nama tabel + seluruh kolomnya, dan untuk tabel
        // sepanjang 'spj_perjalanan_dinas_manual_import_rows' hasilnya
        // melewati batas 64 karakter milik MySQL.
        $staging = [
            'perjalanan_dinas_manual_imports' => ['perjalanan_dinas_manual_import_rows', 'pdm'],
            'spj_perjalanan_dinas_manual_imports' => ['spj_perjalanan_dinas_manual_import_rows', 'spjm'],
        ];

        foreach ($staging as $induk => [$baris, $singkat]) {
            Schema::create($induk, function (Blueprint $table) use ($singkat) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nama_file', 255);
                $table->enum('status', ['staged', 'committed'])->default('staged');
                $table->unsignedSmallInteger('tahun');
                $table->unsignedInteger('total_baris')->default(0);
                $table->unsignedInteger('jumlah_baru')->default(0);
                $table->unsignedInteger('jumlah_update')->default(0);
                $table->unsignedInteger('jumlah_ditolak')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('committed_at')->nullable();
                $table->timestamps();

                $table->index('status', $singkat.'_imports_status_index');
            });

            Schema::create($baris, function (Blueprint $table) use ($induk, $singkat) {
                $table->id();
                $table->foreignId('import_id')
                    ->constrained($induk, indexName: $singkat.'_rows_import_id_foreign')
                    ->cascadeOnDelete();
                $table->unsignedInteger('nomor_baris');
                $table->enum('aksi', ['baru', 'update', 'ditolak']);
                $table->text('alasan')->nullable();

                // Isi mentah baris, apa adanya dari berkas. Disimpan sebagai
                // JSON supaya satu bentuk tabel staging melayani dua jenis
                // data yang kolomnya berbeda jauh.
                $table->json('isi')->nullable();

                $table->timestamps();
                $table->index(['import_id', 'nomor_baris'], $singkat.'_rows_import_baris_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_perjalanan_dinas_manual_import_rows');
        Schema::dropIfExists('spj_perjalanan_dinas_manual_imports');
        Schema::dropIfExists('perjalanan_dinas_manual_import_rows');
        Schema::dropIfExists('perjalanan_dinas_manual_imports');
        Schema::dropIfExists('spj_perjalanan_dinas_manual');
        Schema::dropIfExists('perjalanan_dinas_manual');
    }
};
