@extends('layouts.app')
@section('activeNav', 'invspj')
@section('title', 'Inventarisasi SPJ')
@section('content')
<style>.inv-filter-grp.inv-filter-bulan{flex:0.5 1 0;min-width:110px;}</style>

@php
    $ringkasRp = function (float $n): string {
        $abs = abs($n);
        if ($abs >= 1e12) return 'Rp'.number_format($n / 1e12, 1, ',', '.').'T';
        if ($abs >= 1e9) return 'Rp'.number_format($n / 1e9, 1, ',', '.').'M';
        if ($abs >= 1e6) return 'Rp'.number_format($n / 1e6, 1, ',', '.').'jt';
        if ($abs >= 1e3) return 'Rp'.number_format($n / 1e3, 0, ',', '.').'rb';
        return 'Rp'.number_format($n, 0, ',', '.');
    };
    $cariLabel = function ($options, $value) {
        foreach ($options as $opt) {
            if ((string) $opt['value'] === (string) $value) return $opt['label'];
        }
        return '';
    };
    $subSelectedLabel = $filters['sub_kegiatan'] !== '' ? $cariLabel($inventaris['pilihan_berlabel']['sub_kegiatan'], $filters['sub_kegiatan']) : '';
    $kodeSelectedLabel = $filters['kode_rekening'] !== '' ? $cariLabel($inventaris['pilihan_berlabel']['kode_rekening'], $filters['kode_rekening']) : '';
    $taggingSelectedLabel = $filters['tagging'] !== '' ? $cariLabel($inventaris['pilihan_berlabel']['tagging'], $filters['tagging']) : '';
    $subOptionsJs = collect([['value' => '', 'label' => 'Semua Sub Kegiatan']])->concat($inventaris['pilihan_berlabel']['sub_kegiatan']);
    $kodeOptionsJs = collect([['value' => '', 'label' => 'Semua Kode Rekening']])->concat($inventaris['pilihan_berlabel']['kode_rekening']);
    $taggingOptionsJs = collect([['value' => '', 'label' => 'Semua Tagging']])->concat($inventaris['pilihan_berlabel']['tagging']);
@endphp

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Inventarisasi SPJ</b></div>
    <div class="ph-title">Inventarisasi SPJ &mdash; Rak Dokumen</div>
  </div>
</div>

<div class="inv-stats">
  <div class="inv-stat"><div class="lbl">Total Dokumen SPJ</div><div class="val">{{ $inventaris['jumlah_dokumen'] }}</div></div>
  <div class="inv-stat"><div class="lbl">Jumlah Bantex/Box</div><div class="val">{{ $inventaris['jumlah_lokasi'] }}</div></div>
  <div class="inv-stat"><div class="lbl">Rata-Rata Jumlah Dokumen/Bantex</div><div class="val">{{ number_format($inventaris['rata_rata_dokumen_per_bantex'], 1, ',', '.') }}</div></div>
</div>

<form method="GET" action="{{ route('inventarisasi-spj.index') }}" class="inv-filter">
  <div class="inv-filter-grp inv-filter-bulan">
    <label class="fl">Bulan</label>
    <select name="bulan">
      <option value="">Semua Bulan</option>
      @foreach ($inventaris['pilihan']['bulan'] as $bulan)
        <option value="{{ $bulan }}" @selected((string) $filters['bulan'] === (string) $bulan)>{{ now()->setMonth($bulan)->locale('id')->translatedFormat('F') }}</option>
      @endforeach
    </select>
  </div>
  <div class="inv-filter-grp">
    <label class="fl">Sub Kegiatan</label>
    <div class="nsearch" id="inv-sub-wrap">
      <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="ns-inp" id="inv-sub-inp" autocomplete="off" placeholder="Semua Sub Kegiatan" value="{{ $subSelectedLabel }}">
      <input type="hidden" name="sub_kegiatan" id="inv-sub" value="{{ $filters['sub_kegiatan'] }}">
      <div class="ns-drop" id="inv-sub-drop"></div>
    </div>
  </div>
  <div class="inv-filter-grp">
    <label class="fl">Kode Rekening</label>
    <div class="nsearch" id="inv-kode-wrap">
      <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="ns-inp" id="inv-kode-inp" autocomplete="off" placeholder="Semua Kode Rekening" value="{{ $kodeSelectedLabel }}">
      <input type="hidden" name="kode_rekening" id="inv-kode" value="{{ $filters['kode_rekening'] }}">
      <div class="ns-drop" id="inv-kode-drop"></div>
    </div>
  </div>
  <div class="inv-filter-grp">
    <label class="fl">Tagging</label>
    <div class="nsearch" id="inv-tagging-wrap">
      <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="ns-inp" id="inv-tagging-inp" autocomplete="off" placeholder="Semua Tagging" value="{{ $taggingSelectedLabel }}">
      <input type="hidden" name="tagging" id="inv-tagging" value="{{ $filters['tagging'] }}">
      <div class="ns-drop" id="inv-tagging-drop"></div>
    </div>
  </div>
  <div class="inv-filter-row2">
    <div class="inv-filter-grp inv-filter-search">
      <label class="fl">Cari</label>
      <input type="text" name="cari" value="{{ $filters['cari'] }}" placeholder="Nomor Dokumen, penerima, uraian, lokasi&hellip;">
    </div>
    <button class="btn prim" type="submit">Terapkan</button>
    <a class="btn" href="{{ route('inventarisasi-spj.index') }}">Reset</a>
  </div>
</form>

@if($inventaris['kosong'])
  <div class="dash-card" style="text-align:center;padding:45px;color:var(--mut)">Tidak ada dokumen sesuai filter.</div>
@else

<div class="inv-rak-wrap" id="inv-level1">
  <div class="inv-rak-title">Rak Bantex / Box <span class="inv-muted">({{ count($inventaris['lokasi']) }} lokasi &middot; {{ $inventaris['jumlah_dokumen'] }} dokumen)</span></div>
  <div class="inv-rak" id="inv-rak">
    @foreach ($inventaris['lokasi'] as $i => $lok)
      <div class="bantex{{ $lok['jumlah_dokumen'] === 0 ? ' kosong' : '' }}" data-lokasi-index="{{ $i }}" title="{{ $lok['lokasi'] }} — {{ $lok['jumlah_dokumen'] }} dokumen">
        <div class="bx-label">
          <div class="bx-brand">Bantex</div>
          <div class="bx-arsip">ARSIP</div>
          <div class="bx-no">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
          <div class="bx-rule"></div>
          <div class="bx-name">{{ $lok['lokasi'] }}</div>
          <div class="bx-rule"></div>
          <div class="bx-count">{{ $lok['jumlah_dokumen'] }} dok</div>
          <div class="bx-meta">{{ $ringkasRp($lok['nominal']) }}</div>
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
    <span>Tabel Detail SPJ <span class="inv-muted">({{ $inventaris['jumlah_dokumen'] }})</span></span>
  </div>
  <div class="inv-table-body" id="inv-table-body">
    <div class="inv-tbl-card">
      <div class="inv-tbl-scroll">
        <table class="inv-modtable" id="inv-table">
          <thead><tr>
            <th>Bulan</th><th>Nomor Dokumen</th><th>Sub Kegiatan</th><th>Kode Rekening</th>
            <th>Tagging</th><th>Uraian</th><th class="ta-r">Nominal</th><th>Penerima</th><th>Lokasi</th>
          </tr></thead>
          <tbody id="inv-table-tbody"></tbody>
        </table>
      </div>
      <div class="inv-pager" id="inv-pager"></div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const lokasiData = {{ Illuminate\Support\Js::from($inventaris['lokasi']) }};
  const rowsAll = {{ Illuminate\Support\Js::from($inventaris['rows']) }};
  const rp = n => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(n || 0);
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

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
      '<div class="pp-folder"></div>' +
      '<div class="pp-sheet pp-s3"></div>' +
      '<div class="pp-sheet pp-s2"></div>' +
      '<div class="pp-sheet pp-front">' +
        '<div class="pp-npd">' + esc(r.nomor_npd || '(tanpa no)') + '</div>' +
        '<div class="pp-nom">' + rp(r.nominal) + '</div>' +
        '<div class="pp-nama">' + esc(r.penerima || '') + '</div>' +
        '<div class="pp-lines"><i></i><i></i><i></i></div>' +
      '</div>' +
    '</div>';
  }

  // Hitung berapa kartu dokumen yang muat dalam N baris pertama, dengan mengukur
  // offsetTop hasil render sebenarnya (flex-wrap responsif, jumlah per baris tidak tetap).
  function itemsForRows(container, rows) {
    const items = Array.from(container.querySelectorAll('.inv-paper'));
    if (!items.length) return 0;
    const tops = [];
    items.forEach(el => { if (!tops.includes(el.offsetTop)) tops.push(el.offsetTop); });
    if (tops.length <= rows) return items.length;
    const boundaryTop = tops[rows];
    return items.filter(el => el.offsetTop < boundaryTop).length;
  }

  let stackLokasiIdx = null, stackPage = 1, stackPerPage = null;
  const STACK_ROWS = 2;

  function renderStackPage() {
    const stack = document.getElementById('inv-stack');
    const pager = document.getElementById('inv-stack-pager');
    const lok = lokasiData[stackLokasiIdx];
    const total = lok.dokumen.length;
    if (!total) {
      stack.innerHTML = '<div class="inv-muted" style="padding:16px;">Bantex ini kosong (sesuai filter aktif).</div>';
      pager.innerHTML = '';
      return;
    }

    if (stackPerPage === null) {
      const probeCount = Math.min(total, 60);
      stack.innerHTML = lok.dokumen.slice(0, probeCount).map((r, i) => paperHtml(stackLokasiIdx, i, r)).join('');
      stackPerPage = itemsForRows(stack, STACK_ROWS) || probeCount;
    }

    const pages = Math.max(1, Math.ceil(total / stackPerPage));
    if (stackPage > pages) stackPage = pages;
    if (stackPage < 1) stackPage = 1;
    const start = (stackPage - 1) * stackPerPage;
    const slice = lok.dokumen.slice(start, start + stackPerPage);
    stack.innerHTML = slice.map((r, i) => paperHtml(stackLokasiIdx, start + i, r)).join('');

    if (pages <= 1) { pager.innerHTML = ''; return; }
    let info = '<div class="pg-info">Menampilkan ' + (start + 1) + '&ndash;' + (start + slice.length) + ' dari ' + total + ' dokumen</div>';
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
    const finish = () => {
      const lok = lokasiData[idx];
      document.getElementById('inv-stack-title').textContent = lok.lokasi + ' — ' + lok.jumlah_dokumen + ' dokumen';
      stackLokasiIdx = idx;
      stackPage = 1;
      stackPerPage = null;
      backTo(2);
      renderStackPage();
    };
    if (el) { el.classList.add('zooming'); setTimeout(() => { el.classList.remove('zooming'); finish(); }, 300); }
    else finish();
  }

  function bukaDok(lokasiIdx, docIdx) {
    const r = lokasiData[lokasiIdx].dokumen[docIdx];
    if (!r) return;
    document.getElementById('inv-doc-loc').textContent = r.lokasi;
    const row = (k, v, big) => '<div class="inv-doc-row"><div class="k">' + k + '</div><div class="v' + (big ? ' big' : '') + '">' + (v || '&mdash;') + '</div></div>';
    document.getElementById('inv-doc-card').innerHTML =
      '<div class="dc-head"><div class="t">' + esc(r.nomor_npd || '(tanpa nomor NPD)') + '</div>' +
        '<div class="s">' + esc(r.bulan_label) + ' &middot; ' + esc(r.lokasi) + '</div></div>' +
      '<div class="dc-body">' +
        row('Bulan', esc(r.bulan_label)) +
        row('Nomor Dokumen', esc(r.nomor_npd)) +
        row('Jenis Dokumen', esc(r.jenis_dokumen)) +
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
    if (!p) return;
    bukaDok(Number(p.dataset.lokasi), Number(p.dataset.doc));
  });

  let page = 1;
  const perPage = 10;
  function renderTable() {
    const tbody = document.getElementById('inv-table-tbody');
    const total = rowsAll.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (page > pages) page = pages;
    if (page < 1) page = 1;
    const start = (page - 1) * perPage;
    const slice = rowsAll.slice(start, start + perPage);
    tbody.innerHTML = slice.length ? slice.map(r => (
      '<tr>' +
        '<td><span class="badge-bulan">' + esc(r.bulan_label) + '</span></td>' +
        '<td class="cell-npd">' + esc(r.nomor_npd) + '</td>' +
        '<td class="cell-clip" title="' + esc(r.sub_kegiatan) + '">' + esc(r.sub_kegiatan) + '</td>' +
        '<td class="cell-clip" title="' + esc(r.kode_rekening) + '">' + esc(r.kode_rekening) + '</td>' +
        '<td class="cell-clip" title="' + esc(r.tagging || '') + '">' + esc(r.tagging || '-') + '</td>' +
        '<td class="cell-clip" title="' + esc(r.uraian) + '">' + esc(r.uraian) + '</td>' +
        '<td class="ta-r">' + rp(r.nominal) + '</td>' +
        '<td class="cell-clip" title="' + esc(r.penerima) + '">' + esc(r.penerima) + '</td>' +
        '<td><span class="badge-lok">' + esc(r.lokasi) + '</span></td>' +
      '</tr>'
    )).join('') : '<tr><td colspan="9" style="text-align:center;color:var(--mut);padding:22px;">Tidak ada data.</td></tr>';
    renderPager(total, pages, start, slice.length);
  }
  function renderPager(total, pages, start, shown) {
    const pg = document.getElementById('inv-pager');
    if (total === 0) { pg.innerHTML = ''; return; }
    let info = '<div class="pg-info">Menampilkan ' + (start + 1) + '&ndash;' + (start + shown) + ' dari ' + total + ' dokumen</div>';
    let btns = '<button class="inv-pg" ' + (page <= 1 ? 'disabled' : '') + ' data-go="' + (page - 1) + '">&lsaquo;</button>';
    const list = [];
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || (i >= page - 1 && i <= page + 1)) list.push(i);
      else if (list[list.length - 1] !== '…') list.push('…');
    }
    list.forEach(i => {
      btns += i === '…' ? '<span class="inv-pg dots">…</span>' : '<button class="inv-pg' + (i === page ? ' active' : '') + '" data-go="' + i + '">' + i + '</button>';
    });
    btns += '<button class="inv-pg" ' + (page >= pages ? 'disabled' : '') + ' data-go="' + (page + 1) + '">&rsaquo;</button>';
    pg.innerHTML = info + '<div class="pg-btns">' + btns + '</div>';
    pg.querySelectorAll('[data-go]').forEach(b => b.addEventListener('click', () => {
      page = Number(b.dataset.go);
      renderTable();
      document.getElementById('inv-table').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }));
  }
  renderTable();

  document.getElementById('inv-table-head').addEventListener('click', function () {
    const body = document.getElementById('inv-table-body');
    const show = body.style.display === 'none';
    body.style.display = show ? '' : 'none';
    this.classList.toggle('open', show);
  });

  function initSearchSelect(inputId, hiddenId, dropId, options) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const drop = document.getElementById(dropId);
    let selectedLabel = input.value;

    function renderList(query) {
      const q = (query || '').toLowerCase().trim();
      const items = options.filter(o => !q || o.label.toLowerCase().includes(q));
      drop.innerHTML = items.length
        ? items.map(o => '<div class="ns-item" data-value="' + String(o.value).replace(/"/g, '&quot;') + '">' + o.label.replace(/</g, '&lt;') + '</div>').join('')
        : '<div class="ns-empty">Tidak ditemukan</div>';
      drop.classList.add('show');
    }
    function hide() { drop.classList.remove('show'); }

    input.addEventListener('focus', () => renderList(input.value === selectedLabel ? '' : input.value));
    input.addEventListener('input', () => renderList(input.value));
    input.addEventListener('blur', () => setTimeout(() => { hide(); input.value = selectedLabel; }, 150));
    drop.addEventListener('mousedown', function (e) {
      const item = e.target.closest('.ns-item[data-value]');
      if (!item) return;
      e.preventDefault();
      hidden.value = item.dataset.value;
      selectedLabel = item.textContent;
      input.value = selectedLabel;
      hide();
    });
  }

  initSearchSelect('inv-sub-inp', 'inv-sub', 'inv-sub-drop', {{ Illuminate\Support\Js::from($subOptionsJs) }});
  initSearchSelect('inv-kode-inp', 'inv-kode', 'inv-kode-drop', {{ Illuminate\Support\Js::from($kodeOptionsJs) }});
  initSearchSelect('inv-tagging-inp', 'inv-tagging', 'inv-tagging-drop', {{ Illuminate\Support\Js::from($taggingOptionsJs) }});
});
</script>
@endif
@endsection
