<?php

use App\Http\Controllers\AnalisisTrenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CetakSpjPerjalananController;
use App\Http\Controllers\DashboardNpdController;
use App\Http\Controllers\DashboardRealisasiController;
use App\Http\Controllers\DataPerjalananDinasController;
use App\Http\Controllers\DataSpjPerjalananDinasController;
use App\Http\Controllers\GajiTunjanganController;
use App\Http\Controllers\GajiTunjanganImportController;
use App\Http\Controllers\InventarisasiSpjController;
use App\Http\Controllers\KebutuhanController;
use App\Http\Controllers\KeteranganLampiranPreviewController;
use App\Http\Controllers\ManajemenDataController;
use App\Http\Controllers\MasterAnggaranImportController;
use App\Http\Controllers\NpdBjController;
use App\Http\Controllers\NpdController;
use App\Http\Controllers\NpdHistorisImportController;
use App\Http\Controllers\NpdKontribusiDiklatController;
use App\Http\Controllers\NpdNarasumberController;
use App\Http\Controllers\NpdNotifikasiController;
use App\Http\Controllers\NpdPdController;
use App\Http\Controllers\NpdTransportController;
use App\Http\Controllers\PegawaiImportController;
use App\Http\Controllers\PelimpahanController;
use App\Http\Controllers\PengembalianController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PerjalananDinasDashboardController;
use App\Http\Controllers\PerjalananDinasPegawaiController;
use App\Http\Controllers\PkptController;
use App\Http\Controllers\PkptImportController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RakBulananImportController;
use App\Http\Controllers\RealisasiPeriodeController;
use App\Http\Controllers\RekananController;
use App\Http\Controllers\RekapPotensiImportController;
use App\Http\Controllers\RekapPotensiPengembalianController;
use App\Http\Controllers\RekonsiliasiGajiController;
use App\Http\Controllers\RincianManualImportController;
use App\Http\Controllers\RincianPenghasilanController;
use App\Http\Controllers\RincianRealisasiController;
use App\Http\Controllers\SegeraHadirController;
use App\Http\Controllers\SimulasiAnggaranController;
use App\Http\Controllers\SimulasiRealisasiController;
use App\Http\Controllers\SiaranController;
use App\Http\Controllers\SpjBerkasController;
use App\Http\Controllers\SpjDashboardController;
use App\Http\Controllers\SpmController;
use App\Http\Controllers\SpmImportController;
use App\Http\Controllers\SuratPerintahController;
use App\Http\Controllers\TunjanganKeluargaController;
use App\Http\Controllers\TunjanganKeluargaImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorImportController;
use App\Http\Controllers\VersiPaguController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth.or.guest');

// Gerbang Pengguna Layanan: tanpa akun, tanpa pendaftaran, tapi wajib kata
// sandi bersama (config akses.sandi_layanan). Dibatasi lima percobaan per
// menit supaya sandinya tidak bisa ditebak beruntun.
Route::post('/layanan/masuk', [AuthController::class, 'masukLayanan'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('layanan.masuk');

// Halaman Pengguna Layanan — tanpa akun, tetapi di balik gerbang kata sandi.
// User yang login sungguhan lolos begitu saja lewat middleware yang sama.
Route::middleware('gerbang-layanan')->group(function () {
    // Dipakai role "layanan" untuk mengisi orderan SP dari luar.
    Route::get('/sp/input', [SuratPerintahController::class, 'publicCreate'])
        ->middleware('baca-saja')->name('sp.input.create');
    Route::post('/sp/input', [SuratPerintahController::class, 'publicStore'])
        ->middleware(['throttle:5,1', 'baca-saja'])->name('sp.input.store');

    // Monitoring SP juga dipakai role "layanan" untuk memantau orderan SP
    // miliknya (lihat CodeSuratPerintah.gs: "Monitoring SP = daftar orderan
    // yang diinput orang kantor"). Role yang login tetap melihatnya lewat
    // sidebar biasa.
    Route::get('/surat-perintah/monitoring', [SuratPerintahController::class, 'monitoring'])->name('surat-perintah.monitoring');

    // Cetak SPJ Perjalanan Dinas (layanan mandiri pegawai). Port dari
    // cetakSPJPerjalanan() di gas-lama/CodePerjalanan.gs: cukup berbekal Nomor
    // SP, tanpa akun. Dokumennya sendiri hanya dilayani untuk NPD berstatus
    // Selesai yang tertaut ke SP (lihat CetakSpjPerjalananController).
    Route::get('/cetak-spj-perjalanan', [CetakSpjPerjalananController::class, 'index'])->name('cetak-spj.index');
    // Saran nomor SP untuk pencarian ketik-langsung. Hanya identitas SP-nya;
    // rincian anggota tetap baru muncul setelah SP dipilih.
    Route::get('/cetak-spj-perjalanan/saran', [CetakSpjPerjalananController::class, 'saran'])
        ->middleware('throttle:60,1')->name('cetak-spj.saran');
    Route::get('/cetak-spj-perjalanan/{npd}/daftar', [CetakSpjPerjalananController::class, 'cetakDaftar'])->name('cetak-spj.daftar');
    Route::get('/cetak-spj-perjalanan/{npd}/spd', [CetakSpjPerjalananController::class, 'cetakSpd'])->name('cetak-spj.spd');
    Route::get('/pengumuman', [PengumumanController::class, 'show'])->name('pengumuman.show');
    Route::get('/tunjangan-keluarga/perubahan', [TunjanganKeluargaController::class, 'form'])->name('tunjangan.form');
    Route::post('/tunjangan-keluarga/perubahan', [TunjanganKeluargaController::class, 'submit'])->middleware('throttle:5,1')->name('tunjangan.submit');
    // Langkah pertama formulir: membuka data satu pegawai lewat NIP (+ 4
    // digit rekening). Dibatasi laju supaya NIP/rekening tidak bisa ditebak
    // beruntun, sama seperti gerbang Dashboard Tunjangan Keluarga.
    Route::post('/tunjangan-keluarga/perubahan/buka', [TunjanganKeluargaController::class, 'bukaFormulir'])->middleware('throttle:10,1')->name('tunjangan.form.buka');
    Route::post('/tunjangan-keluarga/perubahan/ganti-nip', [TunjanganKeluargaController::class, 'gantiNipFormulir'])->name('tunjangan.form.ganti-nip');
});

Route::middleware('auth.or.guest')->group(function () {
    Route::get('/dashboard', DashboardRealisasiController::class)
        ->middleware('menu-akses:dashboard')
        ->name('dashboard.index');
    // Dua sub menu Rincian Realisasi berbagi satu kunci akses 'rincian':
    // angkanya sama, cuma dipotong berbeda (kumulatif setahun vs per bulan),
    // jadi tidak ada peran yang boleh melihat satu tapi tidak yang lain.
    Route::middleware('menu-akses:rincian')->group(function () {
        Route::get('/rincian-realisasi', [RincianRealisasiController::class, 'index'])
            ->name('rincian.index');
        Route::get('/rincian-realisasi/periodik', [RincianRealisasiController::class, 'periodik'])
            ->name('rincian.periodik');
    });
    Route::get('/analisis-tren', AnalisisTrenController::class)
        ->middleware('menu-akses:analisis')
        ->name('analisis.index');

    // Simulasi Pergeseran/Perubahan Anggaran: sub menu ke-2 di Analisis dan
    // Tren, gerbang akses menumpang key 'analisis' yang sama (bukan key
    // config/akses.php baru) karena secara peran ini satu modul yang sama.
    Route::middleware('menu-akses:analisis')->prefix('analisis-tren/simulasi')->name('simulasi-anggaran.')->group(function () {
        Route::get('/', [SimulasiAnggaranController::class, 'index'])->name('index');

        // Simulasi siapa pun boleh disunting/dihapus oleh pemegang kunci menu
        // 'analisis' - tidak ada batasan kepemilikan - sehingga rute
        // pengubahnya perlu ditutup tersendiri untuk role baca-saja.
        //
        // "create" WAJIB didaftarkan sebelum /{simulasiAnggaran}, kalau tidak
        // kata itu tertangkap sebagai parameter dan halamannya jadi 404 untuk
        // semua role.
        Route::middleware('baca-saja')->group(function () {
            Route::get('/create', [SimulasiAnggaranController::class, 'create'])->name('create');
            Route::post('/', [SimulasiAnggaranController::class, 'store'])->name('store');
        });

        Route::get('/{simulasiAnggaran}', [SimulasiAnggaranController::class, 'show'])->name('show');

        Route::middleware('baca-saja')->group(function () {
            Route::put('/{simulasiAnggaran}', [SimulasiAnggaranController::class, 'update'])->name('update');
            Route::delete('/{simulasiAnggaran}', [SimulasiAnggaranController::class, 'destroy'])->name('destroy');
        });

        Route::get('/{simulasiAnggaran}/export-excel', [SimulasiAnggaranController::class, 'exportExcel'])->name('export-excel');
        Route::get('/{simulasiAnggaran}/export-pdf', [SimulasiAnggaranController::class, 'exportPdf'])->name('export-pdf');
    });
    // Simulasi Realisasi: memperkirakan capaian akhir tahun. Menumpang kunci
    // menu 'analisis' yang sama dengan Simulasi Pergeseran karena secara peran
    // keduanya satu modul. Urutan pendaftaran menaruh 'create' sebelum
    // /{simulasiRealisasi} - kalau terbalik, kata "create" tertangkap sebagai
    // parameter dan halamannya jadi 404.
    Route::middleware('menu-akses:analisis')->prefix('analisis-tren/simulasi-realisasi')->name('simulasi-realisasi.')->group(function () {
        Route::get('/', [SimulasiRealisasiController::class, 'index'])->name('index');

        Route::middleware('baca-saja')->group(function () {
            Route::get('/create', [SimulasiRealisasiController::class, 'create'])->name('create');
            Route::post('/', [SimulasiRealisasiController::class, 'store'])->name('store');
        });

        Route::get('/{simulasiRealisasi}', [SimulasiRealisasiController::class, 'show'])->name('show');

        Route::middleware('baca-saja')->group(function () {
            Route::put('/{simulasiRealisasi}', [SimulasiRealisasiController::class, 'update'])->name('update');
            Route::delete('/{simulasiRealisasi}', [SimulasiRealisasiController::class, 'destroy'])->name('destroy');
        });

        Route::get('/{simulasiRealisasi}/export-excel', [SimulasiRealisasiController::class, 'exportExcel'])->name('export-excel');
        Route::get('/{simulasiRealisasi}/export-pdf', [SimulasiRealisasiController::class, 'exportPdf'])->name('export-pdf');
    });

    // Monitoring PKPT: sub menu ke-4 Analisis dan Tren. Kunci menunya SENDIRI
    // ('pkpt', bukan menumpang 'analisis') karena cakupan rolenya memang beda -
    // Pengguna Layanan punya 'rincian' tapi tidak boleh melihat PKPT.
    Route::get('/analisis-tren/monitoring-pkpt', [PkptController::class, 'index'])
        ->middleware('menu-akses:pkpt')
        ->name('pkpt.index');

    // Estimasi Kebutuhan Kegiatan Pengawasan: sub menu ke-5 & ke-6 Analisis
    // dan Tren. Dua kunci menu berbeda - 'keb-data' (rekap, dibaca juga oleh
    // Perencanaan & Bendahara) dan 'keb-input' (formulir, hanya Irban).
    // Penyimpanan & penghapusan tetap diperiksa ulang di controller: unit
    // kerjanya diambil dari role, bukan dari isian.
    Route::get('/analisis-tren/kebutuhan-anggaran', [KebutuhanController::class, 'index'])
        ->middleware('menu-akses:keb-data')
        ->name('kebutuhan.index');
    Route::delete('/analisis-tren/kebutuhan-anggaran/{kebutuhan}', [KebutuhanController::class, 'destroy'])
        ->middleware(['menu-akses:keb-data', 'baca-saja'])
        ->name('kebutuhan.destroy');
    Route::middleware(['menu-akses:keb-input', 'baca-saja'])->group(function () {
        Route::get('/analisis-tren/kebutuhan-anggaran/input', [KebutuhanController::class, 'create'])->name('kebutuhan.create');
        Route::post('/analisis-tren/kebutuhan-anggaran', [KebutuhanController::class, 'store'])->name('kebutuhan.store');
    });

    Route::get('/dashboard/perjalanan-dinas', PerjalananDinasDashboardController::class)
        ->middleware('menu-akses:dashpd')
        ->name('dashboard.perjalanan.index');
    Route::get('/dashboard/perjalanan-dinas/pegawai/{pegawai}', PerjalananDinasPegawaiController::class)
        ->middleware('menu-akses:dashpd')
        ->name('dashboard.perjalanan.pegawai');
    Route::get('/dashboard/spj-pengawasan', [SpjDashboardController::class, 'index'])
        ->middleware('menu-akses:dashspj')
        ->name('dashboard.spj.index');
    Route::post('/dashboard/spj-pengawasan/{npd}/verifikasi', [SpjDashboardController::class, 'verify'])
        ->middleware(['menu-akses:dashspj', 'role:superadmin,verifikator'])
        ->name('dashboard.spj.verify');
    Route::get('/dashboard/nota-pencairan-dana', DashboardNpdController::class)
        ->middleware('menu-akses:dashnpd')
        ->name('dashboard.npd.index');

    // Siaran (broadcast) di lonceng notifikasi. Mengirim dan menghapus hanya
    // superadmin; menandai dibaca boleh semua akun yang login - Pengguna
    // Layanan tidak punya akun, jadi tidak ada bacaan yang bisa dicatat.
    Route::post('/siaran', [SiaranController::class, 'store'])
        ->middleware(['role:superadmin', 'throttle:20,1'])->name('siaran.store');
    Route::delete('/siaran/{siaran}', [SiaranController::class, 'destroy'])
        ->middleware('role:superadmin')->name('siaran.destroy');
    Route::post('/siaran/{siaran}/baca', [SiaranController::class, 'baca'])
        ->middleware('auth')->name('siaran.baca');
    Route::get('/inventarisasi-spj', [InventarisasiSpjController::class, 'index'])
        ->middleware('menu-akses:invspj')->name('inventarisasi-spj.index');
    Route::get('/inventarisasi-spj/{npd}/rincian', [InventarisasiSpjController::class, 'rincian'])
        ->middleware('menu-akses:invspj')->name('inventarisasi-spj.rincian');
    // Mengubah isi Inventarisasi SPJ: superadmin dan Pengelola SPJ saja
    // (config akses.kelola.invspj). Pemegang kunci menu lainnya hanya membaca.
    Route::middleware(['menu-akses:invspj', 'kelola:invspj'])->group(function () {
        Route::post('/inventarisasi-spj/bantex', [InventarisasiSpjController::class, 'storeBantex'])->name('inventarisasi-spj.bantex.store');
        Route::delete('/inventarisasi-spj/bantex/{bantex}', [InventarisasiSpjController::class, 'destroyBantex'])->name('inventarisasi-spj.bantex.destroy');
        Route::put('/inventarisasi-spj/{npd}', [InventarisasiSpjController::class, 'updateDetail'])->name('inventarisasi-spj.detail.update');
        Route::post('/inventarisasi-spj/{npd}/restore', [InventarisasiSpjController::class, 'restoreDetail'])->name('inventarisasi-spj.detail.restore');
    });
    Route::get('/tunjangan-keluarga/dashboard', [TunjanganKeluargaController::class, 'dashboard'])
        ->middleware('menu-akses:dash-tk')->name('tunjangan.dashboard');
    // Gerbang privasi dashboard. Berdiri sendiri, tidak menumpang rute Data
    // Gaji, karena ada role yang memegang dash-tk tanpa memegang gt-gaji.
    // Dibatasi lajunya supaya NIP + 4 digit rekening tidak bisa ditebak beruntun.
    Route::post('/tunjangan-keluarga/dashboard/verifikasi', [TunjanganKeluargaController::class, 'verifikasi'])
        ->middleware(['menu-akses:dash-tk', 'throttle:10,1'])->name('tunjangan.dashboard.verifikasi');
    Route::post('/tunjangan-keluarga/dashboard/ganti-nip', [TunjanganKeluargaController::class, 'gantiNip'])
        ->middleware('menu-akses:dash-tk')->name('tunjangan.dashboard.ganti-nip');
    Route::get('/tunjangan-keluarga/monitoring', [TunjanganKeluargaController::class, 'monitoring'])
        ->middleware('menu-akses:tk-monitor')->name('tunjangan.monitoring');

    /*
     * Data Gaji & Tunjangan. Tidak ada pembatasan role di sini selain
     * menu-akses: siapa yang boleh membuka menunya sudah diatur
     * config('akses.menu'), dan siapa yang melihat data SELURUH pegawai
     * diatur config('gaji_tunjangan.role_data_penuh'). Role di luar daftar
     * itu - termasuk Pengguna Layanan yang masuk tanpa akun - harus
     * memverifikasi NIP + 4 digit rekening lebih dulu dan hanya menerima
     * barisnya sendiri, disaring di server oleh GajiTunjanganService.
     */
    // Di sidebar keempatnya satu butir ("Rincian Penghasilan") dan dipilih
    // lewat pemilih di halaman, tetapi tiap penyajian tetap dijaga kunci
    // menunya masing-masing (gt-gaji, gt-beban, gt-kondisi, gt-total), bukan
    // satu kunci untuk keempatnya, supaya hak aksesnya tetap benar bila suatu
    // saat salah satu ditutup untuk sebuah role di config/akses.php.
    foreach (['gaji', 'beban', 'kondisi', 'total'] as $jenisGt) {
        Route::get('/gaji-tunjangan/'.$jenisGt, [GajiTunjanganController::class, 'index'])
            ->defaults('jenis', $jenisGt)
            ->middleware('menu-akses:gt-'.$jenisGt)
            ->name('gaji-tunjangan.tabel.'.$jenisGt);
    }

    Route::post('/gaji-tunjangan/verifikasi', [GajiTunjanganController::class, 'verifikasi'])
        ->middleware(['menu-akses:gt-gaji', 'throttle:10,1'])
        ->name('gaji-tunjangan.verifikasi');
    Route::post('/gaji-tunjangan/ganti-nip', [GajiTunjanganController::class, 'gantiNip'])
        ->middleware('menu-akses:gt-gaji')
        ->name('gaji-tunjangan.ganti-nip');

    // Cetak Rincian Penghasilan: formulir pembuatan dokumen, terbuka untuk
    // semua role yang punya menunya (termasuk layanan) - sama seperti GAS,
    // yang sengaja tidak memasang gate di menu ini.
    Route::get('/rincian-penghasilan/cetak', [RincianPenghasilanController::class, 'create'])
        ->middleware('menu-akses:gt-cetak')->name('gaji-tunjangan.rincian.create');
    Route::post('/rincian-penghasilan/uang-harian', [RincianPenghasilanController::class, 'uangHarian'])
        ->middleware(['menu-akses:gt-cetak', 'throttle:60,1'])->name('gaji-tunjangan.rincian.uang-harian');
    Route::post('/rincian-penghasilan/cetak', [RincianPenghasilanController::class, 'store'])
        ->middleware(['menu-akses:gt-cetak', 'throttle:20,1'])->name('gaji-tunjangan.rincian.store');
    Route::get('/rincian-penghasilan/{dokumen}/cetak', [RincianPenghasilanController::class, 'cetak'])
        ->middleware('menu-akses:gt-cetak')->name('gaji-tunjangan.rincian.cetak');

    // Daftar penandatangan surat disusun superadmin (diambil dari Data
    // Pegawai); role lain hanya memilih dari daftar itu. Dijaga ulang di
    // controller, bukan cuma dengan menyembunyikan panelnya di form.
    Route::post('/rincian-penghasilan/penandatangan', [RincianPenghasilanController::class, 'simpanPenandatangan'])
        ->middleware(['menu-akses:gt-cetak', 'role:superadmin'])->name('gaji-tunjangan.rincian.ttd.store');
    Route::delete('/rincian-penghasilan/penandatangan/{penandatangan}', [RincianPenghasilanController::class, 'hapusPenandatangan'])
        ->middleware(['menu-akses:gt-cetak', 'role:superadmin'])->name('gaji-tunjangan.rincian.ttd.destroy');

    /*
     * Rekonsiliasi Gaji Induk: Status Tunjangan Keluarga yang dikunci di awal
     * bulan dibandingkan dengan status yang tersirat dari nominal gajinya.
     *
     * Halamannya dijaga menu-akses:gt-rekon (superadmin & Bendahara
     * Pengeluaran), tetapi mengunci/menyunting/menghapus lognya HANYA
     * superadmin - dijaga middleware sekaligus diperiksa lagi di controller.
     */
    Route::get('/rekonsiliasi-gaji', [RekonsiliasiGajiController::class, 'index'])
        ->middleware('menu-akses:gt-rekon')->name('gaji-tunjangan.rekonsiliasi');

    // Rekap Potensi Pengembalian: baca-saja, isinya sensitif (kelebihan
    // pembayaran per pegawai) jadi aksesnya disamakan dengan Rekonsiliasi.
    Route::get('/gaji-tunjangan/rekap-potensi', RekapPotensiPengembalianController::class)
        ->middleware('menu-akses:gt-potensi')->name('gaji-tunjangan.rekap-potensi');
    Route::post('/rekonsiliasi-gaji/kunci', [RekonsiliasiGajiController::class, 'kunci'])
        ->middleware(['menu-akses:gt-rekon', 'role:superadmin'])->name('gaji-tunjangan.rekonsiliasi.kunci');
    Route::put('/rekonsiliasi-gaji/baris/{baris}', [RekonsiliasiGajiController::class, 'sunting'])
        ->middleware(['menu-akses:gt-rekon', 'role:superadmin'])->name('gaji-tunjangan.rekonsiliasi.sunting');
    Route::delete('/rekonsiliasi-gaji/{kunci}', [RekonsiliasiGajiController::class, 'hapus'])
        ->middleware(['menu-akses:gt-rekon', 'role:superadmin'])->name('gaji-tunjangan.rekonsiliasi.hapus');

    // Daftar Rincian Penghasilan: hanya role pengelola (lihat gt-daftar di
    // config/akses.php). Penghapusan dijaga ulang di controller.
    Route::get('/rincian-penghasilan', [RincianPenghasilanController::class, 'index'])
        ->middleware('menu-akses:gt-daftar')->name('gaji-tunjangan.rincian.index');
    Route::delete('/rincian-penghasilan/{dokumen}', [RincianPenghasilanController::class, 'destroy'])
        ->middleware('menu-akses:gt-daftar')->name('gaji-tunjangan.rincian.destroy');

    // Semua role yang login, kecuali "layanan" (layanan tidak login). Daftarnya
    // diambil dari User::ROLE_OPTIONS supaya role baru otomatis ikut - dulu
    // ditulis tangan, dan role Irban per unit sempat tertinggal dari sini.
    Route::middleware('role:'.implode(',', User::ROLE_OPTIONS))->group(function () {
        // Data SP: daftar seluruh SP, hanya untuk pemegang kunci menunya.
        Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])
            ->middleware('menu-akses:sp-data')->name('surat-perintah.index');

        // Membuat SP mengubah data, jadi tertutup untuk role baca-saja.
        Route::middleware(['menu-akses:sp-input', 'baca-saja'])->group(function () {
            Route::get('/surat-perintah/create', [SuratPerintahController::class, 'create'])->name('surat-perintah.create');
            Route::post('/surat-perintah', [SuratPerintahController::class, 'store'])->name('surat-perintah.store');
        });
        Route::get('/surat-perintah/export-pdf', [SuratPerintahController::class, 'exportPdf'])->name('surat-perintah.export-pdf');
        Route::get('/surat-perintah/{suratPerintah}/file', [SuratPerintahController::class, 'downloadFile'])->name('surat-perintah.file');

        Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
        Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
    });

    // Manajemen Users & Pelimpahan: khusus superadmin.
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/username', [UserController::class, 'updateUsername'])->name('users.username.update');
        Route::patch('/users/{user}/toggle-aktif', [UserController::class, 'toggleAktif'])->name('users.toggle-aktif');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/pelimpahan', [PelimpahanController::class, 'index'])->name('pelimpahan.index');
        Route::post('/pelimpahan/opd', [PelimpahanController::class, 'updateOpd'])->name('pelimpahan.opd.update');
        Route::post('/pelimpahan/kpa', [PelimpahanController::class, 'storeKpa'])->name('pelimpahan.kpa.store');
        Route::put('/pelimpahan/kpa/{kpa}', [PelimpahanController::class, 'updateKpa'])->name('pelimpahan.kpa.update');
        Route::patch('/pelimpahan/kpa/{kpa}/toggle-aktif', [PelimpahanController::class, 'toggleKpaAktif'])->name('pelimpahan.kpa.toggle-aktif');
        Route::post('/pelimpahan/pptk', [PelimpahanController::class, 'storePptkRoster'])->name('pelimpahan.pptk.store');
        Route::patch('/pelimpahan/pptk/{pptkRoster}/toggle-aktif', [PelimpahanController::class, 'togglePptkRoster'])->name('pelimpahan.pptk.toggle-aktif');
        Route::post('/pelimpahan/sub-kegiatan', [PelimpahanController::class, 'setSubKegiatan'])->name('pelimpahan.sub-kegiatan.set');

        Route::get('/manajemen-data/import/npd-historis', [NpdHistorisImportController::class, 'create'])->name('manajemen-data.import.npd-historis.create');
        Route::get('/manajemen-data/import/npd-historis/template', [NpdHistorisImportController::class, 'template'])->name('manajemen-data.import.npd-historis.template');
        Route::post('/manajemen-data/import/npd-historis', [NpdHistorisImportController::class, 'store'])->name('manajemen-data.import.npd-historis.store');
        Route::get('/manajemen-data/import/npd-historis/{import}/preview', [NpdHistorisImportController::class, 'preview'])->name('manajemen-data.import.npd-historis.preview');
        Route::post('/manajemen-data/import/npd-historis/{import}/confirm', [NpdHistorisImportController::class, 'confirm'])->name('manajemen-data.import.npd-historis.confirm');
        Route::get('/manajemen-data/import/npd-historis/{import}/report/{mode}', [NpdHistorisImportController::class, 'report'])->name('manajemen-data.import.npd-historis.report');
    });

    /*
     * Modul Data Kepegawaian: Data Pegawai dan Data Tunjangan Keluarga.
     *
     * MEMBACA daftarnya terbuka untuk pemegang kunci menunya (tk-pegawai,
     * tk-data). MENGUBAH - tambah, sunting, kosongkan, impor - serta membuka
     * dokumen pendukung keluarga tetap milik superadmin dan Kepegawaian
     * (config akses.kelola), yang memegang modul ini penuh tanpa membawa
     * kewenangan superadmin yang lain.
     */
    Route::get('/tunjangan-keluarga/pegawai', [TunjanganKeluargaController::class, 'pegawai'])
        ->middleware('menu-akses:tk-pegawai')->name('tunjangan.pegawai.index');
    Route::get('/tunjangan-keluarga/data', [TunjanganKeluargaController::class, 'data'])
        ->middleware('menu-akses:tk-data')->name('tunjangan.data.index');

    // Data Pegawai. Rute "tambah" didaftarkan lebih dulu supaya tidak
    // tertangkap {pegawai}.
    Route::middleware(['menu-akses:tk-pegawai', 'kelola:tk-pegawai'])->group(function () {
        Route::get('/tunjangan-keluarga/pegawai/tambah', [TunjanganKeluargaController::class, 'createPegawai'])->name('tunjangan.pegawai.create');
        Route::post('/tunjangan-keluarga/pegawai', [TunjanganKeluargaController::class, 'storePegawai'])->name('tunjangan.pegawai.store');
        Route::get('/tunjangan-keluarga/pegawai/{pegawai}/edit', [TunjanganKeluargaController::class, 'editPegawai'])->name('tunjangan.pegawai.edit');
        Route::put('/tunjangan-keluarga/pegawai/{pegawai}', [TunjanganKeluargaController::class, 'updatePegawai'])->name('tunjangan.pegawai.update');
    });

    // Data Tunjangan Keluarga: sumber data mentah dashboard, beserta impornya.
    Route::middleware(['menu-akses:tk-data', 'kelola:tk-data'])->group(function () {
        Route::get('/tunjangan-keluarga/import', [TunjanganKeluargaImportController::class, 'create'])->name('tunjangan.import.create');
        Route::get('/tunjangan-keluarga/import/template', [TunjanganKeluargaImportController::class, 'template'])->name('tunjangan.import.template');
        Route::post('/tunjangan-keluarga/import', [TunjanganKeluargaImportController::class, 'store'])->name('tunjangan.import.store');
        Route::get('/tunjangan-keluarga/import/{import}', [TunjanganKeluargaImportController::class, 'preview'])->name('tunjangan.import.preview');
        Route::post('/tunjangan-keluarga/import/{import}/confirm', [TunjanganKeluargaImportController::class, 'confirm'])->name('tunjangan.import.confirm');

        Route::delete('/tunjangan-keluarga/data/{pegawai}', [TunjanganKeluargaController::class, 'hapusData'])->name('tunjangan.data.hapus');
        Route::get('/tunjangan-keluarga/data/{pegawai}/edit', [TunjanganKeluargaController::class, 'editData'])->name('tunjangan.data.edit');
        Route::post('/tunjangan-keluarga/data/{pegawai}', [TunjanganKeluargaController::class, 'simpanData'])->name('tunjangan.data.simpan');
        Route::get('/tunjangan-keluarga/data-dokumen/{tunjanganKeluarga}', [TunjanganKeluargaController::class, 'unduhDokumenData'])->name('tunjangan.data.dokumen');
    });

    // Hanya PPTK dan superadmin boleh mengubah / menghapus data SP, toggle Monitoring, & ubah Pengajuan.
    Route::middleware('role:pptk,superadmin')->group(function () {
        Route::get('/surat-perintah/{suratPerintah}/edit', [SuratPerintahController::class, 'edit'])->name('surat-perintah.edit');
        Route::put('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'update'])->name('surat-perintah.update');
        Route::delete('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'destroy'])->name('surat-perintah.destroy');
        Route::patch('/surat-perintah/{suratPerintah}/toggle-pantau', [SuratPerintahController::class, 'togglePantau'])->name('surat-perintah.toggle-pantau');
        Route::patch('/surat-perintah/{suratPerintah}/pengajuan', [SuratPerintahController::class, 'updatePengajuan'])->name('surat-perintah.pengajuan');
    });

    // Toggle "Sumber NPD" sengaja lebih luas daripada toggle Monitoring: BPP
    // ikut boleh mematikannya, mengikuti setSumberNPD() di gas-lama yang
    // dijaga _guardRole(['pptk','bpp','bendahara']).
    Route::middleware('role:pptk,bpp,superadmin')->group(function () {
        Route::patch('/surat-perintah/{suratPerintah}/toggle-sumber-npd', [SuratPerintahController::class, 'toggleSumberNpd'])->name('surat-perintah.toggle-sumber-npd');
    });

    // Edit Pemberitahuan dari Tim Keuangan (Monitoring SP): hanya 4 role ini.
    Route::middleware('role:superadmin,pptk,bpp,verifikator')->group(function () {
        Route::post('/pengumuman', [PengumumanController::class, 'store'])->name('pengumuman.store');
    });

    // Log aktivitas (audit trail): hanya superadmin.
    Route::get('/audit-log', [AuditLogController::class, 'index'])
        ->middleware('menu-akses:audit-log')->name('audit-log.index');

    // Menu yang rumahnya sudah ada tetapi isinya belum - lihat
    // SegeraHadirController::HALAMAN. Cukup dijaga menu-akses: kuncinya
    // (sp-cetaksppd) dipegang semua role, termasuk Pengguna Layanan, dan
    // daftar role tulisan tangan di sini dulu membuat sebagian pemegang
    // kuncinya malah tertolak.
    foreach (array_keys(SegeraHadirController::HALAMAN) as $menuSegera) {
        Route::get('/segera/'.$menuSegera, SegeraHadirController::class)
            ->defaults('menu', $menuSegera)
            ->middleware('menu-akses:'.$menuSegera)
            ->name('segera.'.$menuSegera);
    }

    // Pembuatan NPD (halaman pemilih jenis + daftar): superadmin dan PPTK.
    Route::get('/npd', [NpdController::class, 'index'])
        ->middleware('menu-akses:npd')->name('npd.index');

    // Daftar Rekanan: daftar induk penyedia yang mengisi pilihan penerima di
    // Pembuatan NPD. Dibuka kelima role keuangan (kunci npd-rekanan); menulis
    // tetap lewat 'baca-saja'.
    Route::get('/npd/rekanan', [RekananController::class, 'index'])
        ->middleware('menu-akses:npd-rekanan')->name('rekanan.index');

    Route::middleware(['menu-akses:npd-rekanan', 'baca-saja'])->group(function () {
        Route::get('/npd/rekanan/tambah', [RekananController::class, 'create'])->name('rekanan.create');
        Route::post('/npd/rekanan', [RekananController::class, 'store'])->name('rekanan.store');
        Route::get('/npd/rekanan/{rekanan}/edit', [RekananController::class, 'edit'])->name('rekanan.edit');
        Route::put('/npd/rekanan/{rekanan}', [RekananController::class, 'update'])->name('rekanan.update');
    });

    // Data NPD: seluruh NPD apa pun statusnya, baca-saja. Dibuka role
    // keuangan, Pimpinan, dan Pengawas (kunci npd-data).
    Route::get('/npd/data', [NpdController::class, 'dataNpd'])
        ->middleware('menu-akses:npd-data')
        ->name('npd.data');

    // Pembuatan NPD: hanya superadmin dan PPTK.
    Route::middleware('role:superadmin,pptk')->group(function () {
        Route::get('/npd/bj/create', [NpdBjController::class, 'create'])->name('npd.bj.create');
        Route::post('/npd/bj', [NpdBjController::class, 'store'])->name('npd.bj.store');
        Route::get('/npd/pd/create', [NpdPdController::class, 'create'])->name('npd.pd.create');
        Route::post('/npd/pd', [NpdPdController::class, 'store'])->name('npd.pd.store');
        Route::get('/npd/ns/create', [NpdNarasumberController::class, 'create'])->name('npd.ns.create');
        Route::post('/npd/ns', [NpdNarasumberController::class, 'store'])->name('npd.ns.store');
        Route::get('/npd/kd/create', [NpdKontribusiDiklatController::class, 'create'])->name('npd.kd.create');
        Route::post('/npd/kd', [NpdKontribusiDiklatController::class, 'store'])->name('npd.kd.store');
        Route::get('/npd/tr/create', [NpdTransportController::class, 'create'])->name('npd.tr.create');
        Route::post('/npd/tr', [NpdTransportController::class, 'store'])->name('npd.tr.store');
        Route::delete('/npd/{npd}', [NpdController::class, 'destroy'])->name('npd.destroy');
    });

    // Edit NPD: PPTK selama masih draft, lalu BPP dan Verifikator saat NPD
    // berada di meja masing-masing. Middleware hanya menyaring role; meja
    // dan ikatan Verifikator ke Sub Kegiatan diperiksa Npd::dapatDieditOleh()
    // di tiap controller, sebelum dan di dalam transaksi penyimpanannya.
    Route::middleware('role:superadmin,pptk,bpp,verifikator')->group(function () {
        // Pratinjau Uraian Lampiran untuk formulir yang belum disimpan.
        // Dibatasi laju karena dipanggil tiap kali isian berubah.
        Route::post('/npd/keterangan-lampiran/pratinjau', KeteranganLampiranPreviewController::class)
            ->middleware('throttle:120,1')->name('npd.keterangan-lampiran.pratinjau');

        Route::get('/npd/bj/{npd}/edit', [NpdBjController::class, 'edit'])->name('npd.bj.edit');
        Route::put('/npd/bj/{npd}', [NpdBjController::class, 'update'])->name('npd.bj.update');
        Route::get('/npd/pd/{npd}/edit', [NpdPdController::class, 'edit'])->name('npd.pd.edit');
        Route::put('/npd/pd/{npd}', [NpdPdController::class, 'update'])->name('npd.pd.update');
        Route::get('/npd/ns/{npd}/edit', [NpdNarasumberController::class, 'edit'])->name('npd.ns.edit');
        Route::put('/npd/ns/{npd}', [NpdNarasumberController::class, 'update'])->name('npd.ns.update');
        Route::get('/npd/kd/{npd}/edit', [NpdKontribusiDiklatController::class, 'edit'])->name('npd.kd.edit');
        Route::put('/npd/kd/{npd}', [NpdKontribusiDiklatController::class, 'update'])->name('npd.kd.update');
        Route::get('/npd/tr/{npd}/edit', [NpdTransportController::class, 'edit'])->name('npd.tr.edit');
        Route::put('/npd/tr/{npd}', [NpdTransportController::class, 'update'])->name('npd.tr.update');
    });
    Route::delete('/npd/{npd}/permanen', [NpdController::class, 'destroyPermanent'])
        ->middleware('role:superadmin')->name('npd.destroy-permanent');

    // Antrean Persetujuan NPD: BPP, dan Bendahara Pengeluaran sebagai
    // pemantau - ia membuka antreannya tetapi tidak mendapat aksi transisi
    // (lihat grup npd.transisi di bawah). Port dari getNPDuntukBPP di
    // gas-lama/CodeRevisi.gs.
    Route::get('/npd/persetujuan', [NpdController::class, 'persetujuan'])
        ->middleware('menu-akses:persetujuan')->name('npd.persetujuan');

    // Antrean Verifikasi NPD: Verifikator. Port dari getNPDuntukVerifikator di gas-lama/CodeRevisi.gs.
    Route::middleware('menu-akses:verifikasi')->group(function () {
        Route::get('/npd/verifikasi', [NpdController::class, 'verifikasi'])->name('npd.verifikasi');
        Route::get('/npd/{npd}/coret', [NpdController::class, 'coret'])->name('npd.coret');
    });

    // Detail dan cetak: siapa pun yang boleh membuka Data NPD - semua pelaku
    // workflow memegang kunci itu, begitu juga Pimpinan dan Pengawas sebagai
    // pemantau baca-saja.
    Route::middleware('menu-akses:npd-data')->group(function () {
        Route::get('/npd/{npd}', [NpdController::class, 'show'])->name('npd.show');
        Route::get('/npd/{npd}/cetak-npd', [NpdController::class, 'cetakNpd'])->name('npd.cetak-npd');
        Route::get('/npd/{npd}/cetak-lampiran', [NpdController::class, 'cetakLampiran'])->name('npd.cetak-lampiran');
        Route::get('/npd/{npd}/cetak-daftar', [NpdController::class, 'cetakDaftar'])->name('npd.cetak-daftar');
        Route::get('/npd/{npd}/cetak-spd', [NpdController::class, 'cetakSpd'])->name('npd.cetak-spd');
        Route::get('/npd/{npd}/cetak-daftar-nara', [NpdController::class, 'cetakDaftarNarasumber'])->name('npd.cetak-daftar-nara');
        Route::get('/npd/{npd}/cetak-daftar-kd', [NpdController::class, 'cetakDaftarKontribusiDiklat'])->name('npd.cetak-daftar-kd');
        // Seluruh dokumen di atas dalam satu berkas, berurutan - berkas SPJ
        // yang sudah diunggah ikut di dalamnya.
        Route::get('/npd/{npd}/cetak-gabungan', [NpdController::class, 'cetakGabungan'])->name('npd.cetak-gabungan');

    });

    // MELIHAT berkas SPJ: isinya lampiran dokumen yang sudah boleh dibaca
    // lewat tombol cetak di detail NPD. Pemegang Inventarisasi SPJ ikut di
    // sini - Pengelola SPJ membuka berkasnya dari sana tanpa memegang Data NPD.
    Route::get('/npd/{npd}/spj-berkas/{berkas}', [SpjBerkasController::class, 'show'])
        ->middleware('menu-akses:npd-data,invspj')->name('npd.spj-berkas.show');

    // Mengarsipkan SPJ dari halaman detail NPD mengubah data, jadi tetap di
    // luar jangkauan pemantau (Pimpinan, Pengawas).
    Route::middleware('role:superadmin,bendahara_pengeluaran,pptk,bpp,verifikator')->group(function () {
        Route::post('/npd/{npd}/arsip-spj', [InventarisasiSpjController::class, 'store'])->name('npd.arsip-spj.store');
    });

    // Mengunggah & menghapus berkas SPJ: pelaku workflow dari halaman detail
    // NPD, dan Pengelola SPJ dari panel Edit Inventarisasi SPJ.
    Route::middleware('role:superadmin,bendahara_pengeluaran,pptk,bpp,verifikator,pengelola_spj')->group(function () {
        Route::post('/npd/{npd}/spj-berkas', [SpjBerkasController::class, 'store'])->name('npd.spj-berkas.store');
        Route::delete('/npd/{npd}/spj-berkas/{berkas}', [SpjBerkasController::class, 'destroy'])->name('npd.spj-berkas.destroy');
    });

    // Approve/Tolak pengajuan perubahan Tunjangan Keluarga: Kepegawaian dan
    // superadmin saja. Daftarnya satu sumber dengan kolom Aksi di halaman
    // Monitoring - lihat PengajuanPerubahanTunjangan::ROLE_PEMROSES.
    Route::post('/tunjangan-keluarga/pengajuan/{pengajuan}/proses', [TunjanganKeluargaController::class, 'proses'])
        ->middleware('role:'.implode(',', \App\Models\PengajuanPerubahanTunjangan::ROLE_PEMROSES))
        ->name('tunjangan.pengajuan.proses');

    // Membuka lampiran: pemroses di atas, ditambah Bendahara Pengeluaran
    // yang sejak awal boleh membacanya.
    Route::get('/tunjangan-keluarga/lampiran/{lampiran}', [TunjanganKeluargaController::class, 'download'])
        ->middleware('role:'.implode(',', \App\Models\PengajuanPerubahanTunjangan::ROLE_LAMPIRAN))
        ->name('tunjangan.lampiran.download');

    // Transisi workflow tidak diberikan kepada Bendahara Pengeluaran.
    Route::middleware('role:superadmin,pptk,bpp,verifikator')->group(function () {
        Route::post('/npd/{npd}/transisi', [NpdController::class, 'transisi'])->name('npd.transisi');

        // Aksi massal: khusus superadmin, sepadan dengan "bendahara" di GAS.
        // Dibatasi laju karena satu permintaan bisa menyentuh 200 NPD.
        Route::post('/npd/transisi-massal', [NpdController::class, 'transisiMassal'])
            ->middleware(['role:superadmin', 'throttle:20,1'])->name('npd.transisi-massal');
    });

    // Kirim Notifikasi WhatsApp pencairan NPD (Data NPD). Pelaku pencairan
    // saja: BPP yang menandai Selesai, BP sebagai pemantau OPD, superadmin.
    // Status NPD ikut diperiksa di controller, bukan cuma di tampilan.
    Route::middleware('role:superadmin,bendahara_pengeluaran,bpp')->group(function () {
        Route::get('/npd/{npd}/notifikasi', [NpdNotifikasiController::class, 'preview'])->name('npd.notifikasi.preview');
        Route::post('/npd/{npd}/notifikasi', [NpdNotifikasiController::class, 'store'])->name('npd.notifikasi.store');
    });

    // Daftar SPM boleh dibaca role keuangan, Pimpinan, dan Pengawas (kunci
    // 'spm'): SPM LS adalah salah satu sumber angka realisasi, jadi tanpa ini
    // pemantauan anggarannya tidak utuh.
    Route::middleware('menu-akses:spm')->group(function () {
        Route::get('/spm/up-gu', [SpmController::class, 'indexUpGu'])->name('spm.up-gu.index');
        Route::get('/spm/ls', [SpmController::class, 'indexLs'])->name('spm.ls.index');
        // Rincian SPM LS ikut boleh dibaca, sama seperti daftarnya.
        //
        // whereNumber WAJIB: rute ini terdaftar SEBELUM /spm/ls/create, dan
        // tanpa batasan itu "create" akan tertangkap sebagai id SPM sehingga
        // formulir tambah SPM LS berubah jadi 404.
        Route::get('/spm/ls/{spm}', [SpmController::class, 'showLs'])
            ->whereNumber('spm')->name('spm.ls.show');
    });

    // Membuat, mengubah, dan menghapus SPM: superadmin dan Bendahara
    // Pengeluaran (config akses.kelola.spm).
    Route::middleware(['menu-akses:spm', 'kelola:spm'])->group(function () {
        Route::get('/spm/up-gu/create', [SpmController::class, 'createUpGu'])->name('spm.up-gu.create');
        Route::post('/spm/up-gu', [SpmController::class, 'storeUpGu'])->name('spm.up-gu.store');
        Route::get('/spm/up-gu/{spm}/edit', [SpmController::class, 'editUpGu'])->name('spm.up-gu.edit');
        Route::put('/spm/up-gu/{spm}', [SpmController::class, 'updateUpGu'])->name('spm.up-gu.update');

        Route::get('/spm/ls/create', [SpmController::class, 'createLs'])->name('spm.ls.create');
        Route::post('/spm/ls', [SpmController::class, 'storeLs'])->name('spm.ls.store');
        Route::get('/spm/ls/{spm}/edit', [SpmController::class, 'editLs'])->name('spm.ls.edit');
        Route::put('/spm/ls/{spm}', [SpmController::class, 'updateLs'])->name('spm.ls.update');

        Route::delete('/spm/{spm}', [SpmController::class, 'destroy'])->name('spm.destroy');

        // Validasi = menyatakan angkanya sudah cocok dengan berkas SP2D, dan
        // sejak itu barisnya tidak bisa dihapus. Membatalkannya dibatasi
        // superadmin (lihat SpmController::batalValidasi).
        Route::post('/spm/{spm}/validasi', [SpmController::class, 'validasi'])->name('spm.validasi');
        Route::delete('/spm/{spm}/validasi', [SpmController::class, 'batalValidasi'])->name('spm.validasi.batal');
    });

    // Pengembalian: Bendahara Pengeluaran, BPP, dan Verifikator boleh input &
    // lihat; HANYA Bendahara Pengeluaran yang boleh menyetujui (lihat
    // middleware role di route setujui di bawah). Hapus draft: pembuatnya
    // sendiri atau Bendahara Pengeluaran - dicek di controller
    // (PengembalianController::destroy), bukan lewat middleware role, karena
    // bukan restriksi per-role murni.
    Route::middleware('role:superadmin,bendahara_pengeluaran,bpp,verifikator')->group(function () {
        Route::get('/pengembalian', [PengembalianController::class, 'index'])
            ->middleware('menu-akses:pengembalian')->name('pengembalian.index');
        Route::get('/pengembalian/create', [PengembalianController::class, 'create'])
            ->middleware('menu-akses:pengembalian-create')->name('pengembalian.create');
        Route::post('/pengembalian', [PengembalianController::class, 'store'])
            ->middleware('menu-akses:pengembalian-create')->name('pengembalian.store');
        Route::get('/pengembalian/{pengembalian}/dokumen-pendukung', [PengembalianController::class, 'unduhDokumenPendukung'])
            ->middleware('menu-akses:pengembalian')->name('pengembalian.dokumen-pendukung');
        Route::get('/pengembalian/{pengembalian}/edit', [PengembalianController::class, 'edit'])
            ->middleware('menu-akses:pengembalian-create')->name('pengembalian.edit');
        Route::put('/pengembalian/{pengembalian}', [PengembalianController::class, 'update'])
            ->middleware('menu-akses:pengembalian-create')->name('pengembalian.update');
        Route::get('/pengembalian/{pengembalian}', [PengembalianController::class, 'show'])
            ->middleware('menu-akses:pengembalian')->name('pengembalian.show');
    });

    // Hapus draft: kepemilikannya diperiksa di controller, jadi Verifikator
    // pun hanya bisa menghapus draft yang ia buat sendiri.
    Route::middleware(['menu-akses:pengembalian', 'role:superadmin,bendahara_pengeluaran,bpp,verifikator'])->group(function () {
        Route::delete('/pengembalian/{pengembalian}', [PengembalianController::class, 'destroy'])->name('pengembalian.destroy');
    });

    Route::middleware(['menu-akses:pengembalian', 'role:superadmin,bendahara_pengeluaran'])->group(function () {
        Route::post('/pengembalian/{pengembalian}/setujui', [PengembalianController::class, 'setujui'])->name('pengembalian.setujui');
    });

    // Manajemen Data (export + import): khusus superadmin, sama seperti dua
    // menu Setting lainnya. Bendahara Pengeluaran dulu ikut di sini.
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/manajemen-data', [ManajemenDataController::class, 'index'])->name('manajemen-data.index');
        Route::get('/manajemen-data/export/{jenis}', [ManajemenDataController::class, 'export'])
            ->whereIn('jenis', ['master-anggaran', 'rak-bulanan', 'npd', 'perjalanan-dinas', 'spj-perjalanan-dinas', 'spm-up-gu', 'spm-ls', 'pegawai', 'vendor', 'rekap-potensi', 'pkpt', 'kebutuhan-anggaran', 'tunjangan-keluarga'])
            ->name('manajemen-data.export');

        // Formulir rekap Perjalanan Dinas per pegawai. Bukan berkas import -
        // Data Perjalanan Dinas dihitung dari NPD, bukan tabel tersendiri.
        // Data Realisasi Anggaran: tarik realisasi seluruh mata anggaran pada
        // rentang tanggal pilihan, dirinci Program > Kegiatan > Sub Kegiatan >
        // Kode Rekening > Tagging. Ketiganya berbagi rentang tanggal yang sama
        // (lihat RealisasiPeriodeController::rentang).
        Route::get('/manajemen-data/realisasi-anggaran', [RealisasiPeriodeController::class, 'index'])->name('manajemen-data.realisasi-periode.index');
        Route::get('/manajemen-data/realisasi-anggaran/excel', [RealisasiPeriodeController::class, 'excel'])->name('manajemen-data.realisasi-periode.excel');
        Route::get('/manajemen-data/realisasi-anggaran/pdf', [RealisasiPeriodeController::class, 'pdf'])->name('manajemen-data.realisasi-periode.pdf');

        Route::get('/manajemen-data/template/perjalanan-dinas', [ManajemenDataController::class, 'templatePerjalananDinas'])
            ->name('manajemen-data.template.perjalanan-dinas');

        // Reset Data: hapus massal permanen per tipe data - lebih sensitif
        // daripada import/export, sengaja dibatasi superadmin saja (bukan
        // ikut role:superadmin,bendahara_pengeluaran di grup ini).
        Route::post('/manajemen-data/reset/{jenis}', [ManajemenDataController::class, 'reset'])
            ->whereIn('jenis', ['pagu', 'rak', 'npd', 'spm-up-gu', 'spm-ls', 'pegawai', 'vendor', 'rekap-potensi', 'pkpt', 'kebutuhan-anggaran', 'tunjangan-keluarga'])
            ->middleware('role:superadmin')
            ->name('manajemen-data.reset');

        // Import Pagu/Master Anggaran: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/master-anggaran', [MasterAnggaranImportController::class, 'create'])->name('manajemen-data.import.master-anggaran.create');
        Route::get('/manajemen-data/import/master-anggaran/template', [MasterAnggaranImportController::class, 'template'])->name('manajemen-data.import.master-anggaran.template');
        Route::post('/manajemen-data/import/master-anggaran', [MasterAnggaranImportController::class, 'store'])->name('manajemen-data.import.master-anggaran.store');
        Route::get('/manajemen-data/import/master-anggaran/{import}/preview', [MasterAnggaranImportController::class, 'preview'])->name('manajemen-data.import.master-anggaran.preview');
        Route::post('/manajemen-data/import/master-anggaran/{import}/konfirmasi', [MasterAnggaranImportController::class, 'konfirmasi'])->name('manajemen-data.import.master-anggaran.konfirmasi');
        Route::delete('/manajemen-data/import/master-anggaran/{import}', [MasterAnggaranImportController::class, 'batalkan'])->name('manajemen-data.import.master-anggaran.batalkan');

        // Versi Pagu (DPA Murni, DPA Pergeseran 1, ...). Import hanya
        // menghasilkan versi draft; aktivasi di sinilah yang mengubah pagu
        // yang berlaku untuk seluruh aplikasi.
        Route::get('/versi-pagu', [VersiPaguController::class, 'index'])->name('versi-pagu.index');
        Route::get('/versi-pagu/{versiPagu}', [VersiPaguController::class, 'show'])->name('versi-pagu.show');
        Route::post('/versi-pagu/{versiPagu}/aktifkan', [VersiPaguController::class, 'aktifkan'])->name('versi-pagu.aktifkan');
        // Nomor DPA sering terbit setelah paguya diimpor; bisa dilengkapi
        // tanpa mengulang impor seluruh dokumen.
        Route::patch('/versi-pagu/{versiPagu}/nomor-dpa', [VersiPaguController::class, 'nomorDpa'])->name('versi-pagu.nomor-dpa');
        Route::delete('/versi-pagu/{versiPagu}', [VersiPaguController::class, 'destroy'])->name('versi-pagu.destroy');

        // Import SPM UP/GU dan LS: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/spm/{jenis}/template', [SpmImportController::class, 'template'])
            ->whereIn('jenis', ['spm-up-gu', 'spm-ls'])
            ->name('manajemen-data.import.spm.template');
        Route::get('/manajemen-data/import/spm/{jenis}', [SpmImportController::class, 'create'])
            ->whereIn('jenis', ['spm-up-gu', 'spm-ls'])
            ->name('manajemen-data.import.spm.create');
        Route::post('/manajemen-data/import/spm/{jenis}', [SpmImportController::class, 'store'])
            ->whereIn('jenis', ['spm-up-gu', 'spm-ls'])
            ->name('manajemen-data.import.spm.store');
        Route::get('/manajemen-data/import/spm/{import}/preview', [SpmImportController::class, 'preview'])->name('manajemen-data.import.spm.preview');
        Route::post('/manajemen-data/import/spm/{import}/konfirmasi', [SpmImportController::class, 'konfirmasi'])->name('manajemen-data.import.spm.konfirmasi');
        Route::delete('/manajemen-data/import/spm/{import}', [SpmImportController::class, 'batalkan'])->name('manajemen-data.import.spm.batalkan');

        // Import RAK Bulanan: upload (format lebar Jan-Des) -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/rak-bulanan', [RakBulananImportController::class, 'create'])->name('manajemen-data.import.rak-bulanan.create');
        Route::post('/manajemen-data/import/rak-bulanan', [RakBulananImportController::class, 'store'])->name('manajemen-data.import.rak-bulanan.store');
        Route::get('/manajemen-data/import/rak-bulanan/{import}/preview', [RakBulananImportController::class, 'preview'])->name('manajemen-data.import.rak-bulanan.preview');
        Route::post('/manajemen-data/import/rak-bulanan/{import}/konfirmasi', [RakBulananImportController::class, 'konfirmasi'])->name('manajemen-data.import.rak-bulanan.konfirmasi');
        Route::delete('/manajemen-data/import/rak-bulanan/{import}', [RakBulananImportController::class, 'batalkan'])->name('manajemen-data.import.rak-bulanan.batalkan');

        // Import Pegawai: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/pegawai', [PegawaiImportController::class, 'create'])->name('manajemen-data.import.pegawai.create');
        Route::get('/manajemen-data/import/pegawai/template', [PegawaiImportController::class, 'template'])->name('manajemen-data.import.pegawai.template');
        Route::post('/manajemen-data/import/pegawai', [PegawaiImportController::class, 'store'])->name('manajemen-data.import.pegawai.store');
        Route::get('/manajemen-data/import/pegawai/{import}/preview', [PegawaiImportController::class, 'preview'])->name('manajemen-data.import.pegawai.preview');
        Route::post('/manajemen-data/import/pegawai/{import}/konfirmasi', [PegawaiImportController::class, 'konfirmasi'])->name('manajemen-data.import.pegawai.konfirmasi');
        Route::delete('/manajemen-data/import/pegawai/{import}', [PegawaiImportController::class, 'batalkan'])->name('manajemen-data.import.pegawai.batalkan');

        // Import Vendor: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        /*
         * Rincian dua kartu Manajemen Data yang judulnya bisa diklik.
         * Isinya data manual penambal periode sebelum migrasi - lihat
         * migrasi 2026_10_02_090000. Sengaja TIDAK diberi menu sidebar
         * sendiri: pintu masuknya cukup dari kartunya.
         */
        Route::get('/manajemen-data/rincian/perjalanan-dinas', [DataPerjalananDinasController::class, 'index'])->name('manajemen-data.rincian.perjalanan-dinas');
        Route::get('/manajemen-data/rincian/perjalanan-dinas/tambah', [DataPerjalananDinasController::class, 'create'])->name('manajemen-data.rincian.perjalanan-dinas.create');
        Route::post('/manajemen-data/rincian/perjalanan-dinas', [DataPerjalananDinasController::class, 'store'])->name('manajemen-data.rincian.perjalanan-dinas.store');
        Route::get('/manajemen-data/rincian/perjalanan-dinas/{rincian}/edit', [DataPerjalananDinasController::class, 'edit'])->name('manajemen-data.rincian.perjalanan-dinas.edit');
        Route::put('/manajemen-data/rincian/perjalanan-dinas/{rincian}', [DataPerjalananDinasController::class, 'update'])->name('manajemen-data.rincian.perjalanan-dinas.update');
        Route::delete('/manajemen-data/rincian/perjalanan-dinas/{rincian}', [DataPerjalananDinasController::class, 'destroy'])->name('manajemen-data.rincian.perjalanan-dinas.destroy');

        Route::get('/manajemen-data/rincian/spj-perjalanan-dinas', [DataSpjPerjalananDinasController::class, 'index'])->name('manajemen-data.rincian.spj-perjalanan-dinas');
        Route::get('/manajemen-data/rincian/spj-perjalanan-dinas/tambah', [DataSpjPerjalananDinasController::class, 'create'])->name('manajemen-data.rincian.spj-perjalanan-dinas.create');
        Route::post('/manajemen-data/rincian/spj-perjalanan-dinas', [DataSpjPerjalananDinasController::class, 'store'])->name('manajemen-data.rincian.spj-perjalanan-dinas.store');
        Route::get('/manajemen-data/rincian/spj-perjalanan-dinas/{rincian}/edit', [DataSpjPerjalananDinasController::class, 'edit'])->name('manajemen-data.rincian.spj-perjalanan-dinas.edit');
        Route::put('/manajemen-data/rincian/spj-perjalanan-dinas/{rincian}', [DataSpjPerjalananDinasController::class, 'update'])->name('manajemen-data.rincian.spj-perjalanan-dinas.update');
        Route::delete('/manajemen-data/rincian/spj-perjalanan-dinas/{rincian}', [DataSpjPerjalananDinasController::class, 'destroy'])->name('manajemen-data.rincian.spj-perjalanan-dinas.destroy');

        /*
         * Import parsial rincian manual. Segmen {jenis} dibatasi dua nilai
         * lewat whereIn, jadi satu set rute melayani Perjalanan Dinas dan
         * SPJ Perjalanan Dinas tanpa menyalin enam rute yang sama.
         */
        Route::prefix('manajemen-data/import/rincian/{jenis}')
            ->whereIn('jenis', ['perjalanan-dinas', 'spj-perjalanan-dinas'])
            ->group(function () {
                Route::get('/', [RincianManualImportController::class, 'create'])->name('manajemen-data.import.rincian.create');
                Route::get('/template', [RincianManualImportController::class, 'template'])->name('manajemen-data.import.rincian.template');
                Route::post('/', [RincianManualImportController::class, 'store'])->name('manajemen-data.import.rincian.store');
                Route::get('/{import}/preview', [RincianManualImportController::class, 'preview'])->name('manajemen-data.import.rincian.preview');
                Route::post('/{import}/konfirmasi', [RincianManualImportController::class, 'konfirmasi'])->name('manajemen-data.import.rincian.konfirmasi');
                Route::delete('/{import}', [RincianManualImportController::class, 'batalkan'])->name('manajemen-data.import.rincian.batalkan');
            });

        Route::get('/manajemen-data/import/rekap-potensi', [RekapPotensiImportController::class, 'create'])->name('manajemen-data.import.rekap-potensi.create');
        Route::get('/manajemen-data/import/rekap-potensi/template', [RekapPotensiImportController::class, 'template'])->name('manajemen-data.import.rekap-potensi.template');
        Route::post('/manajemen-data/import/rekap-potensi', [RekapPotensiImportController::class, 'store'])->name('manajemen-data.import.rekap-potensi.store');
        Route::get('/manajemen-data/import/rekap-potensi/{import}/preview', [RekapPotensiImportController::class, 'preview'])->name('manajemen-data.import.rekap-potensi.preview');
        Route::post('/manajemen-data/import/rekap-potensi/{import}/konfirmasi', [RekapPotensiImportController::class, 'konfirmasi'])->name('manajemen-data.import.rekap-potensi.konfirmasi');
        Route::delete('/manajemen-data/import/rekap-potensi/{import}', [RekapPotensiImportController::class, 'batalkan'])->name('manajemen-data.import.rekap-potensi.batalkan');

        Route::get('/manajemen-data/import/vendor', [VendorImportController::class, 'create'])->name('manajemen-data.import.vendor.create');
        Route::get('/manajemen-data/import/vendor/template', [VendorImportController::class, 'template'])->name('manajemen-data.import.vendor.template');
        Route::post('/manajemen-data/import/vendor', [VendorImportController::class, 'store'])->name('manajemen-data.import.vendor.store');
        Route::get('/manajemen-data/import/vendor/{import}/preview', [VendorImportController::class, 'preview'])->name('manajemen-data.import.vendor.preview');
        Route::post('/manajemen-data/import/vendor/{import}/konfirmasi', [VendorImportController::class, 'konfirmasi'])->name('manajemen-data.import.vendor.konfirmasi');
        Route::delete('/manajemen-data/import/vendor/{import}', [VendorImportController::class, 'batalkan'])->name('manajemen-data.import.vendor.batalkan');

        // Import Data PKPT: pilih Tahun Anggaran, unggah berkas PKPT ->
        // staging (preview/dry-run) -> konfirmasi simpan. Baris dikenali dari
        // Tahun + Unit Kerja + Nomor.
        Route::get('/manajemen-data/import/pkpt', [PkptImportController::class, 'create'])->name('manajemen-data.import.pkpt.create');
        Route::get('/manajemen-data/import/pkpt/template', [PkptImportController::class, 'template'])->name('manajemen-data.import.pkpt.template');
        Route::post('/manajemen-data/import/pkpt', [PkptImportController::class, 'store'])->name('manajemen-data.import.pkpt.store');
        Route::get('/manajemen-data/import/pkpt/{import}/preview', [PkptImportController::class, 'preview'])->name('manajemen-data.import.pkpt.preview');
        Route::post('/manajemen-data/import/pkpt/{import}/konfirmasi', [PkptImportController::class, 'konfirmasi'])->name('manajemen-data.import.pkpt.konfirmasi');
        Route::delete('/manajemen-data/import/pkpt/{import}', [PkptImportController::class, 'batalkan'])->name('manajemen-data.import.pkpt.batalkan');

        // Import Data Gaji & Tunjangan: pilih jenis + bulan + tahun, unggah
        // berkas SIPD apa adanya -> staging (preview/dry-run) -> konfirmasi.
        // Konfirmasi MENIMPA seluruh data periode yang sama.
        Route::get('/manajemen-data/import/gaji-tunjangan', [GajiTunjanganImportController::class, 'create'])->name('gaji-tunjangan.import.create');
        Route::post('/manajemen-data/import/gaji-tunjangan', [GajiTunjanganImportController::class, 'store'])->name('gaji-tunjangan.import.store');
        Route::get('/manajemen-data/import/gaji-tunjangan/{import}/preview', [GajiTunjanganImportController::class, 'preview'])->name('gaji-tunjangan.import.preview');
        Route::post('/manajemen-data/import/gaji-tunjangan/{import}/konfirmasi', [GajiTunjanganImportController::class, 'konfirmasi'])->name('gaji-tunjangan.import.konfirmasi');
        Route::delete('/manajemen-data/import/gaji-tunjangan/{import}', [GajiTunjanganImportController::class, 'batalkan'])->name('gaji-tunjangan.import.batalkan');
    });

});
