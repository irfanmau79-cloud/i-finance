<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;

/**
 * Masa berlaku batch staging import (pola preview/dry-run).
 *
 * KENAPA DIHITUNG DARI created_at, BUKAN DARI KOLOM expires_at
 * ------------------------------------------------------------
 * Keenam tabel staging mendeklarasikan `$table->timestamp('expires_at')` -
 * NOT NULL, tanpa default, dan kebetulan menjadi kolom TIMESTAMP PERTAMA di
 * tabelnya. Pada MySQL/MariaDB dengan `explicit_defaults_for_timestamp = OFF`
 * (default MariaDB 10.x, yang dipakai mayoritas shared hosting cPanel),
 * kolom dengan bentuk persis itu OTOMATIS diberi
 * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` oleh servernya.
 *
 * Akibatnya UPDATE apa pun ke baris batch - misalnya penulisan penghitung
 * jumlah_baru/jumlah_update di akhir buatDariUpload() - MENIMPA expires_at
 * dengan jam DB saat itu. Jendela staging runtuh jadi nol detik, dan kalau
 * zona waktu server DB berbeda dari APP_TIMEZONE nilainya bahkan langsung
 * mundur berjam-jam. User selalu ditolak dengan "Sesi staging sudah
 * kedaluwarsa" padahal berkasnya baru saja diunggah. Di MySQL 8 (lokal,
 * `explicit_defaults_for_timestamp = ON`) perilaku ini tidak ada, sehingga
 * bug-nya hanya muncul di server.
 *
 * Migrasi 2026_09_01_090000 sudah menormalkan kolomnya jadi `datetime NULL`
 * supaya aturan implisit itu tidak berlaku lagi. Perhitungan di sini adalah
 * lapis kedua: created_at selalu `timestamp NULL` sehingga TIDAK PERNAH kena
 * aturan tersebut, dan nilainya secara konstruksi sama dengan
 * `expires_at - MENIT`. Jadi menurunkan batas dari created_at bukan sekadar
 * tambal - hasilnya identik dengan maksud aslinya, tapi kebal terhadap
 * konfigurasi server DB.
 *
 * Kolom expires_at TETAP ditulis saat batch dibuat supaya isinya tetap
 * terbaca manusia dan tetap berguna untuk kueri ad-hoc; ia hanya tidak lagi
 * dijadikan satu-satunya sumber kebenaran.
 *
 * Model pemakai wajib punya konstanta STATUS_STAGED dan kolom created_at.
 */
trait StagingKedaluwarsa
{
    /** Masa berlaku staging dalam menit, satu sumber untuk semua jenis import. */
    public static function menitKedaluwarsa(): int
    {
        return max(1, (int) config('anggaran.menit_staging_import', 120));
    }

    /**
     * Batas waktu efektif batch ini. null kalau tidak bisa ditentukan sama
     * sekali (created_at maupun expires_at kosong) - kasus itu sengaja
     * DIANGGAP BELUM kedaluwarsa supaya kegagalan membaca jam tidak pernah
     * menghanguskan pekerjaan user yang sudah mengunggah berkas.
     */
    public function batasKedaluwarsa(): ?CarbonInterface
    {
        if ($this->created_at !== null) {
            return $this->created_at->copy()->addMinutes(static::menitKedaluwarsa());
        }

        return $this->expires_at;
    }

    public function kedaluwarsa(): bool
    {
        if ($this->status !== static::STATUS_STAGED) {
            return false;
        }

        $batas = $this->batasKedaluwarsa();

        return $batas !== null && $batas->isPast();
    }

    /** Buang batch staging yang sudah kedaluwarsa supaya tabel tidak menumpuk. Aman dipanggil kapan saja. */
    public static function bersihkanKedaluwarsa(): int
    {
        return static::query()
            ->where('status', static::STATUS_STAGED)
            ->whereNotNull('created_at')
            ->where('created_at', '<', now()->subMinutes(static::menitKedaluwarsa()))
            ->delete();
    }
}
