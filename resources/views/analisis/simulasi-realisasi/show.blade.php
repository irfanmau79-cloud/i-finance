@extends('layouts.app')

@section('activeNav', 'simulasi-realisasi')
@section('title', 'Simulasi Realisasi — '.$simulasiRealisasi->nama)

@section('content')
<style>
    .sr-summary{border:1px solid var(--line);border-radius:14px;margin-bottom:16px;overflow:hidden;background:var(--surface)}
    .sr-summary-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 18px;
        background:linear-gradient(135deg,var(--surface-2),var(--surface-3));border-bottom:1px solid var(--line);flex-wrap:wrap}
    .sr-summary-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1px;background:var(--line)}
    .sr-cell{background:var(--surface);padding:14px 18px}
    .sr-cell .lbl{font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--mut)}
    .sr-cell .val{font-size:19px;font-weight:800;color:var(--tegas);margin-top:5px;font-variant-numeric:tabular-nums}
    @media(max-width:1000px){.sr-summary-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.sr-summary-grid{grid-template-columns:1fr}}

    .sr-meta{display:grid;grid-template-columns:1fr 1.4fr;gap:12px;margin-bottom:16px}
    .sr-meta label{display:block;font-size:12.5px;font-weight:700;color:var(--tegas);margin-bottom:5px}
    .sr-meta input,.sr-meta textarea{width:100%;box-sizing:border-box;border:1.5px solid var(--line);border-radius:10px;
        padding:10px 12px;font-family:inherit;font-size:13.5px;background:var(--surface);color:var(--ink)}
    .sr-meta textarea{resize:vertical;min-height:44px}
    @media(max-width:800px){.sr-meta{grid-template-columns:1fr}}

    .rr-label{white-space:normal;word-break:break-word}
    .rr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
    table.pivot .ind4{padding-left:94px;color:var(--mut)}
    table.pivot .row-lvl4{background:var(--surface);color:var(--mut)}

    /* Baris rencana: menempel di bawah barisnya, sedikit masuk ke dalam. */
    .sr-rencana-sel{background:var(--surface-2);padding:10px 14px 12px 96px !important}
    .sr-daftar{display:flex;flex-direction:column;gap:7px;margin-bottom:8px}
    .sr-item{display:grid;grid-template-columns:minmax(0,1fr) 190px 34px 34px;gap:8px;align-items:center}
    /* Selama belum ditekan ceklis, isian ini BELUM masuk kolom Proyeksi -
       ditandai garis emas di kiri dan tombol ceklis yang menyala. */
    .sr-item[data-sr-siap="0"]{border-left:3px solid var(--gold);padding-left:7px;margin-left:-10px}
    .ic-btn.ok{border-color:var(--ok);color:var(--ok)}
    .ic-btn.ok:hover{background:var(--ok-bg)}
    .sr-item[data-sr-siap="1"] .sr-simpan{opacity:.35}
    .sr-item[data-sr-siap="0"] .sr-simpan{background:var(--ok-bg)}
    .sr-item input{box-sizing:border-box;border:1.5px solid var(--line);border-radius:9px;padding:8px 11px;
        font-family:inherit;font-size:13px;background:var(--surface);color:var(--ink);width:100%}
    .sr-item input.sr-nominal{text-align:right;font-weight:700;font-variant-numeric:tabular-nums;border-color:var(--gold);color:var(--tegas)}
    .sr-item input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.13)}
    .sr-item input.sr-nominal:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(217,169,56,.22)}
    .sr-kosong{font-size:12.5px;color:var(--mut);font-style:italic}
    @media(max-width:700px){.sr-item{grid-template-columns:1fr 110px 34px 34px}.sr-rencana-sel{padding-left:20px !important}}

    .sr-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
</style>

@php
    $rp = fn ($n) => 'Rp '.fmt_rupiah($n);
    $warna = fn ($sisa) => $sisa < -0.004 ? 'var(--err)' : 'inherit';
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb"><a href="{{ route('simulasi-realisasi.index') }}" style="color:inherit;">Simulasi Realisasi</a> / <b>{{ $simulasiRealisasi->nama }}</b></div>
        <div class="ph-title">{{ $simulasiRealisasi->nama }}</div>
    </div>
    <div class="ph-actions">
        <a class="btn" href="{{ route('simulasi-realisasi.export-excel', $simulasiRealisasi) }}">Unduh Excel</a>
        <a class="btn" target="_blank" href="{{ route('simulasi-realisasi.export-pdf', $simulasiRealisasi) }}">Cetak PDF</a>
    </div>
</div>

@if (session('success'))<div class="sumbar ok" style="margin-bottom:14px;"><span>{{ session('success') }}</span></div>@endif
@if ($errors->any())
    <div class="err-box" style="display:block;margin-bottom:14px;">
        <ul style="margin:0;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="sr-summary">
    <div class="sr-summary-head">
        <div>
            <div style="font-weight:800;color:var(--tegas);font-size:15px;">Proyeksi Capaian Akhir Tahun</div>
            <div class="sub" style="margin:2px 0 0;">Realisasi (Estimasi) = realisasi yang sudah terjadi + Proyeksi yang diisikan di bawah.</div>
        </div>
    </div>
    <div class="sr-summary-grid">
        <div class="sr-cell"><div class="lbl">Pagu</div><div class="val">{{ $rp($total['pagu']) }}</div></div>
        <div class="sr-cell"><div class="lbl">Realisasi</div><div class="val">{{ $rp($total['realisasi']) }}</div></div>
        <div class="sr-cell"><div class="lbl">Realisasi (Estimasi)</div><div class="val" data-sr-agg="realisasi-estimasi" data-sr-node="total">{{ $rp($total['realisasi_estimasi']) }}</div></div>
        <div class="sr-cell"><div class="lbl">Sisa Anggaran</div><div class="val" data-sr-agg="sisa-anggaran" data-sr-node="total">{{ $rp($total['sisa_anggaran']) }}</div></div>
        <div class="sr-cell"><div class="lbl">Proyeksi</div><div class="val" data-sr-agg="proyeksi" data-sr-node="total">{{ $rp($total['proyeksi']) }}</div></div>
        <div class="sr-cell"><div class="lbl">Sisa Anggaran (Estimasi)</div><div class="val" data-sr-agg="sisa-estimasi" data-sr-node="total">{{ $rp($total['sisa_estimasi']) }}</div></div>
    </div>
</div>

<form method="POST" action="{{ route('simulasi-realisasi.update', $simulasiRealisasi) }}" id="sr-form">
    @csrf
    @method('PUT')

    <div class="dash-card">
        <div class="sr-meta">
            <div>
                <label for="nama">Nama Simulasi</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama', $simulasiRealisasi->nama) }}" maxlength="150" required>
            </div>
            <div>
                <label for="keterangan">Keterangan</label>
                <textarea id="keterangan" name="keterangan" maxlength="1000" rows="1">{{ old('keterangan', $simulasiRealisasi->keterangan) }}</textarea>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
            <h3 style="margin:0;color:var(--tegas)">Rencana Realisasi per Mata Anggaran</h3>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button type="button" class="btn" id="sr-open-all">Buka Semua</button>
                <button type="button" class="btn" id="sr-close-all">Tutup Semua</button>
            </div>
        </div>
        <div class="sub">
            Klik baris untuk membuka rinciannya.
            @if (boleh_ubah())
                Pada tiap tagging, tekan <strong>+ Tambah Realisasi</strong> untuk mencatat rencana
                belanja bernama &mdash; boleh lebih dari satu. Pagu dan realisasi berjalan tidak dapat
                diubah di sini.
            @else
                Pagu, realisasi berjalan, dan rencananya ditampilkan apa adanya.
            @endif
        </div>

        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:12px;">
            <table class="realisasi pivot" style="width:100%;min-width:1080px;">
                <colgroup>
                    <col style="width:32%;"><col style="width:12%;"><col style="width:12%;">
                    <col style="width:12%;"><col style="width:12%;"><col style="width:12%;"><col style="width:8%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Program / Kegiatan / Sub Kegiatan / Kode Rekening / Tagging</th>
                        <th class="num">Pagu</th>
                        <th class="num">Realisasi</th>
                        <th class="num">Sisa Anggaran</th>
                        <th class="num">Proyeksi</th>
                        <th class="num">Realisasi (Estimasi)</th>
                        <th class="num">Sisa Anggaran (Estimasi)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tree as $pi => $program)
                        @php $pNode = 'p'.$pi; @endphp
                        <tr class="row-lvl0" data-rr-toggle="{{ $pNode }}" data-rr-node="{{ $pNode }}">
                            <td class="rr-label"><div class="uraian"><svg class="tgl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>{{ $program['nama'] }}</span></div></td>
                            <td class="rr-num">{{ $rp($program['pagu']) }}</td>
                            <td class="rr-num">{{ $rp($program['realisasi']) }}</td>
                            <td class="rr-num" data-sr-agg="sisa-anggaran" data-sr-node="{{ $pNode }}">{{ $rp($program['sisa_anggaran']) }}</td>
                            <td class="rr-num" data-sr-agg="proyeksi" data-sr-node="{{ $pNode }}">{{ $rp($program['proyeksi']) }}</td>
                            <td class="rr-num" data-sr-agg="realisasi-estimasi" data-sr-node="{{ $pNode }}">{{ $rp($program['realisasi_estimasi']) }}</td>
                            <td class="rr-num" data-sr-agg="sisa-estimasi" data-sr-node="{{ $pNode }}">{{ $rp($program['sisa_estimasi']) }}</td>
                        </tr>

                        @foreach ($program['kegiatan'] as $ki => $kegiatan)
                            @php $kNode = $pNode.'-k'.$ki; @endphp
                            <tr class="row-lvl1" data-rr-toggle="{{ $kNode }}" data-rr-node="{{ $kNode }}" data-rr-ancestors="{{ $pNode }}">
                                <td class="rr-label ind1"><div class="uraian"><svg class="tgl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>{{ $kegiatan['nama'] }}</span></div></td>
                                <td class="rr-num">{{ $rp($kegiatan['pagu']) }}</td>
                                <td class="rr-num">{{ $rp($kegiatan['realisasi']) }}</td>
                                <td class="rr-num" data-sr-agg="sisa-anggaran" data-sr-node="{{ $kNode }}">{{ $rp($kegiatan['sisa_anggaran']) }}</td>
                            <td class="rr-num" data-sr-agg="proyeksi" data-sr-node="{{ $kNode }}">{{ $rp($kegiatan['proyeksi']) }}</td>
                            <td class="rr-num" data-sr-agg="realisasi-estimasi" data-sr-node="{{ $kNode }}">{{ $rp($kegiatan['realisasi_estimasi']) }}</td>
                            <td class="rr-num" data-sr-agg="sisa-estimasi" data-sr-node="{{ $kNode }}">{{ $rp($kegiatan['sisa_estimasi']) }}</td>
                            </tr>

                            @foreach ($kegiatan['subKegiatan'] as $si => $sub)
                                @php $sNode = $kNode.'-s'.$si; @endphp
                                <tr class="row-lvl2" data-rr-toggle="{{ $sNode }}" data-rr-node="{{ $sNode }}" data-rr-ancestors="{{ $pNode }} {{ $kNode }}">
                                    <td class="rr-label ind2"><div class="uraian"><svg class="tgl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>{{ $sub['nama'] }}</span></div></td>
                                    <td class="rr-num">{{ $rp($sub['pagu']) }}</td>
                                    <td class="rr-num">{{ $rp($sub['realisasi']) }}</td>
                                    <td class="rr-num" data-sr-agg="sisa-anggaran" data-sr-node="{{ $sNode }}">{{ $rp($sub['sisa_anggaran']) }}</td>
                            <td class="rr-num" data-sr-agg="proyeksi" data-sr-node="{{ $sNode }}">{{ $rp($sub['proyeksi']) }}</td>
                            <td class="rr-num" data-sr-agg="realisasi-estimasi" data-sr-node="{{ $sNode }}">{{ $rp($sub['realisasi_estimasi']) }}</td>
                            <td class="rr-num" data-sr-agg="sisa-estimasi" data-sr-node="{{ $sNode }}">{{ $rp($sub['sisa_estimasi']) }}</td>
                                </tr>

                                @foreach ($sub['rekening'] as $ri => $rekening)
                                    @php $rNode = $sNode.'-r'.$ri; @endphp
                                    <tr class="row-lvl3" data-rr-toggle="{{ $rNode }}" data-rr-node="{{ $rNode }}" data-rr-ancestors="{{ $pNode }} {{ $kNode }} {{ $sNode }}">
                                        <td class="rr-label ind3"><div class="uraian"><svg class="tgl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>{{ $rekening['kode'] }} {{ $rekening['uraian'] }}</span></div></td>
                                        <td class="rr-num">{{ $rp($rekening['pagu']) }}</td>
                                        <td class="rr-num">{{ $rp($rekening['realisasi']) }}</td>
                                        <td class="rr-num" data-sr-agg="sisa-anggaran" data-sr-node="{{ $rNode }}">{{ $rp($rekening['sisa_anggaran']) }}</td>
                            <td class="rr-num" data-sr-agg="proyeksi" data-sr-node="{{ $rNode }}">{{ $rp($rekening['proyeksi']) }}</td>
                            <td class="rr-num" data-sr-agg="realisasi-estimasi" data-sr-node="{{ $rNode }}">{{ $rp($rekening['realisasi_estimasi']) }}</td>
                            <td class="rr-num" data-sr-agg="sisa-estimasi" data-sr-node="{{ $rNode }}">{{ $rp($rekening['sisa_estimasi']) }}</td>
                                    </tr>

                                    @foreach ($rekening['baris'] as $row)
                                        @php $anc = $pNode.' '.$kNode.' '.$sNode.' '.$rNode; @endphp
                                        <tr class="row-lvl4" data-rr-ancestors="{{ $anc }}"
                                            data-sr-row="{{ $row->id }}" data-sr-pagu="{{ (float) $row->pagu }}" data-sr-realisasi="{{ (float) $row->realisasi }}">
                                            <td class="rr-label ind4"><div class="uraian"><span class="spacer"></span><span>{{ $row->tagging_nama ?? 'Tanpa Tagging' }}</span></div></td>
                                            <td class="rr-num">{{ $rp($row->pagu) }}</td>
                                            <td class="rr-num">{{ $rp($row->realisasi) }}</td>
                                            <td class="rr-num">{{ $rp($row->sisa_anggaran) }}</td>
                                            <td class="rr-num" data-sr-baris="proyeksi">{{ $rp($row->proyeksi_total) }}</td>
                                            <td class="rr-num" data-sr-baris="realisasi-estimasi">{{ $rp($row->realisasi_estimasi) }}</td>
                                            <td class="rr-num" data-sr-baris="sisa-estimasi" style="color:{{ $warna($row->sisa_estimasi) }}">{{ $rp($row->sisa_estimasi) }}</td>
                                        </tr>
                                        <tr class="sr-rencana" data-rr-ancestors="{{ $anc }}">
                                            <td colspan="7" class="sr-rencana-sel">
                                                <div class="sr-daftar" data-sr-daftar="{{ $row->id }}">
                                                    @forelse ($row->items as $i => $item)
                                                        <div class="sr-item" data-sr-siap="1">
                                                            <input type="text" class="sr-nama" value="{{ $item->nama }}" maxlength="255" placeholder="Nama rencana, misalnya Perjalanan dinas ke Cirebon">
                                                            <input type="text" class="sr-nominal" inputmode="decimal" value="{{ fmt_rupiah($item->nominal) }}" placeholder="0,00" aria-label="Nominal rencana">
                                                            <input type="hidden" name="items[{{ $row->id }}][{{ $i }}][nama]" value="{{ $item->nama }}">
                                                            <input type="hidden" name="items[{{ $row->id }}][{{ $i }}][nominal]" value="{{ (float) $item->nominal }}">
                                                            <button type="button" class="ic-btn ok sr-simpan" title="Masukkan ke Proyeksi"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>
                                                            <button type="button" class="ic-btn danger sr-hapus" title="Hapus rencana"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                                        </div>
                                                    @empty
                                                        <div class="sr-kosong">Belum ada rencana pada tagging ini.</div>
                                                    @endforelse
                                                </div>
                                                @if (boleh_ubah())
                                                <button type="button" class="btn sr-tambah" data-sr-target="{{ $row->id }}">+ Tambah Realisasi</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach
                        @endforeach
                    @empty
                        <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:24px;">Simulasi ini tidak berisi mata anggaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (boleh_ubah())
            <div class="sr-actions"><button type="submit" class="btn prim">Simpan Simulasi</button></div>
        @else
            <div class="sub" style="margin-top:16px;">Anda membuka simulasi ini sebagai pemantau &mdash; perubahan tidak dapat disimpan.</div>
        @endif
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabel = document.getElementById('sr-form');
    if (! tabel) return;

    // ---- Buka/tutup pohon ----
    const terbuka = new Set();
    function segarkanPohon() {
        document.querySelectorAll('[data-rr-ancestors]').forEach(function (baris) {
            const leluhur = baris.dataset.rrAncestors.split(' ').filter(Boolean);
            baris.hidden = ! leluhur.every(n => terbuka.has(n));
        });
        document.querySelectorAll('[data-rr-toggle]').forEach(function (baris) {
            const panah = baris.querySelector('.tgl');
            if (panah) panah.classList.toggle('open', terbuka.has(baris.dataset.rrToggle));
        });
    }
    document.querySelectorAll('[data-rr-toggle]').forEach(function (baris) {
        baris.addEventListener('click', function (e) {
            if (e.target.closest('input,button,a,textarea')) return;
            const n = baris.dataset.rrToggle;
            terbuka.has(n) ? terbuka.delete(n) : terbuka.add(n);
            segarkanPohon();
        });
    });
    document.getElementById('sr-open-all').addEventListener('click', function () {
        document.querySelectorAll('[data-rr-toggle]').forEach(b => terbuka.add(b.dataset.rrToggle));
        segarkanPohon();
    });
    document.getElementById('sr-close-all').addEventListener('click', function () {
        terbuka.clear();
        segarkanPohon();
    });

    // ---- Format angka ----
    function formatTampil(mentah) {
        let bersih = String(mentah).replace(/[^0-9,]/g, '');
        const koma = bersih.indexOf(',');
        if (koma !== -1) bersih = bersih.slice(0, koma + 1) + bersih.slice(koma + 1).replace(/,/g, '');
        let [utuh, pecahan] = bersih.split(',');
        utuh = (utuh || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return pecahan !== undefined ? utuh + ',' + pecahan.slice(0, 2) : utuh;
    }
    function baca(tampilan) {
        if (! tampilan) return 0;
        return parseFloat(String(tampilan).replace(/\./g, '').replace(',', '.')) || 0;
    }
    function rupiah(n) {
        const negatif = n < 0;
        const bagian = Math.abs(n).toFixed(2).split('.');
        bagian[0] = bagian[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (negatif ? '-' : '') + 'Rp ' + bagian[0] + ',' + bagian[1];
    }
    function persen(n) {
        return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d),)/g, '.') + '%';
    }

    // ---- Hitung ulang seluruh angka turunan ----
    function hitung() {
        const simpul = {};
        const catat = function (kunci, pagu, realisasi, proyeksi) {
            const s = simpul[kunci] || (simpul[kunci] = { pagu: 0, realisasi: 0, proyeksi: 0 });
            s.pagu += pagu; s.realisasi += realisasi; s.proyeksi += proyeksi;
        };

        document.querySelectorAll('[data-sr-row]').forEach(function (baris) {
            const pagu = parseFloat(baris.dataset.srPagu) || 0;
            const realisasi = parseFloat(baris.dataset.srRealisasi) || 0;
            const daftar = document.querySelector('[data-sr-daftar="' + baris.dataset.srRow + '"]');

            // HANYA isian yang sudah ditekan ceklis yang ikut dihitung. Yang masih
            // diketik belum masuk Proyeksi - itu gunanya tombol ceklis.
            let proyeksi = 0;
            if (daftar) {
                daftar.querySelectorAll('.sr-item[data-sr-siap="1"]').forEach(function (item) {
                    const nominal = item.querySelector('input[type=hidden][name$="[nominal]"]');
                    proyeksi += parseFloat(nominal ? nominal.value : 0) || 0;
                });
            }

            const estimasi = realisasi + proyeksi;
            const sisaEstimasi = pagu - estimasi;
            baris.querySelector('[data-sr-baris="proyeksi"]').textContent = rupiah(proyeksi);
            baris.querySelector('[data-sr-baris="realisasi-estimasi"]').textContent = rupiah(estimasi);
            const selSisa = baris.querySelector('[data-sr-baris="sisa-estimasi"]');
            selSisa.textContent = rupiah(sisaEstimasi);
            selSisa.style.color = sisaEstimasi < -0.004 ? 'var(--err)' : '';

            (baris.dataset.rrAncestors || '').split(' ').filter(Boolean).forEach(n => catat(n, pagu, realisasi, proyeksi));
            catat('total', pagu, realisasi, proyeksi);
        });

        document.querySelectorAll('[data-sr-agg]').forEach(function (sel) {
            const s = simpul[sel.dataset.srNode] || { pagu: 0, realisasi: 0, proyeksi: 0 };
            const estimasi = s.realisasi + s.proyeksi;
            const jenis = sel.dataset.srAgg;

            if (jenis === 'sisa-anggaran') sel.textContent = rupiah(s.pagu - s.realisasi);
            else if (jenis === 'proyeksi') sel.textContent = rupiah(s.proyeksi);
            else if (jenis === 'realisasi-estimasi') sel.textContent = rupiah(estimasi);
            else if (jenis === 'sisa-estimasi') {
                const sisa = s.pagu - estimasi;
                sel.textContent = rupiah(sisa);
                sel.style.color = sisa < -0.004 ? 'var(--err)' : '';
            }
        });
    }

    // ---- Tambah / hapus rencana ----
    function nomorBerikut(daftar) {
        return daftar.querySelectorAll('.sr-item').length;
    }
    function pasangBaris(daftar, rowId) {
        const kosong = daftar.querySelector('.sr-kosong');
        if (kosong) kosong.remove();

        const i = Date.now() + '-' + Math.floor(Math.random() * 1000);
        const wadah = document.createElement('div');
        wadah.className = 'sr-item';
        wadah.dataset.srSiap = '0';
        wadah.innerHTML =
            '<input type="text" class="sr-nama" maxlength="255" placeholder="Nama rencana, misalnya Perjalanan dinas ke Cirebon">' +
            '<input type="text" class="sr-nominal" inputmode="decimal" placeholder="0,00" aria-label="Nominal rencana">' +
            '<input type="hidden" name="items[' + rowId + '][' + i + '][nama]" value="">' +
            '<input type="hidden" name="items[' + rowId + '][' + i + '][nominal]" value="0">' +
            '<button type="button" class="ic-btn ok sr-simpan" title="Masukkan ke Proyeksi"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>' +
            '<button type="button" class="ic-btn danger sr-hapus" title="Hapus rencana"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        daftar.appendChild(wadah);
        wadah.querySelector('.sr-nama').focus();
    }

    tabel.addEventListener('click', function (e) {
        const tambah = e.target.closest('.sr-tambah');
        if (tambah) {
            e.preventDefault();
            pasangBaris(document.querySelector('[data-sr-daftar="' + tambah.dataset.srTarget + '"]'), tambah.dataset.srTarget);
            hitung();
            return;
        }
        const simpan = e.target.closest('.sr-simpan');
        if (simpan) {
            e.preventDefault();
            masukkan(simpan.closest('.sr-item'));
            return;
        }
        const hapus = e.target.closest('.sr-hapus');
        if (hapus) {
            e.preventDefault();
            const daftar = hapus.closest('.sr-daftar');
            hapus.closest('.sr-item').remove();
            if (! daftar.querySelector('.sr-item')) {
                const p = document.createElement('div');
                p.className = 'sr-kosong';
                p.textContent = 'Belum ada rencana pada tagging ini.';
                daftar.appendChild(p);
            }
            hitung();
        }
    });

    /**
     * Memindahkan isian yang terlihat ke isian tersembunyi yang benar-benar
     * dikirim, lalu menandai barisnya siap. Sejak titik ini nominalnya ikut
     * dihitung ke kolom Proyeksi dan naik ke atas - tanpa memuat ulang halaman.
     *
     * Ini BELUM menyimpan ke basis data: penyimpanan sesungguhnya tetap lewat
     * tombol Simpan Simulasi di bawah.
     */
    function masukkan(item) {
        if (! item) return;
        const nama = item.querySelector('.sr-nama');
        const nominal = item.querySelector('.sr-nominal');
        const hNama = item.querySelector('input[type=hidden][name$="[nama]"]');
        const hNominal = item.querySelector('input[type=hidden][name$="[nominal]"]');

        // Baris yang benar-benar kosong tidak perlu dimasukkan.
        if (! nama.value.trim() && baca(nominal.value) <= 0) return;

        hNama.value = nama.value;
        hNominal.value = baca(nominal.value);
        item.dataset.srSiap = '1';
        hitung();
    }

    tabel.addEventListener('input', function (e) {
        const item = e.target.closest('.sr-item');
        if (! item) return;

        if (e.target.classList.contains('sr-nominal')) {
            e.target.value = formatTampil(e.target.value);
        } else if (! e.target.classList.contains('sr-nama')) {
            return;
        }

        // Begitu diubah, isian keluar lagi dari hitungan sampai ceklisnya
        // ditekan - supaya angka di kolom Proyeksi selalu angka yang memang
        // sudah dikonfirmasi, bukan yang sedang setengah diketik.
        item.dataset.srSiap = '0';
        hitung();
    });

    // Enter di dalam baris rencana memasukkan isiannya, bukan mengirim formulir.
    tabel.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const item = e.target.closest('.sr-item');
        if (! item) return;
        e.preventDefault();
        masukkan(item);
    });

    hitung();
});
</script>
@endsection
