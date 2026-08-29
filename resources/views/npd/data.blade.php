@extends('layouts.app')

@section('activeNav', 'npd-data')
@section('title', 'Data Nota Pencairan Dana')

@section('content')
<style>
  .dn-kpi{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0 18px;}
  .dn-kpi-card{position:relative;padding:16px 18px;border:1px solid var(--line);border-radius:14px;background:#fff;overflow:hidden;text-align:left;font-family:inherit;}
  .dn-kpi-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--navy);}
  .dn-kpi-card.ok::before{background:var(--ok);}
  .dn-kpi-card.proses::before{background:#2f6fa8;}
  .dn-kpi-card.warn::before{background:var(--warn);}
  .dn-kpi-lbl{font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--mut);}
  .dn-kpi-val{font-size:26px;font-weight:800;color:var(--navy);line-height:1.15;margin-top:6px;font-variant-numeric:tabular-nums;}
  .dn-kpi-card.ok .dn-kpi-val{color:var(--ok);}
  .dn-kpi-card.proses .dn-kpi-val{color:#2f6fa8;}
  .dn-kpi-card.warn .dn-kpi-val{color:var(--warn);}
  .dn-kpi-sub{font-size:12px;color:var(--mut);margin-top:3px;}
  /* Hanya KPI keempat yang bisa diklik - kartunya jadi tombol. */
  button.dn-kpi-card{width:100%;cursor:pointer;transition:.15s;}
  button.dn-kpi-card:hover{border-color:var(--warn);box-shadow:0 4px 14px rgba(176,125,29,.16);transform:translateY(-1px);}
  button.dn-kpi-card.aktif{border-color:var(--warn);background:#fdfaf3;box-shadow:0 0 0 3px rgba(176,125,29,.14);}
  .dn-kpi-aksi{display:inline-flex;align-items:center;gap:5px;margin-top:8px;font-size:11px;font-weight:700;color:var(--warn);}
  .dn-kpi-aksi svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;}
  @media(max-width:1000px){.dn-kpi{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media(max-width:620px){.dn-kpi{grid-template-columns:1fr;}}

  /* Lebar kolom & gaya tabel disamakan dengan Pembuatan NPD: kelas
     .realisasi .npd-table yang sama, table-layout:fixed, dan tanpa
     min-width - jadi tabelnya pas selebar kartu, bukan digulir mendatar. */
  table.dn-tabel td.kol-npd{font-weight:600;color:var(--navy);}
  table.dn-tabel td.kol-status{text-align:center;}

  /* Baris penyaring per kolom - sama seperti Tabel Rincian SPJ. */
  table.dn-tabel tr.kolom-saring th{padding:6px 8px;background:#fbfcfe;text-transform:none;letter-spacing:normal;position:static;}
  table.dn-tabel tr.kolom-saring input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:7px;
    padding:6px 9px;font-family:inherit;font-size:12px;font-weight:400;color:var(--ink);background:#fff;transition:border-color .15s,box-shadow .15s;}
  table.dn-tabel tr.kolom-saring input::placeholder{color:#a9b6c4;}
  table.dn-tabel tr.kolom-saring input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);}
  table.dn-tabel tr.kolom-saring .saring-kosong{display:flex;justify-content:center;}
  table.dn-tabel tr.kolom-saring button{border:1px solid var(--line);background:#fff;border-radius:7px;width:30px;height:30px;
    display:inline-flex;align-items:center;justify-content:center;color:var(--mut);cursor:pointer;transition:.15s;}
  table.dn-tabel tr.kolom-saring button:hover{border-color:var(--navy);color:var(--navy);}
  table.dn-tabel tr.kolom-saring button svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;}

  .dn-lihat{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid var(--navy);
    border-radius:8px;background:var(--navy);color:#fff;transition:.15s;}
  .dn-lihat:hover{background:var(--navy-d);transform:translateY(-1px);box-shadow:0 4px 10px rgba(21,49,74,.2);}
  .dn-lihat svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;}
  .dn-kaki{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 2px 0;}
  .dn-perpage{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--mut);}
  .dn-perpage select{border:1px solid var(--line);border-radius:8px;padding:5px 8px;font-family:inherit;font-size:12px;background:#fff;}
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
        <col style="width:11%;"><col style="width:16%;"><col style="width:14%;"><col style="width:13%;">
        <col style="width:14%;"><col style="width:13%;"><col style="width:9%;"><col style="width:10%;">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const semua = {{ Illuminate\Support\Js::from($baris) }};
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

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
        '<td title="' + esc(r.penerima) + '">' + esc(r.penerima) + '</td>' +
        '<td class="num">' + esc(r.nominal_teks) + '</td>' +
        '<td class="kol-status"><span class="badge ' + esc(r.badge) + '">' + esc(r.status) + '</span>' +
          (r.draft_mengendap ? ' <span class="badge st-dikembalikan" title="Sudah ' + r.umur_hari + ' hari tanpa aksi">' + r.umur_hari + ' hari</span>' : '') +
        '</td>' +
        '<td style="text-align:center;"><a class="dn-lihat" href="' + r.url + '" title="Lihat NPD" aria-label="Lihat NPD">' +
          '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a></td>' +
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

  perbarui();
});
</script>
@endsection
