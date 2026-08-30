@extends('layouts.app')
@section('activeNav', 'invspj')
@section('title', 'Inventarisasi Dokumen Pertanggungjawaban')
@section('content')
<style>
  /* ---------- KPI ---------- */
  .inv-kpi{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:16px 0 18px;}
  .inv-kpi-card{position:relative;padding:16px 18px;border:1px solid var(--line);border-radius:14px;background:#fff;overflow:hidden;}
  .inv-kpi-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--navy);}
  .inv-kpi-card.ok::before{background:var(--ok);}
  .inv-kpi-card.warn::before{background:var(--warn);}
  .inv-kpi-lbl{font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--mut);}
  .inv-kpi-val{font-size:26px;font-weight:800;color:var(--navy);line-height:1.15;margin-top:6px;font-variant-numeric:tabular-nums;}
  .inv-kpi-card.ok .inv-kpi-val{color:var(--ok);}
  .inv-kpi-card.warn .inv-kpi-val{color:var(--warn);}
  .inv-kpi-sub{font-size:12px;color:var(--mut);margin-top:3px;}
  .inv-kpi-bar{height:5px;border-radius:3px;background:#eef2f7;margin-top:10px;overflow:hidden;}
  .inv-kpi-bar i{display:block;height:100%;border-radius:3px;background:currentColor;}
  @media(max-width:860px){.inv-kpi{grid-template-columns:1fr;}}

  /* ---------- Penyaring ---------- */
  .inv-saring{padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:#f8fafc;margin-bottom:18px;}
  .inv-saring-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;}
  .inv-saring label{display:block;font-size:11.5px;font-weight:700;color:var(--navy);margin-bottom:5px;}
  .inv-saring input[type=text]{width:100%;box-sizing:border-box;background:#fff;border:1.5px solid var(--line);border-radius:9px;padding:9px 11px;font-family:inherit;font-size:13px;color:var(--ink);transition:border-color .15s,box-shadow .15s;}
  .inv-saring input[type=text]:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);}

  {{-- Gaya combobox penyaring kini bersama, lihat layouts/partials/styles. --}}
  .inv-saring-kaki{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;}
  .inv-saring-hasil{font-size:12px;color:var(--mut);}
  .inv-saring-hasil b{color:var(--navy);}

  /* ---------- Bantex ---------- */
  .bantex-create{display:flex;align-items:flex-end;gap:12px;margin:18px 0;padding:16px 18px;border:1px solid #dce6ef;border-radius:14px;background:linear-gradient(135deg,#fff,#f5f8fb);}
  .bantex-create .fg{flex:1;min-width:180px}.bantex-create label.fl{margin-top:0}
  .bantex-create .fg.nomor{flex:0 0 150px;min-width:130px}
  .inv-rak{gap:28px 24px;}
  .inv-rak .bantex{width:92px;height:216px;border-radius:10px 10px 5px 5px;padding-top:14px;}
  .inv-rak .bantex .bx-label{width:72px;padding:7px 4px 8px;}
  .inv-rak .bantex .bx-no{font-size:21px;margin:3px 0;}
  .inv-rak .bantex .bx-name{font-size:10px;min-height:30px;}
  .inv-rak .bantex .bx-count{font-size:9px;margin-top:3px;}
  .inv-rak .bantex .bx-meta{font-size:8px;}
  .inv-rak .bantex.kosong{opacity:.75;filter:saturate(.45);border-style:dashed;}
  .inv-rak .bantex.kosong::after{content:"KOSONG";position:absolute;left:50%;bottom:48px;transform:translateX(-50%);font-size:8px;font-weight:800;letter-spacing:1px;color:#dbe8f7;}

  /* ---------- Tabel Rincian SPJ ---------- */
  #inv-table{min-width:1180px;}
  /* Nomor NPD tidak boleh patah ke baris berikutnya - kolomnya dibuat cukup
     lebar untuk nomor terpanjang, dan isinya dikunci satu baris. */
  #inv-table td.cell-npd,#inv-table th.kol-npd{white-space:nowrap;}
  /* Baris penyaring per kolom, tepat di bawah judul kolom. Isian memanjang
     penuh selebar kolomnya dan menyaring seketika - tidak ada tombol
     Terapkan, sama seperti penyaring di atas tabel. */
  #inv-table tr.kolom-saring th{padding:6px 8px;background:#fbfcfe;border-bottom:1px solid var(--line);}
  #inv-table tr.kolom-saring input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:7px;
    padding:6px 9px;font-family:inherit;font-size:12px;font-weight:400;color:var(--ink);background:#fff;
    text-transform:none;letter-spacing:normal;transition:border-color .15s,box-shadow .15s;}
  #inv-table tr.kolom-saring input::placeholder{color:#a9b6c4;font-weight:400;}
  #inv-table tr.kolom-saring input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);}
  #inv-table tr.kolom-saring .kolom-saring-kosong{display:flex;justify-content:center;}
  #inv-table tr.kolom-saring button{border:1px solid var(--line);background:#fff;border-radius:7px;width:30px;height:30px;
    display:inline-flex;align-items:center;justify-content:center;color:var(--mut);cursor:pointer;transition:.15s;}
  #inv-table tr.kolom-saring button:hover{border-color:var(--navy);color:var(--navy);}
  #inv-table tr.kolom-saring button svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;}

  #inv-table .spj-catatan{display:block;padding:6px 8px;border-radius:7px;background:#f7f9fc;color:var(--mut);white-space:normal;overflow-wrap:anywhere;line-height:1.4;}
  #inv-table .spj-lokasi{display:inline-flex;align-items:center;gap:5px;background:#fff5e2;color:#8a6113;border:1px solid #f2dfb3;border-radius:20px;padding:4px 9px;font-size:11px;font-weight:700;white-space:normal;}
  #inv-table .spj-lokasi svg{width:12px;height:12px;flex:0 0 12px;fill:none;stroke:currentColor;stroke-width:2;}
  #inv-table .spj-actions{display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;}
  #inv-table .spj-view-btn,#inv-table .spj-edit-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border-radius:8px;cursor:pointer;transition:.15s;}
  #inv-table .spj-view-btn{border:1px solid var(--navy);background:var(--navy);color:#fff;}
  #inv-table .spj-view-btn:hover{background:var(--navy-d);transform:translateY(-1px);box-shadow:0 4px 10px rgba(21,49,74,.2);}
  #inv-table .spj-edit-btn{border:1px solid #cdd9e5;background:#fff;color:var(--navy);}
  #inv-table .spj-edit-btn:hover{background:var(--navy);border-color:var(--navy);color:#fff;transform:translateY(-1px);box-shadow:0 4px 10px rgba(21,49,74,.18);}
  #inv-table .spj-view-btn svg,#inv-table .spj-edit-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;}
  .spj-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
  .spj-status.lengkap{background:#e8f5ee;color:var(--ok);}
  .spj-status.belum_lengkap{background:#fbf3e2;color:var(--warn);}
  .spj-status.dikembalikan{background:#e9eef3;color:var(--navy);}
  .spj-status.tidak_ditemukan{background:#fdecea;color:var(--err);}
  .inv-pager-kaki{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 12px;}
  .inv-perpage{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--mut);}
  .inv-perpage select{border:1px solid var(--line);border-radius:8px;padding:5px 8px;font-family:inherit;font-size:12px;background:#fff;}

  /* ---------- Panel rincian & edit ---------- */
  .spj-detail-modal,.spj-edit-modal{max-width:920px;background:#f8fafc;}
  .spj-detail-modal .mdl-h,.spj-edit-modal .mdl-h{padding:20px 24px 16px;background:linear-gradient(135deg,var(--navy),#24527a);color:#fff;border-radius:14px 14px 0 0;}
  .spj-detail-modal .mdl-b,.spj-edit-modal .mdl-b{padding:20px 24px;max-height:74vh;overflow:auto;}
  .spj-detail-head-sub{display:block;margin-top:3px;color:#c9d9e8;font-size:11.5px;font-weight:400;}
  .spj-detail-section{margin-bottom:16px;}
  .spj-detail-section:last-child{margin-bottom:0;}
  .spj-detail-section-title{display:flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--navy);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;}
  .spj-detail-section-title::before{content:"";width:4px;height:15px;border-radius:4px;background:var(--gold);}
  .spj-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 12px;}
  .spj-detail-item{padding:10px 12px;border-radius:9px;background:#f7f9fc;border:1px solid #edf1f5;min-width:0;}
  .spj-detail-item.span2{grid-column:1/-1;}
  .spj-detail-item .k{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--mut);font-weight:700;margin-bottom:3px;}
  .spj-detail-item .v{color:var(--ink);font-weight:600;overflow-wrap:anywhere;white-space:pre-wrap;}
  .spj-orang{width:100%;border-collapse:collapse;background:#fff;border:1px solid #edf1f5;border-radius:9px;overflow:hidden;font-size:12.5px;}
  .spj-orang th{background:#f7f9fc;color:var(--mut);font-size:10px;text-transform:uppercase;letter-spacing:.5px;text-align:left;padding:8px 10px;}
  .spj-orang td{padding:8px 10px;border-top:1px solid #f0f3f7;overflow-wrap:anywhere;}
  .spj-orang td.num{text-align:right;white-space:nowrap;}
  .spj-edit-section{padding:16px;border:1px solid #e1e8ef;border-radius:12px;background:#fff;margin-bottom:14px;}
  .spj-edit-section-title{display:flex;align-items:center;gap:8px;margin-bottom:13px;color:var(--navy);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;}
  .spj-edit-section-title::before{content:"";width:4px;height:15px;border-radius:4px;background:var(--gold);}
  .spj-edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
  .spj-edit-section label.fl{display:block;margin:0 0 5px;font-size:11.5px;font-weight:700;color:var(--navy);}
  .spj-edit-section select,.spj-edit-section textarea{width:100%;box-sizing:border-box;background:#fff;border:1.5px solid var(--line);border-radius:9px;padding:10px 12px;font-family:inherit;font-size:13px;color:var(--ink);transition:border-color .15s,box-shadow .15s;}
  .spj-edit-section textarea{resize:vertical;min-height:120px;line-height:1.55;}
  .spj-edit-section select:focus,.spj-edit-section textarea:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.11);}
  .spj-edit-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:2px;}
  .spj-memuat{padding:26px;text-align:center;color:var(--mut);}
  @media(max-width:640px){.spj-detail-grid,.spj-edit-grid{grid-template-columns:1fr}.spj-detail-item.span2{grid-column:auto}}
</style>

@php
  $ringkasRp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
  $kpi = $inventaris['kpi'];
@endphp

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Inventarisasi Dokumen Pertanggungjawaban</b></div>
    <div class="ph-title">Inventarisasi Dokumen Pertanggungjawaban</div>
  </div>
</div>

@if (session('success'))
  <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
  <div class="err-box" style="display:block;">
    <strong>Terjadi kesalahan:</strong>
    <ul style="margin:6px 0 0;padding-left:18px;">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="inv-kpi">
  <div class="inv-kpi-card">
    <div class="inv-kpi-lbl">Jumlah Nota Pencairan Dana (NPD)</div>
    <div class="inv-kpi-val" id="kpi-total">{{ number_format($kpi['jumlah_npd'], 0, ',', '.') }}</div>
    <div class="inv-kpi-sub">NPD berstatus Selesai</div>
  </div>
  <div class="inv-kpi-card ok">
    <div class="inv-kpi-lbl">NPD dengan SPJ Lengkap</div>
    <div class="inv-kpi-val" id="kpi-lengkap">{{ number_format($kpi['lengkap'], 0, ',', '.') }}</div>
    <div class="inv-kpi-sub"><span id="kpi-lengkap-persen">{{ number_format($kpi['lengkap_persen'], 1, ',', '.') }}</span>% dari seluruh NPD</div>
    <div class="inv-kpi-bar" style="color:var(--ok);"><i id="kpi-lengkap-bar" style="width:{{ $kpi['lengkap_persen'] }}%;"></i></div>
  </div>
  <div class="inv-kpi-card warn">
    <div class="inv-kpi-lbl">NPD dengan SPJ Belum Lengkap</div>
    <div class="inv-kpi-val" id="kpi-belum">{{ number_format($kpi['belum_lengkap'], 0, ',', '.') }}</div>
    <div class="inv-kpi-sub"><span id="kpi-belum-persen">{{ number_format($kpi['belum_lengkap_persen'], 1, ',', '.') }}</span>% dari seluruh NPD</div>
    <div class="inv-kpi-bar" style="color:var(--warn);"><i id="kpi-belum-bar" style="width:{{ $kpi['belum_lengkap_persen'] }}%;"></i></div>
  </div>
</div>

<div class="inv-saring">
  <div class="inv-saring-grid">
    @foreach ([
      ['id' => 'bulan', 'label' => 'Bulan', 'semua' => 'Semua Bulan'],
      ['id' => 'program', 'label' => 'Program', 'semua' => 'Semua Program'],
      ['id' => 'kegiatan', 'label' => 'Kegiatan', 'semua' => 'Semua Kegiatan'],
      ['id' => 'sub', 'label' => 'Sub Kegiatan', 'semua' => 'Semua Sub Kegiatan'],
      ['id' => 'tagging', 'label' => 'Tagging', 'semua' => 'Semua Tagging'],
    ] as $kombo)
      <div>
        <label for="f-{{ $kombo['id'] }}-inp">{{ $kombo['label'] }}</label>
        <div class="inv-kombo" id="f-{{ $kombo['id'] }}-wrap" data-semua="{{ $kombo['semua'] }}">
          <input type="text" class="kb-inp" id="f-{{ $kombo['id'] }}-inp" autocomplete="off"
                 role="combobox" aria-expanded="false" placeholder="{{ $kombo['semua'] }}">
          <svg class="kb-chev" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
          <input type="hidden" id="f-{{ $kombo['id'] }}">
          <div class="kb-drop" id="f-{{ $kombo['id'] }}-drop" role="listbox"></div>
        </div>
      </div>
    @endforeach
    <div>
      <label for="f-cari">Pencarian</label>
      <input type="text" id="f-cari" placeholder="Isi manual&hellip;" value="{{ $filters['cari'] }}">
    </div>
  </div>
  <div class="inv-saring-kaki">
    <div class="inv-saring-hasil"><b id="saring-jumlah">{{ $kpi['jumlah_npd'] }}</b> NPD sesuai filter</div>
    <button type="button" class="btn" id="f-reset">Reset Filter</button>
  </div>
</div>

@if($bolehEditDetail)
<form method="POST" action="{{ route('inventarisasi-spj.bantex.store') }}" class="bantex-create">
  @csrf
  <div class="fg nomor">
    <label class="fl" for="bantex-nomor">Nomor Penyimpanan</label>
    <input id="bantex-nomor" name="nomor" inputmode="numeric" maxlength="2" required placeholder="07">
  </div>
  <div class="fg">
    <label class="fl" for="bantex-nama">Nama Bantex/Box</label>
    <input id="bantex-nama" name="nama" maxlength="100" required placeholder="Contoh: PDTT Irban II">
  </div>
  <button class="btn prim" type="submit">+ Tambah Bantex/Box</button>
</form>
@endif

@if($inventaris['kosong'] && count($inventaris['lokasi']) === 0)
  <div class="dash-card" style="text-align:center;padding:45px;color:var(--mut)">Belum ada Bantex/Box. Tambahkan Bantex/Box pertama untuk mulai menata arsip.</div>
@else

<div class="inv-rak-wrap" id="inv-level1">
  <div class="inv-rak-title">Visualisasi Penyimpanan Dokumen</div>
  <div class="inv-rak" id="inv-rak">
    @foreach ($inventaris['lokasi'] as $i => $lok)
      <div class="bantex{{ $lok['jumlah_npd'] === 0 ? ' kosong' : '' }}" data-lokasi-index="{{ $i }}" title="{{ $lok['lokasi'] }} — {{ $lok['jumlah_npd'] }} NPD">
        <div class="bx-label">
          <div class="bx-brand">Penyimpanan</div>
          <div class="bx-arsip">ARSIP</div>
          <div class="bx-no">{{ $lok['nomor'] ?? str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
          <div class="bx-rule"></div>
          <div class="bx-name">{{ $lok['nama'] }}</div>
          <div class="bx-rule"></div>
          <div class="bx-count">{{ $lok['jumlah_npd'] }} NPD</div>
        </div>
        <div class="bx-hole"></div>
      </div>
    @endforeach
  </div>
</div>

<div class="inv-stack-wrap" id="inv-level2" style="display:none;">
  <div class="inv-crumb">
    <button type="button" class="btn" id="inv-back-1">&#8592; Kembali ke Rak</button>
    <span class="inv-stack-title" id="inv-stack-title"></span>
  </div>
  <div class="inv-stack" id="inv-stack"></div>
  <div class="inv-pager" id="inv-stack-pager"></div>
</div>

<div class="inv-doc-wrap" id="inv-level3" style="display:none;">
  <div class="inv-crumb">
    <button type="button" class="btn" id="inv-back-2">&#8592; Kembali ke Tumpukan</button>
    <span class="inv-stack-title" id="inv-doc-loc"></span>
  </div>
  <div class="inv-doc-card" id="inv-doc-card"></div>
</div>

<div class="inv-table-wrap">
  <div class="inv-table-head open" id="inv-table-head">
    <svg id="inv-tbl-chev" viewBox="0 0 24 24" width="16" height="16" style="transition:transform .2s;"><polyline points="9 18 15 12 9 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
    <span>Tabel Rincian SPJ <span class="inv-muted" id="inv-table-count">({{ $kpi['jumlah_npd'] }})</span></span>
  </div>
  <div class="inv-table-body" id="inv-table-body">
    <div class="inv-tbl-card">
      <div class="inv-tbl-scroll">
        <table class="inv-modtable" id="inv-table" style="table-layout:fixed;">
          <colgroup>
            <col style="width:20%;"><col style="width:16%;"><col style="width:16%;">
            <col style="width:11%;"><col style="width:27%;"><col style="width:10%;">
          </colgroup>
          <thead><tr>
            <th class="kol-npd">Nomor NPD</th><th>Koordinator</th><th>Lokasi Penyimpanan</th>
            <th>Status</th><th>Catatan</th><th style="text-align:center;">Aksi</th>
          </tr>
          <tr class="kolom-saring">
            <th><input type="text" data-kolom="nomor_npd" placeholder="Ketik nomor NPD&hellip;" aria-label="Saring Nomor NPD"></th>
            <th><input type="text" data-kolom="koordinator" placeholder="Ketik koordinator&hellip;" aria-label="Saring Koordinator"></th>
            <th><input type="text" data-kolom="lokasi" placeholder="Ketik lokasi&hellip;" aria-label="Saring Lokasi Penyimpanan"></th>
            <th><input type="text" data-kolom="status_label" placeholder="Ketik status&hellip;" aria-label="Saring Status"></th>
            <th><input type="text" data-kolom="catatan" placeholder="Ketik catatan&hellip;" aria-label="Saring Catatan"></th>
            <th>
              <div class="kolom-saring-kosong">
                <button type="button" id="kolom-saring-reset" title="Kosongkan penyaring kolom" aria-label="Kosongkan penyaring kolom">
                  <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
            </th>
          </tr></thead>
          <tbody id="inv-table-tbody"></tbody>
        </table>
      </div>
      <div class="inv-pager-kaki">
        <div class="inv-perpage">
          <span>Tampilkan</span>
          <select id="inv-perpage">
            @foreach ([10, 25, 50, 100, 250] as $n)
              <option value="{{ $n }}" @selected($n === 10)>{{ $n }}</option>
            @endforeach
          </select>
          <span>data</span>
        </div>
        <div class="inv-pager" id="inv-pager" style="padding:0;"></div>
      </div>
    </div>
  </div>
</div>

<div class="mdl-ov" id="spj-detail-ov">
  <div class="mdl spj-detail-modal">
    <div class="mdl-h">Rincian SPJ<span class="spj-detail-head-sub">Data lengkap Nota Pencairan Dana dan status inventarisasinya</span></div>
    <div class="mdl-b">
      <div id="spj-detail-content"><div class="spj-memuat">Memuat rincian&hellip;</div></div>
      <div class="mdl-f" style="padding:16px 0 0;display:flex;justify-content:flex-end;">
        <button type="button" class="btn prim" id="spj-detail-close">Tutup</button>
      </div>
    </div>
  </div>
</div>

@if ($bolehEditDetail)
<div class="mdl-ov" id="spj-mdl-ov">
  <div class="mdl spj-edit-modal">
    <div class="mdl-h">Edit Data SPJ<span class="spj-detail-head-sub">Data NPD hanya dapat dibaca; yang dapat diubah adalah lokasi, status, dan catatan</span></div>
    <div class="mdl-b">
      <div id="spj-edit-baca"><div class="spj-memuat">Memuat rincian NPD&hellip;</div></div>

      <form method="POST" id="spj-edit-form">
        @csrf
        @method('PUT')
        <div class="spj-edit-section">
          <div class="spj-edit-section-title">Data yang Dikelola Pengelola SPJ</div>
          <div class="spj-edit-grid">
            <div>
              <label class="fl" for="spj-f-lokasi">Lokasi Penyimpanan</label>
              <select name="lokasi" id="spj-f-lokasi" data-cari>
                <option value="">&mdash; Belum Ditetapkan &mdash;</option>
                @foreach($inventaris['bantex'] as $bantex)
                  <option value="{{ $bantex['label'] }}">{{ $bantex['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="fl" for="spj-f-status">Status SPJ</label>
              <select name="status" id="spj-f-status">
                @foreach ($inventaris['status_list'] as $nilai => $label)
                  <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div style="margin-top:14px;">
            <label class="fl" for="spj-f-catatan">Catatan</label>
            <textarea name="catatan" id="spj-f-catatan" maxlength="1000" placeholder="Catatan kelengkapan berkas, kekurangan dokumen, atau keterangan lain."></textarea>
          </div>
        </div>
      </form>

      <div class="spj-edit-actions">
        <button type="button" class="btn" onclick="spjModalClose()">Batal</button>
        <button type="submit" form="spj-edit-form" class="btn prim">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
  const lokasiData = {{ Illuminate\Support\Js::from($inventaris['lokasi']) }};
  const semuaBaris = {{ Illuminate\Support\Js::from($inventaris['detail_spj']) }};
  const rantai = {{ Illuminate\Support\Js::from($inventaris['filter_hierarchy']) }};
  const statusLabel = {{ Illuminate\Support\Js::from($inventaris['status_list']) }};
  const bolehEditDetail = @json($bolehEditDetail);
  const rincianUrl = {{ Illuminate\Support\Js::from(url('/inventarisasi-spj')) }};

  const rp = n => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(n || 0);
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
  const NAMA_BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

  /* =========================================================
   * PENYARING - seluruhnya di sisi peramban. Tidak ada tombol
   * Terapkan dan tidak ada muat ulang halaman: begitu pilihan
   * berubah, KPI, tabel, dan pencacahnya langsung ikut berubah.
   * ========================================================= */
  const fCari = document.getElementById('f-cari');

  /**
   * Combobox yang bisa diketik untuk mencari. Daftar pilihannya diganti dari
   * luar lewat setPilihan(), jadi komponen ini juga dipakai untuk dropdown
   * bertingkat. Nilai terpilih disimpan di input tersembunyi; yang terlihat
   * hanyalah labelnya, dan saat kotaknya ditinggalkan labelnya dipulihkan
   * supaya ketikan setengah jalan tidak tertinggal di layar.
   */
  function buatKombo(id) {
    const wrap = document.getElementById('f-' + id + '-wrap');
    const inp = document.getElementById('f-' + id + '-inp');
    const drop = document.getElementById('f-' + id + '-drop');
    const nilai = document.getElementById('f-' + id);
    const labelSemua = wrap.dataset.semua;

    let pilihan = [];
    let label = '';
    let sorot = -1;
    let onUbah = null;

    const tampil = () => pilihan.filter(o => {
      const q = inp.value.trim().toLowerCase();

      return !q || q === label.toLowerCase() || o.label.toLowerCase().includes(q);
    });

    function gambar() {
      const daftar = tampil();
      drop.innerHTML = daftar.length
        ? daftar.map((o, i) => '<div class="kb-item' + (o.value === nilai.value ? ' terpilih' : '') +
            (i === sorot ? ' sorot' : '') + '" role="option" data-nilai="' + esc(o.value) + '">' + esc(o.label) + '</div>').join('')
        : '<div class="kb-kosong">Tidak ditemukan</div>';
    }

    function buka() {
      if (inp.disabled) return;
      sorot = -1;
      gambar();
      wrap.classList.add('buka');
      inp.setAttribute('aria-expanded', 'true');
    }

    function tutup() {
      wrap.classList.remove('buka');
      inp.setAttribute('aria-expanded', 'false');
      inp.value = label;
    }

    function pilih(v) {
      const opsi = pilihan.find(o => o.value === v);
      nilai.value = opsi ? opsi.value : '';
      label = opsi && opsi.value !== '' ? opsi.label : '';
      inp.value = label;
      tutup();
      if (onUbah) onUbah();
    }

    inp.addEventListener('focus', buka);
    inp.addEventListener('click', buka);
    inp.addEventListener('input', function () { sorot = -1; gambar(); wrap.classList.add('buka'); });
    inp.addEventListener('blur', () => setTimeout(tutup, 130));
    inp.addEventListener('keydown', function (e) {
      const daftar = tampil();
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!wrap.classList.contains('buka')) buka();
        sorot = Math.min(Math.max(sorot + (e.key === 'ArrowDown' ? 1 : -1), 0), daftar.length - 1);
        gambar();
        const el = drop.querySelector('.kb-item.sorot');
        if (el) el.scrollIntoView({block: 'nearest'});
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (daftar[sorot]) pilih(daftar[sorot].value);
      } else if (e.key === 'Escape') {
        tutup();
        inp.blur();
      }
    });
    drop.addEventListener('mousedown', function (e) {
      const item = e.target.closest('.kb-item[data-nilai]');
      if (!item) return;
      e.preventDefault();
      pilih(item.dataset.nilai);
    });

    return {
      get value() { return nilai.value; },
      kosongkan() {
        nilai.value = '';
        label = '';
        inp.value = '';
      },
      /** Daftar pilihan baru; nilai terpilih yang tidak lagi ada ikut dikosongkan. */
      setPilihan(daftar) {
        pilihan = [{value: '', label: labelSemua}].concat(daftar);
        const masih = pilihan.find(o => o.value === nilai.value && o.value !== '');
        label = masih ? masih.label : '';
        nilai.value = masih ? masih.value : '';
        inp.value = label;
        inp.disabled = daftar.length === 0;
        inp.placeholder = daftar.length === 0 ? 'Tidak ada pilihan' : labelSemua;
      },
      onUbah(fn) { onUbah = fn; },
    };
  }

  const kBulan = buatKombo('bulan');
  const kProgram = buatKombo('program');
  const kKegiatan = buatKombo('kegiatan');
  const kSub = buatKombo('sub');
  const kTagging = buatKombo('tagging');

  // Bulan diisi dari data yang benar-benar ada, dan tidak bertingkat.
  kBulan.setPilihan(
    [...new Set(semuaBaris.map(r => r.bulan))].sort((a, b) => a - b)
      .map(b => ({value: String(b), label: NAMA_BULAN[b - 1]}))
  );

  /**
   * Pilihan satu tingkat dari rantai Program > Kegiatan > Sub Kegiatan >
   * Tagging, dibatasi oleh pilihan di atasnya - kombinasi yang tidak akan
   * menghasilkan baris tidak pernah muncul.
   */
  function pilihanRantai(kunci, batas) {
    return [...new Set(
      rantai.filter(r => Object.entries(batas).every(([k, v]) => !v || r[k] === v))
        .map(r => r[kunci]).filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, 'id', {numeric: true}))
      .map(v => ({value: v, label: v}));
  }

  function segarkanDropdown() {
    kProgram.setPilihan(pilihanRantai('program', {}));
    kKegiatan.setPilihan(pilihanRantai('kegiatan', {program: kProgram.value}));
    kSub.setPilihan(pilihanRantai('sub_kegiatan', {program: kProgram.value, kegiatan: kKegiatan.value}));
    kTagging.setPilihan(pilihanRantai('tagging', {program: kProgram.value, kegiatan: kKegiatan.value, sub_kegiatan: kSub.value}));
  }

  // Penyaring per kolom di bawah judul tabel. Bekerja BERSAMA penyaring di
  // atas tabel: baris harus lolos keduanya.
  const kolomSaring = Array.from(document.querySelectorAll('#inv-table tr.kolom-saring input[data-kolom]'));

  function lolosKolom(r) {
    return kolomSaring.every(inp => {
      const q = inp.value.trim().toLowerCase();
      if (!q) return true;

      return String(r[inp.dataset.kolom] || '').toLowerCase().includes(q);
    });
  }

  function barisTersaring() {
    const q = (fCari.value || '').toLowerCase().trim();

    return semuaBaris.filter(r => {
      if (!lolosKolom(r)) return false;
      if (kBulan.value && String(r.bulan) !== kBulan.value) return false;
      if (kProgram.value && r.program !== kProgram.value) return false;
      if (kKegiatan.value && r.kegiatan !== kKegiatan.value) return false;
      if (kSub.value && r.sub_kegiatan !== kSub.value) return false;
      if (kTagging.value && r.tagging !== kTagging.value) return false;
      if (!q) return true;

      return [r.nomor_npd, r.koordinator, r.lokasi, r.catatan, r.sub_kegiatan, r.uraian, r.nomor_sp]
        .join(' ').toLowerCase().includes(q);
    });
  }

  function perbaruiKpi(baris) {
    const total = baris.length;
    const lengkap = baris.filter(r => r.status === 'lengkap').length;
    const belum = total - lengkap;
    const persen = n => total > 0 ? (n / total * 100) : 0;
    const angka = n => new Intl.NumberFormat('id-ID').format(n);
    const satuDesimal = n => n.toFixed(1).replace('.', ',');

    document.getElementById('kpi-total').textContent = angka(total);
    document.getElementById('kpi-lengkap').textContent = angka(lengkap);
    document.getElementById('kpi-belum').textContent = angka(belum);
    document.getElementById('kpi-lengkap-persen').textContent = satuDesimal(persen(lengkap));
    document.getElementById('kpi-belum-persen').textContent = satuDesimal(persen(belum));
    document.getElementById('kpi-lengkap-bar').style.width = persen(lengkap) + '%';
    document.getElementById('kpi-belum-bar').style.width = persen(belum) + '%';
    document.getElementById('saring-jumlah').textContent = angka(total);
    document.getElementById('inv-table-count').textContent = '(' + angka(total) + ')';
  }

  /* ========================= TABEL RINCIAN SPJ ========================= */
  let halaman = 1;
  let perHalaman = 10;
  let tersaring = [];

  const lencanaStatus = s => '<span class="spj-status ' + esc(s) + '">' + esc(statusLabel[s] || statusLabel.belum_lengkap) + '</span>';

  function gambarTabel() {
    const tbody = document.getElementById('inv-table-tbody');
    const total = tersaring.length;
    const halamanTotal = Math.max(1, Math.ceil(total / perHalaman));
    halaman = Math.min(Math.max(halaman, 1), halamanTotal);
    const mulai = (halaman - 1) * perHalaman;
    const potong = tersaring.slice(mulai, mulai + perHalaman);

    tbody.innerHTML = potong.length ? potong.map(r =>
      '<tr>' +
        '<td class="cell-npd">' + esc(r.nomor_npd) + '</td>' +
        '<td class="cell-clip" title="' + esc(r.koordinator) + '">' + esc(r.koordinator) + '</td>' +
        '<td><span class="spj-lokasi"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>' + esc(r.lokasi || 'Belum Ditetapkan') + '</span></td>' +
        '<td>' + lencanaStatus(r.status) + '</td>' +
        '<td><span class="spj-catatan" title="' + esc(r.catatan || '') + '">' + esc(r.catatan || 'Tidak ada catatan') + '</span></td>' +
        '<td><div class="spj-actions">' +
          '<button type="button" class="spj-view-btn" data-spj-view="' + r.npd_id + '" title="Lihat Rincian" aria-label="Lihat Rincian"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>' +
          (bolehEditDetail ? '<button type="button" class="spj-edit-btn" data-spj-edit="' + r.npd_id + '" title="Edit" aria-label="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></button>' : '') +
        '</div></td>' +
      '</tr>'
    ).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--mut);padding:22px;">Tidak ada data.</td></tr>';

    gambarPager(total, halamanTotal, mulai, potong.length);
  }

  function gambarPager(total, halamanTotal, mulai, tampil) {
    const pg = document.getElementById('inv-pager');
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
      gambarTabel();
      document.getElementById('inv-table').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }));
  }

  function perbarui(kembaliKeAwal) {
    if (kembaliKeAwal) halaman = 1;
    tersaring = barisTersaring();
    perbaruiKpi(tersaring);
    gambarTabel();
  }

  fCari.addEventListener('input', () => perbarui(true));
  kolomSaring.forEach(inp => inp.addEventListener('input', () => perbarui(true)));
  document.getElementById('kolom-saring-reset').addEventListener('click', function () {
    kolomSaring.forEach(inp => { inp.value = ''; });
    perbarui(true);
  });
  kBulan.onUbah(() => perbarui(true));
  [kProgram, kKegiatan, kSub, kTagging].forEach(k => k.onUbah(() => {
    segarkanDropdown();
    perbarui(true);
  }));
  document.getElementById('inv-perpage').addEventListener('change', function () {
    perHalaman = Number(this.value);
    perbarui(true);
  });
  document.getElementById('f-reset').addEventListener('click', function () {
    [kBulan, kProgram, kKegiatan, kSub, kTagging].forEach(k => k.kosongkan());
    fCari.value = '';
    kolomSaring.forEach(inp => { inp.value = ''; });
    segarkanDropdown();
    perbarui(true);
  });

  segarkanDropdown();
  perbarui(true);

  /* ========================= RAK / TUMPUKAN / DOKUMEN ========================= */
  function backTo(level) {
    document.getElementById('inv-level1').style.display = level === 1 ? '' : 'none';
    document.getElementById('inv-level2').style.display = level === 2 ? '' : 'none';
    document.getElementById('inv-level3').style.display = level === 3 ? '' : 'none';
    window.scrollTo({top: 0, behavior: 'smooth'});
  }
  document.getElementById('inv-back-1').addEventListener('click', () => backTo(1));
  document.getElementById('inv-back-2').addEventListener('click', () => backTo(2));

  function paperHtml(lokIdx, docIdx, r) {
    return '<div class="inv-paper" data-lokasi="' + lokIdx + '" data-doc="' + docIdx + '" title="' + esc(r.nomor_npd || '') + '">' +
      '<div class="pp-folder"></div><div class="pp-sheet pp-s3"></div><div class="pp-sheet pp-s2"></div>' +
      '<div class="pp-sheet pp-front">' +
        '<div class="pp-npd">' + esc(r.nomor_npd || '(tanpa no)') + '</div>' +
        '<div class="pp-nom">' + rp(r.nominal) + '</div>' +
        '<div class="pp-nama">' + esc(r.penerima || '') + '</div>' +
        '<div class="pp-lines"><i></i><i></i><i></i></div>' +
      '</div></div>';
  }

  function itemsForRows(container, rows) {
    const items = Array.from(container.querySelectorAll('.inv-paper'));
    if (!items.length) return 0;
    const tops = [];
    items.forEach(el => { if (!tops.includes(el.offsetTop)) tops.push(el.offsetTop); });
    if (tops.length <= rows) return items.length;
    return items.filter(el => el.offsetTop < tops[rows]).length;
  }

  let stackLokasiIdx = null, stackPage = 1, stackPerPage = null;
  const STACK_ROWS = 2;

  function renderStackPage() {
    const stack = document.getElementById('inv-stack');
    const pager = document.getElementById('inv-stack-pager');
    const lok = lokasiData[stackLokasiIdx];
    const total = lok.dokumen.length;
    if (!total) {
      stack.innerHTML = '<div class="inv-muted" style="padding:16px;">Bantex ini kosong.</div>';
      pager.innerHTML = '';
      return;
    }

    if (stackPerPage === null) {
      const probe = Math.min(total, 60);
      stack.innerHTML = lok.dokumen.slice(0, probe).map((r, i) => paperHtml(stackLokasiIdx, i, r)).join('');
      stackPerPage = itemsForRows(stack, STACK_ROWS) || probe;
    }

    const pages = Math.max(1, Math.ceil(total / stackPerPage));
    stackPage = Math.min(Math.max(stackPage, 1), pages);
    const start = (stackPage - 1) * stackPerPage;
    const slice = lok.dokumen.slice(start, start + stackPerPage);
    stack.innerHTML = slice.map((r, i) => paperHtml(stackLokasiIdx, start + i, r)).join('');

    if (pages <= 1) { pager.innerHTML = ''; return; }
    let info = '<div class="pg-info">Menampilkan ' + (start + 1) + '&ndash;' + (start + slice.length) + ' dari ' + total + ' Dokumen NPD</div>';
    let btns = '<button class="inv-pg" ' + (stackPage <= 1 ? 'disabled' : '') + ' data-stack-go="' + (stackPage - 1) + '">&lsaquo;</button>';
    const list = [];
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || (i >= stackPage - 1 && i <= stackPage + 1)) list.push(i);
      else if (list[list.length - 1] !== '…') list.push('…');
    }
    list.forEach(i => {
      btns += i === '…' ? '<span class="inv-pg dots">…</span>' : '<button class="inv-pg' + (i === stackPage ? ' active' : '') + '" data-stack-go="' + i + '">' + i + '</button>';
    });
    btns += '<button class="inv-pg" ' + (stackPage >= pages ? 'disabled' : '') + ' data-stack-go="' + (stackPage + 1) + '">&rsaquo;</button>';
    pager.innerHTML = info + '<div class="pg-btns">' + btns + '</div>';
    pager.querySelectorAll('[data-stack-go]').forEach(b => b.addEventListener('click', () => {
      stackPage = Number(b.dataset.stackGo);
      renderStackPage();
    }));
  }

  function bukaLokasi(idx, el) {
    const selesai = () => {
      const lok = lokasiData[idx];
      document.getElementById('inv-stack-title').textContent = lok.lokasi + ' — ' + lok.jumlah_npd + ' Dokumen NPD';
      stackLokasiIdx = idx;
      stackPage = 1;
      stackPerPage = null;
      backTo(2);
      renderStackPage();
    };
    if (el) { el.classList.add('zooming'); setTimeout(() => { el.classList.remove('zooming'); selesai(); }, 300); }
    else selesai();
  }

  function bukaDok(lokasiIdx, docIdx) {
    const r = lokasiData[lokasiIdx].dokumen[docIdx];
    if (!r) return;
    document.getElementById('inv-doc-loc').textContent = r.lokasi;
    const row = (k, v, big) => '<div class="inv-doc-row"><div class="k">' + k + '</div><div class="v' + (big ? ' big' : '') + '">' + (v || '&mdash;') + '</div></div>';
    // Jenis dokumen "NPD" ditulis panjang supaya sama dengan penamaan di modul lain.
    const jenis = r.jenis_dokumen === 'NPD' ? 'Nota Pencairan Dana (NPD)' : r.jenis_dokumen;
    document.getElementById('inv-doc-card').innerHTML =
      '<div class="dc-head"><div class="t">' + esc(r.nomor_npd || '(tanpa nomor NPD)') + '</div>' +
        '<div class="s">' + esc(r.bulan_label) + ' &middot; ' + esc(r.lokasi) + '</div></div>' +
      '<div class="dc-body">' +
        row('Bulan', esc(r.bulan_label)) +
        row('Nomor Dokumen', esc(r.nomor_npd)) +
        row('Jenis Dokumen', esc(jenis)) +
        row('Program', esc(r.program)) +
        row('Kegiatan', esc(r.kegiatan)) +
        row('Sub Kegiatan', esc(r.sub_kegiatan)) +
        row('Kode Rekening', esc(r.kode_rekening)) +
        row('Tagging', esc(r.tagging || '-')) +
        row('Uraian', esc(r.uraian)) +
        row('Nominal', rp(r.nominal), true) +
        row('Penerima', esc(r.penerima)) +
        row('Lokasi', esc(r.lokasi)) +
      '</div>';
    backTo(3);
  }

  document.querySelectorAll('[data-lokasi-index]').forEach(function (el) {
    el.addEventListener('click', function () { bukaLokasi(Number(el.dataset.lokasiIndex), el); });
  });
  document.getElementById('inv-stack').addEventListener('click', function (e) {
    const p = e.target.closest('.inv-paper');
    if (p) bukaDok(Number(p.dataset.lokasi), Number(p.dataset.doc));
  });

  /* ========================= RINCIAN NPD ========================= */
  const item = (label, value, span2) => '<div class="spj-detail-item' + (span2 ? ' span2' : '') + '"><div class="k">' + label + '</div><div class="v">' + esc(value || '-') + '</div></div>';
  const bagian = (judul, isi) => '<section class="spj-detail-section"><div class="spj-detail-section-title">' + judul + '</div><div class="spj-detail-grid">' + isi + '</div></section>';

  /** Rincian NPD baca-saja: dipakai bersama oleh panel Lihat Rincian dan panel Edit. */
  function rincianHtml(d) {
    let html = bagian('Identitas Nota Pencairan Dana',
      item('Nomor NPD', d.nomor_npd) + item('Tanggal', d.tanggal) +
      item('Jenis NPD', d.jenis) + item('Status NPD', d.status_npd) +
      item('Nominal', d.nominal) + item('Tagging', d.tagging) +
      item('Terbilang', d.terbilang, true)
    ) + bagian('Struktur Anggaran',
      item('Program', d.program, true) + item('Kegiatan', d.kegiatan, true) +
      item('Sub Kegiatan', d.sub_kegiatan, true) +
      item('Kode Rekening', (d.kode_rekening || '') + ' ' + (d.uraian_rekening || ''), true) +
      item('Uraian', d.uraian, true)
    );

    if (d.surat_perintah) {
      const sp = d.surat_perintah;
      html += bagian('Surat Perintah',
        item('Nomor SP', sp.nomor) + item('Tanggal SP', sp.tanggal) +
        item('Unit Kerja', sp.unit_kerja) + item('Lokasi Penugasan', sp.lokasi) +
        item('Jumlah Anggota', sp.jumlah_anggota) +
        item('Sumber', sp.diwarisi_dari_induk ? 'Diwarisi dari NPD Perjalanan Dinas induk' : 'Melekat pada NPD ini') +
        item('Keterangan', sp.keterangan, true)
      );
    }

    if (d.orang && d.orang.length) {
      html += '<section class="spj-detail-section"><div class="spj-detail-section-title">' + esc(d.label_orang) + ' (' + d.orang.length + ')</div>' +
        '<table class="spj-orang"><thead><tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th style="text-align:right;">Nominal</th></tr></thead><tbody>' +
        d.orang.map(o => '<tr><td>' + esc(o.nama) + (o.penerima ? ' <span class="spj-status lengkap">Penerima</span>' : '') + '</td>' +
          '<td>' + esc(o.nip || '-') + '</td><td>' + esc(o.jabatan || '-') + '</td>' +
          '<td class="num">' + esc(o.nominal) + '</td></tr>').join('') +
        '</tbody></table></section>';
    }

    return html + bagian('Inventarisasi SPJ',
      item('Lokasi Penyimpanan', d.lokasi || 'Belum Ditetapkan') +
      item('Status SPJ', statusLabel[d.status] || '-') +
      item('Catatan', d.catatan || 'Tidak ada catatan', true)
    );
  }

  const cacheRincian = {};
  function ambilRincian(npdId) {
    if (cacheRincian[npdId]) return Promise.resolve(cacheRincian[npdId]);

    return fetch(rincianUrl + '/' + npdId + '/rincian', {headers: {'Accept': 'application/json'}})
      .then(r => { if (!r.ok) throw new Error('gagal'); return r.json(); })
      .then(d => { cacheRincian[npdId] = d; return d; });
  }

  const detailOv = document.getElementById('spj-detail-ov');
  const detailIsi = document.getElementById('spj-detail-content');

  function bukaRincian(npdId) {
    detailIsi.innerHTML = '<div class="spj-memuat">Memuat rincian&hellip;</div>';
    detailOv.classList.add('show');
    ambilRincian(npdId)
      .then(d => { detailIsi.innerHTML = rincianHtml(d); })
      .catch(() => { detailIsi.innerHTML = '<div class="spj-memuat">Rincian gagal dimuat.</div>'; });
  }
  document.getElementById('spj-detail-close').addEventListener('click', () => detailOv.classList.remove('show'));
  detailOv.addEventListener('click', e => { if (e.target === detailOv) detailOv.classList.remove('show'); });

  document.getElementById('inv-table-tbody').addEventListener('click', function (e) {
    const btn = e.target.closest('[data-spj-view]');
    if (btn) bukaRincian(Number(btn.dataset.spjView));
  });

  /* ========================= EDIT ========================= */
  if (bolehEditDetail) {
    const spjOv = document.getElementById('spj-mdl-ov');
    const form = document.getElementById('spj-edit-form');
    const baca = document.getElementById('spj-edit-baca');
    const fLokasi = document.getElementById('spj-f-lokasi');
    const fStatus = document.getElementById('spj-f-status');
    const fCatatan = document.getElementById('spj-f-catatan');

    window.spjModalClose = function () { spjOv.classList.remove('show'); };

    function bukaEdit(npdId) {
      const r = tersaring.find(x => x.npd_id === npdId) || semuaBaris.find(x => x.npd_id === npdId);
      form.action = rincianUrl + '/' + npdId;
      fLokasi.value = r && r.lokasi ? r.lokasi : '';
      fStatus.value = r ? r.status : 'belum_lengkap';
      fCatatan.value = r && r.catatan ? r.catatan : '';
      baca.innerHTML = '<div class="spj-memuat">Memuat rincian NPD&hellip;</div>';
      spjOv.classList.add('show');

      ambilRincian(npdId)
        .then(d => {
          baca.innerHTML = rincianHtml(d);
          // Nilai dari peladen menang atas salinan di tabel.
          fLokasi.value = d.lokasi || '';
          fStatus.value = d.status;
          fCatatan.value = d.catatan || '';
        })
        .catch(() => { baca.innerHTML = '<div class="spj-memuat">Rincian NPD gagal dimuat.</div>'; });
    }

    document.getElementById('inv-table-tbody').addEventListener('click', function (e) {
      const btn = e.target.closest('[data-spj-edit]');
      if (btn) bukaEdit(Number(btn.dataset.spjEdit));
    });

    spjOv.addEventListener('click', e => { if (e.target === spjOv) spjModalClose(); });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    detailOv.classList.remove('show');
    if (bolehEditDetail) window.spjModalClose();
  });

  /* ========================= Lipat tabel ========================= */
  const kepala = document.getElementById('inv-table-head');
  const badan = document.getElementById('inv-table-body');
  const chev = document.getElementById('inv-tbl-chev');
  kepala.addEventListener('click', function () {
    const terbuka = kepala.classList.toggle('open');
    badan.style.display = terbuka ? '' : 'none';
    chev.style.transform = terbuka ? 'rotate(90deg)' : '';
  });
  chev.style.transform = 'rotate(90deg)';
});
</script>
@endif
@endsection
