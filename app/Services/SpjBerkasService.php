<?php

namespace App\Services;

use App\Helpers\AuditLog;
use App\Models\Npd;
use App\Models\SpjBerkas;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Seluruh urusan berkas SPJ yang ditempelkan ke NPD: menyimpan, menghapus,
 * dan mengubahnya jadi halaman PDF agar bisa ikut "Cetak Semua (1 Berkas)".
 *
 * Dipusatkan di satu kelas karena pintu masuknya ADA BANYAK - formulir lima
 * jenis NPD, halaman detail NPD, dan modul Inventarisasi SPJ - sementara
 * aturannya harus sama di semua pintu (jenis berkas, batas ukuran, tempat
 * simpan, catatan audit). Menyalin aturan itu ke tiap pintu adalah cara
 * paling cepat membuat satu pintu tertinggal saat aturannya berubah.
 *
 * FITUR PEMBANTU, BUKAN KEWAJIBAN: tidak ada satu pun alur yang gagal karena
 * berkas SPJ belum diunggah, dan tidak ada validasi yang mensyaratkannya.
 */
class SpjBerkasService
{
    /** Batas jumlah berkas per NPD. Satu SPJ dipindai per lembar, jadi 20 lembar sudah sangat lapang. */
    public const MAKS_BERKAS = 20;

    /** Batas ukuran satu berkas (KB). Sama dengan dokumen pendukung Pengembalian. */
    public const MAKS_UKURAN_KB = 5120;

    /**
     * Aturan validasi isian unggah SPJ, dipakai BERSAMA oleh lima Store
     * Request NPD dan controller unggah tersendiri. Satu-satunya tempat
     * jenis berkas yang diizinkan ditulis.
     *
     * mimes DAN mimetypes dipasang berdua, seperti pada dokumen pendukung
     * Pengembalian: 'mimes' memeriksa ekstensi, 'mimetypes' memeriksa isi
     * berkasnya - berkas .exe yang diganti nama jadi .pdf lolos yang pertama
     * tapi tertahan yang kedua.
     *
     * @return array<string, array<int, string>>
     */
    public static function aturan(string $kunci = 'spj'): array
    {
        return [
            $kunci => ['nullable', 'array', 'max:'.self::MAKS_BERKAS],
            $kunci.'.*' => [
                'file',
                'mimes:pdf,jpg,jpeg',
                'mimetypes:application/pdf,image/jpeg',
                'max:'.self::MAKS_UKURAN_KB,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function labelIsian(string $kunci = 'spj'): array
    {
        return [$kunci => 'Berkas SPJ', $kunci.'.*' => 'Berkas SPJ'];
    }

    /**
     * Simpan berkas yang diunggah untuk satu NPD.
     *
     * Dipanggil SESUDAH NPD tersimpan (di luar transaksi penyimpanan NPD):
     * berkas yang sudah tertulis ke disk tidak ikut ter-rollback kalau
     * transaksinya gagal, jadi menulisnya lebih dulu hanya akan meninggalkan
     * berkas yatim di storage.
     *
     * @param  array<int, UploadedFile|null>  $berkas
     * @return int jumlah berkas yang benar-benar tersimpan
     */
    public function simpan(Npd $npd, array $berkas, ?User $user): int
    {
        $berkas = array_values(array_filter($berkas, static fn ($b) => $b instanceof UploadedFile && $b->isValid()));

        if ($berkas === []) {
            return 0;
        }

        $tersimpan = DB::transaction(function () use ($npd, $berkas, $user) {
            // Nomor urut lanjut dari yang sudah ada, dikunci supaya dua
            // unggahan bersamaan tidak memakai urutan yang sama.
            $urutan = (int) SpjBerkas::query()->where('npd_id', $npd->id)->lockForUpdate()->max('urutan');
            $sudahAda = SpjBerkas::query()->where('npd_id', $npd->id)->count();
            $sisaKuota = max(0, self::MAKS_BERKAS - $sudahAda);
            $hasil = 0;

            foreach (array_slice($berkas, 0, $sisaKuota) as $file) {
                $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
                $path = $file->storeAs('spj/'.$npd->id, Str::uuid().'.'.$ext, 'local');

                if (! $path) {
                    continue;
                }

                SpjBerkas::create([
                    'npd_id' => $npd->id,
                    'path' => $path,
                    // Nama asli disimpan hanya untuk ditampilkan & jadi nama
                    // saat diunduh; nama berkas di disk tetap UUID supaya
                    // nama kiriman tidak pernah dipakai sebagai path.
                    'nama_asli' => Str::limit($file->getClientOriginalName(), 240, ''),
                    'mime' => $ext === 'pdf' ? 'application/pdf' : 'image/jpeg',
                    'ukuran' => (int) $file->getSize(),
                    'urutan' => ++$urutan,
                    'diunggah_oleh' => $user?->id,
                ]);

                $hasil++;
            }

            return $hasil;
        });

        if ($tersimpan > 0) {
            AuditLog::catat('Unggah Berkas SPJ', ($npd->nomor_lengkap ?: 'NPD #'.$npd->id).' | '.$tersimpan.' berkas');
        }

        return $tersimpan;
    }

    /** Hapus satu berkas beserta isinya di disk. */
    public function hapus(SpjBerkas $berkas): void
    {
        $npd = $berkas->npd;
        $path = $berkas->path;
        $nama = $berkas->nama_asli;

        $berkas->delete();
        Storage::disk('local')->delete($path);

        AuditLog::catat('Hapus Berkas SPJ', ($npd?->nomor_lengkap ?: 'NPD #'.$berkas->npd_id).' | '.$nama);
    }

    /**
     * Hapus seluruh berkas satu NPD - dipakai saat NPD dihapus permanen.
     * Barisnya ikut terhapus sendiri lewat cascade, tapi isi disk tidak:
     * itu yang dibersihkan di sini, dan harus dipanggil SEBELUM NPD-nya
     * dihapus (sesudahnya, path-nya sudah tidak bisa dibaca lagi).
     */
    public function hapusMilikNpd(Npd $npd): void
    {
        $paths = SpjBerkas::query()->where('npd_id', $npd->id)->pluck('path')->all();

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    /** @return Collection<int, SpjBerkas> */
    public function daftar(Npd $npd): Collection
    {
        return SpjBerkas::query()->where('npd_id', $npd->id)->orderBy('urutan')->orderBy('id')->get();
    }

    /**
     * Isi biner satu berkas SPJ sebagai PDF.
     *
     * PDF dikembalikan apa adanya - tidak dirender ulang - supaya berkas yang
     * sudah ditandatangani dan dipindai tetap persis seperti aslinya.
     *
     * JPG dibungkus jadi satu halaman F4 (ukuran kertas NPD), dan ARAH
     * KERTASNYA MENGIKUTI ARAH GAMBAR: pindaian mendatar dapat kertas
     * mendatar (330x215), pindaian tegak dapat kertas tegak (215x330).
     *
     * Sebelumnya kertasnya selalu tegak, dan pindaian mendatar dijejalkan ke
     * dalamnya: lebarnya mentok lebih dulu sehingga gambar menyusut jadi
     * sepertiga tinggi halaman - tulisan di SPJ jadi terlalu kecil untuk
     * dibaca, dan sisa halaman kosong dua pertiga. Memutar gambarnya bukan
     * jalan keluar (pembaca harus memiringkan kertas); yang diputar kertasnya.
     */
    public function pdf(SpjBerkas $berkas): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($berkas->path)) {
            return null;
        }

        if ($berkas->pdf()) {
            return $disk->get($berkas->path);
        }

        $absolut = $this->gambarTegak($disk->path($berkas->path));
        [$lebarPx, $tinggiPx] = @getimagesize($absolut) ?: [0, 0];

        // Kertas F4 seperti dokumen NPD lain, margin 8mm di keempat sisi.
        // Sisi mana yang jadi lebar ditentukan arah gambarnya.
        $mendatar = $lebarPx > 0 && $tinggiPx > 0 && $lebarPx > $tinggiPx;
        $halamanL = $mendatar ? 330.0 : 215.0;
        $halamanT = $mendatar ? 215.0 : 330.0;
        $margin = 8.0;
        $muatL = $halamanL - 2 * $margin;
        $muatT = $halamanT - 2 * $margin;

        if ($lebarPx > 0 && $tinggiPx > 0) {
            // 0.998: mPDF membandingkan lebar gambar dengan lebar area cetak
            // pada presisi yang sudah dibulatkan ke dua desimal. Pada gambar
            // yang PAS selebar area cetak, pembulatan itu bisa membuatnya
            // terbaca satu sepersepuluh milimeter lebih lebar - dan mPDF lalu
            // menggeser gambarnya ke halaman berikutnya. Menyisakan 0,2%
            // ruang jauh lebih murah daripada satu halaman kosong di tengah
            // bendel yang sudah ditandatangani.
            $skala = min($muatL / $lebarPx, $muatT / $tinggiPx) * 0.998;
            $lebar = $lebarPx * $skala;
            $tinggi = $tinggiPx * $skala;
        } else {
            $lebar = $muatL;
            $tinggi = $muatT;
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [$halamanL, $halamanT],
            'margin_left' => $margin,
            'margin_right' => $margin,
            'margin_top' => $margin,
            'margin_bottom' => $margin,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $mpdf->WriteHTML(sprintf(
            '<div style="text-align:center;"><img src="%s" style="width:%.2fmm;height:%.2fmm;"></div>',
            htmlspecialchars($absolut, ENT_QUOTES),
            $lebar,
            $tinggi,
        ));

        $hasil = $mpdf->Output('', Destination::STRING_RETURN);

        if ($absolut !== $disk->path($berkas->path)) {
            @unlink($absolut);
        }

        return $hasil;
    }

    /**
     * Seluruh berkas SPJ satu NPD sebagai daftar PDF siap digabung.
     *
     * @return array<int, string>
     */
    public function pdfUntukGabungan(Npd $npd): array
    {
        return $this->daftar($npd)
            ->map(fn (SpjBerkas $berkas) => $this->pdf($berkas))
            ->filter(fn (?string $isi) => is_string($isi) && $isi !== '')
            ->values()
            ->all();
    }

    /**
     * Pindaian dari kamera ponsel sering tersimpan mendatar dengan penanda
     * EXIF "orientation" sebagai satu-satunya petunjuk arah tegaknya. mPDF
     * tidak membaca penanda itu, jadi tanpa langkah ini lembar SPJ bisa
     * tercetak miring/terbalik di berkas gabungan.
     *
     * Mengembalikan path berkas SEMENTARA yang sudah diputar, atau path asli
     * kalau tidak perlu diputar (atau kalau ext-exif tidak tersedia -
     * memutar tanpa tahu arahnya justru memperburuk).
     */
    private function gambarTegak(string $absolut): string
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $absolut;
        }

        $exif = @exif_read_data($absolut);
        $arah = (int) ($exif['Orientation'] ?? 1);
        $derajat = match ($arah) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($derajat === 0) {
            return $absolut;
        }

        $gambar = @imagecreatefromjpeg($absolut);

        if (! $gambar) {
            return $absolut;
        }

        $diputar = imagerotate($gambar, $derajat, 0);
        // tempnam() SUDAH membuat berkasnya; menempelkan '.jpg' menghasilkan
        // path lain dan meninggalkan berkas pertama menggantung di temp.
        // Jadi yang dipakai path dari tempnam() apa adanya - mPDF membaca
        // jenis gambar dari isinya, bukan dari ekstensinya.
        $sementara = tempnam(sys_get_temp_dir(), 'spj');
        imagejpeg($diputar, $sementara, 92);
        imagedestroy($gambar);
        imagedestroy($diputar);

        return $sementara;
    }
}
