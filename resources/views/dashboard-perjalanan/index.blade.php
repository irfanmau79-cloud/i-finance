@extends('layouts.app')

@section('activeNav', 'dashpd')
@section('title', 'Dashboard Perjalanan Dinas')

@section('content')
@php
    use App\Services\PerjalananDinasDashboardService as PdService;

    $rupiah = fn ($nilai) => 'Rp '.fmt_rupiah((float) $nilai);
    $angka = fn ($nilai) => number_format((float) $nilai, 0, ',', '.');
    $rekap = $dashboard['rekap'];
    $tren = $dashboard['tren'];

    // Kolom tabel rekap, urut sama dengan GAS. 'bidang' teks (A-Z dulu),
    // sisanya angka (tertinggi dulu) - lihat sortPDRekap() di gas-lama.
    $kolom = [
        'bidang' => ['label' => 'Bidang', 'num' => false],
        'pegawai' => ['label' => 'Pegawai', 'num' => true],
        'hari' => ['label' => 'Total Hari', 'num' => true],
        'uh' => ['label' => 'Uang Harian', 'num' => true],
        'akom' => ['label' => 'Akomodasi', 'num' => true],
        'trans' => ['label' => 'Transportasi', 'num' => true],
        'terima' => ['label' => 'Total Diterima', 'num' => true],
    ];
@endphp

<style>
  .pd-rekap th.pd-sort{cursor:pointer;user-select:none;white-space:nowrap;}
  .pd-rekap th.pd-sort:hover{color:var(--tegas);}
  .pd-sarrow{font-size:10px;margin-left:2px;}
  .pd-bidang-row{cursor:pointer;}
  .pd-bidang-row:hover{background:var(--surface-2);}
  .pd-bidang-row.pd-open{background:var(--navy-l);}
  .pd-caret{display:inline-block;width:13px;color:var(--tegas);}
  .pd-anggota-row{background:var(--surface-2);}
  .pd-anggota-nama{padding-left:30px !important;}
  .pd-anggota-jab{display:block;color:var(--mut);font-size:11px;margin-top:1px;}
  .pd-total-row{background:var(--navy-l);font-weight:700;border-top:2px solid var(--navy);}
  .pd-filter{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin:10px 0 4px;}
  .pd-filter > div{flex:1;min-width:220px;}
  .pd-chart{position:relative;height:400px;margin-top:10px;}
  .pd-empty{text-align:center;color:var(--mut);padding:40px;}
</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard Perjalanan Dinas</b></div>
    <div class="ph-title">Dashboard Perjalanan Dinas</div>
  </div>
  <div class="ph-actions">
    <a class="btn" href="{{ request()->fullUrl() }}" style="white-space:nowrap;">&#8635; Muat Ulang</a>
  </div>
</div>

{{-- ===== Rekap per Bidang: SELALU setahun penuh, tidak ikut filter ===== --}}
<div class="dash-card" style="margin-bottom:16px;">
  <h3>Rekapan per Bidang</h3>

  <div style="overflow-x:auto;border:1px solid var(--line);border-radius:8px;margin-top:14px;">
    <table class="realisasi pd-rekap" id="pd-rekap-table" style="width:100%;min-width:820px;">
      <thead>
        <tr>
          @foreach ($kolom as $key => $def)
            <th class="pd-sort {{ $def['num'] ? 'num' : '' }}" data-sort="{{ $key }}">
              {{ $def['label'] }} <span class="pd-sarrow"></span>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody id="pd-rekap-body">
        @forelse ($rekap['rows'] as $i => $row)
          <tr class="pd-bidang-row" data-grup="{{ $i }}"
              data-bidang="{{ $row['bidang'] }}" data-pegawai="{{ $row['pegawai'] }}"
              data-hari="{{ $row['hari'] }}" data-uh="{{ $row['uh'] }}"
              data-akom="{{ $row['akom'] }}" data-trans="{{ $row['trans'] }}" data-terima="{{ $row['terima'] }}">
            <td style="font-weight:600;"><span class="pd-caret">&#9656;</span>{{ $row['bidang'] }}</td>
            <td class="num">{{ $row['pegawai'] }}</td>
            <td class="num">{{ $angka($row['hari']) }}</td>
            <td class="num">{{ $rupiah($row['uh']) }}</td>
            <td class="num">{{ $rupiah($row['akom']) }}</td>
            <td class="num">{{ $rupiah($row['trans']) }}</td>
            <td class="num" style="font-weight:600;">{{ $rupiah($row['terima']) }}</td>
          </tr>

          @foreach ($row['anggota'] as $anggota)
            <tr class="pd-anggota-row" data-anak="{{ $i }}" hidden
                data-bidang="{{ $anggota['nama'] }}" data-pegawai="{{ $anggota['nama'] }}"
                data-hari="{{ $anggota['hari'] }}" data-uh="{{ $anggota['uh'] }}"
                data-akom="{{ $anggota['akom'] }}" data-trans="{{ $anggota['trans'] }}" data-terima="{{ $anggota['terima'] }}">
              <td class="pd-anggota-nama">
                @if ($anggota['pegawai_id'])
                  <a href="{{ route('dashboard.perjalanan.pegawai', $anggota['pegawai_id']) }}">{{ $anggota['nama'] }}</a>
                @else
                  {{ $anggota['nama'] }}
                @endif
                @if ($anggota['jabatan'])
                  <span class="pd-anggota-jab">{{ $anggota['jabatan'] }}</span>
                @endif
              </td>
              <td class="num">&mdash;</td>
              <td class="num">{{ $angka($anggota['hari']) }}</td>
              <td class="num">{{ $rupiah($anggota['uh']) }}</td>
              <td class="num">{{ $rupiah($anggota['akom']) }}</td>
              <td class="num">{{ $rupiah($anggota['trans']) }}</td>
              <td class="num">{{ $rupiah($anggota['terima']) }}</td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data.</td></tr>
        @endforelse

        @if ($rekap['rows'] !== [])
          <tr class="pd-total-row" id="pd-total-row">
            <td style="font-weight:800;">{{ $rekap['total']['bidang'] }}</td>
            <td class="num">{{ $rekap['total']['pegawai'] }}</td>
            <td class="num">{{ $angka($rekap['total']['hari']) }}</td>
            <td class="num">{{ $rupiah($rekap['total']['uh']) }}</td>
            <td class="num">{{ $rupiah($rekap['total']['akom']) }}</td>
            <td class="num">{{ $rupiah($rekap['total']['trans']) }}</td>
            <td class="num">{{ $rupiah($rekap['total']['terima']) }}</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- ===== Tren Bulanan: mengikuti filter ===== --}}
<div class="dash-card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
    <div>
      <h3 style="margin:0;">Tren Bulanan</h3>
      <div class="sub">
        Januari &ndash; Desember &middot; {{ $dashboard['metrik_label'] }} &middot;
        {{ $tren['cakupan'] }} ({{ $tren['jumlah_pegawai'] }} pegawai)
      </div>
    </div>
    <div class="an-seg">
      @foreach (PdService::METRIK as $key => $label)
        <a class="an-seg-btn {{ $dashboard['metrik'] === $key ? 'active' : '' }}"
           href="{{ route('dashboard.perjalanan.index', array_merge($filters, ['metrik' => $key])) }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>

  <form method="GET" class="pd-filter" id="pd-filter">
    <input type="hidden" name="metrik" value="{{ $dashboard['metrik'] }}">
    <div>
      <label class="fl" for="pd-bidang-inp" style="margin-top:0;">Bidang</label>
      <div class="nsearch pd-cari" id="pd-bidang-wrap">
        <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="ns-inp" id="pd-bidang-inp" placeholder="Semua Bidang" autocomplete="off" role="combobox" aria-expanded="false">
        <svg class="ns-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        <input type="hidden" id="pd-bidang" name="bidang" value="{{ $filters['bidang'] }}">
        <div class="ns-drop" id="pd-bidang-drop"></div>
      </div>
    </div>
    <div>
      <label class="fl" for="pd-pegawai-inp" style="margin-top:0;">Nama Pegawai</label>
      <div class="nsearch pd-cari" id="pd-pegawai-wrap">
        <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="ns-inp" id="pd-pegawai-inp" placeholder="Semua Pegawai" autocomplete="off" role="combobox" aria-expanded="false">
        <svg class="ns-chev" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        <input type="hidden" id="pd-pegawai" name="pegawai" value="{{ $filters['pegawai'] }}">
        <div class="ns-drop" id="pd-pegawai-drop"></div>
      </div>
    </div>
    <div style="flex:0 0 auto;min-width:0;display:flex;gap:8px;">
      <button class="btn prim" style="white-space:nowrap;">Terapkan</button>
      <a class="btn" href="{{ route('dashboard.perjalanan.index') }}" style="white-space:nowrap;">Reset Filter</a>
    </div>
  </form>

  @if ($tren['kosong'])
    <div class="pd-empty">Tidak ada data untuk filter ini.</div>
  @else
    <div class="pd-chart"><canvas id="pd-chart"></canvas></div>
  @endif
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@include('layouts.partials.chart-tema')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- Dua dropdown yang bisa dicari (port .nsearch dari gas-lama) ----
    const OPSI_BIDANG = {{ Illuminate\Support\Js::from($dashboard['pilihan']['bidang']) }};
    const OPSI_PEGAWAI = {{ Illuminate\Support\Js::from($dashboard['pilihan']['pegawai']) }};

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    /**
     * Combobox: input teks untuk mencari, <input type=hidden> yang benar-benar
     * terkirim, dan daftar pilihan yang tersaring saat mengetik.
     * opsi() dipanggil tiap kali dibuka supaya daftar pegawai selalu mengikuti
     * bidang yang sedang terpilih.
     */
    function comboBox(id, opsi, kosongLabel, saatPilih) {
        const wrap = document.getElementById(id + '-wrap');
        const inp = document.getElementById(id + '-inp');
        const hidden = document.getElementById(id);
        const drop = document.getElementById(id + '-drop');
        let daftar = [];

        function labelDari(value) {
            const found = opsi().find(o => String(o.value) === String(value));
            return found ? found.label : '';
        }

        function render() {
            const q = (inp.value || '').trim().toLowerCase();
            daftar = opsi().filter(o => o.label.toLowerCase().includes(q));

            let html = '<div class="ns-item" data-value="">' + esc(kosongLabel) + '</div>';
            html += daftar.length
                ? daftar.map(o => '<div class="ns-item" data-value="' + esc(o.value) + '">' + esc(o.label)
                    + (o.sub ? '<span class="sub">' + esc(o.sub) + '</span>' : '') + '</div>').join('')
                : (q ? '<div class="ns-item ns-kosong">Tidak ada yang cocok</div>' : '');

            drop.innerHTML = html;
            drop.querySelectorAll('.ns-item:not(.ns-kosong)').forEach(function (it) {
                it.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pilih(it.dataset.value);
                });
            });
        }

        function buka() { render(); drop.classList.add('show'); inp.setAttribute('aria-expanded', 'true'); }
        function tutup() { drop.classList.remove('show'); inp.setAttribute('aria-expanded', 'false'); }

        function pilih(value) {
            hidden.value = value;
            inp.value = value ? labelDari(value) : '';
            tutup();
            if (saatPilih) saatPilih(value);
        }

        inp.addEventListener('focus', buka);
        inp.addEventListener('input', function () { drop.classList.add('show'); render(); });
        inp.addEventListener('blur', function () {
            setTimeout(function () {
                // Ketikan yang tidak dipilih dikembalikan ke label nilai aktif,
                // supaya kotaknya tidak menyisakan teks yang tidak berlaku.
                inp.value = hidden.value ? labelDari(hidden.value) : '';
                tutup();
            }, 150);
        });
        inp.addEventListener('keydown', function (e) {
            const items = Array.from(drop.querySelectorAll('.ns-item:not(.ns-kosong)'));
            if (!items.length) return;
            let idx = items.findIndex(it => it.classList.contains('hl'));

            if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, items.length - 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); }
            else if (e.key === 'Enter') { if (idx >= 0) { e.preventDefault(); pilih(items[idx].dataset.value); } return; }
            else if (e.key === 'Escape') { tutup(); return; }
            else return;

            items.forEach(it => it.classList.remove('hl'));
            items[idx].classList.add('hl');
            items[idx].scrollIntoView({ block: 'nearest' });
        });

        wrap.querySelector('.ns-chev').addEventListener('mousedown', function (e) {
            e.preventDefault();
            if (drop.classList.contains('show')) tutup(); else inp.focus();
        });

        // Isi label awal dari nilai yang sedang aktif.
        if (hidden.value) inp.value = labelDari(hidden.value);

        return { setValue: pilih, getValue: () => hidden.value };
    }

    const comboPegawai = comboBox(
        'pd-pegawai',
        () => {
            const b = document.getElementById('pd-bidang').value;
            return OPSI_PEGAWAI.filter(o => !b || o.bidang === b).map(o => ({ value: o.value, label: o.label, sub: o.bidang }));
        },
        'Semua Pegawai'
    );

    comboBox(
        'pd-bidang',
        () => OPSI_BIDANG.map(b => ({ value: b, label: b })),
        'Semua Bidang',
        function () {
            // Pegawai yang tidak lagi berada di bidang terpilih dilepas.
            const b = document.getElementById('pd-bidang').value;
            const aktif = comboPegawai.getValue();
            if (aktif && b && !OPSI_PEGAWAI.some(o => o.value === aktif && o.bidang === b)) {
                comboPegawai.setValue('');
            }
        }
    );

    // ---- Buka/tutup rincian anggota per bidang ----
    document.querySelectorAll('.pd-bidang-row').forEach(function (row) {
        row.addEventListener('click', function () {
            const anak = document.querySelectorAll('[data-anak="' + row.dataset.grup + '"]');
            const buka = anak.length > 0 && anak[0].hidden;
            anak.forEach(tr => { tr.hidden = !buka; });
            row.classList.toggle('pd-open', buka);
            row.querySelector('.pd-caret').innerHTML = buka ? '&#9662;' : '&#9656;';
        });
    });

    // ---- Urutkan tabel rekap; anggota ikut kolom & arah yang sama ----
    const body = document.getElementById('pd-rekap-body');
    const total = document.getElementById('pd-total-row');
    let sortKey = null;
    let sortDir = -1; // -1 = tertinggi dulu

    function nilai(tr, key) {
        const v = tr.dataset[key];
        return key === 'bidang' || (key === 'pegawai' && tr.classList.contains('pd-anggota-row')) ? String(v ?? '') : (Number(v) || 0);
    }

    function urutkan(key) {
        if (sortKey === key) sortDir = -sortDir;
        else { sortKey = key; sortDir = (key === 'bidang') ? 1 : -1; }

        document.querySelectorAll('.pd-rekap th.pd-sort').forEach(function (th) {
            th.querySelector('.pd-sarrow').innerHTML =
                th.dataset.sort === sortKey ? (sortDir < 0 ? '&#9660;' : '&#9650;') : '';
        });

        // Tiap bidang dibawa bersama anak-anaknya supaya tetap satu grup.
        const grup = Array.from(body.querySelectorAll('.pd-bidang-row')).map(function (induk) {
            return { induk, anak: Array.from(body.querySelectorAll('[data-anak="' + induk.dataset.grup + '"]')) };
        });

        grup.sort(function (a, b) {
            const va = nilai(a.induk, sortKey), vb = nilai(b.induk, sortKey);
            return (typeof va === 'string' ? String(va).localeCompare(String(vb)) : va - vb) * sortDir;
        });

        grup.forEach(function (g) {
            // Kolom "Pegawai" tidak berlaku di level anggota - jatuh ke nama.
            const kunciAnak = (sortKey === 'bidang' || sortKey === 'pegawai') ? 'bidang' : sortKey;
            g.anak.sort(function (a, b) {
                const va = nilai(a, kunciAnak), vb = nilai(b, kunciAnak);
                return (typeof va === 'string' ? String(va).localeCompare(String(vb)) : va - vb) * sortDir;
            });
            body.appendChild(g.induk);
            g.anak.forEach(tr => body.appendChild(tr));
        });

        if (total) body.appendChild(total);
    }

    document.querySelectorAll('.pd-rekap th.pd-sort').forEach(function (th) {
        th.addEventListener('click', () => urutkan(th.dataset.sort));
    });

    // ---- Grafik tren ----
    const canvas = document.getElementById('pd-chart');
    if (canvas && typeof Chart !== 'undefined') {
        const data = {{ Illuminate\Support\Js::from($tren['bulan']) }};
        const metrikHari = {{ Illuminate\Support\Js::from($dashboard['metrik'] === 'hari') }};

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.map(x => x.label),
                datasets: [{
                    label: {{ Illuminate\Support\Js::from($dashboard['metrik_label']) }},
                    data: data.map(x => x.nilai),
                    backgroundColor: warnaGrafik().utama,
                    borderRadius: 4,
                    maxBarThickness: 34,
                    categoryPercentage: 0.6,
                    barPercentage: 0.7,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: i => metrikHari
                                ? i.raw.toLocaleString('id-ID') + ' hari'
                                : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(i.raw),
                        },
                    },
                },
                scales: { y: { beginAtZero: true } },
            },
        });
    }
});
</script>
@endsection
