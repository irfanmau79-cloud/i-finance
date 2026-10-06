<?php

namespace App\Services;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\NpdNarasumber;
use App\Models\NpdPenerima;
use App\Models\NpdPenerimaPph;
use App\Models\NpdPeserta;
use App\Models\NpdRevisi;
use App\Models\NpdTim;
use App\Models\NpdTimPaket;
use App\Models\SuratPerintah;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Jejak penyuntingan NPD oleh BPP dan Verifikator.
 *
 * Tiga hal yang dikerjakan di sini, dan ketiganya berangkat dari POTRET -
 * salinan isi NPD beserta seluruh rinciannya pada satu saat:
 *
 * 1. potret() sebelum dan sesudah suntingan dibandingkan (selisih()) menjadi
 *    daftar "bagian apa, semula apa, menjadi apa" yang tampil di halaman
 *    detail NPD.
 * 2. Potret sebelum suntingan PERTAMA disimpan utuh. Itulah draft awal
 *    buatan PPTK, dan dari situlah "Cetak Draft NPD" dirender (draftAwal()).
 * 3. Suntingan selama NPD masih di meja PPTK TIDAK dicatat: itu masih
 *    pekerjaan menyusun draft, belum ada yang dikoreksi.
 */
class NpdRevisiService
{
    /** Status yang suntingannya belum dihitung sebagai koreksi. */
    private const STATUS_DRAFT_PPTK = 'Draft NPD - PPTK';

    /** Kolom NPD yang dibandingkan: kunci => [label, jenis tampilan]. */
    private const KOLOM_NPD = [
        'master_anggaran_id' => ['Sumber Dana', 'anggaran'],
        'surat_perintah_id' => ['Surat Perintah', 'sp'],
        'npd_referensi_id' => ['Referensi NPD Kontribusi', 'npd'],
        'tanggal_npd' => ['Tanggal NPD', 'tanggal'],
        'bulan' => ['Bulan', 'teks'],
        'tahun' => ['Tahun', 'teks'],
        'jenis_panjar' => ['Jenis NPD', 'teks'],
        'mode_kd' => ['Mode Kontribusi Diklat', 'teks'],
        'nominal' => ['Nominal NPD', 'uang'],
        'sisa_anggaran_manual' => ['Sisa Anggaran di PDF', 'sisa'],
    ];

    /** Isi detail_json yang dikenal. Kunci lain tetap dibandingkan dengan label turunan namanya. */
    private const KOLOM_DETAIL = [
        'nomor_sp' => ['Nomor SP', 'teks'],
        'tanggal_sp' => ['Tanggal SP', 'tanggal'],
        'uraian_sp' => ['Uraian SP', 'teks'],
        'berangkat_dari' => ['Berangkat Dari', 'teks'],
        'tujuan' => ['Tujuan', 'teks'],
        'tanggal_berangkat' => ['Tanggal Berangkat', 'tanggal'],
        'tanggal_pulang' => ['Tanggal Pulang', 'tanggal'],
        'keterangan_lampiran' => ['Uraian Lampiran', 'teks'],
        'pptk_penerima' => ['PPTK Sebagai Penerima', 'ya'],
        'pptk_rekening' => ['Rekening PPTK', 'teks'],
        'uraian_kegiatan' => ['Uraian Kegiatan', 'teks'],
        'tanggal_mulai' => ['Tanggal Mulai', 'tanggal'],
        'tanggal_selesai' => ['Tanggal Selesai', 'tanggal'],
        'nama_pelatihan' => ['Nama Pelatihan', 'teks'],
        'penerima_index' => ['Penerima Dana (urutan ke-)', 'urutan'],
        'penerima_transfer' => ['Tujuan Transfer', 'transfer'],
        'ppn' => ['PPN', 'uang'],
        'pph_jenis' => ['Jenis PPh', 'teks'],
        'pph_nilai' => ['PPh', 'uang'],
        'biaya_lain' => ['Biaya Lain', 'uang'],
    ];

    /** Rincian per jenis NPD: kunci potret => [label baris, kolom => [label, jenis]]. */
    private const RINCIAN = [
        'penerima' => ['Penerima', [
            'nama' => ['Nama', 'teks'],
            'rekening' => ['Rekening', 'teks'],
            'bruto' => ['Bruto', 'uang'],
            'ppn' => ['PPN', 'uang'],
            'biaya_ku_rtgs' => ['Biaya KU/RTGS', 'uang'],
            'keterangan' => ['Keterangan', 'teks'],
        ]],
        'tim' => ['Anggota Tim', [
            'nama' => ['Nama', 'teks'],
            'jabatan' => ['Jabatan', 'teks'],
            'nip' => ['NIP', 'teks'],
            'rekening' => ['Rekening', 'teks'],
            'bbm_liter' => ['BBM (liter)', 'liter'],
            'bbm_tarif' => ['Tarif BBM', 'uang'],
            'tol' => ['e-Toll', 'uang'],
            'tiket' => ['Tiket', 'uang'],
            'representatif' => ['Uang Representatif', 'uang'],
            'is_penerima' => ['Penerima Dana', 'ya'],
        ]],
        'narasumber' => ['Narasumber', [
            'nama' => ['Nama', 'teks'],
            'jabatan' => ['Jabatan', 'teks'],
            'rekening' => ['Rekening', 'teks'],
            'jumlah_jp' => ['Jumlah JP', 'angka'],
            'tarif_jp' => ['Tarif/JP', 'uang'],
            'transport' => ['Transport', 'uang'],
            'pph21' => ['PPh 21', 'uang'],
            'uraian' => ['Uraian', 'teks'],
        ]],
        'peserta' => ['Peserta', [
            'nama' => ['Nama', 'teks'],
            'pangkat' => ['Pangkat', 'teks'],
            'nip' => ['NIP', 'teks'],
            'rekening' => ['Rekening', 'teks'],
            'volume_kontribusi' => ['Volume Kontribusi', 'angka'],
            'tarif_kontribusi' => ['Tarif Kontribusi', 'uang'],
            'volume_mooc' => ['Volume MOOC', 'angka'],
            'tarif_mooc' => ['Tarif MOOC', 'uang'],
            'hari_uh' => ['Hari Uang Harian', 'angka'],
            'tarif_uh' => ['Tarif Uang Harian', 'uang'],
            'volume_akomodasi' => ['Volume Akomodasi', 'angka'],
            'tarif_akomodasi' => ['Tarif Akomodasi', 'uang'],
            'hari_saku' => ['Hari Uang Saku', 'angka'],
            'tarif_saku' => ['Tarif Uang Saku', 'uang'],
            'transport' => ['Transport', 'uang'],
        ]],
    ];

    /**
     * Isi NPD beserta seluruh rinciannya saat ini, dibaca ulang dari basis
     * data. Nilainya atribut MENTAH (bukan hasil cast), supaya bisa
     * dihidupkan kembali menjadi model lewat hidupkan() tanpa selisih.
     *
     * @return array<string, mixed>
     */
    public function potret(Npd $npd): array
    {
        $urut = fn ($query) => $query->orderBy('id');

        $npd = Npd::withTrashed()->with([
            'penerima' => $urut,
            'penerima.pphList' => $urut,
            'tim' => $urut,
            'tim.paket' => $urut,
            'narasumber' => $urut,
            'peserta' => $urut,
        ])->findOrFail($npd->id);

        return [
            'npd' => $npd->getAttributes(),
            'penerima' => $npd->penerima->map(fn (NpdPenerima $p) => $p->getAttributes() + [
                'pph_list' => $p->pphList->map(fn (NpdPenerimaPph $pph) => $pph->getAttributes())->all(),
            ])->all(),
            'tim' => $npd->tim->map(fn (NpdTim $t) => $t->getAttributes() + [
                'paket' => $t->paket->map(fn (NpdTimPaket $paket) => $paket->getAttributes())->all(),
            ])->all(),
            'narasumber' => $npd->narasumber->map(fn (NpdNarasumber $n) => $n->getAttributes())->all(),
            'peserta' => $npd->peserta->map(fn (NpdPeserta $p) => $p->getAttributes())->all(),
        ];
    }

    /**
     * Potret menjadi model Npd lengkap dengan relasi rinciannya, TANPA
     * menyentuh basis data. Dipakai mencetak draft awal lewat pembangun PDF
     * yang sama dengan dokumen aslinya; relasi lain (masterAnggaran, dst)
     * dimuat belakangan oleh pembangun itu sendiri lewat loadMissing().
     */
    public function hidupkan(array $potret): Npd
    {
        $npd = $this->model(Npd::class, $potret['npd'] ?? []);

        $npd->setRelation('penerima', new Collection(array_map(function (array $baris) {
            $penerima = $this->model(NpdPenerima::class, $baris, ['pph_list']);
            $penerima->setRelation('pphList', new Collection(array_map(
                fn (array $pph) => $this->model(NpdPenerimaPph::class, $pph),
                $baris['pph_list'] ?? []
            )));

            return $penerima;
        }, $potret['penerima'] ?? [])));

        $npd->setRelation('tim', new Collection(array_map(function (array $baris) {
            $anggota = $this->model(NpdTim::class, $baris, ['paket']);
            $anggota->setRelation('paket', new Collection(array_map(
                fn (array $paket) => $this->model(NpdTimPaket::class, $paket),
                $baris['paket'] ?? []
            )));

            return $anggota;
        }, $potret['tim'] ?? [])));

        $npd->setRelation('narasumber', new Collection(array_map(
            fn (array $baris) => $this->model(NpdNarasumber::class, $baris),
            $potret['narasumber'] ?? []
        )));

        $npd->setRelation('peserta', new Collection(array_map(
            fn (array $baris) => $this->model(NpdPeserta::class, $baris),
            $potret['peserta'] ?? []
        )));

        return $npd;
    }

    /** Draft awal buatan PPTK, atau NULL bila NPD ini belum pernah disunting BPP/Verifikator. */
    public function draftAwal(Npd $npd): ?Npd
    {
        $pertama = NpdRevisi::query()->where('npd_id', $npd->id)->orderBy('id')->first();

        return $pertama ? $this->hidupkan($pertama->potret_sebelum) : null;
    }

    /**
     * Catat satu kali penyimpanan formulir Edit NPD: histori status seperti
     * biasa, ditambah baris npd_revisi bila penyuntingnya bukan lagi PPTK di
     * tahap draft dan memang ada yang berubah. Dipanggil DI DALAM transaksi
     * penyimpanan, sesudah seluruh rincian baru tersimpan.
     *
     * @param  array<string, mixed>  $sebelum  hasil potret() sebelum NPD diubah
     */
    public function catatEdit(Npd $npd, User $user, array $sebelum, string $catatan): void
    {
        $perubahan = $npd->status === self::STATUS_DRAFT_PPTK
            ? []
            : $this->selisih($sebelum, $this->potret($npd));

        if ($perubahan !== []) {
            $catatan .= ' '.count($perubahan).' perubahan tercatat di Histori Perubahan Data.';
        }

        $npd->catatHistoriStatus($user, 'edit', $npd->status, $npd->status, $catatan);

        if ($perubahan !== []) {
            NpdRevisi::create([
                'npd_id' => $npd->id,
                'user_id' => $user->id,
                'peran' => $user->role,
                'status_saat' => $npd->status,
                'potret_sebelum' => $sebelum,
                'perubahan' => $perubahan,
            ]);
        }
    }

    /**
     * Daftar bagian yang berbeda di antara dua potret.
     *
     * Yang dibandingkan adalah TAMPILAN nilainya (rupiah, tanggal, nama mata
     * anggaran), bukan nilai mentahnya - "1000" dan "1000.00" bukan
     * perubahan, dan yang dibaca petugas memang bentuk tampilannya.
     *
     * @return array<int, array{bagian: string, lama: string, baru: string}>
     */
    public function selisih(array $sebelum, array $sesudah): array
    {
        $hasil = [];

        foreach (self::KOLOM_NPD as $kunci => [$label, $jenis]) {
            $this->bandingkan($hasil, $label,
                $this->tampil($sebelum['npd'][$kunci] ?? null, $jenis),
                $this->tampil($sesudah['npd'][$kunci] ?? null, $jenis));
        }

        $detailLama = $this->detail($sebelum);
        $detailBaru = $this->detail($sesudah);

        foreach (array_unique([...array_keys($detailLama), ...array_keys($detailBaru)]) as $kunci) {
            [$label, $jenis] = self::KOLOM_DETAIL[$kunci] ?? [Str::headline((string) $kunci), 'bebas'];

            $this->bandingkan($hasil, $label,
                $this->tampil($detailLama[$kunci] ?? null, $jenis),
                $this->tampil($detailBaru[$kunci] ?? null, $jenis));
        }

        foreach (array_keys(self::RINCIAN) as $kunci) {
            array_push($hasil, ...$this->selisihRincian($kunci, $sebelum[$kunci] ?? [], $sesudah[$kunci] ?? []));
        }

        return $hasil;
    }

    /**
     * Baris rincian dicocokkan lewat NAMA lebih dulu - menghapus penerima
     * kedua tidak boleh terbaca sebagai "penerima kedua berubah jadi yang
     * ketiga". Sisanya dipasangkan menurut urutan (nama yang diganti), dan
     * yang tetap tidak berpasangan dilaporkan dihapus/ditambahkan.
     *
     * @return array<int, array{bagian: string, lama: string, baru: string}>
     */
    private function selisihRincian(string $kunci, array $lama, array $baru): array
    {
        [$labelBaris] = self::RINCIAN[$kunci];
        $lama = array_values($lama);
        $baru = array_values($baru);

        $antrean = [];
        foreach ($baru as $i => $baris) {
            $antrean[$this->kunciNama($baris)][] = $i;
        }

        $pasangan = [];
        $sisaLama = [];

        foreach ($lama as $i => $baris) {
            $nama = $this->kunciNama($baris);

            if (! empty($antrean[$nama])) {
                $pasangan[$i] = array_shift($antrean[$nama]);
            } else {
                $sisaLama[] = $i;
            }
        }

        $sisaBaru = array_values(array_diff(array_keys($baru), $pasangan));

        while ($sisaLama !== [] && $sisaBaru !== []) {
            $pasangan[array_shift($sisaLama)] = array_shift($sisaBaru);
        }

        ksort($pasangan);
        $hasil = [];

        foreach ($pasangan as $iLama => $iBaru) {
            $rataLama = $this->rataBaris($kunci, $lama[$iLama]);
            $rataBaru = $this->rataBaris($kunci, $baru[$iBaru]);
            $nama = trim((string) ($lama[$iLama]['nama'] ?? '')) ?: 'ke-'.($iLama + 1);

            foreach (array_unique([...array_keys($rataLama), ...array_keys($rataBaru)]) as $kolom) {
                $this->bandingkan($hasil, "{$labelBaris} {$nama} - {$kolom}", $rataLama[$kolom] ?? '-', $rataBaru[$kolom] ?? '-');
            }
        }

        foreach ($sisaLama as $i) {
            $hasil[] = [
                'bagian' => $labelBaris.' '.(trim((string) ($lama[$i]['nama'] ?? '')) ?: 'ke-'.($i + 1)),
                'lama' => $this->ringkasBaris($kunci, $lama[$i]),
                'baru' => 'Dihapus',
            ];
        }

        foreach ($sisaBaru as $i) {
            $hasil[] = [
                'bagian' => $labelBaris.' '.(trim((string) ($baru[$i]['nama'] ?? '')) ?: 'ke-'.($i + 1)),
                'lama' => '-',
                'baru' => 'Ditambahkan: '.$this->ringkasBaris($kunci, $baru[$i]),
            ];
        }

        return $hasil;
    }

    /**
     * Satu baris rincian sebagai peta label kolom => tampilan nilai. PPh
     * (milik penerima) dan paket perjalanan (milik anggota tim) ikut
     * diratakan ke sini sebagai kolom tersendiri.
     *
     * @return array<string, string>
     */
    private function rataBaris(string $kunci, array $baris): array
    {
        $rata = [];

        foreach (self::RINCIAN[$kunci][1] as $kolom => [$label, $jenis]) {
            $rata[$label] = $this->tampil($baris[$kolom] ?? null, $jenis);
        }

        if ($kunci === 'penerima') {
            $pph = array_map(
                fn (array $p) => ($p['jenis'] ?? 'PPh').': '.$this->tampil($p['nilai'] ?? 0, 'uang'),
                $baris['pph_list'] ?? []
            );
            $rata['PPh'] = $pph === [] ? '-' : implode('; ', $pph);
        }

        if ($kunci === 'tim') {
            // Nominal BBM yang BERLAKU, apa pun cara pengisiannya - supaya NPD
            // lama (liter x tarif) yang dibuka lalu disimpan lewat formulir
            // baru tidak terbaca "berubah" padahal nominalnya sama.
            $rata['Nominal BBM'] = $this->tampil(\App\Helpers\NpdPerjalananHitung::bbm($baris), 'uang');

            foreach (array_values($baris['paket'] ?? []) as $i => $paket) {
                $rata['Paket '.($i + 1)] = sprintf(
                    '%s %s, %s hari x %s, %s malam x %s',
                    $paket['cluster'] ?? '',
                    $paket['wilayah'] ?? '',
                    $this->tampil($paket['lama_hari'] ?? 0, 'angka'),
                    $this->tampil($paket['tarif_uh'] ?? 0, 'uang'),
                    $this->tampil($paket['malam'] ?? 0, 'angka'),
                    $this->tampil($paket['tarif_akom'] ?? 0, 'uang'),
                );
            }
        }

        return $rata;
    }

    /** Isi satu baris dalam satu kalimat, tanpa kolom yang kosong atau nol. */
    private function ringkasBaris(string $kunci, array $baris): string
    {
        $bagian = [];

        foreach ($this->rataBaris($kunci, $baris) as $label => $nilai) {
            if (! in_array($nilai, ['-', '0', 'Rp 0,00', 'Tidak'], true)) {
                $bagian[] = "{$label}: {$nilai}";
            }
        }

        return implode('; ', $bagian);
    }

    private function kunciNama(array $baris): string
    {
        return mb_strtolower(trim((string) ($baris['nama'] ?? '')));
    }

    /** @param  array<int, array{bagian: string, lama: string, baru: string}>  $hasil */
    private function bandingkan(array &$hasil, string $bagian, string $lama, string $baru): void
    {
        if ($lama !== $baru) {
            $hasil[] = ['bagian' => $bagian, 'lama' => $lama, 'baru' => $baru];
        }
    }

    /** @return array<string, mixed> */
    private function detail(array $potret): array
    {
        $detail = $potret['npd']['detail_json'] ?? null;

        if (is_string($detail)) {
            $detail = json_decode($detail, true);
        }

        return is_array($detail) ? $detail : [];
    }

    private function tampil(mixed $nilai, string $jenis): string
    {
        $kosong = $nilai === null || $nilai === '' || $nilai === [];

        return match ($jenis) {
            'uang' => 'Rp '.number_format((float) $nilai, 2, ',', '.'),
            'sisa' => $kosong ? 'Angka sistem' : 'Rp '.number_format((float) $nilai, 2, ',', '.'),
            'angka' => rtrim(rtrim(number_format((float) $nilai, 10, ',', ''), '0'), ',') ?: '0',
            // Liter kini diturunkan dari nominal : tarif; dua desimal cukup
            // untuk dibaca dan menghindari selisih semu di digit ke-sekian.
            'liter' => rtrim(rtrim(number_format((float) $nilai, 2, ',', ''), '0'), ',') ?: '0',
            'ya' => filter_var($nilai, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak',
            'urutan' => $kosong ? '-' : (string) ((int) $nilai + 1),
            'tanggal' => $kosong ? '-' : $this->tanggal((string) $nilai),
            'anggaran' => $kosong ? '-' : $this->namaAnggaran((int) $nilai),
            'sp' => $kosong ? '-' : (SuratPerintah::query()->whereKey($nilai)->value('nomor_sp') ?: '#'.$nilai),
            'npd' => $kosong ? '-' : (Npd::withTrashed()->whereKey($nilai)->value('nomor_lengkap') ?: 'NPD #'.$nilai),
            'transfer' => $kosong ? '-' : implode('; ', array_map(
                fn ($p) => trim(($p['nama'] ?? '').' '.($p['rekening'] ?? '')).' '.$this->tampil($p['nominal'] ?? 0, 'uang'),
                array_filter((array) $nilai, 'is_array')
            )),
            'bebas' => $kosong ? '-' : (is_bool($nilai) ? ($nilai ? 'Ya' : 'Tidak') : (is_scalar($nilai) ? trim((string) $nilai) : (string) json_encode($nilai, JSON_UNESCAPED_UNICODE))),
            default => $kosong ? '-' : trim((string) $nilai),
        };
    }

    private function tanggal(string $nilai): string
    {
        try {
            return Carbon::parse($nilai)->format('d-m-Y');
        } catch (\Throwable) {
            return $nilai;
        }
    }

    private function namaAnggaran(int $id): string
    {
        $anggaran = MasterAnggaran::with('tagging')->find($id);

        if ($anggaran === null) {
            return 'Mata anggaran #'.$id;
        }

        return trim($anggaran->sub_kegiatan_lengkap.' | '.$anggaran->rekening_lengkap
            .($anggaran->tagging?->nama ? ' | '.$anggaran->tagging->nama : ''));
    }

    /**
     * @param  class-string<Model>  $kelas
     * @param  array<int, string>  $buang  kunci anak yang bukan kolom tabelnya
     */
    private function model(string $kelas, array $atribut, array $buang = []): Model
    {
        $model = (new $kelas)->setRawAttributes(array_diff_key($atribut, array_flip($buang)), true);
        $model->exists = true;

        return $model;
    }
}
