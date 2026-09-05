@extends('layouts.app')

@section('activeNav', 'keb-input')
@section('title', 'Estimasi Kebutuhan Kegiatan Pengawasan')

@section('content')
<style>
  .keb-unit-head{margin:6px 2px 2px}
  .keb-unit-head .nama{font-size:22px;font-weight:800;color:var(--tegas);line-height:1.2}

  .keb-pkpt-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:10px;max-height:340px}
  .keb-pkpt-tabel{width:100%;min-width:820px;font-size:13px}
  .keb-pkpt-tabel td,.keb-pkpt-tabel th{font-size:13px}
  .keb-pkpt-no{white-space:nowrap;font-weight:700;color:var(--tegas)}

  .keb-keg-head{display:flex;align-items:center;gap:10px;border-bottom:2px solid var(--line);padding-bottom:10px;margin-bottom:4px}
  .keb-keg-no{width:30px;height:30px;border-radius:8px;background:var(--navy);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex:0 0 auto}
  .keb-keg-head h3{margin:0;font-size:17px}

  /* Kotak centang buatan sendiri: checkbox bawaan browser terlalu samar untuk
     pilihan sepenting "kegiatan ini di luar PKPT". */
  .keb-cek{display:flex;align-items:center;gap:10px;margin-top:14px;cursor:pointer;user-select:none}
  .keb-cek input{position:absolute;opacity:0;width:0;height:0}
  .keb-cek .kotak{width:20px;height:20px;border-radius:5px;border:2px solid var(--mut);background:var(--surface);flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center}
  .keb-cek .kotak svg{width:13px;height:13px;stroke:#fff;fill:none;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round;visibility:hidden}
  .keb-cek input:checked + .kotak{background:var(--navy);border-color:var(--navy)}
  .keb-cek input:checked + .kotak svg{visibility:visible}
  .keb-cek input:focus-visible + .kotak{box-shadow:0 0 0 3px rgba(15,39,64,.18)}
  .keb-cek span.teks{font-weight:600;color:var(--tegas);font-size:13px}

  .keb-sub{margin-top:18px;font-weight:700;color:var(--tegas);font-size:13.5px}
  .keb-baris{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
  .keb-baris > div{flex:1;min-width:200px}
  .keb-baris input[type="date"]{width:100%;box-sizing:border-box}

  .keb-rin{border:1px solid var(--line);border-radius:10px;padding:14px;margin-top:12px;background:var(--surface-2)}
  .keb-rin-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .keb-rin-badge{background:var(--info-bg);color:var(--info);border-radius:6px;padding:2px 10px;font-weight:700;font-size:12px}
  .keb-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .keb-grid label{font-size:12px;color:var(--mut);font-weight:600;display:block;margin-bottom:4px}
  .keb-grid input,.keb-grid select{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border:1px solid var(--line);border-radius:7px;background:var(--surface)}
  .keb-grid input[readonly]{background:var(--surface-3);font-weight:700;color:var(--tegas)}
  .keb-grid .span2{grid-column:span 2}
  .keb-grid .span3{grid-column:span 3}
  .keb-grid input.est{background:var(--navy-l);border-color:var(--navy-l);font-weight:800;font-size:13.5px;color:var(--tegas)}
  @media(max-width:820px){.keb-grid{grid-template-columns:1fr}.keb-grid .span2,.keb-grid .span3{grid-column:span 1}}

  .keb-total{margin-top:16px;padding:12px 14px;background:var(--navy);border-radius:9px;text-align:right;color:#fff;font-weight:800;font-size:15px}
  .keb-bar{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .keb-bar .grand{font-weight:700;color:var(--tegas)}

  .keb-dialog{border:none;border-radius:14px;padding:0;max-width:960px;width:calc(100% - 32px);box-shadow:0 24px 60px rgba(15,23,42,.28);background:var(--surface);color:var(--ink)}
  .keb-dialog::backdrop{background:rgba(15,23,42,.45)}
  .keb-dialog .isi{padding:20px 22px}
  .keb-dialog h3{margin:0 0 10px;font-size:17px;color:var(--tegas)}
  .keb-dialog .tabel-wrap{overflow:auto;max-height:52vh;border:1px solid var(--line);border-radius:8px}
  .keb-dialog table{width:100%;min-width:760px}
  .keb-dialog .aksi{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Estimasi Kebutuhan Kegiatan Pengawasan</b></div>
    <div class="ph-title">Estimasi Kebutuhan Kegiatan Pengawasan</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ route('kebutuhan.index') }}" style="white-space:nowrap;">Lihat Data Kebutuhan</a>
  </div>
</div>

<div class="keb-unit-head">
  <div class="sub" style="font-size:12px;margin:0;">Unit Kerja</div>
  <div class="nama">{{ $unit }}</div>
  <div class="sub" style="margin:2px 0 0;">Tahun Anggaran {{ $tahun }} &middot; unit kerja mengikuti akun Anda dan tidak dapat diubah.</div>
</div>

@if ($errors->any())
  <div class="err-box" style="display:block;margin-top:14px;">
    <strong>Periksa kembali isian berikut:</strong>
    <ul style="margin:6px 0 0;padding-left:18px;">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="dash-card" style="margin-top:14px;">
  <h3 style="margin:0 0 4px;">Daftar PKPT Belum Terlaksana</h3>
  <div class="sub">Program kerja pengawasan tahunan unit ini yang belum terlaksana. Pilih salah satunya di tiap kegiatan di bawah.</div>
  @if (count($bahan['belum']))
    <div class="keb-pkpt-wrap">
      <table class="realisasi keb-pkpt-tabel">
        <thead><tr>
          <th style="width:56px;">Nomor</th>
          <th>Area Pengawasan dan Pembinaan</th>
          <th style="width:150px;">Jenis Kegiatan</th>
          <th style="width:150px;" class="num">Estimasi Anggaran</th>
          <th style="width:140px;">Rencana Pelaksanaan</th>
        </tr></thead>
        <tbody>
          @foreach ($bahan['belum'] as $b)
            <tr>
              <td class="keb-pkpt-no">{{ $b['nomor'] }}</td>
              <td>{{ $b['area'] }}</td>
              <td>{{ $b['jenis'] }}</td>
              <td class="num">{{ fmt_rupiah($b['estimasi']) }}</td>
              <td>{{ $b['rencana'] ?: '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="sub" style="margin-top:8px;margin-bottom:0;">
      Tidak ada kegiatan PKPT yang belum terlaksana untuk unit ini. Kegiatan tetap bisa diusulkan dengan mencentang
      &ldquo;tidak terdapat dalam PKPT&rdquo;.
    </div>
  @endif
</div>

<form method="POST" action="{{ route('kebutuhan.store') }}" id="keb-form">
  @csrf
  <div id="keb-kegiatan-list"></div>

  <div class="dash-card keb-bar">
    <button type="button" class="btn" id="keb-tambah-kegiatan" style="white-space:nowrap;">+ Tambah Kegiatan</button>
    <div style="flex:1;"></div>
    <div class="grand">Total Semua Kegiatan: <span id="keb-grand-total">{{ fmt_rupiah(0) }}</span></div>
    <button type="button" class="btn prim" id="keb-konfirmasi" style="white-space:nowrap;">Konfirmasi &amp; Tinjau &rarr;</button>
  </div>
</form>

<dialog class="keb-dialog" id="keb-ringkasan">
  <div class="isi">
    <h3>Ringkasan Kebutuhan Anggaran</h3>
    <div class="sub">Periksa sekali lagi sebelum disimpan. Angka di bawah dihitung ulang di server saat disimpan.</div>
    <div class="tabel-wrap">
      <table class="realisasi">
        <thead><tr>
          <th>Unit Kerja</th><th>Tanggal</th>
          <th class="num">UH Dalam Kota</th><th class="num">UH Luar Kota</th>
          <th class="num">Akomodasi</th><th class="num">Transport</th>
          <th class="num">Estimasi Kebutuhan</th><th>Keterangan</th>
        </tr></thead>
        <tbody id="keb-ringkasan-body"></tbody>
      </table>
    </div>
    <div style="text-align:right;margin-top:10px;font-weight:800;color:var(--tegas);">
      Total: <span id="keb-ringkasan-total">{{ fmt_rupiah(0) }}</span>
    </div>
    <div class="aksi">
      <button type="button" class="btn" id="keb-batal">Kembali</button>
      <button type="button" class="btn prim" id="keb-simpan">Simpan</button>
    </div>
  </div>
</dialog>

<script>
(function () {
  const UNIT = @json($unit);
  const BELUM = @json($bahan['belum']);
  const OPSI_AREA = @json($bahan['area']);
  const OPSI_JENIS = @json($bahan['jenis']);
  const TARIF_DALAM = @json(config('kebutuhan.tarif_uh_dalam'));
  const TARIF_LUAR = @json(config('kebutuhan.tarif_uh_luar'));
  const TARIF_AKOM = @json(config('kebutuhan.tarif_akomodasi'));
  const JENIS_ANGGOTA = @json(config('kebutuhan.jenis_anggota'));
  const LAMA = @json(old('kegiatan'));

  const daftar = document.getElementById('keb-kegiatan-list');
  const form = document.getElementById('keb-form');
  const dialog = document.getElementById('keb-ringkasan');

  const angka = (v) => {
    const n = parseFloat(String(v ?? '').replace(/[^0-9.\-]/g, ''));
    return isNaN(n) ? 0 : n;
  };
  const rupiah = (n) => Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /** Satu rincian kosong. */
  const rincianBaru = () => ({
    jenis_anggota: '', jumlah_orang: '', hari_dalam: '', tarif_uh_dalam: '',
    hari_luar: '', tarif_uh_luar: '', jumlah_malam: '', tarif_akomodasi: '',
    akom_manual: false, tarif_akomodasi_manual: '',
  });

  const kegiatanBaru = () => ({
    luar_pkpt: false, nomor_pkpt: '', area: '', jenis_kegiatan: '', keterangan: '',
    tanggal_mulai: '', tanggal_selesai: '', total_transport: '', rincian: [rincianBaru()],
  });

  // Setelah validasi server gagal, isian dipulihkan dari old() supaya
  // pekerjaan yang sudah diketik tidak hilang.
  let KEG = [];
  if (Array.isArray(LAMA) && LAMA.length) {
    KEG = LAMA.map((k) => ({
      luar_pkpt: !!Number(k.luar_pkpt ?? 0),
      nomor_pkpt: k.nomor_pkpt ?? '',
      area: k.area ?? '',
      jenis_kegiatan: k.jenis_kegiatan ?? '',
      keterangan: k.keterangan ?? '',
      tanggal_mulai: k.tanggal_mulai ?? '',
      tanggal_selesai: k.tanggal_selesai ?? '',
      total_transport: k.total_transport ?? '',
      rincian: Object.values(k.rincian ?? {}).map((d) => ({
        jenis_anggota: d.jenis_anggota ?? '',
        jumlah_orang: d.jumlah_orang ?? '',
        hari_dalam: d.hari_dalam ?? '',
        tarif_uh_dalam: d.tarif_uh_dalam ?? '',
        hari_luar: d.hari_luar ?? '',
        tarif_uh_luar: d.tarif_uh_luar ?? '',
        jumlah_malam: d.jumlah_malam ?? '',
        tarif_akomodasi: d.tarif_akomodasi ?? '',
        akom_manual: angka(d.tarif_akomodasi) > 0 && !TARIF_AKOM.includes(angka(d.tarif_akomodasi)),
        tarif_akomodasi_manual: d.tarif_akomodasi ?? '',
      })),
    }));
    KEG.forEach((k) => { if (!k.rincian.length) k.rincian.push(rincianBaru()); });
  }
  if (!KEG.length) KEG = [kegiatanBaru()];

  // ---- perhitungan (cermin dari KebutuhanAnggaranService::hitungKegiatan) ----
  const tarifAkomEfektif = (d) => angka(d.akom_manual ? d.tarif_akomodasi_manual : d.tarif_akomodasi);
  const hitungRincian = (d) => {
    const uhDalam = angka(d.hari_dalam) * angka(d.tarif_uh_dalam);
    const uhLuar = angka(d.hari_luar) * angka(d.tarif_uh_luar);
    const akom = angka(d.jumlah_malam) * tarifAkomEfektif(d);
    return { uhDalam, uhLuar, akom, est: uhDalam + uhLuar + akom };
  };
  const hitungKegiatan = (k) => {
    const awal = { uhDalam: 0, uhLuar: 0, akom: 0, est: 0 };
    const jml = k.rincian.reduce((a, d) => {
      const h = hitungRincian(d);
      return { uhDalam: a.uhDalam + h.uhDalam, uhLuar: a.uhLuar + h.uhLuar, akom: a.akom + h.akom, est: a.est + h.est };
    }, awal);
    jml.transport = angka(k.total_transport);
    jml.total = jml.est + jml.transport;
    return jml;
  };
  const gabungTarif = (k, kunci) => {
    const set = [...new Set(k.rincian.map((d) => angka(d[kunci])).filter((t) => t > 0))].sort((a, b) => a - b);
    return set.length ? set.map((t) => t.toLocaleString('id-ID')).join('; ') : '-';
  };

  // ---- render ----
  const opsiTarif = (list, terpilih, denganManual) => {
    let html = '<option value="">&mdash; pilih &mdash;</option>';
    html += list.map((t) => `<option value="${t}"${angka(terpilih) === t ? ' selected' : ''}>Rp ${t.toLocaleString('id-ID')}</option>`).join('');
    if (denganManual) html += `<option value="manual"${terpilih === 'manual' ? ' selected' : ''}>Isi Manual</option>`;
    return html;
  };

  function rincianHtml(ki, ri, d) {
    const h = hitungRincian(d);
    const nama = (f) => `kegiatan[${ki}][rincian][${ri}][${f}]`;
    const akomTerpilih = d.akom_manual ? 'manual' : d.tarif_akomodasi;
    return `
      <div class="keb-rin" data-rincian="${ri}">
        <div class="keb-rin-head">
          <span class="keb-rin-badge">Rincian ${ri + 1}</span>
          <div style="flex:1;"></div>
          <button type="button" class="btn" style="padding:4px 10px;font-size:11.5px;color:var(--err-teks);" data-aksi="hapus-rincian">&times; Hapus</button>
        </div>
        <div class="keb-grid">
          <div class="span2">
            <label>Jenis Anggota</label>
            <select name="${nama('jenis_anggota')}" data-f="jenis_anggota">
              <option value="">&mdash; pilih &mdash;</option>
              ${JENIS_ANGGOTA.map((x) => `<option${x === d.jenis_anggota ? ' selected' : ''}>${esc(x)}</option>`).join('')}
            </select>
          </div>
          <div><label>Jumlah Orang</label><input type="number" min="0" name="${nama('jumlah_orang')}" data-f="jumlah_orang" value="${esc(d.jumlah_orang)}"></div>

          <div><label>Jml Hari (Dalam Kota)</label><input type="number" min="0" name="${nama('hari_dalam')}" data-f="hari_dalam" value="${esc(d.hari_dalam)}"></div>
          <div><label>Uang Harian Dalam Kota</label><select name="${nama('tarif_uh_dalam')}" data-f="tarif_uh_dalam">${opsiTarif(TARIF_DALAM, d.tarif_uh_dalam, false)}</select></div>
          <div><label>Jumlah UH Dalam Kota</label><input readonly data-hasil="uhDalam" value="${rupiah(h.uhDalam)}"></div>

          <div><label>Jml Hari (Luar Kota)</label><input type="number" min="0" name="${nama('hari_luar')}" data-f="hari_luar" value="${esc(d.hari_luar)}"></div>
          <div><label>Uang Harian Luar Kota</label><select name="${nama('tarif_uh_luar')}" data-f="tarif_uh_luar">${opsiTarif(TARIF_LUAR, d.tarif_uh_luar, false)}</select></div>
          <div><label>Jumlah UH Luar Kota</label><input readonly data-hasil="uhLuar" value="${rupiah(h.uhLuar)}"></div>

          <div><label>Jumlah Malam</label><input type="number" min="0" name="${nama('jumlah_malam')}" data-f="jumlah_malam" value="${esc(d.jumlah_malam)}"></div>
          <div><label>Tarif Akomodasi</label><select data-f="pilih_akomodasi">${opsiTarif(TARIF_AKOM, akomTerpilih, true)}</select></div>
          ${d.akom_manual
            ? `<div><label>Tarif Akomodasi (manual)</label><input type="number" min="0" data-f="tarif_akomodasi_manual" value="${esc(d.tarif_akomodasi_manual)}"></div>`
            : '<div></div>'}
          <div><label>Total Akomodasi</label><input readonly data-hasil="akom" value="${rupiah(h.akom)}"></div>
          <div class="span2">
            <label>Estimasi Kebutuhan Rincian</label>
            <input readonly class="est" data-hasil="est" value="${rupiah(h.est)}">
          </div>
        </div>
        {{-- Tarif akomodasi yang benar-benar dikirim: pilihan daftar, atau isian manualnya. --}}
        <input type="hidden" name="${nama('tarif_akomodasi')}" data-kirim="tarif_akomodasi" value="${tarifAkomEfektif(d)}">
      </div>`;
  }

  function kegiatanHtml(k, ki) {
    const jml = hitungKegiatan(k);
    const nama = (f) => `kegiatan[${ki}][${f}]`;
    const labelPkpt = k.nomor_pkpt ? labelPkptByNomor(k.nomor_pkpt) : '';
    return `
      <div class="dash-card" style="margin-top:16px;" data-kegiatan="${ki}">
        <div class="keb-keg-head">
          <span class="keb-keg-no">${ki + 1}</span>
          <h3>Kegiatan ${ki + 1}</h3>
          <div style="flex:1;"></div>
          ${KEG.length > 1 ? '<button type="button" class="btn" style="padding:6px 12px;color:var(--err-teks);" data-aksi="hapus-kegiatan">&times; Hapus Kegiatan</button>' : ''}
        </div>

        <div style="margin-top:4px;">
          <label class="fl">Pilih Kegiatan pada PKPT</label>
          <div class="nsearch" data-cari="pkpt"${k.luar_pkpt ? ' style="opacity:.5;pointer-events:none;"' : ''}>
            <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="ns-inp" autocomplete="off" placeholder="Ketik untuk cari kegiatan PKPT&hellip;" value="${esc(labelPkpt)}">
            <div class="ns-drop"></div>
          </div>
          <input type="hidden" name="${nama('nomor_pkpt')}" data-f="nomor_pkpt" value="${esc(k.nomor_pkpt)}">
        </div>

        <label class="keb-cek">
          <input type="checkbox" name="${nama('luar_pkpt')}" value="1" data-f="luar_pkpt"${k.luar_pkpt ? ' checked' : ''}>
          <span class="kotak"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
          <span class="teks">Kegiatan yang akan dilaksanakan tidak terdapat dalam PKPT</span>
        </label>

        ${k.luar_pkpt ? `
          <div style="margin-top:12px;">
            <label class="fl">Keterangan Kegiatan</label>
            <textarea rows="3" style="width:100%;resize:vertical;box-sizing:border-box;" name="${nama('keterangan')}" data-f="keterangan"
                      placeholder="Jelaskan kegiatan yang akan dilaksanakan&hellip;">${esc(k.keterangan)}</textarea>
          </div>` : ''}

        <div class="keb-sub">Detail Kegiatan</div>
        <div class="keb-baris">
          <div>
            <label class="fl">Area Pengawasan dan Pembinaan</label>
            <div class="nsearch" data-cari="area">
              <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="ns-inp" autocomplete="off" placeholder="Pilih / isi manual area&hellip;" name="${nama('area')}" data-f="area" value="${esc(k.area)}">
              <div class="ns-drop"></div>
            </div>
          </div>
          <div>
            <label class="fl">Jenis Kegiatan</label>
            <div class="nsearch" data-cari="jenis">
              <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="ns-inp" autocomplete="off" placeholder="Pilih / isi manual jenis&hellip;" name="${nama('jenis_kegiatan')}" data-f="jenis_kegiatan" value="${esc(k.jenis_kegiatan)}">
              <div class="ns-drop"></div>
            </div>
          </div>
        </div>
        <div class="keb-baris">
          <div><label class="fl">Tanggal Mulai</label><input type="date" name="${nama('tanggal_mulai')}" data-f="tanggal_mulai" value="${esc(k.tanggal_mulai)}"></div>
          <div><label class="fl">Tanggal Selesai</label><input type="date" name="${nama('tanggal_selesai')}" data-f="tanggal_selesai" value="${esc(k.tanggal_selesai)}"></div>
        </div>

        <div class="keb-sub">Estimasi Anggaran</div>
        <div data-rincian-list>${k.rincian.map((d, ri) => rincianHtml(ki, ri, d)).join('')}</div>
        <button type="button" class="btn" style="margin-top:12px;padding:8px 14px;font-weight:600;" data-aksi="tambah-rincian">+ Tambah Rincian</button>

        <div style="margin-top:16px;max-width:340px;">
          <label class="fl">Estimasi Transport (BBM/TOL/Tiket)</label>
          <input type="number" min="0" name="${nama('total_transport')}" data-f="total_transport" value="${esc(k.total_transport)}"
                 placeholder="0" style="width:100%;box-sizing:border-box;font-weight:600;">
          <div class="sub" style="margin-top:3px;margin-bottom:0;">Berlaku untuk seluruh rincian dalam kegiatan ini.</div>
        </div>

        <div class="keb-total" data-total-kegiatan>Total Estimasi Kebutuhan Anggaran &mdash; ${esc(UNIT)}: ${rupiah(jml.total)}</div>
      </div>`;
  }

  function labelPkptByNomor(nomor) {
    const b = BELUM.find((x) => String(x.nomor) === String(nomor));
    return b ? `[${b.nomor}] ${b.area} — ${b.jenis}` : '';
  }

  function render() {
    daftar.innerHTML = KEG.map((k, ki) => kegiatanHtml(k, ki)).join('');
    hitungGrand();
  }

  function kotakKegiatan(el) {
    const kotak = el.closest('[data-kegiatan]');
    return { kotak, ki: Number(kotak.dataset.kegiatan), k: KEG[Number(kotak.dataset.kegiatan)] };
  }

  function perbaruiTotalKegiatan(kotak, k) {
    const jml = hitungKegiatan(k);
    const el = kotak.querySelector('[data-total-kegiatan]');
    if (el) el.innerHTML = `Total Estimasi Kebutuhan Anggaran &mdash; ${esc(UNIT)}: ${rupiah(jml.total)}`;
    hitungGrand();
  }

  function hitungGrand() {
    const total = KEG.reduce((a, k) => a + hitungKegiatan(k).total, 0);
    document.getElementById('keb-grand-total').textContent = rupiah(total);
  }

  /** Hanya blok rincian satu kegiatan yang digambar ulang - render penuh
      membuat halaman melompat ke atas saat menambah rincian. */
  function renderRincian(kotak, k, ki) {
    kotak.querySelector('[data-rincian-list]').innerHTML = k.rincian.map((d, ri) => rincianHtml(ki, ri, d)).join('');
    perbaruiTotalKegiatan(kotak, k);
  }

  // ---- interaksi ----
  daftar.addEventListener('input', (e) => {
    const f = e.target.dataset.f;
    if (!f) return;
    const { kotak, k } = kotakKegiatan(e.target);
    const kotakRincian = e.target.closest('[data-rincian]');

    if (kotakRincian) {
      const d = k.rincian[Number(kotakRincian.dataset.rincian)];
      d[f] = e.target.value;
      const h = hitungRincian(d);
      kotakRincian.querySelector('[data-hasil="uhDalam"]').value = rupiah(h.uhDalam);
      kotakRincian.querySelector('[data-hasil="uhLuar"]').value = rupiah(h.uhLuar);
      kotakRincian.querySelector('[data-hasil="akom"]').value = rupiah(h.akom);
      kotakRincian.querySelector('[data-hasil="est"]').value = rupiah(h.est);
      kotakRincian.querySelector('[data-kirim="tarif_akomodasi"]').value = tarifAkomEfektif(d);
    } else {
      k[f] = e.target.value;
    }
    perbaruiTotalKegiatan(kotak, k);
  });

  daftar.addEventListener('change', (e) => {
    const f = e.target.dataset.f;
    if (!f) return;
    const { kotak, ki, k } = kotakKegiatan(e.target);

    if (f === 'luar_pkpt') {
      k.luar_pkpt = e.target.checked;
      if (k.luar_pkpt) k.nomor_pkpt = '';
      render();
      return;
    }

    const kotakRincian = e.target.closest('[data-rincian]');
    if (!kotakRincian) return;
    const d = k.rincian[Number(kotakRincian.dataset.rincian)];

    if (f === 'pilih_akomodasi') {
      d.akom_manual = e.target.value === 'manual';
      d.tarif_akomodasi = d.akom_manual ? '' : e.target.value;
      renderRincian(kotak, k, ki);
      return;
    }

    d[f] = e.target.value;
    const h = hitungRincian(d);
    kotakRincian.querySelector('[data-hasil="uhDalam"]').value = rupiah(h.uhDalam);
    kotakRincian.querySelector('[data-hasil="uhLuar"]').value = rupiah(h.uhLuar);
    kotakRincian.querySelector('[data-hasil="akom"]').value = rupiah(h.akom);
    kotakRincian.querySelector('[data-hasil="est"]').value = rupiah(h.est);
    kotakRincian.querySelector('[data-kirim="tarif_akomodasi"]').value = tarifAkomEfektif(d);
    perbaruiTotalKegiatan(kotak, k);
  });

  daftar.addEventListener('click', (e) => {
    const aksi = e.target.closest('[data-aksi]')?.dataset.aksi;
    if (!aksi) return;
    const { kotak, ki, k } = kotakKegiatan(e.target);

    if (aksi === 'hapus-kegiatan') {
      KEG.splice(ki, 1);
      if (!KEG.length) KEG.push(kegiatanBaru());
      render();
    } else if (aksi === 'tambah-rincian') {
      k.rincian.push(rincianBaru());
      renderRincian(kotak, k, ki);
      kotak.querySelector('[data-rincian-list]').lastElementChild?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    } else if (aksi === 'hapus-rincian') {
      const ri = Number(e.target.closest('[data-rincian]').dataset.rincian);
      k.rincian.splice(ri, 1);
      if (!k.rincian.length) k.rincian.push(rincianBaru());
      renderRincian(kotak, k, ki);
    }
  });

  // ---- dropdown searchable (PKPT / Area / Jenis) ----
  daftar.addEventListener('focusin', (e) => bukaDrop(e.target));
  daftar.addEventListener('input', (e) => bukaDrop(e.target));
  daftar.addEventListener('focusout', (e) => {
    const drop = e.target.closest('.nsearch')?.querySelector('.ns-drop');
    if (drop) setTimeout(() => drop.classList.remove('show'), 150);
  });

  function bukaDrop(input) {
    const cari = input.closest?.('.nsearch');
    if (!cari || !input.classList.contains('ns-inp')) return;
    const jenis = cari.dataset.cari;
    const drop = cari.querySelector('.ns-drop');
    const q = (input.value || '').toLowerCase();

    if (jenis === 'pkpt') {
      const hit = BELUM
        .map((b) => ({ nomor: b.nomor, label: `[${b.nomor}] ${b.area} — ${b.jenis}` }))
        .filter((o) => o.label.toLowerCase().includes(q))
        .slice(0, 60);
      drop.innerHTML = hit.length
        ? hit.map((o) => `<div class="ns-item" data-nomor="${esc(o.nomor)}">${esc(o.label)}</div>`).join('')
        : '<div class="ns-empty">Tidak ada kegiatan cocok</div>';
    } else {
      const opsi = jenis === 'area' ? OPSI_AREA : OPSI_JENIS;
      const hit = opsi.filter((x) => String(x).toLowerCase().includes(q)).slice(0, 60);
      drop.innerHTML = '<div class="ns-item manual" data-manual="1">&#9998; Isi Manual</div>'
        + hit.map((x) => `<div class="ns-item" data-nilai="${esc(x)}">${esc(x)}</div>`).join('');
    }
    drop.classList.add('show');
  }

  daftar.addEventListener('mousedown', (e) => {
    const item = e.target.closest('.ns-item');
    if (!item) return;
    e.preventDefault();
    const cari = item.closest('.nsearch');
    const input = cari.querySelector('.ns-inp');
    const { kotak, ki, k } = kotakKegiatan(item);

    if (cari.dataset.cari === 'pkpt') {
      const b = BELUM.find((x) => String(x.nomor) === item.dataset.nomor);
      if (b) {
        k.nomor_pkpt = b.nomor;
        k.area = b.area;
        k.jenis_kegiatan = b.jenis;
        k.luar_pkpt = false;
        k.keterangan = '';
        render();
      }
      return;
    }

    const f = input.dataset.f;
    if (item.dataset.manual !== undefined) {
      k[f] = '';
      input.value = '';
      input.placeholder = cari.dataset.cari === 'area' ? 'Ketik area secara manual…' : 'Ketik jenis secara manual…';
      input.focus();
    } else {
      k[f] = item.dataset.nilai;
      input.value = item.dataset.nilai;
    }
    cari.querySelector('.ns-drop').classList.remove('show');
    perbaruiTotalKegiatan(kotak, k);
  });

  document.getElementById('keb-tambah-kegiatan').addEventListener('click', () => {
    KEG.push(kegiatanBaru());
    render();
    daftar.lastElementChild?.scrollIntoView({ block: 'start', behavior: 'smooth' });
  });

  // ---- konfirmasi ----
  document.getElementById('keb-konfirmasi').addEventListener('click', () => {
    // Pemeriksaan cepat di sini hanya untuk mencegah bolak-balik yang
    // percuma; yang menentukan tetap validasi di server.
    const salah = [];
    KEG.forEach((k, i) => {
      const n = i + 1;
      if (!k.luar_pkpt && !k.nomor_pkpt && !k.area) salah.push(`Kegiatan ${n}: pilih kegiatan PKPT atau centang di luar PKPT.`);
      if (k.luar_pkpt && !String(k.keterangan).trim()) salah.push(`Kegiatan ${n}: isi Keterangan Kegiatan.`);
      if (!k.tanggal_mulai || !k.tanggal_selesai) salah.push(`Kegiatan ${n}: isi Tanggal Mulai dan Tanggal Selesai.`);
      if (k.tanggal_mulai && k.tanggal_selesai && k.tanggal_selesai < k.tanggal_mulai) salah.push(`Kegiatan ${n}: Tanggal Selesai tidak boleh sebelum Tanggal Mulai.`);
      k.rincian.forEach((d, r) => {
        if (!d.jenis_anggota) salah.push(`Kegiatan ${n} rincian ${r + 1}: pilih Jenis Anggota.`);
        if (angka(d.jumlah_orang) < 1) salah.push(`Kegiatan ${n} rincian ${r + 1}: isi Jumlah Orang.`);
        if (angka(d.hari_dalam) + angka(d.hari_luar) + angka(d.jumlah_malam) === 0) {
          salah.push(`Kegiatan ${n} rincian ${r + 1}: isi jumlah hari atau jumlah malamnya.`);
        }
      });
    });
    if (salah.length) { alert(salah[0]); return; }

    const body = document.getElementById('keb-ringkasan-body');
    let total = 0;
    body.innerHTML = KEG.map((k) => {
      const j = hitungKegiatan(k);
      total += j.total;
      const ket = k.luar_pkpt ? k.keterangan : [k.area, k.jenis_kegiatan].filter(Boolean).join(' — ');
      return `<tr>
        <td>${esc(UNIT)}</td>
        <td>${esc(k.tanggal_mulai || '?')} s.d. ${esc(k.tanggal_selesai || '?')}</td>
        <td class="num">${esc(gabungTarif(k, 'tarif_uh_dalam'))}</td>
        <td class="num">${esc(gabungTarif(k, 'tarif_uh_luar'))}</td>
        <td class="num">${rupiah(j.akom)}</td>
        <td class="num">${rupiah(j.transport)}</td>
        <td class="num" style="font-weight:700;">${rupiah(j.total)}</td>
        <td>${esc(ket || '-')}</td>
      </tr>`;
    }).join('');
    document.getElementById('keb-ringkasan-total').textContent = rupiah(total);
    dialog.showModal();
  });

  document.getElementById('keb-batal').addEventListener('click', () => dialog.close());
  document.getElementById('keb-simpan').addEventListener('click', () => {
    dialog.close();
    form.submit();
  });

  render();
})();
</script>
@endsection
