<?php

use App\Models\MasterAnggaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan KODE dan URAIAN untuk program, kegiatan, sub kegiatan, dan
 * rekening menjadi kolom sendiri-sendiri. Kebalikan arah dari migrasi
 * 2026_07_27_090014_merge_kode_rekening_uraian_master_anggaran: format
 * template DPA resmi memang memisahkan keduanya, jadi menyimpannya
 * tergabung memaksa aplikasi menebak batas kode lewat "spasi pertama"
 * (MasterAnggaran::pisahKodeUraian) - rapuh untuk nama kegiatan yang
 * kebetulan diawali angka.
 *
 * KOMPATIBILITAS - dua kolom turunan sengaja DIPERTAHANKAN dengan makna
 * yang sama persis seperti sebelumnya supaya modul lain tidak ikut
 * dibongkar (87 referensi kode_rekening_bersih, 120 referensi
 * sub_kegiatan_kunci di app/, resources/, dan tests/):
 *
 *   - kode_rekening_bersih : sekarang cermin 1:1 dari kode_rekening
 *     (kode_rekening sendiri sudah bersih setelah migrasi ini).
 *   - sub_kegiatan_kunci / program_kunci / *_normal : tetap diturunkan
 *     dari string GABUNGAN "{kode} {nama}", BUKAN dari nama saja. RAK
 *     Bulanan dan Pelimpahan mencocokkan satu sel Excel berisi "kode
 *     nama" terhadap kunci ini (lihat RakBulananImport::
 *     resolveSubKegiatanKodeRekening) - menurunkannya dari nama saja
 *     akan memutus pencocokan itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_program', 50)->nullable()->after('id');
            $table->string('kode_kegiatan', 50)->nullable()->after('program');
            $table->string('kode_sub_kegiatan', 50)->nullable()->after('kegiatan');
            $table->string('rekening', 255)->nullable()->after('kode_rekening');
        });

        // Backfill: pecah nilai gabungan lama, TAPI hanya kalau token pertama
        // benar-benar berbentuk kode (angka bertitik). Tanpa syarat itu
        // "Sub Kegiatan Pengawasan" akan terpecah jadi kode "Sub" - dan dua
        // sub kegiatan berbeda yang berawalan kata sama akan bertabrakan di
        // unique key yang dibuat di bawah. Aturan ini sama persis dengan
        // MasterAnggaran::pecahNilaiGabungan().
        $pecah = function (?string $gabungan): array {
            [$kode, $nama] = MasterAnggaran::pisahKodeUraian($gabungan);

            return $nama !== '' && preg_match('/^\d[\d.]*$/', $kode) === 1
                ? [$kode, $nama]
                : [null, trim((string) $gabungan)];
        };

        DB::table('master_anggaran')->orderBy('id')
            ->select('id', 'program', 'kegiatan', 'sub_kegiatan', 'kode_rekening')
            ->chunkById(500, function ($rows) use ($pecah) {
                foreach ($rows as $row) {
                    [$kodeProgram, $namaProgram] = $pecah($row->program);
                    [$kodeKegiatan, $namaKegiatan] = $pecah($row->kegiatan);
                    [$kodeSub, $namaSub] = $pecah($row->sub_kegiatan);
                    [$kodeRek, $uraianRek] = $pecah($row->kode_rekening);

                    DB::table('master_anggaran')->where('id', $row->id)->update([
                        'kode_program' => $kodeProgram,
                        'program' => $namaProgram,
                        'kode_kegiatan' => $kodeKegiatan,
                        'kegiatan' => $namaKegiatan,
                        // Kolom kode wajib terisi; sub kegiatan/rekening tanpa
                        // kode memakai nilai utuhnya sebagai kunci.
                        'kode_sub_kegiatan' => $kodeSub ?? $namaSub,
                        'sub_kegiatan' => $namaSub,
                        'kode_rekening' => $kodeRek ?? $uraianRek,
                        'rekening' => $kodeRek !== null ? $uraianRek : null,
                    ]);
                }
            });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->string('kode_sub_kegiatan', 50)->nullable(false)->change();
            // kode_rekening kembali pendek: sekarang murni kode, uraiannya
            // sudah pindah ke kolom rekening.
            $table->string('kode_rekening', 50)->nullable(false)->change();
        });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->dropUnique('master_anggaran_sub_keg_kode_bersih_tagging_unique');
            $table->unique(['kode_sub_kegiatan', 'kode_rekening', 'tagging_id'], 'master_anggaran_identitas_unique');
        });
    }

    public function down(): void
    {
        // Gabungkan kembali kode + nama ke satu kolom seperti sebelumnya.
        DB::table('master_anggaran')->orderBy('id')
            ->select('id', 'kode_program', 'program', 'kode_kegiatan', 'kegiatan', 'kode_sub_kegiatan', 'sub_kegiatan', 'kode_rekening', 'rekening')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('master_anggaran')->where('id', $row->id)->update([
                        'program' => MasterAnggaran::gabungKodeUraian((string) $row->kode_program, $row->program),
                        'kegiatan' => MasterAnggaran::gabungKodeUraian((string) $row->kode_kegiatan, $row->kegiatan),
                        'sub_kegiatan' => MasterAnggaran::gabungKodeUraian((string) $row->kode_sub_kegiatan, $row->sub_kegiatan),
                        'kode_rekening' => MasterAnggaran::gabungKodeUraian((string) $row->kode_rekening, $row->rekening),
                    ]);
                }
            });

        Schema::table('master_anggaran', function (Blueprint $table) {
            $table->dropUnique('master_anggaran_identitas_unique');
            $table->string('kode_rekening', 500)->nullable(false)->change();
            $table->unique(['sub_kegiatan', 'kode_rekening_bersih', 'tagging_id'], 'master_anggaran_sub_keg_kode_bersih_tagging_unique');
            $table->dropColumn(['kode_program', 'kode_kegiatan', 'kode_sub_kegiatan', 'rekening']);
        });
    }
};
