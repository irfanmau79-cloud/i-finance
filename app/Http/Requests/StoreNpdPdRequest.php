<?php

namespace App\Http\Requests;

use App\Services\SpjBerkasService;
use App\Helpers\NpdPerjalananHitung;
use App\Models\ClusterUh;
use App\Models\Npd;
use App\Models\SuratPerintah;
use App\Support\AnggaranNpd;
use App\Support\PenerimaPd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNpdPdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // PPTK hanya boleh memakai Sub Kegiatan limpahannya sendiri. Dropdown
            // di formulir memang sudah disaring, tetapi id-nya dikirim lewat isian
            // tersembunyi - jadi batasnya ditegakkan lagi di sini.
            'master_anggaran_id' => AnggaranNpd::aturan($this->user(), $this->route('npd')),
            // NPD Perjalanan Dinas WAJIB berangkat dari Surat Perintah - tidak
            // boleh lagi dibuat lepas. SP-nya pun harus yang benar-benar layak
            // jadi sumber: berjenis Uang Harian/Akomodasi, masih berstatus
            // Diterima PPTK, dan penandanya sebagai sumber NPD masih menyala.
            'surat_perintah_id' => ['required', Rule::exists('surat_perintah', 'id')
                ->where('status', SuratPerintah::STATUS_DITERIMA_PPTK)
                ->where('sumber_npd', true)
                ->where('jenis_permintaan', SuratPerintah::JENIS_UANG_HARIAN)],
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

            // 'nomor_sp' dan 'tanggal_sp' sengaja TIDAK divalidasi di sini:
            // keduanya diambil langsung dari Surat Perintah yang dipilih
            // (lihat NpdPdController), supaya tidak bisa dikarang lewat form.
            'uraian_sp' => ['required', 'string'],
            'berangkat_dari' => ['required', 'string', 'max:150'],
            'tujuan' => ['required', 'string', 'max:150'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_pulang' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'keterangan_lampiran' => ['nullable', 'string'],
            // Penanda dari formulir: 'otomatis' berarti isian di atas hanya
            // pratinjau dan TIDAK disimpan - uraiannya dirangkai ulang saat
            // mencetak, mengikuti data terakhir. Formulir/permintaan lama yang
            // tidak mengirim penanda ini dianggap 'manual', supaya teks yang
            // sudah dikirim tetap tersimpan seperti dulu.
            'keterangan_mode' => ['nullable', Rule::in(['otomatis', 'manual'])],

            'penerima_index' => ['required', 'integer', 'min:0'],

            // "Pilih Penerima" (App\Support\PenerimaPd): koordinator, semua
            // anggota, atau PPTK. Hanya mengalihkan tujuan transfer pada
            // Lampiran; tim tetap utuh untuk Daftar Pembayaran dan SPD, jadi
            // penerima_index di atas tetap wajib. `pptk_penerima` = cara lama
            // (kotak centang), masih diterima bila mode_penerima tidak dikirim.
            'mode_penerima' => ['nullable', Rule::in(array_keys(PenerimaPd::PILIHAN))],
            'pptk_penerima' => ['nullable', 'boolean'],
            'pptk_rekening' => ['nullable', 'string', 'max:100'],

            'tim' => ['required', 'array', 'min:1'],
            'tim.*.pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            // "Input Manual": nama diketik sendiri, tidak ditautkan ke Data
            // Pegawai dan tidak masuk Dashboard Perjalanan Dinas.
            'tim.*.input_manual' => ['nullable', 'boolean'],
            'tim.*.nama' => ['required', 'string', 'max:255'],
            'tim.*.jabatan' => ['nullable', 'string', 'max:255'],
            'tim.*.nip' => ['nullable', 'string', 'max:30'],
            'tim.*.rekening' => ['nullable', 'string', 'max:100'],
            // Total Nominal BBM: yang diketik sekarang. Liter tidak lagi diketik -
            // dihitung dari nominal dibagi tarif, jadi tarifnya wajib ada.
            'tim.*.bbm_nominal' => ['nullable', 'numeric', 'min:0', function (string $atribut, mixed $nilai, \Closure $gagal) {
                $tarif = $this->input(str_replace('bbm_nominal', 'bbm_tarif', $atribut));

                if ((float) $nilai > 0 && (float) $tarif <= 0) {
                    $gagal('Tarif BBM per liter wajib diisi bila Total Nominal BBM diisi.');
                }
            }],
            // Liter hanya diterima untuk permintaan cara lama (tanpa bbm_nominal).
            'tim.*.bbm_liter' => ['nullable', 'numeric', 'min:0'],
            'tim.*.bbm_tarif' => ['nullable', 'numeric', 'min:0'],
            // "Tambah Jenis BBM": BBM dengan tarif per liter yang berbeda.
            // Aturannya sama dengan jenis pertama; baris yang nominalnya
            // dikosongkan tidak disimpan (NpdPerjalananHitung::bbmLain()).
            'tim.*.bbm_lain' => ['nullable', 'array', 'max:10'],
            'tim.*.bbm_lain.*.nominal' => ['nullable', 'numeric', 'min:0', function (string $atribut, mixed $nilai, \Closure $gagal) {
                $tarif = $this->input(preg_replace('/nominal$/', 'tarif', $atribut));

                if ((float) $nilai > 0 && (float) $tarif <= 0) {
                    $gagal('Tarif BBM per liter wajib diisi bila Total Nominal BBM diisi.');
                }
            }],
            'tim.*.bbm_lain.*.tarif' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tol' => ['nullable', 'numeric', 'min:0'],
            'tim.*.tiket' => ['nullable', 'numeric', 'min:0'],
            'tim.*.representatif' => ['nullable', 'numeric', 'min:0'],

            // Paket boleh KOSONG (cluster tidak dipilih, semuanya 0): anggota
            // itu ikut ditugaskan dalam SP tetapi tidak dibayar. Begitu
            // cluster dipilih, wilayah/hari/tarifnya wajib seperti biasa.
            // Paket kosong tidak disimpan - lihat NpdPdController::rapikanTim().
            'tim.*.paket' => ['nullable', 'array'],
            'tim.*.paket.*.cluster' => ['nullable', Rule::in(ClusterUh::KODE)],
            'tim.*.paket.*.wilayah' => ['nullable', 'required_with:tim.*.paket.*.cluster', 'string', 'max:100'],
            'tim.*.paket.*.lama_hari' => ['nullable', 'required_with:tim.*.paket.*.cluster', 'integer', 'min:0'],
            'tim.*.paket.*.tarif_uh' => ['nullable', 'required_with:tim.*.paket.*.cluster', 'numeric', 'min:0'],
            'tim.*.paket.*.malam' => ['nullable', 'integer', 'min:0'],
            'tim.*.paket.*.tarif_akom' => ['nullable', 'numeric', 'min:0'],
            // Unggah SPJ - OPSIONAL, lihat App\Services\SpjBerkasService.
            // Aturannya diambil dari service supaya kelima jenis NPD dan
            // pintu unggah di Inventarisasi SPJ tidak bisa berbeda-beda.
            ...SpjBerkasService::aturan(),
        ];
    }

    /**
     * Tarif Uang Harian cluster TIDAK boleh datang dari peramban.
     *
     * Di formulir, isian tarif untuk cluster jarak dan Dalam Kota memang
     * dibuat read-only dan diisi otomatis dari tabel cluster - tetapi
     * read-only hanya menahan jari, bukan payload. Tanpa pemeriksaan ini
     * siapa pun yang bisa mengirim POST dapat menaikkan tarif sesukanya, dan
     * angka itu langsung menjadi nominal NPD yang dicairkan.
     *
     * Yang ditolak, bukan ditimpa: selisih tarif berarti payload dikarang
     * ATAU standar biaya berubah sementara formulirnya masih terbuka. Dua
     * duanya harus dilihat petugas, karena menimpa angka tanpa bilang-bilang
     * pada dokumen yang akan ditandatangani lebih berbahaya daripada gagal
     * simpan. Luar Provinsi (LP) dan Manual (MN) dikecualikan - tarifnya
     * memang diketik manual per NPD (lihat ClusterUh::KODE_MANUAL).
     *
     * Tarif akomodasi juga tetap manual: menginap di luar daftar standar
     * biaya memang terjadi (lihat juga config/kebutuhan.php).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tim = $this->input('tim');

            if (! is_array($tim)) {
                return;
            }

            // Sengaja TANPA saringan `aktif`: cluster yang kelak dinonaktifkan
            // tetap harus bisa disunting pada NPD lama yang memakainya.
            $tarifCluster = ClusterUh::query()->pluck('tarif', 'kode');

            foreach ($tim as $iTim => $anggota) {
                foreach ((array) ($anggota['paket'] ?? []) as $iPaket => $paket) {
                    $kode = $paket['cluster'] ?? null;

                    // Paket tanpa cluster hanya boleh bernilai 0 (anggota
                    // tidak dibayar). Kalau ada nilainya, cluster-nya yang
                    // lupa dipilih - jangan sampai angkanya hilang diam-diam.
                    if (blank($kode) && NpdPerjalananHitung::subUh((array) $paket) + NpdPerjalananHitung::subAkom((array) $paket) > 0) {
                        $validator->errors()->add(
                            "tim.{$iTim}.paket.{$iPaket}.cluster",
                            'Cluster wajib dipilih bila Uang Harian atau Akomodasi paket ini diisi.',
                        );
                    }

                    // Kode kosong, kode asing, dan cluster isian manual (Luar
                    // Provinsi, Manual) ditangani di tempat lain: dua yang
                    // pertama oleh aturan Rule::in di rules(), cluster manual
                    // karena tarifnya memang bebas.
                    if (! is_string($kode) || ! in_array($kode, ClusterUh::KODE, true)) {
                        continue;
                    }

                    if (in_array($kode, ClusterUh::KODE_MANUAL, true)) {
                        continue;
                    }

                    $isian = "tim.{$iTim}.paket.{$iPaket}.tarif_uh";

                    if (! isset($tarifCluster[$kode])) {
                        $validator->errors()->add($isian, "Cluster {$kode} belum ada di tabel cluster uang harian, jadi tarifnya tidak dapat diperiksa. Jalankan ClusterUhSeeder lebih dulu.");

                        continue;
                    }

                    $seharusnya = round((float) $tarifCluster[$kode], 2);
                    $dikirim = round((float) ($paket['tarif_uh'] ?? 0), 2);

                    if ($dikirim !== $seharusnya) {
                        $validator->errors()->add($isian, sprintf(
                            'Tarif Uang Harian cluster %s sudah ditetapkan Rp%s per hari, bukan Rp%s. Muat ulang formulirnya bila standar biayanya baru berubah.',
                            $kode,
                            number_format($seharusnya, 0, ',', '.'),
                            number_format($dikirim, 0, ',', '.'),
                        ));
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'master_anggaran_id' => 'Sumber Dana',
            'surat_perintah_id' => 'Surat Perintah',
            'jenis_panjar' => 'Jenis NPD',
            'tanggal_npd' => 'Tanggal NPD',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'sisa_anggaran_manual' => 'Sisa Anggaran (cetak PDF)',
            'uraian_sp' => 'Uraian/Maksud Perjalanan',
            'berangkat_dari' => 'Berangkat Dari',
            'tujuan' => 'Tujuan',
            'tanggal_berangkat' => 'Tanggal Berangkat',
            'tanggal_pulang' => 'Tanggal Pulang',
            'penerima_index' => 'Penerima Dana',
            'mode_penerima' => 'Pilih Penerima',
            'tim.*.nama' => 'Nama Anggota',
            'tim.*.paket' => 'Paket Tujuan',
            'tim.*.paket.*.cluster' => 'Cluster',
            'tim.*.paket.*.wilayah' => 'Wilayah Tujuan',
            'tim.*.paket.*.lama_hari' => 'Lama Hari',
            'tim.*.paket.*.tarif_uh' => 'Tarif Uang Harian',
            ...SpjBerkasService::labelIsian(),
        ];
    }
}
