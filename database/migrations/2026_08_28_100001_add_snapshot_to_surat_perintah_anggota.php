<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anggota Surat Perintah menyimpan SNAPSHOT identitas, bukan sekadar FK ke
 * pegawai - menyamakan perilaku dengan GAS (_spNormalisasiAnggota di
 * CodeSuratPerintah.gs). Dua alasannya, keduanya nyata di lapangan:
 *
 * 1. Mode "Isi Manual": GAS mengizinkan anggota DI LUAR master Pegawai
 *    (mis. pegawai instansi lain yang ikut penugasan). Dengan pegawai_id
 *    NOT NULL, orang seperti itu tidak bisa dicatat sama sekali.
 *
 * 2. Dokumen historis: komentar GAS menyebut "snapshot lama dipertahankan
 *    agar perubahan master tidak mengubah dokumen historis". Tanpa snapshot,
 *    memperbaiki satu NIP di master Pegawai akan diam-diam mengubah SP yang
 *    sudah ditandatangani.
 *
 * pegawai_id DIPERTAHANKAN (nullable) sebagai tautan ke master bila anggota
 * memang dipilih dari daftar, supaya penelusuran ke pegawai tetap mungkin.
 * jabatan_sp menjadi nullable karena di GAS "Jabatan Dalam Tim bersifat
 * opsional".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->string('nama', 255)->nullable()->after('pegawai_id');
            $table->string('nip', 50)->nullable()->after('nama');
            $table->string('golongan', 50)->nullable()->after('nip');
            $table->string('pangkat', 100)->nullable()->after('golongan');
            $table->string('jabatan', 150)->nullable()->after('pangkat');
            $table->string('rekening', 100)->nullable()->after('jabatan');
            $table->boolean('manual')->default(false)->after('rekening');
        });

        // Backfill snapshot dari master untuk baris yang sudah ada. Sengaja
        // per-baris, bukan UPDATE ... JOIN: sintaks join pada UPDATE tidak
        // didukung SQLite yang dipakai test suite.
        DB::table('surat_perintah_anggota')->orderBy('id')
            ->select('id', 'pegawai_id')
            ->chunkById(500, function ($rows) {
                $pegawai = DB::table('pegawai')
                    ->whereIn('id', collect($rows)->pluck('pegawai_id')->filter()->all())
                    ->get(['id', 'nama', 'nip', 'golongan', 'pangkat', 'jabatan', 'rekening'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $master = $pegawai->get($row->pegawai_id);

                    if (! $master) {
                        continue;
                    }

                    DB::table('surat_perintah_anggota')->where('id', $row->id)->update([
                        'nama' => $master->nama,
                        'nip' => $master->nip,
                        'golongan' => $master->golongan,
                        'pangkat' => $master->pangkat,
                        'jabatan' => $master->jabatan,
                        'rekening' => $master->rekening,
                    ]);
                }
            });

        // Baris tanpa master (seharusnya tidak ada) tetap butuh nama karena
        // kolomnya akan dijadikan NOT NULL.
        DB::table('surat_perintah_anggota')->whereNull('nama')->update(['nama' => '(tanpa nama)']);

        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->string('nama', 255)->nullable(false)->change();
            $table->string('jabatan_sp', 50)->nullable()->change();
        });

        // pegawai_id boleh kosong untuk anggota mode manual. Foreign key lama
        // dilepas dulu karena definisinya ikut kolomnya.
        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
        });

        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->unsignedBigInteger('pegawai_id')->nullable()->change();
        });

        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
        });

        DB::table('surat_perintah_anggota')->whereNull('pegawai_id')->delete();

        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->unsignedBigInteger('pegawai_id')->nullable(false)->change();
            $table->string('jabatan_sp', 50)->nullable(false)->change();
        });

        Schema::table('surat_perintah_anggota', function (Blueprint $table) {
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->cascadeOnDelete();
            $table->dropColumn(['nama', 'nip', 'golongan', 'pangkat', 'jabatan', 'rekening', 'manual']);
        });
    }
};
