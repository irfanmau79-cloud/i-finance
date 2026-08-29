<?php

namespace App\Http\Requests;

use App\Models\SuratPerintah;
use Illuminate\Validation\Rule;

/**
 * Edit Surat Perintah, mengikuti editSP() di CodeSuratPerintah.gs.
 *
 * Dua beda penting dari form Input:
 * - Jenis Permintaan dan SP induk TIDAK bisa diubah setelah SP dibuat.
 *   Mengubahnya berarti mengubah arti dokumen (dan penomorannya), jadi SP
 *   yang salah jenis dihapus lalu diinput ulang.
 * - Anggota tidak wajib di sini, sama seperti GAS: parameter "wajib" pada
 *   _spNormalisasiAnggota tidak dikirim dari editSP.
 */
class UpdateSuratPerintahRequest extends StoreSuratPerintahRequest
{
    public function reimburse(): bool
    {
        return $this->route('suratPerintah')?->isReimburse() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('suratPerintah')?->id;

        return array_merge(parent::rules(), [
            'jenis_permintaan' => ['prohibited'],
            'sp_induk_id' => ['prohibited'],

            // Identitas SP tetap wajib saat edit - nilainya sudah ada di form,
            // termasuk pada SP Reimburse (field-nya read-only, tetap terkirim).
            'nomor_sp' => ['required', 'string', 'max:100', Rule::unique('surat_perintah', 'nomor_sp')->ignore($id)],
            'tanggal_sp' => ['required', 'date'],
            'unit_kerja' => ['required', Rule::in(self::UNIT_KERJA)],
            'lokasi' => ['required', 'string', 'max:100'],
            'nama_pengirim' => ['required', 'string', 'max:100'],
            'tujuan_transfer' => ['required', 'string', 'max:150'],
            'irban_dibayar' => ['required', 'in:1,0'],
            'rincian_tgl_bayar' => ['required', 'string', 'max:255'],
            'keterangan' => ['required', 'string'],

            'komponen' => ['nullable', 'array'],
            'file_url' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'anggota' => ['nullable', 'array', 'max:'.SuratPerintah::MAKS_ANGGOTA],
        ]);
    }

    protected function prepareForValidation(): void
    {
        // Form Input menyuntikkan jenis_permintaan sebagai default; pada Edit
        // default itu harus dibuang lagi supaya aturan "prohibited" tidak
        // memicu error. Yang dikirim pengguna secara eksplisit TETAP
        // dipertahankan agar percobaan mengubah jenis benar-benar ditolak.
        $dikirimPengguna = $this->exists('jenis_permintaan');

        parent::prepareForValidation();

        if (! $dikirimPengguna) {
            $this->request->remove('jenis_permintaan');
        }
    }
}
