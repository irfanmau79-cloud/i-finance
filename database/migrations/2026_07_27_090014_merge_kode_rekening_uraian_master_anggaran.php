<?php

use App\Models\MasterAnggaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gabungkan kode_rekening + uraian_rekening jadi satu kolom (kode_rekening),
 * persis seperti program/kegiatan/sub_kegiatan yang sudah lebih dulu berupa
 * satu kolom teks bebas. Format gabungan: "{kode} {uraian}" (dipisah spasi
 * pertama - sama seperti konvensi data legacy di app/Console/Commands/
 * ImportMaster.php).
 *
 * Karena kode_rekening (versi bersih/pendek) dipakai sebagai kunci
 * exact-match di banyak tempat (unique constraint, RAK Bulanan, import SPM
 * LS/NPD Historis, whitelist SPJ Perjalanan Dinas, filter dropdown), kolom
 * turunan kode_rekening_bersih ditambahkan sebagai "kunci sejati" pengganti
 * kode_rekening lama - tabel lain (rak_bulanan dkk.) TIDAK ikut berubah,
 * tetap menyimpan kode pendek seperti sekarang; kode_rekening_bersih-lah
 * yang jadi sisi pembanding di kode aplikasi (lihat MasterAnggaran::
 * pisahKodeUraian()/booted()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_rekening_bersih', 50)->nullable()->after('kode_rekening');
        });

        // Backfill kode_rekening_bersih dari nilai kode_rekening LAMA (masih
        // bersih di titik ini, belum digabung dengan uraian).
        DB::table('master_anggaran')->orderBy('id')->select('id', 'kode_rekening')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('master_anggaran')->where('id', $row->id)
                        ->update(['kode_rekening_bersih' => $row->kode_rekening]);
                }
            });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_rekening', 500)->nullable(false)->change();
        });

        // Gabungkan kode_rekening = kode lama + uraian_rekening (kalau ada).
        DB::table('master_anggaran')->orderBy('id')->select('id', 'kode_rekening', 'uraian_rekening')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $gabungan = MasterAnggaran::gabungKodeUraian($row->kode_rekening, $row->uraian_rekening);
                    DB::table('master_anggaran')->where('id', $row->id)->update(['kode_rekening' => $gabungan]);
                }
            });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_rekening_bersih', 50)->nullable(false)->change();
            $table->dropUnique(['sub_kegiatan', 'kode_rekening', 'tagging_id']);
            // Nama eksplisit - nama otomatis (mengandung 'kode_rekening_bersih')
            // melebihi batas 64 karakter identifier MySQL.
            $table->unique(['sub_kegiatan', 'kode_rekening_bersih', 'tagging_id'], 'master_anggaran_sub_keg_kode_bersih_tagging_unique');
            $table->dropColumn('uraian_rekening');
        });

        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->string('kode_rekening', 500)->nullable()->change();
            $table->dropColumn('uraian_rekening');
        });
    }

    public function down(): void
    {
        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->string('uraian_rekening', 255)->nullable();
        });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('uraian_rekening', 255)->nullable()->after('kode_rekening');
        });

        DB::table('master_anggaran')->orderBy('id')->select('id', 'kode_rekening', 'kode_rekening_bersih')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    [, $uraian] = MasterAnggaran::pisahKodeUraian($row->kode_rekening);
                    DB::table('master_anggaran')->where('id', $row->id)->update([
                        'kode_rekening' => $row->kode_rekening_bersih,
                        'uraian_rekening' => $uraian !== '' ? $uraian : null,
                    ]);
                }
            });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_rekening', 50)->nullable(false)->change();
            $table->dropUnique('master_anggaran_sub_keg_kode_bersih_tagging_unique');
            $table->unique(['sub_kegiatan', 'kode_rekening']);
            $table->dropColumn('kode_rekening_bersih');
        });

        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->string('kode_rekening', 50)->nullable()->change();
        });
    }
};
