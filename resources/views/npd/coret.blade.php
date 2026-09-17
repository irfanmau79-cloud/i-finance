@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Coret Dokumen Nota Pencairan Dana')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / Verifikasi NPD / <b>Coret Dokumen NPD</b></div>
        <div class="ph-title">Coret Dokumen Nota Pencairan Dana &mdash; {{ $npd->nomor_lengkap ?? 'Belum bernomor (masih Draft)' }}</div>
    </div>
</div>

<div class="dash-card">
    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Gagal memproses aksi:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Bilah alat menempel di atas saat halaman digulung: dokumennya panjang,
         dan alat yang hilang dari layar memaksa pemakai menggulung balik
         setiap kali ganti warna atau mode. --}}
    <div class="ct-bar" id="ct-bar">
        <div class="ct-grup" role="group" aria-label="Mode alat">
            {{-- GESER adalah mode awal, dan itu keputusan yang menentukan:
                 dengan mode mencoret sebagai mode awal, satu gerakan menggulung
                 halaman dengan tetikus yang tertekan sudah meninggalkan
                 coretan di dokumen yang akan ditandatangani. --}}
            <button type="button" class="ct-mode aktif" data-mode="geser" title="Geser / gulung dokumen (tidak mencoret)">
                <svg viewBox="0 0 24 24"><path d="M18 11V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2"/><path d="M14 10V4a2 2 0 0 0-2-2 2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2 2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>
                <span>Geser</span>
            </button>
            <button type="button" class="ct-mode" data-mode="coret" title="Coret teks + catatan (klik satu kata, atau tarik untuk beberapa kata)">
                <svg viewBox="0 0 24 24"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                <span>Coret + Catatan</span>
            </button>
            <button type="button" class="ct-mode" data-mode="pena" title="Pena (coret bebas)">
                <svg viewBox="0 0 24 24"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                <span>Pena</span>
            </button>
            <button type="button" class="ct-mode" data-mode="stabilo" title="Stabilo (sorot tulisan, tembus pandang)">
                <svg viewBox="0 0 24 24"><path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
                <span>Stabilo</span>
            </button>
            <button type="button" class="ct-mode" data-mode="teks" title="Tulis teks di dokumen">
                <svg viewBox="0 0 24 24"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                <span>Teks</span>
            </button>
            <button type="button" class="ct-mode" data-mode="sticky" title="Tempel catatan sticky">
                <svg viewBox="0 0 24 24"><path d="M15.5 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5z"/><polyline points="15 3 15 9 21 9"/></svg>
                <span>Sticky</span>
            </button>
            <button type="button" class="ct-mode" data-mode="hapus" title="Hapus satu coretan (klik coretannya)">
                <svg viewBox="0 0 24 24"><path d="M20 20H7L3 16a2 2 0 0 1 0-2.8l7.6-7.6a2 2 0 0 1 2.8 0l6.2 6.2a2 2 0 0 1 0 2.8L13 20"/><line x1="18" y1="20" x2="21" y2="20"/></svg>
                <span>Hapus</span>
            </button>
        </div>

        <span class="ct-pisah" aria-hidden="true"></span>

        <div class="ct-grup ct-warna" role="group" aria-label="Warna">
            @foreach (['#e11d48' => 'Merah', '#1d4ed8' => 'Biru', '#15803d' => 'Hijau', '#111827' => 'Hitam', '#f59e0b' => 'Kuning'] as $hex => $nama)
                <button type="button" class="ct-swatch{{ $hex === '#e11d48' ? ' aktif' : '' }}" data-warna="{{ $hex }}"
                        style="--sw:{{ $hex }}" title="{{ $nama }}" aria-label="Warna {{ $nama }}"></button>
            @endforeach
            <input type="color" id="ct-warna-lain" value="#e11d48" title="Warna lain">
        </div>

        <span class="ct-pisah" aria-hidden="true"></span>

        <div class="ct-grup ct-tebal" role="group" aria-label="Ketebalan">
            <button type="button" class="ct-tb" data-tebal="1" title="Tipis"><i style="height:2px"></i></button>
            <button type="button" class="ct-tb aktif" data-tebal="2" title="Sedang"><i style="height:4px"></i></button>
            <button type="button" class="ct-tb" data-tebal="3" title="Tebal"><i style="height:7px"></i></button>
        </div>

        <span class="ct-pisah" aria-hidden="true"></span>

        <div class="ct-grup" role="group" aria-label="Perbesaran">
            <button type="button" class="ic-btn" id="ct-zoom-keluar" title="Perkecil">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="20" y1="20" x2="16.7" y2="16.7"/></svg>
            </button>
            <span class="ct-zoom-nilai" id="ct-zoom-nilai">80%</span>
            <button type="button" class="ic-btn" id="ct-zoom-masuk" title="Perbesar">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="20" y1="20" x2="16.7" y2="16.7"/></svg>
            </button>
        </div>

        <span class="ct-pisah" aria-hidden="true"></span>

        <div class="ct-grup">
            <button type="button" class="ic-btn" id="ct-undo" title="Batalkan coretan terakhir">
                <svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            </button>
            <button type="button" class="ic-btn danger" id="ct-bersih" title="Hapus semua coretan baru">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
        </div>

        <span class="ct-status" id="ct-status">Memuat dokumen&hellip;</span>
    </div>

    <div class="ct-petunjuk" id="ct-petunjuk">Mode <b>Geser</b> aktif &mdash; dokumen aman dari coretan. Pilih <b>Coret + Catatan</b>, <b>Pena</b>, <b>Stabilo</b>, <b>Teks</b>, atau <b>Sticky</b> untuk mulai mencoret.</div>

    <div id="ct-daftar"></div>

    <form method="POST" action="{{ route('npd.transisi', $npd) }}" id="coret-form" style="margin-top:16px;max-width:560px;">
        @csrf
        <input type="hidden" name="aksi" value="kembali_bpp">
        <input type="hidden" name="coretan_json" id="coret-json-field" value="">

        <label class="fl">Catatan Revisi (wajib)</label>
        <textarea name="catatan" id="coret-catatan" rows="3" required style="width:100%;box-sizing:border-box;">{{ old('catatan') }}</textarea>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <a class="btn" href="{{ route('npd.show', $npd) }}">Batal</a>
            <button type="submit" class="btn prim">Kembalikan ke BPP</button>
        </div>
    </form>
</div>

{{-- Kotak isian teks/sticky. Satu kotak dipakai bersama kedua mode dan
     dipindahkan ke titik yang diklik, bukan prompt() peramban: prompt tidak
     bisa memuat beberapa baris, dan tidak terlihat sebagai bagian dokumen. --}}
<div class="ct-pop" id="ct-pop" hidden>
    <div class="ct-pop-judul" id="ct-pop-judul">Tulis teks</div>
    {{-- Hanya untuk Coret + Catatan: teks yang dicoret, supaya jelas catatannya menyangkut apa. --}}
    <div class="ct-pop-kutip" id="ct-pop-kutip" hidden></div>
    <textarea id="ct-pop-teks" rows="3" maxlength="600" placeholder="Ketik catatan&hellip;"></textarea>
    <div class="ct-pop-aksi" style="justify-content:space-between;">
        {{-- Hapus hanya muncul saat menyunting catatan yang sudah ada. --}}
        <button type="button" class="btn danger" id="ct-pop-hapus" hidden>Hapus</button>
        <button type="button" class="btn" id="ct-pop-batal">Batal</button>
        <button type="button" class="btn prim" id="ct-pop-tempel">Tempel</button>
    </div>
</div>

<style>
  .ct-bar{position:sticky;top:calc(var(--tb-h) + 4px);z-index:30;display:flex;flex-wrap:wrap;
    align-items:center;gap:var(--sp-2);padding:var(--sp-3);margin-bottom:var(--sp-3);
    border:1px solid var(--line);border-radius:var(--r-md);background:var(--surface);
    box-shadow:var(--shadow);}
  .ct-grup{display:flex;align-items:center;gap:4px;}
  .ct-pisah{width:1px;align-self:stretch;background:var(--line);margin:0 2px;}

  .ct-mode{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border:1px solid var(--line);
    border-radius:var(--r-sm);background:var(--surface);color:var(--ink);font-size:12.5px;
    font-weight:600;cursor:pointer;transition:.15s;}
  .ct-mode svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;
    stroke-linecap:round;stroke-linejoin:round;}
  .ct-mode:hover{border-color:var(--aksen);color:var(--aksen-d);}
  .ct-mode.aktif{background:var(--navy);border-color:var(--navy);color:#fff;}
  .ct-mode.aktif:hover{color:#fff;}
  @media(max-width:1180px){.ct-mode span{display:none;}.ct-mode{padding:7px 9px;}}

  .ct-swatch{width:22px;height:22px;padding:0;border-radius:50%;border:2px solid var(--line);
    background:var(--sw);cursor:pointer;transition:.15s;}
  .ct-swatch:hover{transform:scale(1.12);}
  /* Warna terpilih ditandai CINCIN di luar bulatannya, bukan dengan
     mengubah warnanya - warnanya sendiri adalah informasinya. */
  .ct-swatch.aktif{border-color:var(--surface);box-shadow:0 0 0 2px var(--tegas);}
  .ct-warna input[type=color]{width:26px;height:26px;padding:0;border:1px solid var(--line);
    border-radius:var(--r-sm);background:none;cursor:pointer;}

  .ct-tb{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;
    padding:0;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface);cursor:pointer;}
  .ct-tb i{display:block;width:16px;border-radius:99px;background:var(--ink);}
  .ct-tb.aktif{border-color:var(--navy);background:var(--navy-l);}
  .ct-zoom-nilai{min-width:44px;text-align:center;font-size:12.5px;font-weight:700;color:var(--tegas);
    font-variant-numeric:tabular-nums;}
  .ct-status{margin-left:auto;font-size:12.5px;color:var(--mut);}

  .ct-petunjuk{font-size:12.5px;color:var(--mut);background:var(--aksen-l);border:1px solid var(--aksen-garis);
    border-radius:var(--r-sm);padding:8px 12px;margin-bottom:var(--sp-4);}
  .ct-petunjuk b{color:var(--tegas);}

  .ct-dok{margin-bottom:var(--sp-6);}
  .ct-dok-judul{display:flex;align-items:center;gap:var(--sp-2);margin-bottom:var(--sp-2);
    font-size:14px;font-weight:700;color:var(--tegas);}
  .ct-dok-jml{font-size:11px;font-weight:700;color:var(--mut);background:var(--surface-3);
    border-radius:999px;padding:2px 8px;}
  /* Kanvas dokumen dibungkus wadah yang menggulung SENDIRI dan dibatasi
     tingginya: dokumen F4 pada 100% masih lebih tinggi dari layar, dan
     halaman yang ikut menggulung membuat bilah alat menjauh dari kanvas. */
  .ct-kertas{display:flex;flex-direction:column;align-items:center;gap:var(--sp-4);
    background:var(--surface-3);border:1px solid var(--line);border-radius:var(--r-md);
    padding:var(--sp-4);max-height:76vh;overflow:auto;}
  .ct-halaman{position:relative;background:var(--kertas);box-shadow:0 2px 10px rgba(16,29,57,.18);}
  .ct-halaman canvas{position:absolute;top:0;left:0;}
  .ct-halaman canvas.ct-latar{position:relative;display:block;}
  /* Kanvas coretan hanya menerima tetikus saat mode mencoret aktif. Dengan
     pointer-events:none, gerakan menggulung & memilih teks jatuh ke wadah di
     bawahnya - itulah mode Geser, tanpa perlu logika pan sendiri. */
  .ct-halaman canvas.ct-coret{pointer-events:none;touch-action:none;}
  body[data-ct-mode="pena"] .ct-halaman canvas.ct-coret,
  body[data-ct-mode="stabilo"] .ct-halaman canvas.ct-coret{pointer-events:auto;cursor:crosshair;}
  body[data-ct-mode="teks"] .ct-halaman canvas.ct-coret,
  body[data-ct-mode="sticky"] .ct-halaman canvas.ct-coret{pointer-events:auto;cursor:copy;}
  body[data-ct-mode="hapus"] .ct-halaman canvas.ct-coret{pointer-events:auto;cursor:pointer;}
  body[data-ct-mode="coret"] .ct-halaman canvas.ct-coret{pointer-events:auto;cursor:default;}
  /* Halaman selain 1 tidak bisa dicoret - lihat CoretanPdf. */
  .ct-halaman.ct-terkunci canvas.ct-coret{pointer-events:none !important;}
  .ct-hal-label{text-align:center;font-size:11.5px;color:var(--mut);margin-top:6px;}

  .ct-pop{position:fixed;z-index:200;width:280px;padding:var(--sp-3);border:1px solid var(--line);
    border-radius:var(--r-md);background:var(--surface);box-shadow:var(--shadow-float);}
  .ct-pop-judul{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;
    color:var(--mut);margin-bottom:6px;}
  .ct-pop textarea{width:100%;box-sizing:border-box;font-size:13px;}
  .ct-pop-kutip{font-size:12.5px;color:var(--ink);background:var(--surface-3);border-radius:var(--r-sm);
    padding:6px 8px;margin-bottom:8px;max-height:72px;overflow:auto;text-decoration:line-through;
    text-decoration-color:var(--err);}
  .ct-pop-aksi{display:flex;justify-content:flex-end;gap:6px;margin-top:8px;}
</style>

<script type="module">
import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.mjs';

const dokumenList = @json($dokumenList);
const strokesSebelumnya = @json($strokesSebelumnya);

const daftarEl = document.getElementById('ct-daftar');
const statusEl = document.getElementById('ct-status');
const petunjukEl = document.getElementById('ct-petunjuk');
const popEl = document.getElementById('ct-pop');
const popTeksEl = document.getElementById('ct-pop-teks');
const popJudulEl = document.getElementById('ct-pop-judul');
const popTempelEl = document.getElementById('ct-pop-tempel');
const popHapusEl = document.getElementById('ct-pop-hapus');
const popKutipEl = document.getElementById('ct-pop-kutip');
const catatanEl = document.getElementById('coret-catatan');

/* Lebar kertas dalam mm yang dipakai PDF saat menyisipkan coretan (lihat
   NpdController::sisipkanCoretan). Ukuran lencana & tebal garis Coret +
   Catatan dihitung dalam mm di PDF, jadi layar memakai konversi yang sama. */
const LEBAR_KERTAS_MM = 215;

/* Lebar acuan kertas di layar pada zoom 100%. Jauh lebih kecil daripada
   1.5x skala PDF yang dipakai sebelumnya: dokumen selebar layar membuat
   setiap gerakan tetikus mendarat di atas kertas, dan itu sumber coretan
   yang tidak disengaja. */
const LEBAR_ACUAN = 640;
const ZOOM_PILIHAN = [0.6, 0.7, 0.8, 0.9, 1, 1.2, 1.4];

const state = {
    mode: 'geser',
    warna: '#e11d48',
    tebal: 2,
    zoomIdx: 2, // 0.8
};

/* Coretan BARU pada kunjungan ini. Coretan lama tidak ada di sini dan tidak
   digambar ulang: pratinjau PDF diambil dari rute cetak yang sudah
   menyisipkan coretan lama ke dalam dokumennya, jadi ia sudah terlihat
   sebagai bagian latar. Itu juga alasan mode Hapus hanya bisa menghapus
   coretan baru - yang lama sudah menyatu dengan dokumen yang dikembalikan
   sebelumnya. */
let butirBaru = [];
const halamanState = [];

/* Coret + Catatan dinomori MENERUS di semua dokumen, melanjutkan nomor dari
   pengembalian sebelumnya - sama persis dengan CoretanPdf::nomorCoret. */
const jumlahCoretLama = strokesSebelumnya.filter(function (b) {
    return b && b.jenis === 'coret' && Array.isArray(b.kotak) && b.kotak.length;
}).length;

function nomorCoret(butir) {
    let urut = jumlahCoretLama;
    for (let i = 0; i < butirBaru.length; i++) {
        if (butirBaru[i].jenis === 'coret') urut++;
        if (butirBaru[i] === butir) return urut;
    }
    return urut;
}

/* Keadaan sementara Coret + Catatan: kata di bawah penunjuk, dan rentang
   kata yang sedang ditarik. Keduanya hanya pratinjau - butirnya baru lahir
   saat catatannya disimpan. */
let hoverKata = null;   // { hs, indeks }
let tarikKata = null;   // { hs, awal, akhir }

const TEBAL_PENA = { 1: 1.5, 2: 3, 3: 6 };       // px pada lebar acuan
const TEBAL_STABILO = { 1: 10, 2: 16, 3: 24 };
const UKURAN_TEKS = { 1: 11, 2: 14, 3: 18 };     // px pada lebar acuan

/* Kertas tempel BERUKURAN TETAP: lebarnya sekian bagian dari lebar halaman,
   tingginya 1,5 kali lebarnya (perbandingan tinggi:lebar 3:2).

   Angkanya DIAMBIL DARI PHP, bukan ditulis ulang di sini: layar dan PDF
   wajib memakai ukuran yang sama, dan dua tetapan kembar dengan komentar
   "harus sama" adalah cara paling lazim keduanya diam-diam berbeda. */
const STICKY_LEBAR = {{ \App\Support\CoretanPdf::STICKY_LEBAR }};
const STICKY_RASIO = {{ \App\Support\CoretanPdf::STICKY_RASIO }};
/* Hurufnya lebih kecil daripada mode Teks: kertasnya sempit dan tetap, jadi
   huruf sebesar teks lepas hanya memuat tiga-empat kata per baris. */
const STICKY_UKURAN = { 1: 8.5, 2: 10.5, 3: 13 };

/* ------------------------------------------------------------- bilah alat */

function setMode(mode) {
    state.mode = mode;
    document.body.dataset.ctMode = mode;
    document.querySelectorAll('.ct-mode').forEach(function (b) {
        b.classList.toggle('aktif', b.dataset.mode === mode);
    });
    tutupPop();
    hoverKata = null;
    tarikKata = null;
    gambarUlang();

    const pesan = {
        geser: 'Mode <b>Geser</b> aktif &mdash; dokumen aman dari coretan. Pilih <b>Coret + Catatan</b>, <b>Pena</b>, <b>Stabilo</b>, <b>Teks</b>, atau <b>Sticky</b> untuk mulai mencoret.',
        coret: 'Mode <b>Coret + Catatan</b> &mdash; klik satu kata, atau tekan lalu tarik untuk beberapa kata. Tulisannya langsung tercoret dan Anda bisa menambahkan catatan. Klik coretan yang sudah ada untuk mengubah catatannya.',
        pena: 'Mode <b>Pena</b> &mdash; tekan lalu tarik di atas dokumen untuk mencoret.',
        stabilo: 'Mode <b>Stabilo</b> &mdash; sapu di atas tulisan; warnanya tembus pandang sehingga tulisan tetap terbaca.',
        teks: 'Mode <b>Teks</b> &mdash; klik di dokumen, lalu ketik teksnya.',
        sticky: 'Mode <b>Sticky</b> &mdash; klik di dokumen untuk menempel catatan; tarik catatan yang sudah ada untuk memindahkannya, atau klik sekali untuk mengubah isinya.',
        hapus: 'Mode <b>Hapus</b> &mdash; klik satu coretan baru untuk menghapusnya. Coretan dari pengembalian sebelumnya sudah menyatu dengan dokumen dan tidak bisa dihapus di sini.',
    };
    petunjukEl.innerHTML = pesan[mode];
}

document.querySelectorAll('.ct-mode').forEach(function (btn) {
    btn.addEventListener('click', function () { setMode(btn.dataset.mode); });
});

document.querySelectorAll('.ct-swatch').forEach(function (btn) {
    btn.addEventListener('click', function () {
        state.warna = btn.dataset.warna;
        document.getElementById('ct-warna-lain').value = state.warna;
        document.querySelectorAll('.ct-swatch').forEach(function (b) {
            b.classList.toggle('aktif', b === btn);
        });
    });
});

document.getElementById('ct-warna-lain').addEventListener('input', function (e) {
    state.warna = e.target.value;
    document.querySelectorAll('.ct-swatch').forEach(function (b) { b.classList.remove('aktif'); });
});

document.querySelectorAll('.ct-tb').forEach(function (btn) {
    btn.addEventListener('click', function () {
        state.tebal = Number(btn.dataset.tebal);
        document.querySelectorAll('.ct-tb').forEach(function (b) { b.classList.toggle('aktif', b === btn); });
    });
});

document.getElementById('ct-undo').addEventListener('click', function () {
    butirBaru.pop();
    gambarUlang();
});

document.getElementById('ct-bersih').addEventListener('click', function () {
    if (butirBaru.length && ! window.confirm('Hapus semua coretan baru pada kunjungan ini?')) return;
    butirBaru = [];
    gambarUlang();
});

document.getElementById('ct-zoom-masuk').addEventListener('click', function () { ubahZoom(1); });
document.getElementById('ct-zoom-keluar').addEventListener('click', function () { ubahZoom(-1); });

function ubahZoom(arah) {
    const baru = Math.min(ZOOM_PILIHAN.length - 1, Math.max(0, state.zoomIdx + arah));
    if (baru === state.zoomIdx) return;
    state.zoomIdx = baru;
    document.getElementById('ct-zoom-nilai').textContent = Math.round(zoom() * 100) + '%';
    terapkanZoom();
}

function zoom() { return ZOOM_PILIHAN[state.zoomIdx]; }

/* Zoom hanya mengubah ukuran TAMPIL kanvas lewat CSS, bukan me-render ulang
   PDF-nya. Koordinat coretan tersimpan relatif (0..1), jadi tidak ada satu
   pun coretan yang perlu dihitung ulang saat diperbesar. */
function terapkanZoom() {
    halamanState.forEach(function (hs) {
        const lebar = hs.lebarAsli * zoom();
        hs.wrap.style.width = lebar + 'px';
        hs.wrap.style.height = (lebar / hs.rasio) + 'px';
        [hs.latar, hs.canvas].forEach(function (c) {
            c.style.width = lebar + 'px';
            c.style.height = (lebar / hs.rasio) + 'px';
        });
    });
}

/* ------------------------------------------------------- menggambar kanvas */

function gambarUlang() {
    halamanState.forEach(function (hs) {
        hs.ctx.clearRect(0, 0, hs.canvas.width, hs.canvas.height);
        butirBaru
            .filter(function (b) { return b.dokumen === hs.dokumen && b.page === hs.pageNumber; })
            .forEach(function (b) { gambarButir(hs, b); });
        gambarPratinjauCoret(hs);
    });
    sinkronCatatan();
}

/**
 * Pratinjau Coret + Catatan di atas kanvas: kotak putus-putus pada kata di
 * bawah penunjuk, dan garis coret samar pada rentang yang sedang ditarik
 * atau yang catatannya sedang diisi (belum disimpan).
 */
function gambarPratinjauCoret(hs) {
    const ctx = hs.ctx, W = hs.canvas.width, H = hs.canvas.height;

    let kotak = null;
    if (tarikKata && tarikKata.hs === hs) {
        kotak = kotakRentang(hs, tarikKata.awal, tarikKata.akhir).kotak;
    } else if (popTarget && popTarget.jenis === 'coret' && popTarget.hs === hs) {
        kotak = popTarget.kotak;
    }

    if (kotak) {
        ctx.save();
        ctx.fillStyle = state.warna;
        ctx.globalAlpha = 0.12;
        kotak.forEach(function (k) { ctx.fillRect(k[0] * W, k[1] * H, k[2] * W, k[3] * H); });
        ctx.globalAlpha = 0.6;
        gambarGarisCoret(hs, kotak, state.warna);
        ctx.restore();
        return;
    }

    if (hoverKata && hoverKata.hs === hs && ! popTarget) {
        const k = hs.kata[hoverKata.indeks];
        ctx.save();
        ctx.strokeStyle = state.warna;
        ctx.globalAlpha = 0.7;
        ctx.lineWidth = Math.max(1, W / 900);
        ctx.setLineDash([4, 3]);
        ctx.strokeRect(k.x * W - 2, k.y * H - 1, k.w * W + 4, k.h * H + 2);
        ctx.restore();
    }
}

function pxPerMm(hs) { return hs.canvas.width / LEBAR_KERTAS_MM; }

/** Garis coret di tengah tinggi huruf tiap baris - sama dengan CoretanPdf::garisCoret. */
function gambarGarisCoret(hs, kotak, warna) {
    const ctx = hs.ctx, W = hs.canvas.width, H = hs.canvas.height, mm = pxPerMm(hs);
    ctx.save();
    ctx.strokeStyle = warna;
    ctx.lineCap = 'butt';
    kotak.forEach(function (k) {
        const tinggiMm = k[3] * H / mm;
        const tengah = (k[1] + k[3] * 0.5) * H;
        ctx.lineWidth = Math.max(0.2, Math.min(1.2, tinggiMm * 0.09)) * mm;
        ctx.beginPath();
        ctx.moveTo(k[0] * W, tengah);
        ctx.lineTo((k[0] + k[2]) * W, tengah);
        ctx.stroke();
    });
    ctx.restore();
}

/** Geometri lencana nomor dalam px kanvas - sama dengan CoretanPdf::lencanaCoret. */
function geometriLencana(hs, butir) {
    const W = hs.canvas.width, H = hs.canvas.height, mm = pxPerMm(hs);
    const k = butir.kotak[butir.kotak.length - 1];
    const d = Math.max(3.2 * mm, Math.min(6 * mm, k[3] * H * 1.15));
    return {
        x: Math.min(W - d, (k[0] + k[2]) * W + 0.6 * mm),
        y: Math.max(0, (k[1] + k[3] * 0.5) * H - d / 2),
        d: d,
    };
}

function gambarLencana(hs, butir) {
    const ctx = hs.ctx, g = geometriLencana(hs, butir), nomor = nomorCoret(butir);
    ctx.save();
    ctx.fillStyle = butir.color;
    ctx.beginPath();
    ctx.arc(g.x + g.d / 2, g.y + g.d / 2, g.d / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.font = '700 ' + (g.d * (nomor > 9 ? 0.52 : 0.66)) + 'px Arial,sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(String(nomor), g.x + g.d / 2, g.y + g.d / 2 + g.d * 0.03);
    ctx.restore();
}

/* ----------------------------------------------- Catatan Revisi otomatis */

/* Daftar Coret + Catatan kunjungan ini ikut dituliskan ke kotak Catatan
   Revisi, supaya BPP membacanya di histori tanpa membuka PDF. Bagian itu
   diperbarui otomatis selama Verifikator tidak menyuntingnya sendiri;
   begitu disunting, isi kotak dibiarkan apa adanya. */
let catatanOtomatis = '';
let catatanOtomatisMati = false;

catatanEl.addEventListener('input', function () {
    if (catatanOtomatis && catatanEl.value.indexOf(catatanOtomatis) === -1) catatanOtomatisMati = true;
});

function ringkasanCoret() {
    const label = {};
    dokumenList.forEach(function (d) { label[d.key] = d.label; });

    const baris = butirBaru.filter(function (b) { return b.jenis === 'coret'; }).map(function (b) {
        const teks = b.teks.length > 80 ? b.teks.slice(0, 77) + '…' : b.teks;
        return nomorCoret(b) + '. [' + (label[b.dokumen] || b.dokumen) + '] "' + teks + '"'
            + (b.catatan ? ' — ' + b.catatan.replace(/\s*\n\s*/g, ' ') : '');
    });

    return baris.length ? 'Coretan pada dokumen:\n' + baris.join('\n') : '';
}

function sinkronCatatan() {
    if (catatanOtomatisMati) return;
    const baru = ringkasanCoret();
    if (baru === catatanOtomatis) return;

    let nilai = catatanEl.value;
    if (catatanOtomatis && nilai.indexOf(catatanOtomatis) !== -1) {
        nilai = nilai.replace(catatanOtomatis, baru);
    } else if (baru) {
        nilai = nilai.trim() ? nilai.replace(/\s+$/, '') + '\n\n' + baru : baru;
    }
    catatanEl.value = nilai.replace(/\s+$/, '');
    catatanOtomatis = baru;
}

/* ------------------------------------------------ kata-kata di dalam PDF */

/**
 * Kotak setiap KATA pada halaman, dibaca dari lapisan teks PDF (pdf.js).
 *
 * mPDF menulis teks per potongan - satu sel tabel atau satu baris - lengkap
 * dengan posisi dan lebarnya, tapi tidak per kata. Letak tiap kata di dalam
 * potongannya dihitung dengan mengukur teksnya di kanvas lalu diskalakan ke
 * lebar asli potongan itu, jadi selisih bentuk huruf antar-font saling
 * meniadakan.
 *
 * Kotak disimpan relatif halaman: y = batas atas huruf (garis dasar - 0,78
 * ukuran huruf), tinggi = ukuran huruf, sehingga tengahnya jatuh di tengah
 * huruf kecil. Hasilnya diurutkan per baris lalu dari kiri ke kanan - itu
 * urutan yang dipakai saat menarik rentang.
 *
 * Tiap kata juga diberi nomor BLOK: potongan-potongan yang rata kirinya sama
 * dan barisnya bersambung (sel tabel yang terbungkus dua baris, nilai Sub
 * Kegiatan yang panjang) dianggap satu blok. Tanpa itu, menarik dari awal
 * sampai akhir isi sel ikut mencoret angka di kolom sebelahnya, karena
 * urutan baca melompat ke sana di antara kedua barisnya.
 */
async function bacaKata(page, viewport) {
    const isi = await page.getTextContent();
    const W = viewport.width, H = viewport.height;
    const ukur = document.createElement('canvas').getContext('2d');
    const kata = [];
    const potongan = [];   // { x, dasar, ukuran, blok } per potongan teks, untuk menyusun blok

    isi.items.forEach(function (item) {
        if (! item.str || ! item.str.trim() || ! item.width) return;

        const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
        // Hanya teks mendatar; teks miring/tegak tidak ada di dokumen NPD.
        if (Math.abs(tx[1]) > 0.01 || Math.abs(tx[2]) > 0.01) return;

        const ukuran = Math.hypot(tx[2], tx[3]);
        const lebar = item.width * viewport.scale;
        if (! ukuran || ! lebar) return;

        ukur.font = ukuran + 'px Arial,sans-serif';
        const skala = lebar / (ukur.measureText(item.str).width || lebar);
        const atas = tx[5] - ukuran * 0.78;

        // Blok: sambung ke potongan sebelumnya yang rata kirinya sama dan
        // tepat satu baris di atasnya; kalau tidak ada, blok baru.
        const induk = potongan.find(function (pt) {
            const jarak = tx[5] - pt.dasar;
            return Math.abs(pt.x - tx[4]) < 1.5 && jarak > 0 && jarak <= ukuran * 1.6 && ! pt.bersambung;
        });
        if (induk) induk.bersambung = true;
        const blok = induk ? induk.blok : potongan.length;
        potongan.push({ x: tx[4], dasar: tx[5], blok: blok, bersambung: false });

        const pola = /\S+/g;
        let m;
        while ((m = pola.exec(item.str)) !== null) {
            const x0 = tx[4] + ukur.measureText(item.str.slice(0, m.index)).width * skala;
            const x1 = tx[4] + ukur.measureText(item.str.slice(0, m.index + m[0].length)).width * skala;
            kata.push({ x: x0 / W, y: atas / H, w: (x1 - x0) / W, h: ukuran / H, dasar: tx[5] / H, teks: m[0], blok: blok });
        }
    });

    kata.sort(function (a, b) {
        return Math.abs(a.dasar - b.dasar) > a.h * 0.35 ? a.dasar - b.dasar : a.x - b.x;
    });

    // Nomor baris: kata yang garis dasarnya berdekatan dianggap satu baris.
    let baris = -1, dasarBaris = -1;
    kata.forEach(function (k) {
        if (baris < 0 || Math.abs(k.dasar - dasarBaris) > k.h * 0.35) { baris++; dasarBaris = k.dasar; }
        k.baris = baris;
    });

    return kata;
}

/**
 * Kata pada titik (x,y) relatif. Dengan $toleran, kata TERDEKAT pun diterima
 * - dipakai saat menarik supaya penunjuk yang sedikit meleset dari tulisan
 * tidak memutus rentangnya.
 */
function kataDi(hs, x, y, toleran) {
    const W = hs.canvas.width, H = hs.canvas.height;
    const px = x * W, py = y * H;
    let terbaik = -1, jarakTerbaik = Infinity;

    (hs.kata || []).forEach(function (k, i) {
        const dx = Math.max(k.x * W - px, 0, px - (k.x + k.w) * W);
        const dy = Math.max(k.y * H - py, 0, py - (k.y + k.h) * H);
        const jarak = Math.hypot(dx, dy * 2);   // meleset tegak lebih mahal: pindah baris
        if (jarak < jarakTerbaik) { jarakTerbaik = jarak; terbaik = i; }
    });

    if (terbaik < 0) return -1;
    const k = hs.kata[terbaik];
    const batas = toleran ? Infinity : Math.max(3, k.h * H * 0.25);
    return jarakTerbaik <= batas ? terbaik : -1;
}

/** Kotak per baris dan teks gabungan untuk rentang kata [awal..akhir] (urutan bebas). */
function kotakRentang(hs, awal, akhir) {
    const dari = Math.min(awal, akhir), sampai = Math.max(awal, akhir);
    const perBaris = new Map();

    // Awal dan akhir di blok yang sama (mis. satu sel tabel) = hanya kata
    // milik blok itu yang tercoret, bukan kolom lain yang terselip di antaranya.
    const blok = hs.kata[dari].blok === hs.kata[sampai].blok ? hs.kata[dari].blok : null;

    hs.kata.slice(dari, sampai + 1).filter(function (k) {
        return blok === null || k.blok === blok;
    }).forEach(function (k) {
        const b = perBaris.get(k.baris);
        if (! b) {
            perBaris.set(k.baris, { x0: k.x, x1: k.x + k.w, y0: k.y, y1: k.y + k.h, teks: [k.teks] });
            return;
        }
        b.x0 = Math.min(b.x0, k.x);
        b.x1 = Math.max(b.x1, k.x + k.w);
        b.y0 = Math.min(b.y0, k.y);
        b.y1 = Math.max(b.y1, k.y + k.h);
        b.teks.push(k.teks);
    });

    const bulat = function (n) { return Math.round(n * 100000) / 100000; };
    const kotak = [], teks = [];
    perBaris.forEach(function (b) {
        kotak.push([bulat(b.x0), bulat(b.y0), bulat(b.x1 - b.x0), bulat(b.y1 - b.y0)]);
        teks.push(b.teks.join(' '));
    });

    return { kotak: kotak, teks: teks.join(' ') };
}

/** Indeks butir Coret + Catatan baru yang garisnya atau lencananya kena titik (x,y). */
function coretDiTitik(hs, x, y) {
    const W = hs.canvas.width, H = hs.canvas.height;
    const px = x * W, py = y * H;

    for (let i = butirBaru.length - 1; i >= 0; i--) {
        const b = butirBaru[i];
        if (b.jenis !== 'coret' || b.dokumen !== hs.dokumen || b.page !== hs.pageNumber) continue;

        const g = geometriLencana(hs, b);
        if (px >= g.x && px <= g.x + g.d && py >= g.y && py <= g.y + g.d) return i;

        for (let j = 0; j < b.kotak.length; j++) {
            const k = b.kotak[j];
            if (px >= k[0] * W - 2 && px <= (k[0] + k[2]) * W + 2 && py >= k[1] * H - 2 && py <= (k[1] + k[3]) * H + 2) return i;
        }
    }
    return -1;
}

function gambarButir(hs, butir) {
    const ctx = hs.ctx;
    const W = hs.canvas.width;
    const H = hs.canvas.height;

    if (butir.jenis === 'teks') {
        ctx.save();
        ctx.fillStyle = butir.color;
        ctx.font = '700 ' + (butir.ukuran * W) + 'px system-ui,Segoe UI,Arial,sans-serif';
        ctx.textBaseline = 'top';
        bungkusTeks(ctx, butir.teks, W - butir.x * W - 8).forEach(function (baris, i) {
            ctx.fillText(baris, butir.x * W, butir.y * H + i * butir.ukuran * W * 1.25);
        });
        ctx.restore();
        return;
    }

    if (butir.jenis === 'sticky') {
        gambarSticky(hs, butir);
        return;
    }

    if (butir.jenis === 'coret') {
        gambarGarisCoret(hs, butir.kotak, butir.color);
        gambarLencana(hs, butir);
        return;
    }

    if (! butir.points || butir.points.length < 2) return;

    ctx.save();
    ctx.strokeStyle = butir.color;
    ctx.lineWidth = butir.width * W;
    if (butir.jenis === 'stabilo') {
        // Sama seperti di PDF: tembus pandang dan berujung persegi.
        ctx.globalAlpha = 0.35;
        ctx.lineCap = 'butt';
        ctx.lineJoin = 'round';
    } else {
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    ctx.beginPath();
    butir.points.forEach(function (p, i) {
        const x = p[0] * W, y = p[1] * H;
        if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
    });
    ctx.stroke();
    ctx.restore();
}

/**
 * Geometri satu kertas tempel dalam piksel kanvas. Satu-satunya tempat
 * ukuran sticky dihitung: digambar, diuji-tabrak, dan digeser memakai
 * angka yang sama, jadi kotak yang terlihat tidak pernah beda dari kotak
 * yang bisa diklik.
 */
function geometriSticky(hs, butir) {
    const W = hs.canvas.width, H = hs.canvas.height;
    const lebar = (butir.lebar || STICKY_LEBAR) * W;
    const tinggi = lebar * STICKY_RASIO;

    return {
        x: butir.x * W, y: butir.y * H,
        lebar: lebar, tinggi: tinggi,
        // Dalam satuan relatif, untuk penjepitan posisi saat digeser.
        lebarRel: lebar / W, tinggiRel: tinggi / H,
        ukuranPx: butir.ukuran * W,
    };
}

/**
 * Kertas tempel bergaya benda nyata: bayangan jatuh, gradien cahaya dari
 * atas, dan sudut kanan-bawah yang terlipat. Digambar dengan urutan yang
 * sama seperti di PDF (lihat CoretanPdf::sticky) supaya pratinjau dan hasil
 * cetak tidak berbeda bentuk.
 */
function gambarSticky(hs, butir) {
    const ctx = hs.ctx;
    const g = geometriSticky(hs, butir);
    const padding = g.lebar * 0.08;
    const lipat = g.lebar * 0.2;

    ctx.save();

    // 1. Bayangan jatuh. Di layar boleh diburamkan - di PDF tidak bisa, jadi
    //    di sana dipakai kertas kedua yang digeser; keduanya membaca sama.
    ctx.save();
    ctx.shadowColor = 'rgba(15,23,42,.35)';
    ctx.shadowBlur = g.lebar * 0.09;
    ctx.shadowOffsetX = g.lebar * 0.035;
    ctx.shadowOffsetY = g.lebar * 0.045;
    ctx.fillStyle = '#fef3b0';
    ctx.fillRect(g.x, g.y, g.lebar, g.tinggi);
    ctx.restore();

    // 2. Kertasnya sendiri: gradien kuning muda ke kuning tua.
    const gradien = ctx.createLinearGradient(g.x, g.y, g.x, g.y + g.tinggi);
    gradien.addColorStop(0, '#fefce8');
    gradien.addColorStop(0.55, '#fef3b0');
    gradien.addColorStop(1, '#fde68a');
    ctx.fillStyle = gradien;
    ctx.fillRect(g.x, g.y, g.lebar, g.tinggi);
    ctx.strokeStyle = '#e6c34a';
    ctx.lineWidth = Math.max(1, g.lebar * 0.006);
    ctx.strokeRect(g.x, g.y, g.lebar, g.tinggi);

    // 3. Teksnya, dipotong pada baris yang masih muat - kertasnya tidak
    //    melebar, jadi yang harus mengalah teksnya.
    ctx.font = g.ukuranPx + 'px system-ui,Segoe UI,Arial,sans-serif';
    ctx.fillStyle = '#6b4e12';
    ctx.textBaseline = 'top';
    const tinggiBaris = g.ukuranPx * 1.3;
    const maksBaris = Math.max(1, Math.floor((g.tinggi - padding * 2 - lipat * 0.4) / tinggiBaris));
    const baris = potongBaris(bungkusTeks(ctx, butir.teks, g.lebar - padding * 2), maksBaris);
    baris.forEach(function (satu, i) {
        ctx.fillText(satu, g.x + padding, g.y + padding + i * tinggiBaris);
    });
    // Baris tersimpan supaya PDF memecah di tempat yang SAMA - lebar huruf
    // hanya benar-benar terukur di sini.
    butir.baris = baris;

    // 4. Sudut terlipat: sisi kertas yang terangkat, lalu bidang di baliknya.
    const sx = g.x + g.lebar, sy = g.y + g.tinggi;
    ctx.beginPath();
    ctx.moveTo(sx - lipat, sy);
    ctx.lineTo(sx, sy);
    ctx.lineTo(sx, sy - lipat);
    ctx.closePath();
    ctx.fillStyle = '#e6c34a';
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(sx - lipat, sy);
    ctx.lineTo(sx, sy - lipat);
    ctx.lineTo(sx - lipat, sy - lipat);
    ctx.closePath();
    ctx.fillStyle = '#fbe89a';
    ctx.fill();

    ctx.restore();
}

/** Sisakan baris yang muat; baris terakhir diberi elipsis kalau ada yang dibuang. */
function potongBaris(baris, maks) {
    if (baris.length <= maks) return baris;
    const dipotong = baris.slice(0, maks);
    dipotong[maks - 1] = dipotong[maks - 1].replace(/\s+\S*$/, '') + '…';
    return dipotong;
}

/** Pecah teks jadi baris yang muat pada lebar tertentu (satuan px kanvas). */
function bungkusTeks(ctx, teks, lebarMuat) {
    const hasil = [];
    String(teks || '').split('\n').forEach(function (paragraf) {
        const kata = paragraf.split(/\s+/).filter(Boolean);
        if (! kata.length) { hasil.push(''); return; }
        let baris = kata[0];
        for (let i = 1; i < kata.length; i++) {
            const calon = baris + ' ' + kata[i];
            if (ctx.measureText(calon).width > lebarMuat && baris) {
                hasil.push(baris);
                baris = kata[i];
            } else {
                baris = calon;
            }
        }
        hasil.push(baris);
    });
    return hasil;
}

/* ------------------------------------------------------------ teks & sticky */

/* popTarget menyimpan APA yang sedang diisi: butir baru pada titik tertentu
   ({hs, x, y, jenis}) atau butir yang sudah ada dan sedang disunting
   ({hs, indeks}). Satu kotak isian dipakai keduanya. */
let popTarget = null;

function bukaPop(target, klienX, klienY) {
    popTarget = target;

    const menyunting = typeof target.indeks === 'number';
    const butir = menyunting ? butirBaru[target.indeks] : null;
    const jenis = menyunting ? butir.jenis : target.jenis;

    if (jenis === 'coret') {
        // Coretan dan catatannya satu kesatuan: kotak ini selalu muncul
        // bersama coretannya, catatannya boleh kosong, dan Hapus membuang
        // keduanya sekaligus.
        popJudulEl.textContent = menyunting ? 'Ubah catatan coretan ' + nomorCoret(butir) : 'Coret + catatan';
        popKutipEl.textContent = menyunting ? butir.teks : target.teks;
        popKutipEl.hidden = false;
        popTeksEl.value = menyunting ? (butir.catatan || '') : '';
        popTeksEl.placeholder = 'Catatan (boleh kosong)…';
        popHapusEl.hidden = ! menyunting;
        popTempelEl.textContent = 'Simpan';
    } else {
        popJudulEl.textContent = (menyunting ? 'Ubah ' : 'Tulis ')
            + (jenis === 'sticky' ? 'catatan sticky' : 'teks');
        popKutipEl.hidden = true;
        popTeksEl.value = menyunting ? butir.teks : '';
        popTeksEl.placeholder = 'Ketik catatan…';
        popHapusEl.hidden = ! menyunting;
        popTempelEl.textContent = menyunting ? 'Simpan' : 'Tempel';
    }

    popEl.hidden = false;
    // Dijaga tetap di dalam layar: titik klik bisa berada di tepi kanan/bawah.
    const lebar = 280, tinggi = popEl.offsetHeight || 170;
    popEl.style.left = Math.min(window.innerWidth - lebar - 12, Math.max(12, klienX + 8)) + 'px';
    popEl.style.top = Math.min(window.innerHeight - tinggi - 12, Math.max(12, klienY + 8)) + 'px';
    popTeksEl.focus();
    popTeksEl.select();
}

function tutupPop() {
    const adaPratinjau = popTarget && popTarget.jenis === 'coret';
    popEl.hidden = true;
    popTarget = null;
    // Coretan yang belum disimpan hanya pratinjau - hilangkan dari kanvas.
    if (adaPratinjau) gambarUlang();
}

document.getElementById('ct-pop-batal').addEventListener('click', tutupPop);
popTempelEl.addEventListener('click', tempelPop);
popHapusEl.addEventListener('click', function () {
    if (popTarget && typeof popTarget.indeks === 'number') {
        butirBaru.splice(popTarget.indeks, 1);
        gambarUlang();
    }
    tutupPop();
});
popTeksEl.addEventListener('keydown', function (e) {
    // Enter menempel, Shift+Enter baris baru - kebiasaan kotak catatan.
    if (e.key === 'Enter' && ! e.shiftKey) { e.preventDefault(); tempelPop(); }
    if (e.key === 'Escape') { e.preventDefault(); tutupPop(); }
});

function tempelPop() {
    if (! popTarget) return;
    const teks = popTeksEl.value.trim();

    // Coret + Catatan: catatan kosong tetap sah - coretannya sendiri sudah
    // menyampaikan sesuatu (mis. salah ketik). Membuangnya lewat Hapus.
    const butirSunting = typeof popTarget.indeks === 'number' ? butirBaru[popTarget.indeks] : null;
    if (popTarget.jenis === 'coret' || (butirSunting && butirSunting.jenis === 'coret')) {
        if (butirSunting) {
            butirSunting.catatan = teks;
        } else {
            butirBaru.push({
                dokumen: popTarget.hs.dokumen, page: 1, jenis: 'coret',
                color: state.warna, kotak: popTarget.kotak, teks: popTarget.teks, catatan: teks,
            });
        }
        popTarget = null;
        tutupPop();
        gambarUlang();
        return;
    }

    // Menyunting jadi kosong = membuang catatannya. Kertas tempel tanpa
    // tulisan tidak menyampaikan apa pun, dan kalau dibiarkan ia jadi kotak
    // kuning yang tidak bisa dijelaskan oleh siapa pun yang membacanya.
    if (typeof popTarget.indeks === 'number') {
        if (teks) {
            butirBaru[popTarget.indeks].teks = teks;
        } else {
            butirBaru.splice(popTarget.indeks, 1);
        }
        tutupPop();
        gambarUlang();
        return;
    }

    if (! teks) { tutupPop(); return; }

    const ukuranRel = popTarget.jenis === 'sticky'
        ? STICKY_UKURAN[state.tebal] / LEBAR_ACUAN
        : UKURAN_TEKS[state.tebal] / LEBAR_ACUAN;

    if (popTarget.jenis === 'sticky') {
        butirBaru.push({
            dokumen: popTarget.hs.dokumen, page: 1, jenis: 'sticky',
            x: popTarget.x, y: popTarget.y, lebar: STICKY_LEBAR,
            ukuran: ukuranRel, teks: teks, color: '#6b4e12',
        });
        jepitSticky(popTarget.hs, butirBaru[butirBaru.length - 1]);
    } else {
        butirBaru.push({
            dokumen: popTarget.hs.dokumen, page: 1, jenis: 'teks',
            x: popTarget.x, y: popTarget.y,
            ukuran: ukuranRel, teks: teks, color: state.warna,
        });
    }

    tutupPop();
    gambarUlang();
}

/* ------------------------------------------------- menggeser kertas tempel */

/** Tahan kertas tempel supaya seluruh badannya tetap di dalam halaman. */
function jepitSticky(hs, butir) {
    const g = geometriSticky(hs, butir);
    butir.x = Math.max(0, Math.min(1 - g.lebarRel, butir.x));
    butir.y = Math.max(0, Math.min(1 - g.tinggiRel, butir.y));
}

/** Indeks kertas tempel paling atas yang memuat titik (x,y) relatif. */
function stickyDiTitik(hs, x, y) {
    const W = hs.canvas.width, H = hs.canvas.height;

    for (let i = butirBaru.length - 1; i >= 0; i--) {
        const b = butirBaru[i];
        if (b.jenis !== 'sticky' || b.dokumen !== hs.dokumen || b.page !== hs.pageNumber) continue;

        const g = geometriSticky(hs, b);
        const px = x * W, py = y * H;
        if (px >= g.x && px <= g.x + g.lebar && py >= g.y && py <= g.y + g.tinggi) return i;
    }

    return -1;
}

/* ----------------------------------------------------------------- menghapus */

/** Jarak titik ke segmen garis, dalam satuan relatif. */
function jarakKeSegmen(px, py, ax, ay, bx, by) {
    const dx = bx - ax, dy = by - ay;
    const panjang = dx * dx + dy * dy;
    let t = panjang ? ((px - ax) * dx + (py - ay) * dy) / panjang : 0;
    t = Math.max(0, Math.min(1, t));
    const cx = ax + t * dx, cy = ay + t * dy;
    return Math.hypot(px - cx, py - cy);
}

/**
 * Butir baru yang kena klik pada titik (x,y) relatif. Dicari dari yang
 * PALING BARU supaya coretan yang tertumpuk di atas yang dihapus lebih dulu -
 * sesuai dengan apa yang dilihat pemakai.
 */
function butirTerkena(hs, x, y) {
    const W = hs.canvas.width, H = hs.canvas.height;
    // Pengali untuk menyamakan satuan: x sudah relatif LEBAR, y relatif
    // TINGGI, jadi keduanya harus dibawa ke satuan yang sama sebelum
    // jaraknya dihitung - kalau tidak, ambang sentuh pada halaman F4 jadi
    // 1,5 kali lebih longgar secara tegak daripada mendatar.
    const keSatuanLebar = H / W;

    for (let i = butirBaru.length - 1; i >= 0; i--) {
        const b = butirBaru[i];
        if (b.dokumen !== hs.dokumen || b.page !== hs.pageNumber) continue;

        if (b.jenis === 'sticky') {
            // Bentuk sticky dihitung satu tempat saja - geometriSticky -
            // supaya kotak yang bisa diklik selalu sama dengan yang terlihat.
            const g = geometriSticky(hs, b);
            if (x * W >= g.x && x * W <= g.x + g.lebar && y * H >= g.y && y * H <= g.y + g.tinggi) return i;
            continue;
        }

        if (b.jenis === 'coret') {
            if (coretDiTitik(hs, x, y) === i) return i;
            continue;
        }

        if (b.jenis === 'teks') {
            const lebar = Math.min(0.5, b.ukuran * 12);
            const tinggi = (b.ukuran * 1.6) / keSatuanLebar;
            if (x >= b.x && x <= b.x + lebar && y >= b.y && y <= b.y + tinggi) return i;
            continue;
        }

        if (! b.points || b.points.length < 2) continue;
        // Ambang sentuh mengikuti tebal garisnya, dengan batas bawah supaya
        // garis tipis pun masih bisa dikenai tanpa harus tepat sekali.
        const ambang = Math.max(0.008, (b.width || 0.005) * 0.8);
        for (let j = 1; j < b.points.length; j++) {
            const d = jarakKeSegmen(
                x, y * keSatuanLebar,
                b.points[j - 1][0], b.points[j - 1][1] * keSatuanLebar,
                b.points[j][0], b.points[j][1] * keSatuanLebar
            );
            if (d <= ambang) return i;
        }
    }
    return -1;
}

/* --------------------------------------------------------------- render PDF */

function posisiRelatif(hs, e) {
    const rect = hs.canvas.getBoundingClientRect();
    const x = rect.width ? (e.clientX - rect.left) / rect.width : 0;
    const y = rect.height ? (e.clientY - rect.top) / rect.height : 0;
    return [Math.min(Math.max(x, 0), 1), Math.min(Math.max(y, 0), 1)];
}

function withTimeout(promise, ms, pesan) {
    return Promise.race([
        promise,
        new Promise(function (_, reject) { setTimeout(function () { reject(new Error(pesan)); }, ms); }),
    ]);
}

function pasangInteraksi(hs) {
    const canvas = hs.canvas;
    let sedang = null;   // garis yang sedang ditarik
    let geser = null;    // { indeks, dx, dy, bergerak } kertas tempel yang sedang digeser

    canvas.addEventListener('pointerdown', function (e) {
        if (state.mode === 'geser') return;
        e.preventDefault();
        const [x, y] = posisiRelatif(hs, e);

        if (state.mode === 'coret') {
            tutupPop();

            // Klik coretan yang sudah ada = ubah catatannya, bukan mencoret
            // ulang di atasnya.
            const ada = coretDiTitik(hs, x, y);
            if (ada >= 0) {
                bukaPop({ hs: hs, indeks: ada }, e.clientX, e.clientY);
                gambarUlang();
                return;
            }

            const i = kataDi(hs, x, y, false);
            if (i < 0) return;
            tarikKata = { hs: hs, awal: i, akhir: i };
            hoverKata = null;
            canvas.setPointerCapture(e.pointerId);
            gambarUlang();
            return;
        }

        if (state.mode === 'sticky') {
            // Klik di atas kertas tempel yang SUDAH ADA berarti memindahkan
            // atau menyuntingnya - bukan menumpuk kertas baru di atasnya.
            // Yang menentukan mana dari keduanya: apakah jarinya sempat
            // bergerak sebelum diangkat (lihat pointerup).
            const indeks = stickyDiTitik(hs, x, y);

            if (indeks >= 0) {
                const b = butirBaru[indeks];
                geser = { indeks: indeks, dx: x - b.x, dy: y - b.y, bergerak: false };
                canvas.setPointerCapture(e.pointerId);
                return;
            }

            // Kertas baru ditempel dengan titik klik sebagai PUSATNYA, bukan
            // sudut kiri-atas: yang diniatkan pemakai adalah "taruh di sini".
            const g = { lebarRel: STICKY_LEBAR, tinggiRel: STICKY_LEBAR * STICKY_RASIO * (hs.canvas.width / hs.canvas.height) };
            bukaPop({
                hs: hs, jenis: 'sticky',
                x: x - g.lebarRel / 2,
                y: y - g.tinggiRel / 2,
            }, e.clientX, e.clientY);
            return;
        }

        if (state.mode === 'teks') {
            bukaPop({ hs: hs, jenis: 'teks', x: x, y: y }, e.clientX, e.clientY);
            return;
        }

        if (state.mode === 'hapus') {
            const idx = butirTerkena(hs, x, y);
            if (idx >= 0) { butirBaru.splice(idx, 1); gambarUlang(); }
            return;
        }

        const stabilo = state.mode === 'stabilo';
        const tebalPx = stabilo ? TEBAL_STABILO[state.tebal] : TEBAL_PENA[state.tebal];
        sedang = {
            dokumen: hs.dokumen, page: 1, jenis: stabilo ? 'stabilo' : 'pena',
            color: state.warna, width: tebalPx / LEBAR_ACUAN, points: [[x, y]],
        };
        butirBaru.push(sedang);
        canvas.setPointerCapture(e.pointerId);
    });

    canvas.addEventListener('pointermove', function (e) {
        const [x, y] = posisiRelatif(hs, e);

        if (geser) {
            e.preventDefault();
            const b = butirBaru[geser.indeks];
            const sebelumX = b.x, sebelumY = b.y;
            b.x = x - geser.dx;
            b.y = y - geser.dy;
            jepitSticky(hs, b);
            if (Math.abs(b.x - sebelumX) > 0.0005 || Math.abs(b.y - sebelumY) > 0.0005) {
                geser.bergerak = true;
            }
            gambarUlang();
            return;
        }

        if (sedang) {
            e.preventDefault();
            sedang.points.push([x, y]);
            gambarUlang();
            return;
        }

        if (tarikKata) {
            e.preventDefault();
            const i = kataDi(hs, x, y, true);
            if (i >= 0 && i !== tarikKata.akhir) { tarikKata.akhir = i; gambarUlang(); }
            return;
        }

        if (state.mode === 'coret') {
            const i = kataDi(hs, x, y, false);
            const baru = i >= 0 && ! popTarget ? { hs: hs, indeks: i } : null;
            const berubah = (baru && baru.indeks) !== (hoverKata && hoverKata.indeks) || (hoverKata && hoverKata.hs !== hs);
            hoverKata = baru;
            canvas.style.cursor = coretDiTitik(hs, x, y) >= 0 ? 'pointer' : (i >= 0 ? 'text' : 'default');
            if (berubah) gambarUlang();
            return;
        }

        // Penunjuk berubah jadi "bisa digeser" saat melewati kertas tempel,
        // supaya pemakai tahu benda itu bisa dipindahkan tanpa harus mencoba.
        if (state.mode === 'sticky') {
            canvas.style.cursor = stickyDiTitik(hs, x, y) >= 0 ? 'move' : 'copy';
        }
    });

    const selesai = function (e) {
        if (tarikKata) {
            // Rentang selesai ditarik: coretannya tampil sebagai pratinjau
            // sampai catatannya disimpan (atau dibatalkan).
            const rentang = kotakRentang(hs, tarikKata.awal, tarikKata.akhir);
            tarikKata = null;
            if (e && e.type === 'pointerup' && rentang.kotak.length) {
                bukaPop({ hs: hs, jenis: 'coret', kotak: rentang.kotak, teks: rentang.teks }, e.clientX, e.clientY);
            }
            gambarUlang();
            return;
        }

        if (state.mode === 'coret' && hoverKata && hoverKata.hs === hs && e && e.type === 'pointerleave') {
            hoverKata = null;
            gambarUlang();
        }

        if (geser) {
            // Diangkat tanpa sempat bergerak = niatnya menyunting, bukan
            // memindahkan.
            if (! geser.bergerak && e && e.clientX !== undefined) {
                bukaPop({ hs: hs, indeks: geser.indeks }, e.clientX, e.clientY);
            }
            geser = null;
            return;
        }

        if (! sedang) return;

        // Satu titik tunggal (klik tanpa menarik) tidak akan pernah terlihat
        // di PDF - polyline butuh dua titik - jadi dibuang daripada ikut
        // tersimpan sebagai coretan hantu.
        if (sedang.points.length < 2) {
            const i = butirBaru.indexOf(sedang);
            if (i >= 0) butirBaru.splice(i, 1);
            gambarUlang();
        }
        sedang = null;
    };
    canvas.addEventListener('pointerup', selesai);
    canvas.addEventListener('pointercancel', selesai);
    canvas.addEventListener('pointerleave', selesai);
}

async function renderDokumen(dok) {
    const bagian = document.createElement('div');
    bagian.className = 'ct-dok';

    const judul = document.createElement('div');
    judul.className = 'ct-dok-judul';
    judul.textContent = dok.label;
    bagian.appendChild(judul);

    const kertas = document.createElement('div');
    kertas.className = 'ct-kertas';
    bagian.appendChild(kertas);
    daftarEl.appendChild(bagian);

    let pdf;
    try {
        const resp = await fetch(dok.url);
        const bytes = await resp.arrayBuffer();
        pdf = await withTimeout(pdfjsLib.getDocument({ data: bytes }).promise, 20000, 'Render PDF terlalu lama (timeout).');
    } catch (err) {
        kertas.className = '';
        const errBox = document.createElement('div');
        errBox.className = 'err-box';
        errBox.style.display = 'block';
        errBox.innerHTML = 'Gagal menampilkan pratinjau &ldquo;' + dok.label + '&rdquo; untuk dicoret (' + err.message + '). '
            + 'Dokumen ini tidak akan dicoret pada pengiriman ini &mdash; Anda tetap bisa membukanya lewat '
            + '<a href="' + dok.url + '" target="_blank">tautan PDF asli</a>.';
        kertas.appendChild(errBox);
        return;
    }

    const jml = document.createElement('span');
    jml.className = 'ct-dok-jml';
    jml.textContent = pdf.numPages + ' halaman';
    judul.appendChild(jml);

    for (let n = 1; n <= pdf.numPages; n++) {
        const page = await pdf.getPage(n);

        /* Dirender pada skala yang menghasilkan lebar acuan, lalu dikalikan
           2 untuk ketajaman - kanvas beresolusi dua kali lalu diperkecil
           lewat CSS, supaya tulisan dokumen tetap terbaca di layar padat
           piksel tanpa menaikkan ukuran tampilnya. */
        const dasar = page.getViewport({ scale: 1 });
        const skala = (LEBAR_ACUAN * 2) / dasar.width;
        const viewport = page.getViewport({ scale: skala });

        const wrap = document.createElement('div');
        wrap.className = 'ct-halaman' + (n === 1 ? '' : ' ct-terkunci');

        const latar = document.createElement('canvas');
        latar.className = 'ct-latar';
        latar.width = viewport.width;
        latar.height = viewport.height;

        const coret = document.createElement('canvas');
        coret.className = 'ct-coret';
        coret.width = viewport.width;
        coret.height = viewport.height;

        wrap.appendChild(latar);
        wrap.appendChild(coret);

        const label = document.createElement('div');
        label.className = 'ct-hal-label';
        label.textContent = n === 1 ? 'Halaman 1' : 'Halaman ' + n + ' — tidak bisa dicoret';

        const kolom = document.createElement('div');
        kolom.appendChild(wrap);
        kolom.appendChild(label);
        kertas.appendChild(kolom);

        try {
            await withTimeout(page.render({ canvasContext: latar.getContext('2d'), viewport: viewport }).promise, 20000, 'Render halaman terlalu lama (timeout).');
        } catch (err) {
            label.textContent = 'Halaman ' + n + ' gagal dirender (' + err.message + ') — tidak bisa dicoret.';
            console.error(err);
            continue;
        }

        const hs = {
            dokumen: dok.key, pageNumber: n,
            canvas: coret, ctx: coret.getContext('2d'), latar: latar, wrap: wrap,
            lebarAsli: LEBAR_ACUAN, rasio: viewport.width / viewport.height,
            kata: [],
        };

        // Lapisan teks untuk Coret + Catatan. Gagal dibaca (mis. dokumen hasil
        // pindai) tidak menggagalkan halaman - alat lain tetap bisa dipakai.
        if (n === 1) {
            try {
                hs.kata = await bacaKata(page, viewport);
            } catch (err) {
                console.error(err);
            }
        }
        halamanState.push(hs);

        if (n === 1) pasangInteraksi(hs);
    }

    terapkanZoom();
}

async function init() {
    setMode('geser');
    document.getElementById('ct-zoom-nilai').textContent = Math.round(zoom() * 100) + '%';

    try {
        for (let i = 0; i < dokumenList.length; i++) {
            statusEl.textContent = 'Memuat ' + dokumenList[i].label + '… (' + (i + 1) + '/' + dokumenList.length + ')';
            await renderDokumen(dokumenList[i]);
        }
        statusEl.textContent = dokumenList.length + ' dokumen dimuat';
    } catch (err) {
        statusEl.textContent = 'Gagal memuat dokumen: ' + err.message;
        console.error(err);
    }
}

document.getElementById('coret-form').addEventListener('submit', function () {
    const semua = strokesSebelumnya.concat(butirBaru);
    document.getElementById('coret-json-field').value = semua.length ? JSON.stringify({ strokes: semua }) : '';
});

window.addEventListener('resize', terapkanZoom);

init();
</script>
@endsection
