<?php

namespace App\Http\Controllers;

use App\Exports\MasterAnggaranExport;
use App\Exports\NpdExport;
use App\Exports\PegawaiExport;
use App\Exports\PerjalananDinasExport;
use App\Exports\PerjalananDinasTemplateExport;
use App\Exports\RakBulananExport;
use App\Exports\SpjPerjalananDinasExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmUpGuExport;
use App\Exports\TunjanganKeluargaExport;
use App\Exports\VendorExport;
use App\Helpers\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ManajemenDataController extends Controller
{
    /** Daftar export yang tersedia di Manajemen Data. Kunci dipakai di URL & di-whitelist juga pada route. */
    private const EXPORTS = [
        'master-anggaran' => ['label' => 'Data Pagu Anggaran', 'class' => MasterAnggaranExport::class],
        'rak-bulanan' => ['label' => 'Data Rencana Anggaran Kas (RAK)', 'class' => RakBulananExport::class],
        'npd' => ['label' => 'Data Nota Pencairan Dana (NPD)', 'class' => NpdExport::class],
        'perjalanan-dinas' => ['label' => 'Data Perjalanan Dinas', 'class' => PerjalananDinasExport::class],
        'spj-perjalanan-dinas' => ['label' => 'Data SPJ Perjalanan Dinas', 'class' => SpjPerjalananDinasExport::class],
        'spm-up-gu' => ['label' => 'Data Surat Perintah Membayar (SPM) UP/GU/TU', 'class' => SpmUpGuExport::class],
        'spm-ls' => ['label' => 'Data Surat Perintah Membayar (SPM) LS', 'class' => SpmLsExport::class],
        'pegawai' => ['label' => 'Data Pegawai', 'class' => PegawaiExport::class],
        'vendor' => ['label' => 'Data Vendor', 'class' => VendorExport::class],
        'tunjangan-keluarga' => ['label' => 'Data Tunjangan Keluarga', 'class' => TunjanganKeluargaExport::class],
    ];

    /**
     * Susunan 10 tipe data yang ditampilkan di halaman Manajemen Data - satu
     * kartu per tipe, masing-masing dengan tombol Import + Export (+
     * Template kalau tersedia). 'import_template' sengaja null untuk RAK
     * Bulanan - hasil Export-nya sendiri SUDAH berperan sebagai template
     * (baris Sub Kegiatan/Kode Rekening terisi, kolom bulan kosong siap
     * diisi), lihat RakBulananExport.
     *
     * Perjalanan Dinas dan SPJ Perjalanan Dinas TIDAK punya tabel sendiri -
     * keduanya cuma tampilan terkomputasi dari tabel npd (+ npd_tim untuk
     * Perjalanan Dinas) yang sama dipakai Dashboard Perjalanan Dinas dan
     * Dashboard SPJ Perjalanan Dinas. Karena itu import-nya diarahkan ke
     * Import NPD Historis yang sama dipakai kartu Data NPD - lihat
     * 'import_note' yang ditampilkan di kartu untuk menjelaskan ini ke user.
     * Keduanya 'import_template' => null: tidak ada template khusus untuk
     * mereka, dan menunjuk ke template NPD membuat tombol Template mengunduh
     * berkas bernama template NPD - menyesatkan dari sisi pengguna.
     */
    private const TIPE_DATA = [
        'pagu' => [
            'label' => 'Data Pagu Anggaran',
            'export_jenis' => 'master-anggaran',
            'import_create' => ['manajemen-data.import.master-anggaran.create', null],
            'import_template' => ['manajemen-data.import.master-anggaran.template', null],
        ],
        'rak' => [
            'label' => 'Data Rencana Anggaran Kas (RAK)',
            'export_jenis' => 'rak-bulanan',
            'import_create' => ['manajemen-data.import.rak-bulanan.create', null],
            'import_template' => null,
        ],
        'npd' => [
            'label' => 'Data Nota Pencairan Dana (NPD)',
            'export_jenis' => 'npd',
            'import_create' => ['manajemen-data.import.npd-historis.create', null],
            'import_template' => ['manajemen-data.import.npd-historis.template', null],
        ],
        'perjalanan-dinas' => [
            'label' => 'Data Perjalanan Dinas',
            'export_jenis' => 'perjalanan-dinas',
            // Tidak punya import: datanya dihitung dari NPD, bukan tabel sendiri.
            'import_create' => null,
            'import_template' => ['manajemen-data.template.perjalanan-dinas', null],
            'import_note' => 'Templatenya berupa formulir rekap per pegawai - Nama, NIP, dan Unit Kerja sudah terisi, tinggal melengkapi angka tiap bulan.',
        ],
        'spj-perjalanan-dinas' => [
            'label' => 'Data SPJ Perjalanan Dinas',
            'export_jenis' => 'spj-perjalanan-dinas',
            // Sama seperti Perjalanan Dinas: tidak punya import maupun template.
            'import_create' => null,
            'import_template' => null,
            'import_note' => 'Datanya berasal dari NPD dengan rekening Belanja Perjalanan Dinas.',
        ],
        'spm-up-gu' => [
            'label' => 'Data Surat Perintah Membayar (SPM) UP/GU/TU',
            'export_jenis' => 'spm-up-gu',
            'import_create' => ['manajemen-data.import.spm.create', 'spm-up-gu'],
            'import_template' => ['manajemen-data.import.spm.template', 'spm-up-gu'],
        ],
        'spm-ls' => [
            'label' => 'Data Surat Perintah Membayar (SPM) LS',
            'export_jenis' => 'spm-ls',
            'import_create' => ['manajemen-data.import.spm.create', 'spm-ls'],
            'import_template' => ['manajemen-data.import.spm.template', 'spm-ls'],
        ],
        'pegawai' => [
            'label' => 'Data Pegawai',
            'export_jenis' => 'pegawai',
            'import_create' => ['manajemen-data.import.pegawai.create', null],
            'import_template' => ['manajemen-data.import.pegawai.template', null],
        ],
        'vendor' => [
            'label' => 'Data Vendor',
            'export_jenis' => 'vendor',
            'import_create' => ['manajemen-data.import.vendor.create', null],
            'import_template' => ['manajemen-data.import.vendor.template', null],
        ],
        'tunjangan-keluarga' => [
            'label' => 'Data Tunjangan Keluarga',
            'export_jenis' => 'tunjangan-keluarga',
            'import_create' => ['tunjangan.import.create', null],
            'import_template' => ['tunjangan.import.template', null],
        ],
        // Satu-satunya kartu tanpa Export: berkasnya datang dari SIPD dan
        // dipakai apa adanya, jadi tidak ada bentuk unduhan yang berguna.
        // 'import_template' juga null - templatenya berkas SIPD itu sendiri,
        // bukan sesuatu yang dibuat sistem ini.
        'gaji-tunjangan' => [
            'label' => 'Data Gaji & Tunjangan',
            'export_jenis' => null,
            'import_create' => ['gaji-tunjangan.import.create', null],
            'import_template' => null,
            'import_note' => 'Unggah berkas Template SIPD apa adanya. Jenis penghasilan, bulan, dan tahun dipilih di formulir import.',
        ],
    ];

    /**
     * Kata kunci konfirmasi ketik-ulang untuk Reset Data (hapus massal
     * permanen) - jenis di sini SENGAJA sama persis dengan kunci
     * TIPE_DATA/$tipeData di view, minus 'perjalanan-dinas' dan
     * 'spj-perjalanan-dinas' yang tidak punya tabel sendiri (murni
     * tampilan terkomputasi dari data NPD - lihat dokumentasi TIPE_DATA).
     */
    private const RESET_KEYWORD = [
        'pagu' => 'PAGU',
        'rak' => 'RAK',
        'npd' => 'NPD',
        'spm-up-gu' => 'SPM UP/GU',
        'spm-ls' => 'SPM LS',
        'pegawai' => 'PEGAWAI',
        'vendor' => 'VENDOR',
        'tunjangan-keluarga' => 'TUNJANGAN KELUARGA',
    ];

    public function index()
    {
        return view('manajemen-data.index', [
            'tipeData' => self::TIPE_DATA,
            'tahunSekarang' => (int) config('anggaran.tahun_aktif'),
            'resetKeyword' => self::RESET_KEYWORD,
        ]);
    }

    /**
     * Formulir rekap Perjalanan Dinas per pegawai: identitas pegawai aktif
     * sudah terisi, lima kolom per bulan siap diisi tangan, dan lima kolom
     * Tahunan berisi rumus. Tidak ada pasangan import-nya - Data Perjalanan
     * Dinas dihitung dari NPD dan npd_tim, bukan tabel tersendiri.
     */
    public function templatePerjalananDinas()
    {
        $export = new PerjalananDinasTemplateExport;
        $filename = 'template-perjalanan-dinas-'.config('anggaran.tahun_aktif').'.xlsx';

        AuditLog::catat('Unduh Template', "Jenis: Data Perjalanan Dinas, Baris: {$export->pegawai()->count()}, File: {$filename}");

        return Excel::download($export, $filename);
    }

    public function export(string $jenis, Request $request)
    {
        abort_unless(isset(self::EXPORTS[$jenis]), 404);

        $meta = self::EXPORTS[$jenis];
        // RakBulananExport butuh tahun (RAK terikat per tahun anggaran) - export lain tidak punya parameter konstruktor.
        if ($jenis === 'rak-bulanan') {
            $tahunAktif = (int) config('anggaran.tahun_aktif');
            $tahun = $request->integer('tahun', $tahunAktif);
            if ($tahun !== $tahunAktif) {
                throw ValidationException::withMessages([
                    'tahun' => "Template RAK Bulanan hanya tersedia untuk Tahun Anggaran {$tahunAktif}.",
                ]);
            }
            $export = new $meta['class']($tahun);
        } else {
            $export = new $meta['class'];
        }
        $jumlahBaris = $export->jumlahBaris();
        $filename = 'export-'.$jenis.'-'.now()->format('Ymd-His').'.xlsx';

        AuditLog::catat('Export Data', "Jenis: {$meta['label']}, Baris: {$jumlahBaris}, File: {$filename}");

        return Excel::download($export, $filename);
    }

    /**
     * Reset Data: hapus SEMUA baris tipe data ini secara permanen. Lebih
     * sensitif daripada import/export (dibatasi superadmin lewat middleware
     * route), jadi juga wajib konfirmasi ketik-ulang kata kunci persis
     * (lihat RESET_KEYWORD) sebelum benar-benar dieksekusi.
     *
     * Urutan antar tipe data PENTING kalau mau reset lebih dari satu:
     * 'pagu' (master_anggaran) direstrict FK oleh npd/spm_detail yang masih
     * memakainya, jadi harus direset PALING TERAKHIR (setelah npd & spm-ls
     * kosong). Reset 'npd'/'spm-ls' otomatis ikut menghapus data Pengembalian
     * terkait (dokumen_tipe berpasangan, tidak punya FK sungguhan sehingga
     * kalau tidak dibersihkan di sini akan jadi baris yatim). Reset 'pegawai'
     * otomatis CASCADE menghapus Tunjangan Keluarga (FK cascadeOnDelete) dan
     * bisa gagal kalau pegawai itu masih menjabat KPA/BPP/PPTK.
     */
    public function reset(string $jenis, Request $request)
    {
        abort_unless(isset(self::RESET_KEYWORD[$jenis]), 404);

        $label = self::TIPE_DATA[$jenis]['label'];
        $keyword = 'HAPUS '.self::RESET_KEYWORD[$jenis];

        $request->validate(['konfirmasi' => ['required', 'string']]);

        if (trim((string) $request->input('konfirmasi')) !== $keyword) {
            return back()->withErrors(['konfirmasi' => "Konfirmasi tidak cocok. Ketik persis \"{$keyword}\" untuk reset {$label}."]);
        }

        try {
            $jumlah = DB::transaction(fn () => $this->jalankanReset($jenis));
        } catch (QueryException $e) {
            return back()->withErrors(['konfirmasi' => "Gagal reset {$label}: masih ada data lain yang bergantung padanya. ".$this->pesanBlokir($jenis)]);
        }

        AuditLog::catat('Reset Data', "Jenis: {$label}, Baris dihapus: {$jumlah}");

        return redirect()->route('manajemen-data.index')->with('success', "{$label} berhasil direset - {$jumlah} baris dihapus permanen.");
    }

    private function jalankanReset(string $jenis): int
    {
        return match ($jenis) {
            'pagu' => $this->resetPagu(),
            'rak' => $this->hapusTabel('rak_bulanan'),
            'npd' => $this->resetNpd(),
            'spm-up-gu' => $this->resetSpm('up_gu'),
            'spm-ls' => $this->resetSpm('ls'),
            'pegawai' => $this->hapusTabel('pegawai'),
            'vendor' => $this->hapusTabel('vendor'),
            'tunjangan-keluarga' => $this->hapusTabel('tunjangan_keluarga'),
        };
    }

    private function hapusTabel(string $table): int
    {
        $jumlah = DB::table($table)->count();
        DB::table($table)->delete();

        return $jumlah;
    }

    /**
     * Reset pagu ikut menghapus seluruh riwayat VERSI pagu (DPA Murni, DPA
     * Pergeseran, ...). versi_pagu_detail memang sudah cascade lewat FK ke
     * master_anggaran, tapi header versinya tidak - kalau dibiarkan, halaman
     * Versi Pagu akan menampilkan versi bertotal miliaran yang isinya nol
     * mata anggaran.
     */
    private function resetPagu(): int
    {
        $jumlah = $this->hapusTabel('master_anggaran');
        DB::table('versi_pagu')->delete();

        return $jumlah;
    }

    /**
     * npd pakai SoftDeletes di Eloquent, tapi DB::table() query builder
     * TIDAK melalui itu - selalu physical DELETE, memicu cascade FK
     * sungguhan ke npd_penerima/npd_tim/npd_narasumber/npd_peserta/
     * npd_histori_status/arsip_spj/spj_detail (semua cascadeOnDelete).
     * Baris Pengembalian yang menunjuk NPD (dokumen_tipe='npd') tidak
     * punya FK sungguhan (kolom polymorphic biasa), jadi dibersihkan
     * manual di sini supaya tidak jadi data yatim.
     */
    private function resetNpd(): int
    {
        $jumlah = DB::table('npd')->count();

        $pengembalianIds = DB::table('pengembalian')->where('dokumen_tipe', 'npd')->pluck('id');
        DB::table('pengembalian_detail')->whereIn('pengembalian_id', $pengembalianIds)->delete();
        DB::table('pengembalian')->where('dokumen_tipe', 'npd')->delete();

        DB::table('npd')->delete();

        return $jumlah;
    }

    /** Tabel spm dipakai bersama UP/GU dan LS (dibedakan jenis_spm) - hapus per jenis, bukan truncate seluruh tabel. */
    private function resetSpm(string $jenisSpm): int
    {
        $jumlah = DB::table('spm')->where('jenis_spm', $jenisSpm)->count();

        if ($jenisSpm === 'ls') {
            // spm_detail (baris mata anggaran LS) ikut cascade otomatis lewat
            // FK spm_id cascadeOnDelete. Pengembalian (dokumen_tipe='spm_ls')
            // tidak punya FK sungguhan - dibersihkan manual seperti NPD.
            $pengembalianIds = DB::table('pengembalian')->where('dokumen_tipe', 'spm_ls')->pluck('id');
            DB::table('pengembalian_detail')->whereIn('pengembalian_id', $pengembalianIds)->delete();
            DB::table('pengembalian')->where('dokumen_tipe', 'spm_ls')->delete();
        }

        DB::table('spm')->where('jenis_spm', $jenisSpm)->delete();

        return $jumlah;
    }

    private function pesanBlokir(string $jenis): string
    {
        return match ($jenis) {
            'pagu' => 'Kemungkinan masih ada NPD atau SPM LS yang memakai mata anggaran ini - reset Data NPD dan Data SPM LS terlebih dahulu.',
            'pegawai' => 'Kemungkinan masih ada pegawai yang menjabat sebagai KPA/BPP/PPTK - lepas penugasan tersebut terlebih dahulu.',
            default => 'Periksa data lain yang mungkin masih bergantung pada data ini.',
        };
    }
}
