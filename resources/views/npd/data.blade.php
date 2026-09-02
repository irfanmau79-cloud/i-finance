@extends('layouts.app')

@section('activeNav', 'npd-data')
@section('title', 'Data Nota Pencairan Dana')

@section('content')
<style>
  .dn-kpi{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0 18px;}
  .dn-kpi-card{position:relative;padding:16px 18px;border:1px solid var(--line);border-radius:14px;background:var(--surface);overflow:hidden;text-align:left;font-family:inherit;}
  .dn-kpi-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--navy);}
  .dn-kpi-card.ok::before{background:var(--ok);}
  .dn-kpi-card.proses::before{background:#2f6fa8;}
  .dn-kpi-card.warn::before{background:var(--warn);}
  .dn-kpi-lbl{font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--mut);}
  .dn-kpi-val{font-size:26px;font-weight:800;color:var(--tegas);line-height:1.15;margin-top:6px;font-variant-numeric:tabular-nums;}
  .dn-kpi-card.ok .dn-kpi-val{color:var(--ok);}
  .dn-kpi-card.proses .dn-kpi-val{color:var(--info);}
  .dn-kpi-card.warn .dn-kpi-val{color:var(--warn);}
  .dn-kpi-sub{font-size:12px;color:var(--mut);margin-top:3px;}
  /* Hanya KPI keempat yang bisa diklik - kartunya jadi tombol. */
  button.dn-kpi-card{width:100%;cursor:pointer;transition:.15s;}
  button.dn-kpi-card:hover{border-color:var(--warn);box-shadow:0 4px 14px rgba(176,125,29,.16);transform:translateY(-1px);}
  button.dn-kpi-card.aktif{border-color:var(--warn);background:var(--warn-bg);box-shadow:0 0 0 3px rgba(176,125,29,.14);}
  .dn-kpi-aksi{display:inline-flex;align-items:center;gap:5px;margin-top:8px;font-size:11px;font-weight:700;color:var(--warn);}
  .dn-kpi-aksi svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;}
  @media(max-width:1000px){.dn-kpi{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media(max-width:620px){.dn-kpi{grid-template-columns:1fr;}}

  /* Lebar kolom & gaya tabel disamakan dengan Pembuatan NPD: kelas
     .realisasi .npd-table yang sama, table-layout:fixed, dan tanpa
     min-width - jadi tabelnya pas selebar kartu, bukan digulir mendatar. */
  table.dn-tabel td.kol-status{text-align:center;}

  /* Baris penyaring per kolom - sama seperti Tabel Rincian SPJ. */
  table.dn-tabel tr.kolom-saring th{padding:6px 8px;background:var(--surface-2);text-transform:none;letter-spacing:normal;position:static;}
  table.dn-tabel tr.kolom-saring input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:7px;
    padding:6px 9px;font-family:inherit;font-size:12px;font-weight:400;color:var(--ink);background:var(--surface);transition:border-color .15s,box-shadow .15s;}
  table.dn-tabel tr.kolom-saring input::placeholder{color:#a9b6c4;}
  table.dn-tabel tr.kolom-saring input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);}
  table.dn-tabel tr.kolom-saring .saring-kosong{display:flex;justify-content:center;}
  table.dn-tabel tr.kolom-saring button{border:1px solid var(--line);background:var(--surface);border-radius:7px;width:30px;height:30px;
    display:inline-flex;align-items:center;justify-content:center;color:var(--mut);cursor:pointer;transition:.15s;}
  table.dn-tabel tr.kolom-saring button:hover{border-color:var(--navy);color:var(--tegas);}
  table.dn-tabel tr.kolom-saring button svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;}

  .dn-lihat{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid var(--navy);
    border-radius:8px;background:var(--navy);color:#fff;transition:.15s;}
  .dn-lihat:hover{background:var(--navy-d);transform:translateY(-1px);box-shadow:0 4px 10px rgba(21,49,74,.2);}
  .dn-lihat svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;}
  /* Aksi berisi dua tombol: Lihat dan Kirim Notifikasi WhatsApp. */
  .dn-aksi{display:inline-flex;align-items:center;justify-content:center;gap:6px;}
  .dn-wa{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #1f9d55;
    border-radius:8px;background:var(--surface);color:#1f9d55;cursor:pointer;transition:.15s;padding:0;position:relative;}
  .dn-wa:hover{background:#1f9d55;color:#fff;transform:translateY(-1px);box-shadow:0 4px 10px rgba(31,157,85,.22);}
  .dn-wa svg{width:15px;height:15px;fill:currentColor;stroke:none;}
  /* Titik kecil penanda "sudah pernah dikirim", supaya kiriman ganda kelihatan sebelum diklik. */
  .dn-wa .dn-wa-dot{position:absolute;top:-3px;right:-3px;min-width:14px;height:14px;padding:0 3px;border-radius:8px;
    background:var(--navy);color:#fff;font-size:9px;font-weight:700;line-height:14px;box-sizing:border-box;}
  .wa-baris{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--line);font-size:13px;}
  .wa-baris:last-child{border-bottom:0;}
  .wa-baris .k{flex:0 0 120px;color:var(--mut);}
  .wa-baris .v{flex:1;color:var(--ink);font-weight:600;word-break:break-word;}
  .wa-pesan{white-space:pre-wrap;background:var(--surface-2);border:1px solid var(--line);border-radius:10px;padding:12px 14px;
    font-size:13px;line-height:1.6;color:var(--ink);margin-top:4px;}
  .wa-peringatan{display:flex;gap:10px;align-items:flex-start;background:var(--warn-bg);border:1px solid var(--garis-warn);border-radius:10px;
    padding:12px 14px;font-size:13px;line-height:1.55;color:var(--warn-teks);margin-top:12px;}
  .wa-peringatan svg{flex:0 0 16px;width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;margin-top:2px;}
  .wa-riwayat{margin-top:14px;font-size:12px;color:var(--mut);line-height:1.7;}
  .btn.wa{background:#1f9d55;border-color:#1f9d55;color:#fff;}
  .btn.wa:hover{background:#188044;border-color:#188044;}
  .btn.wa[aria-disabled="true"]{opacity:.5;pointer-events:none;}
  .dn-kaki{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 2px 0;}
  .dn-perpage{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--mut);}
  .dn-perpage select{border:1px solid var(--line);border-radius:8px;padding:5px 8px;font-family:inherit;font-size:12px;background:var(--surface);}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / Nota Pencairan Dana (NPD) / <b>Data NPD</b></div>
    <div class="ph-title">Data Nota Pencairan Dana</div>
  </div>
</div>

<div class="dn-kpi">
  <div class="dn-kpi-card">
    <div class="dn-kpi-lbl">Jumlah NPD</div>
    <div class="dn-kpi-val">{{ number_format($kpi['total'], 0, ',', '.') }}</div>
    <div class="dn-kpi-sub">Seluruh status</div>
  </div>
  <div class="dn-kpi-card ok">
    <div class="dn-kpi-lbl">Jumlah NPD Selesai</div>
    <div class="dn-kpi-val">{{ number_format($kpi['selesai'], 0, ',', '.') }}</div>
    <div class="dn-kpi-sub">Sudah tuntas seluruh tahapan</div>
  </div>
  <div class="dn-kpi-card proses">
    <div class="dn-kpi-lbl">Jumlah NPD Dalam Proses</div>
    <div class="dn-kpi-val">{{ number_format($kpi['proses'], 0, ',', '.') }}</div>
    <div class="dn-kpi-sub">Semua status selain Selesai</div>
  </div>
  <button type="button" class="dn-kpi-card warn" id="kpi-draft" aria-pressed="false">
    <div class="dn-kpi-lbl">Draft NPD &gt; 7 Hari</div>
    <div class="dn-kpi-val">{{ number_format($kpi['draft_mengendap'], 0, ',', '.') }}</div>
    <div class="dn-kpi-sub">Dibuat PPTK, belum ada aksi apa pun</div>
    <div class="dn-kpi-aksi"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg><span id="kpi-draft-aksi">Klik untuk menyaring tabel</span></div>
  </button>
</div>

<div class="dash-card wf-card">
  <h3 style="margin-bottom:4px;">Daftar Nota Pencairan Dana (NPD)</h3>
  <div class="sub" id="dn-info" style="margin-bottom:14px;">{{ number_format($kpi['total'], 0, ',', '.') }} NPD</div>

  <div class="npd-scroll">
    <table class="realisasi npd-table dn-tabel" id="dn-tabel" style="width:100%;table-layout:fixed;">
      <colgroup>
        {{-- Lebar sama dengan tabel Pembuatan/Verifikasi/Persetujuan NPD -
             lihat catatan pengukurannya di npd/_tabel-workflow.blade.php. --}}
        <col style="width:11%;"><col style="width:15%;"><col style="width:13%;"><col style="width:12%;">
        <col style="width:13.5%;"><col style="width:12.5%;"><col style="width:13%;"><col style="width:10%;">
      </colgroup>
      <thead>
        <tr>
          <th>Nomor NPD</th><th>Sub Kegiatan</th><th>Kode Rekening</th><th>Tagging</th>
          <th>Penerima</th><th class="num">Nominal</th><th class="st">Status</th>
          <th style="text-align:center;">Aksi</th>
        </tr>
        <tr class="kolom-saring">
          <th><input type="text" data-kolom="nomor_npd" placeholder="Ketik nomor&hellip;" aria-label="Saring Nomor NPD"></th>
          <th><input type="text" data-kolom="sub_kegiatan" placeholder="Ketik sub kegiatan&hellip;" aria-label="Saring Sub Kegiatan"></th>
          <th><input type="text" data-kolom="kode_rekening" placeholder="Ketik kode&hellip;" aria-label="Saring Kode Rekening"></th>
          <th><input type="text" data-kolom="tagging" placeholder="Ketik tagging&hellip;" aria-label="Saring Tagging"></th>
          <th><input type="text" data-kolom="penerima" placeholder="Ketik penerima&hellip;" aria-label="Saring Penerima"></th>
          <th><input type="text" data-kolom="nominal_teks" placeholder="Ketik nominal&hellip;" aria-label="Saring Nominal"></th>
          <th><input type="text" data-kolom="status" placeholder="Ketik status&hellip;" aria-label="Saring Status"></th>
          <th>
            <div class="saring-kosong">
              <button type="button" id="dn-saring-reset" title="Kosongkan penyaring" aria-label="Kosongkan penyaring">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </th>
        </tr>
      </thead>
      <tbody id="dn-tbody"></tbody>
    </table>
  </div>

  <div class="dn-kaki">
    <div class="dn-perpage">
      <span>Tampilkan</span>
      <select id="dn-perpage">
        @foreach ([10, 25, 50, 100, 250] as $n)
          <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
        @endforeach
      </select>
      <span>data</span>
    </div>
    <div class="inv-pager" id="dn-pager" style="padding:0;"></div>
  </div>
</div>

{{-- Kirim Notifikasi WhatsApp: isinya diambil dari server saat tombol ditekan,
     supaya nomor tujuan & bunyi pesan selalu yang terbaru dan tidak pernah
     dirakit ulang di sisi tampilan. --}}
<div class="mdl-ov" id="wa-mdl-ov">
  <div class="mdl" style="max-width:560px;">
    <div class="mdl-h">Kirim Notifikasi Pencairan</div>
    <div class="mdl-b" id="wa-mdl-body"></div>
    <div class="mdl-f">
      <button type="button" class="btn" data-wa-tutup>Tutup</button>
      <a class="btn wa" id="wa-buka" target="_blank" rel="noopener">Buka WhatsApp</a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const semua = {{ Illuminate\Support\Js::from($baris) }};
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

  const IKON_WA = '<svg viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.25.69-1.44 1.32-1.99 1.37-.53.05-1.02.24-3.44-.72-2.9-1.14-4.74-4.1-4.88-4.29-.14-.19-1.16-1.55-1.16-2.96 0-1.41.74-2.1 1-2.39.26-.29.57-.36.76-.36.19 0 .38 0 .55.01.18.01.41-.07.64.49.25.6.83 2.06.9 2.21.07.15.12.32.02.51-.09.19-.14.31-.28.48-.14.17-.3.37-.42.5-.14.14-.29.29-.12.57.16.29.73 1.2 1.56 1.94 1.07.96 1.98 1.25 2.26 1.39.29.14.45.12.62-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.64-.14.26.09 1.66.78 1.94.93.29.14.48.21.55.33.07.12.07.69-.18 1.38Z"/></svg>';

  // Tombol Kirim Notifikasi hanya digambar untuk baris yang server izinkan
  // (boleh_notifikasi). Angka kecil di pojoknya = sudah pernah dikirim.
  function tombolNotifikasi(r) {
    const judul = r.notifikasi_terkirim > 0
      ? 'Kirim Notifikasi WhatsApp (sudah dikirim ' + r.notifikasi_terkirim + ' kali)'
      : 'Kirim Notifikasi WhatsApp';

    return '<button type="button" class="dn-wa" data-notif="' + r.notifikasi_url + '" title="' + esc(judul) + '" aria-label="' + esc(judul) + '">' +
      IKON_WA + (r.notifikasi_terkirim > 0 ? '<span class="dn-wa-dot">' + r.notifikasi_terkirim + '</span>' : '') +
    '</button>';
  }

  const saringKolom = Array.from(document.querySelectorAll('#dn-tabel tr.kolom-saring input[data-kolom]'));
  const tombolDraft = document.getElementById('kpi-draft');
  const aksiDraft = document.getElementById('kpi-draft-aksi');

  let hanyaDraft = false;
  let halaman = 1;
  let perHalaman = 25;
  let tersaring = [];

  function saring() {
    return semua.filter(r => {
      if (hanyaDraft && !r.draft_mengendap) return false;

      return saringKolom.every(inp => {
        const q = inp.value.trim().toLowerCase();
        if (!q) return true;

        return String(r[inp.dataset.kolom] || '').toLowerCase().includes(q);
      });
    });
  }

  function gambar() {
    const tbody = document.getElementById('dn-tbody');
    const total = tersaring.length;
    const halamanTotal = Math.max(1, Math.ceil(total / perHalaman));
    halaman = Math.min(Math.max(halaman, 1), halamanTotal);
    const mulai = (halaman - 1) * perHalaman;
    const potong = tersaring.slice(mulai, mulai + perHalaman);

    tbody.innerHTML = potong.length ? potong.map(r =>
      '<tr>' +
        '<td class="kol-npd">' + esc(r.nomor_npd) + '</td>' +
        '<td title="' + esc(r.sub_kegiatan) + '">' + esc(r.sub_kegiatan) + '</td>' +
        '<td>' + esc(r.kode_rekening) + '</td>' +
        '<td>' + esc(r.tagging) + '</td>' +
        '<td title="' + esc(r.penerima) + '">' +
          '<div class="pen-nm">' + esc(r.penerima) + '</div>' +
          '<div class="pen-sub">(' + esc(r.jenis_label) + ')</div></td>' +
        '<td class="num">' + esc(r.nominal_teks) + '</td>' +
        '<td class="kol-status"><div class="stat-kolom">' +
          '<span class="badge ' + esc(r.badge) + '">' + esc(r.status) + '</span>' +
          (r.draft_mengendap ? '<span class="badge st-dikembalikan" title="Sudah ' + r.umur_hari + ' hari tanpa aksi">' + r.umur_hari + ' hari</span>' : '') +
        '</div></td>' +
        '<td style="text-align:center;"><div class="dn-aksi">' +
          '<a class="dn-lihat" href="' + r.url + '" title="Lihat NPD" aria-label="Lihat NPD">' +
          '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>' +
          (r.boleh_notifikasi ? tombolNotifikasi(r) : '') +
        '</div></td>' +
      '</tr>'
    ).join('') : '<tr><td colspan="8" style="text-align:center;color:var(--mut);padding:24px;">Tidak ada data.</td></tr>';

    document.getElementById('dn-info').textContent = new Intl.NumberFormat('id-ID').format(total) + ' NPD' +
      (hanyaDraft ? ' — hanya draft yang mengendap lebih dari 7 hari' : '');

    gambarPager(total, halamanTotal, mulai, potong.length);
  }

  function gambarPager(total, halamanTotal, mulai, tampil) {
    const pg = document.getElementById('dn-pager');
    if (total === 0) { pg.innerHTML = ''; return; }

    let info = '<div class="pg-info">Menampilkan ' + (mulai + 1) + '&ndash;' + (mulai + tampil) + ' dari ' + total + ' NPD</div>';
    let btns = '<button class="inv-pg" ' + (halaman <= 1 ? 'disabled' : '') + ' data-go="' + (halaman - 1) + '">&lsaquo;</button>';
    const daftar = [];
    for (let i = 1; i <= halamanTotal; i++) {
      if (i === 1 || i === halamanTotal || (i >= halaman - 1 && i <= halaman + 1)) daftar.push(i);
      else if (daftar[daftar.length - 1] !== '…') daftar.push('…');
    }
    daftar.forEach(i => {
      btns += i === '…' ? '<span class="inv-pg dots">…</span>' : '<button class="inv-pg' + (i === halaman ? ' active' : '') + '" data-go="' + i + '">' + i + '</button>';
    });
    btns += '<button class="inv-pg" ' + (halaman >= halamanTotal ? 'disabled' : '') + ' data-go="' + (halaman + 1) + '">&rsaquo;</button>';

    pg.innerHTML = info + '<div class="pg-btns">' + btns + '</div>';
    pg.querySelectorAll('[data-go]').forEach(b => b.addEventListener('click', () => {
      halaman = Number(b.dataset.go);
      gambar();
    }));
  }

  function perbarui() {
    halaman = 1;
    tersaring = saring();
    gambar();
  }

  saringKolom.forEach(inp => inp.addEventListener('input', perbarui));

  document.getElementById('dn-saring-reset').addEventListener('click', function () {
    saringKolom.forEach(inp => { inp.value = ''; });
    hanyaDraft = false;
    tombolDraft.classList.remove('aktif');
    tombolDraft.setAttribute('aria-pressed', 'false');
    aksiDraft.textContent = 'Klik untuk menyaring tabel';
    perbarui();
  });

  // KPI keempat bekerja sebagai sakelar: klik untuk menyaring, klik lagi untuk melepas.
  tombolDraft.addEventListener('click', function () {
    hanyaDraft = !hanyaDraft;
    tombolDraft.classList.toggle('aktif', hanyaDraft);
    tombolDraft.setAttribute('aria-pressed', hanyaDraft ? 'true' : 'false');
    aksiDraft.textContent = hanyaDraft ? 'Sedang menyaring — klik untuk melepas' : 'Klik untuk menyaring tabel';
    perbarui();
    document.getElementById('dn-tabel').scrollIntoView({behavior: 'smooth', block: 'nearest'});
  });

  document.getElementById('dn-perpage').addEventListener('change', function () {
    perHalaman = Number(this.value);
    perbarui();
  });

  /* ---------- Kirim Notifikasi WhatsApp ---------- */

  const waOv = document.getElementById('wa-mdl-ov');
  const waBody = document.getElementById('wa-mdl-body');
  const waBuka = document.getElementById('wa-buka');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const IKON_PERINGATAN = '<svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  let waUrl = null;
  let waBaris = null;

  function waTutup() { waOv.classList.remove('show'); }

  document.querySelectorAll('[data-wa-tutup]').forEach(b => b.addEventListener('click', waTutup));
  waOv.addEventListener('click', e => { if (e.target === waOv) waTutup(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && waOv.classList.contains('show')) waTutup(); });

  // Didelegasikan ke tbody: barisnya digambar ulang tiap kali menyaring atau
  // pindah halaman, jadi pendengar per tombol akan ikut hilang.
  document.getElementById('dn-tbody').addEventListener('click', function (e) {
    const tombol = e.target.closest('[data-notif]');
    if (!tombol) return;

    waBaris = semua.find(x => x.notifikasi_url === tombol.dataset.notif) || null;
    waMuat(tombol.dataset.notif);
  });

  function waMuat(url) {
    waUrl = url;
    waBody.innerHTML = '<div style="padding:16px 0;color:var(--mut);font-size:13px;">Menyiapkan pesan&hellip;</div>';
    waBuka.style.display = 'none';
    waOv.classList.add('show');

    fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(res => res.ok ? res.json() : Promise.reject(res.status))
      .then(waGambar)
      .catch(() => {
        waBody.innerHTML = '<div class="wa-peringatan">' + IKON_PERINGATAN +
          '<div>Gagal menyiapkan notifikasi. Muat ulang halaman lalu coba lagi.</div></div>';
      });
  }

  function waGambar(d) {
    const t = d.tujuan;
    let html = '<div class="wa-baris"><div class="k">Nomor NPD</div><div class="v">' + esc(d.nomor_npd) + '</div></div>';

    if (d.nomor_sp) {
      html += '<div class="wa-baris"><div class="k">Nomor SP</div><div class="v">' + esc(d.nomor_sp) + '</div></div>';
    }

    html += '<div class="wa-baris"><div class="k">Tujuan Transfer</div><div class="v">' +
      (t.nama ? esc(t.nama) : '<span style="color:var(--mut);font-weight:400;">Tidak ditemukan</span>') +
      '<div style="font-weight:400;color:var(--mut);font-size:12px;margin-top:2px;">' + esc(t.sumber) + '</div></div></div>';

    html += '<div class="wa-baris"><div class="k">Nomor WhatsApp</div><div class="v">' +
      (t.nomor_tampil ? esc(t.nomor_tampil) : '<span style="color:var(--mut);font-weight:400;">Belum ada</span>') + '</div></div>';

    html += '<div style="margin-top:12px;"><div class="k" style="font-size:12px;color:var(--mut);">Isi pesan</div>' +
      '<div class="wa-pesan">' + esc(d.pesan) + '</div></div>';

    if (!d.tautan) {
      // Inilah "popup"-nya: pengiriman ditahan, bukan dikirim ke nomor kosong.
      const pesanNomor = t.nomor
        ? 'Nomor handphone yang tersimpan (' + esc(t.nomor) + ') tidak dikenali sebagai nomor yang sah.'
        : (t.nama
            ? 'Nomor handphone <b>' + esc(t.nama) + '</b> belum diisi.'
            : 'Penerima tujuan transfer NPD ini tidak ditemukan di Data Pegawai maupun Data Vendor.');

      const jalanKeluar = d.url_ubah_pegawai
        ? ' <a href="' + d.url_ubah_pegawai + '" style="color:inherit;text-decoration:underline;">Lengkapi di Data Pegawai</a>, lalu buka lagi halaman ini.'
        : ' Minta superadmin melengkapinya di Data Pegawai (atau Import Vendor untuk penerima vendor) lebih dulu.';

      html += '<div class="wa-peringatan">' + IKON_PERINGATAN + '<div>' + pesanNomor + jalanKeluar + '</div></div>';
    }

    if (d.riwayat.length) {
      html += '<div class="wa-riwayat"><b>Sudah pernah dikirim:</b><br>' +
        d.riwayat.map(r => esc(r.waktu) + ' &mdash; ' + esc(r.oleh) + ' (' + esc(r.nomor) + ')').join('<br>') + '</div>';
    }

    waBody.innerHTML = html;

    if (d.tautan) {
      waBuka.href = d.tautan;
      waBuka.style.display = '';
      waBuka.textContent = d.riwayat.length ? 'Buka WhatsApp Lagi' : 'Buka WhatsApp';
    }
  }

  // Pencatatan jejak dikirim bersamaan dengan tautannya dibuka. Tautan tetap
  // dibiarkan berjalan apa adanya supaya WhatsApp tidak diblokir peramban.
  waBuka.addEventListener('click', function () {
    if (!waUrl) return;

    fetch(waUrl, {
      method: 'POST',
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'},
    }).then(res => res.ok ? res.json() : Promise.reject(res.status))
      .then(() => {
        // Penanda "sudah pernah dikirim" ikut naik tanpa memuat ulang halaman.
        if (waBaris) waBaris.notifikasi_terkirim += 1;
        waTutup();
        perbarui();
      })
      .catch(() => {});
  });

  perbarui();
});
</script>
@endsection
