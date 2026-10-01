<?php

namespace App\Support;

/**
 * Menerjemahkan penanda mode dari formulir NPD menjadi nilai yang disimpan.
 *
 * Saat mode 'otomatis', kotak Keterangan Lampiran di formulir hanya berisi
 * PRATINJAU dari uraian yang akan dirangkai saat mencetak - isinya tidak boleh
 * ikut tersimpan. Kalau tersimpan, uraian NPD itu membeku pada kalimat saat ia
 * dibuat dan tidak lagi mengikuti perubahan datanya, padahal selama ini
 * perilakunya memang mengikuti.
 *
 * Permintaan tanpa penanda (formulir lama, import, test yang sudah ada)
 * diperlakukan sebagai 'manual' supaya teks yang dikirim tetap tersimpan
 * seperti sebelumnya.
 *
 * @param  array<string, mixed>  $data
 */
class KeteranganLampiranIsian
{
    public static function dari(array $data): ?string
    {
        if (($data['keterangan_mode'] ?? 'manual') === 'otomatis') {
            return null;
        }

        $teks = $data['keterangan_lampiran'] ?? null;

        return filled($teks) ? $teks : null;
    }
}
