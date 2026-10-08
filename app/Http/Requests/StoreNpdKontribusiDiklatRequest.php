<?php

namespace App\Http\Requests;

use App\Services\SpjBerkasService;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Support\AnggaranNpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNpdKontribusiDiklatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Daftar Tujuan Transfer tidak diperiksa (dan tidak ikut tersimpan)
        // saat seluruh dananya dialihkan ke PPTK.
        $tanpaDaftar = Rule::excludeIf($this->boolean('pptk_penerima'));

        return [
            'mode' => ['required', Rule::in(Npd::MODE_KD_LIST)],
            // Referensi SP - hanya mode Perjalanan Dinas, dan OPSIONAL (boleh
            // input manual tanpa referensi). SP-nya harus layak jadi sumber
            // NPD, sama seperti pada NPD Perjalanan Dinas: berjenis Uang
            // Harian/Akomodasi, masih Diterima PPTK, dan penanda Sumber NPD
            // menyala. Saat menyunting, SP yang sudah tertaut tetap diterima
            // walau statusnya kini mengikuti NPD ini.
            'surat_perintah_id' => ['exclude_unless:mode,perjalanan', 'nullable', 'integer', Rule::exists('surat_perintah', 'id')
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
                        ->where('sumber_npd', true)
                        ->where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN))
                    ->when($this->route('npd')?->surat_perintah_id, fn ($q, $id) => $q->orWhere('id', $id)))],
            // PPTK hanya boleh memakai Sub Kegiatan limpahannya sendiri. Dropdown
            // di formulir memang sudah disaring, tetapi id-nya dikirim lewat isian
            // tersembunyi - jadi batasnya ditegakkan lagi di sini.
            'master_anggaran_id' => AnggaranNpd::aturan($this->user(), $this->route('npd')),
            'jenis_panjar' => ['required', Rule::in(Npd::JENIS_PANJAR_LIST)],
            'tanggal_npd' => ['required', 'date'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            // Selalu tahun anggaran berjalan - isiannya sudah dihapus dari
            // formulir, tetapi tetap ditegakkan di sini.
            'tahun' => ['required', 'integer', 'in:'.config('anggaran.tahun_aktif')],

            // Hanya untuk kolom "SISA ANGGARAN" di PDF NPD - lihat
            // Npd::sisaAnggaranCetak(). Dikosongkan berarti memakai angka
            // sistem. Saat isian ini dikunci kembali, nilainya diabaikan di
            // controller, bukan ditolak, supaya formulir lama tidak gagal.
            'sisa_anggaran_manual' => ['nullable', 'numeric', 'min:0'],

            'nama_pelatihan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'penerima_index' => ['required', 'integer', 'min:0'],

            /*
             * Tujuan Transfer - untuk KEDUA mode: BOLEH lebih dari satu
             * penerima, masing-masing dengan nominalnya sendiri - satu
             * koordinator menerima semuanya, atau dibagi ke beberapa orang.
             * Dulu mode Kontribusi memilih satu peserta lewat radio "Penerima
             * Dana"; radio itu sudah dihapus dan skemanya disamakan.
             *
             * Jumlah seluruh nominalnya wajib sama persis dengan Total Bruto;
             * itu diperiksa di controller karena butuh subtotal peserta.
             *
             * Bila "PPTK Sebagai Penerima Transfer" dicentang, seluruh dana
             * ke PPTK dan daftar ini diabaikan sepenuhnya.
             */
            'pptk_penerima' => ['nullable', 'boolean'],
            'pptk_rekening' => ['nullable', 'string', 'max:100'],
            'penerima_transfer' => [$tanpaDaftar, 'required', 'array', 'min:1', 'max:100'],
            'penerima_transfer.*.nama' => [$tanpaDaftar, 'required', 'string', 'max:255'],
            'penerima_transfer.*.rekening' => [$tanpaDaftar, 'nullable', 'string', 'max:100'],
            'penerima_transfer.*.nominal' => [$tanpaDaftar, 'required', 'numeric', 'min:0'],

            'keterangan_lampiran' => ['nullable', 'string'],
            // Penanda dari formulir: 'otomatis' berarti isian di atas hanya
            // pratinjau dan TIDAK disimpan - uraiannya dirangkai ulang saat
            // mencetak, mengikuti data terakhir. Formulir/permintaan lama yang
            // tidak mengirim penanda ini dianggap 'manual', supaya teks yang
            // sudah dikirim tetap tersimpan seperti dulu.
            'keterangan_mode' => ['nullable', Rule::in(['otomatis', 'manual'])],
            'ppn' => ['nullable', 'numeric', 'min:0'],
            'pph_jenis' => ['nullable', 'string', 'max:50'],
            'pph_nilai' => ['nullable', 'numeric', 'min:0'],
            'biaya_lain' => ['nullable', 'numeric', 'min:0'],

            'peserta' => ['required', 'array', 'min:1'],
            'peserta.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'peserta.*.nama' => ['required', 'string', 'max:255'],
            'peserta.*.pangkat' => ['nullable', 'string', 'max:100'],
            'peserta.*.nip' => ['nullable', 'string', 'max:50'],
            'peserta.*.rekening' => ['nullable', 'string', 'max:100'],
            'peserta.*.volume_kontribusi' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_kontribusi' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.volume_mooc' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_mooc' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.hari_uh' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_uh' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.volume_akomodasi' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_akomodasi' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.hari_saku' => ['nullable', 'integer', 'min:0'],
            'peserta.*.tarif_saku' => ['nullable', 'numeric', 'min:0'],
            'peserta.*.transport' => ['nullable', 'numeric', 'min:0'],
            // Unggah SPJ - OPSIONAL, lihat App\Services\SpjBerkasService.
            // Aturannya diambil dari service supaya kelima jenis NPD dan
            // pintu unggah di Inventarisasi SPJ tidak bisa berbeda-beda.
            ...SpjBerkasService::aturan(),
        ];
    }

    /**
     * Dua perapian sebelum validasi, supaya urutan penerima tidak bergantung
     * pada penomoran baris di peramban:
     *
     * - `peserta` diurutkan ulang dari 0. Formulir menamai barisnya
     *   peserta[idx] dengan idx yang terus bertambah (baris yang dihapus
     *   tidak dipakai ulang), sedangkan penerima_index dibaca sebagai
     *   URUTAN peserta - lihat pemakaiannya di controller dan dokumen cetak.
     *
     * - penerima_index yang kosong dianggap 0, di kedua mode. Radio
     *   "Penerima Dana" per peserta sudah tidak ada di formulir: penerima
     *   dananya kini ditentukan Tujuan Transfer (penerima_transfer) atau
     *   "PPTK Sebagai Penerima Transfer". Nilainya masih diterima supaya
     *   kiriman lama tidak gagal, tetapi tidak lagi wajib.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('peserta'))) {
            $this->merge(['peserta' => array_values($this->input('peserta'))]);
        }

        if (blank($this->input('penerima_index'))) {
            $this->merge(['penerima_index' => 0]);
        }
    }

    public function attributes(): array
    {
        return [
            'mode' => 'Mode NPD',
            'surat_perintah_id' => 'Referensi SP',
            'master_anggaran_id' => 'Sumber Dana',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'sisa_anggaran_manual' => 'Sisa Anggaran (cetak PDF)',
            'nama_pelatihan' => 'Nama Pelatihan',
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_selesai' => 'Tanggal Selesai',
            'penerima_index' => 'Penerima Dana',
            'penerima_transfer' => 'Tujuan Transfer',
            'pptk_penerima' => 'PPTK Sebagai Penerima Transfer',
            'pptk_rekening' => 'No. Rekening PPTK',
            'penerima_transfer.*.nama' => 'Nama Penerima Transfer',
            'penerima_transfer.*.nominal' => 'Nominal Penerima Transfer',
            'peserta.*.nama' => 'Nama Peserta',
            ...SpjBerkasService::labelIsian(),
        ];
    }
}
