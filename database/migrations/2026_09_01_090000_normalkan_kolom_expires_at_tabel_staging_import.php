<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menormalkan kolom `expires_at` di seluruh tabel staging import.
 *
 * MASALAH DI PRODUKSI
 * -------------------
 * Keenam tabel di bawah dibuat dengan `$table->timestamp('expires_at')`:
 * NOT NULL, tanpa default, dan menjadi kolom TIMESTAMP PERTAMA di tabelnya.
 * Pada MySQL/MariaDB dengan `explicit_defaults_for_timestamp = OFF` - default
 * MariaDB 10.x yang dipakai mayoritas shared hosting cPanel - kolom berbentuk
 * persis itu OTOMATIS diberi `DEFAULT CURRENT_TIMESTAMP ON UPDATE
 * CURRENT_TIMESTAMP` oleh server DB-nya.
 *
 * Akibatnya UPDATE apa pun ke baris batch (misalnya penulisan penghitung
 * jumlah_baru/jumlah_update di akhir buatDariUpload()) menimpa expires_at
 * dengan jam DB saat itu, sehingga jendela staging runtuh jadi nol detik dan
 * user selalu ditolak "Sesi staging sudah kedaluwarsa" tepat setelah
 * mengunggah berkas. Di MySQL 8 (lokal, `explicit_defaults_for_timestamp =
 * ON`) perilaku ini tidak ada - itu sebabnya bug-nya hanya muncul di server.
 *
 * PERBAIKAN
 * ---------
 * Kolomnya diubah menjadi `datetime NULL`:
 *
 *   - NULL-able => aturan auto-init/auto-update implisit MariaDB tidak
 *     berlaku lagi (aturan itu hanya menyasar kolom TIMESTAMP yang NOT NULL);
 *   - DATETIME  => tidak ada lagi konversi zona waktu oleh server DB, jadi
 *     nilainya tidak bisa bergeser saat zona waktu MySQL berbeda dari
 *     APP_TIMEZONE.
 *
 * Setelah perubahan ini kolom TIMESTAMP pertama yang tersisa (committed_at /
 * executed_at / created_at) semuanya sudah NULL-able, sehingga tabelnya kebal
 * seluruhnya terhadap aturan implisit tersebut.
 *
 * Lapis kedua ada di App\Models\Concerns\StagingKedaluwarsa: masa berlaku
 * dihitung dari created_at, bukan dari kolom ini.
 */
return new class extends Migration
{
    /** Tabel staging import beserta nilai status "staging"-nya. */
    private const TABEL = [
        'master_anggaran_imports',
        'spm_imports',
        'rak_bulanan_imports',
        'npd_historis_imports',
        'pegawai_imports',
        'vendor_imports',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'expires_at')) {
                continue;
            }

            // Batch staging yang lebih tua daripada masa berlaku terpanjang
            // yang masuk akal sudah mati menurut definisi mana pun dan tidak
            // pernah menyentuh data sebenarnya - dibuang supaya tabelnya
            // bersih sebelum kolomnya diubah. Batch yang masih baru SENGAJA
            // dibiarkan: setelah migrasi ini batch tersebut hidup kembali dan
            // bisa dilanjutkan user tanpa unggah ulang.
            DB::table($tabel)
                ->where('status', 'staged')
                ->whereNotNull('created_at')
                ->where('created_at', '<', now()->subDay())
                ->delete();

            Schema::table($tabel, function (Blueprint $table) {
                $table->dateTime('expires_at')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'expires_at')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->change();
            });
        }
    }
};
