@extends('layouts.app')

@section('activeNav', 'spm-ls')
@php
    $spmEdit = $spm ?? null;

    // Pilihan dropdown Penerima dipadatkan jadi satu nilai ("manual",
    // "pegawai:12", "vendor:3") supaya satu isian saja yang perlu dikirim.
    $penerimaSumberAwal = old('penerima_sumber');

    if ($penerimaSumberAwal === null && $spmEdit) {
        $penerimaSumberAwal = match (true) {
            $spmEdit->penerima_pegawai_id !== null => 'pegawai:'.$spmEdit->penerima_pegawai_id,
            $spmEdit->penerima_vendor_id !== null => 'vendor:'.$spmEdit->penerima_vendor_id,
            $spmEdit->penerima !== null => 'manual',
            default => null,
        };
    }

    $bankAwal = old('bank_tujuan', $spmEdit?->bank_tujuan) ?? '';
    // Bank yang tidak ada di daftar berarti dulu diketik sendiri.
    $bankManualAwal = $bankAwal !== '' && ! in_array($bankAwal, config('bank.daftar'), true);
@endphp
@section('title', 'Data Realisasi Surat Perintah Pencairan Dana (SP2D) LS')

@section('content')
<style>
  /* ===== Pemilih mata anggaran + daftar kode rekening SPM LS =====
     Satu SPM/SP2D LS mencakup beberapa kode rekening, jadi Program/Kegiatan/
     Sub Kegiatan dipilih SEKALI di atas dan tiap kode rekening menjadi satu
     baris ringkas: kode rekening, tagging, sisa anggaran versi sistem, lalu
     nominal bruto untuk kode+tagging itu. */
  .spm-pick{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px 14px;}
  @media(max-width:900px){.spm-pick{grid-template-columns:1fr;}}

  .spm-baris-head,.spm-baris{display:grid;
    grid-template-columns:minmax(0,2.2fr) minmax(0,1.5fr) minmax(0,1.25fr) minmax(0,1.25fr) 34px;
    gap:10px;align-items:center;}
  .spm-baris-head{padding:0 2px 7px;font-size:10.5px;font-weight:700;letter-spacing:.5px;
    text-transform:uppercase;color:var(--mut);border-bottom:2px solid var(--line);}
  .spm-baris-head .num{text-align:right;}
  .spm-baris{padding:11px 2px;border-bottom:1px solid var(--line);}
  .spm-baris:last-child{border-bottom:none;}
  .spm-baris .sb-sisa{text-align:right;font-size:13px;font-weight:700;color:var(--ok);white-space:nowrap;}
  .spm-baris .sb-sisa.kosong{color:var(--mut);font-weight:400;}
  .spm-baris input[data-ma-nominal]{text-align:right;}
  .spm-baris .sb-nota{display:block;margin-top:4px;font-size:11px;line-height:1.35;color:var(--err);}
  .spm-baris .sb-konteks{display:block;margin-top:4px;font-size:11px;color:var(--mut);}
  .spm-baris .ic-btn{margin:0;}

  .spm-penerima{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1.4fr) minmax(0,1fr);gap:6px 14px;}
  @media(max-width:820px){.spm-penerima{grid-template-columns:1fr;}}
  .spm-penerima input[readonly]{background:var(--surface-2);color:var(--mut);}
  :root[data-tema="gelap"] .spm-penerima input[readonly]{background:var(--surface-2);}
  .spm-bank-manual{margin-top:6px;}

  /* Rekap penutup: bruto dikurangi pajak. Netto sengaja paling menonjol
     karena itulah angka yang benar-benar diterima penerima. */
  .spm-rekap{margin-top:20px;border:1px solid var(--line);border-radius:var(--radius-sm);
    padding:6px 16px;background:var(--surface-2);}
  .spm-rekap .baris{display:flex;justify-content:space-between;align-items:baseline;gap:16px;
    padding:9px 0;font-size:13px;color:var(--mut);}
  .spm-rekap .baris + .baris{border-top:1px solid var(--line);}
  .spm-rekap .v{font-variant-numeric:tabular-nums;font-weight:700;color:var(--ink);}
  .spm-rekap .v.kurang{color:var(--err);}
  .spm-rekap .netto{padding:12px 0;font-size:14px;font-weight:700;color:var(--tegas);}
  .spm-rekap .netto .v{font-size:17px;color:var(--ok);}

  .spm-kosong{padding:20px 12px;text-align:center;font-size:12.5px;color:var(--mut);}
  .spm-tambah:disabled{opacity:.5;cursor:not-allowed;}

  /* Di layar sempit baris berubah jadi kartu: judul kolom pindah ke tiap
     isian supaya angkanya tidak kehilangan konteks. */
  @media(max-width:820px){
    .spm-baris-head{display:none;}
    .spm-baris{grid-template-columns:1fr;gap:4px;padding:14px 2px;position:relative;}
    .spm-baris > div::before{content:attr(data-l);display:block;margin-bottom:4px;
      font-size:11px;font-weight:700;color:var(--tegas);}
    .spm-baris .sb-sisa{text-align:left;}
    .spm-baris input[data-ma-nominal]{text-align:left;}
    .spm-baris .ic-btn{position:absolute;top:8px;right:0;}
  }
</style>

<div class="dash-card">
    <h3>Data Realisasi Surat Perintah Pencairan Dana (SP2D) LS</h3>

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

    <form method="POST" action="{{ $spmEdit ? route('spm.ls.update', $spmEdit) : route('spm.ls.store') }}" id="spm-ls-form">
        @csrf
        @if ($spmEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_dokumen">Tanggal SPM</label>
                <input type="date" id="tanggal_dokumen" name="tanggal_dokumen" value="{{ old('tanggal_dokumen', $spmEdit?->tanggal_dokumen?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_dokumen">Nomor SPM</label>
                <input type="text" id="nomor_dokumen" name="nomor_dokumen" value="{{ old('nomor_dokumen', $spmEdit?->nomor_dokumen) }}">
            </div>
        </div>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="tanggal_sp2d">Tanggal SP2D (opsional)</label>
                <input type="date" id="tanggal_sp2d" name="tanggal_sp2d" value="{{ old('tanggal_sp2d', $spmEdit?->tanggal_sp2d?->format('Y-m-d')) }}">
            </div>
            <div class="fg">
                <label class="fl" for="nomor_sp2d">Nomor SP2D (opsional)</label>
                <input type="text" id="nomor_sp2d" name="nomor_sp2d" value="{{ old('nomor_sp2d', $spmEdit?->nomor_sp2d) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Mata Anggaran</h3>
        <div class="spm-pick">
            <div class="fg">
                <label class="fl" for="ma-program">Program</label>
                <select id="ma-program" data-cari><option value="">Memuat data&hellip;</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="ma-kegiatan">Kegiatan</label>
                <select id="ma-kegiatan" data-cari disabled><option value="">Pilih program dulu</option></select>
            </div>
            <div class="fg">
                <label class="fl" for="ma-sub">Sub Kegiatan</label>
                <select id="ma-sub" data-cari disabled><option value="">Pilih kegiatan dulu</option></select>
            </div>
        </div>

        <button type="button" class="add spm-tambah" id="baris-add" disabled>+ Kode Rekening</button>

        <div class="spm-baris-head" style="margin-top:18px;">
            <span>Kode Rekening</span>
            <span>Tagging</span>
            <span class="num">Sisa Anggaran (sistem)</span>
            <span class="num">Nominal Bruto (Rp)</span>
            <span></span>
        </div>
        <div id="baris-list"></div>
        <div class="spm-kosong" id="baris-kosong">
            Belum ada kode rekening. Pilih Program, Kegiatan, dan Sub Kegiatan di atas, lalu tekan <strong>+ Kode Rekening</strong>.
        </div>

        <div class="sumbar" style="margin-top:16px;">
            <span>Total Bruto</span>
            <span class="v" id="total-nominal">Rp 0</span>
        </div>

        <h3 style="margin-top:22px;">Biaya Pajak</h3>
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="ppn">PPN (Rp)</label>
                <input type="text" data-rupiah data-pajak id="ppn" name="ppn" value="{{ old('ppn', $spmEdit?->ppn) }}">
            </div>
            <div class="fg"></div>
            <div class="fg">
                <label class="fl" for="jenis_pph1">Jenis PPh 1</label>
                <input type="text" id="jenis_pph1" name="jenis_pph1" placeholder="mis. PPh Pasal 22" value="{{ old('jenis_pph1', $spmEdit?->jenis_pph1) }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph1">Nilai PPh 1 (Rp)</label>
                <input type="text" data-rupiah data-pajak id="pph1" name="pph1" value="{{ old('pph1', $spmEdit?->pph1) }}">
            </div>
            <div class="fg">
                <label class="fl" for="jenis_pph2">Jenis PPh 2 (opsional)</label>
                <input type="text" id="jenis_pph2" name="jenis_pph2" placeholder="mis. PPh Pasal 23" value="{{ old('jenis_pph2', $spmEdit?->jenis_pph2) }}">
            </div>
            <div class="fg">
                <label class="fl" for="pph2">Nilai PPh 2 (Rp)</label>
                <input type="text" data-rupiah data-pajak id="pph2" name="pph2" value="{{ old('pph2', $spmEdit?->pph2) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Penerima</h3>
        <div class="fg">
            <label class="fl" for="penerima_sumber">Pilih Penerima</label>
            <select id="penerima_sumber" name="penerima_sumber" data-cari>
                <option value="">&mdash; Pilih dari Data Pegawai / Vendor &mdash;</option>
                <option value="manual" @selected($penerimaSumberAwal === 'manual')>Isi Manual</option>
                @foreach ($pegawaiList as $pegawai)
                    <option value="pegawai:{{ $pegawai->id }}"
                            data-sub="Pegawai&nbsp;&middot; {{ trim(($pegawai->jabatan ?? '').' — '.($pegawai->bidang ?? ''), ' —') }}"
                            data-rekening="{{ $pegawai->rekening }}"
                            @selected($penerimaSumberAwal === 'pegawai:'.$pegawai->id)>{{ $pegawai->nama }}</option>
                @endforeach
                @foreach ($vendorList as $vendor)
                    <option value="vendor:{{ $vendor->id }}"
                            data-sub="Vendor&nbsp;&middot; {{ $vendor->jenis_usaha ?: 'Pihak ketiga' }}"
                            data-rekening="{{ $vendor->rekening }}"
                            @selected($penerimaSumberAwal === 'vendor:'.$vendor->id)>{{ $vendor->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="spm-penerima">
            <div class="fg">
                <label class="fl" for="penerima">Nama Penerima</label>
                <input type="text" id="penerima" name="penerima" value="{{ old('penerima', $spmEdit?->penerima) }}">
            </div>
            <div class="fg">
                <label class="fl" for="bank_pilih">Bank Tujuan</label>
                <select id="bank_pilih" data-cari>
                    <option value="">&mdash; Pilih Bank &mdash;</option>
                    <option value="manual" @selected($bankManualAwal)>Isi Manual</option>
                    @foreach (config('bank.daftar') as $bank)
                        <option value="{{ $bank }}" @selected(! $bankManualAwal && $bankAwal === $bank)>{{ $bank }}</option>
                    @endforeach
                </select>
                <input type="text" id="bank_tujuan" name="bank_tujuan" placeholder="Ketik nama bank"
                       value="{{ $bankAwal }}" @class(['spm-bank-manual']) @if (! $bankManualAwal) hidden @endif>
            </div>
            <div class="fg">
                <label class="fl" for="nomor_rekening">Nomor Rekening</label>
                <input type="text" id="nomor_rekening" name="nomor_rekening" inputmode="numeric"
                       value="{{ old('nomor_rekening', $spmEdit?->nomor_rekening) }}">
            </div>
        </div>

        <h3 style="margin-top:22px;">Uraian</h3>
        <div class="fg">
            <textarea id="uraian" name="uraian" rows="4" placeholder="Keperluan pencairan dana ini&hellip;">{{ old('uraian', $spmEdit?->uraian) }}</textarea>
        </div>

        <div class="spm-rekap">
            <div class="baris"><span>Total Bruto</span><span class="v" id="rekap-bruto">Rp 0</span></div>
            <div class="baris"><span>Biaya Pajak</span><span class="v kurang" id="rekap-pajak">&minus; Rp 0</span></div>
            <div class="baris netto"><span>Nominal Netto</span><span class="v" id="rekap-netto">Rp 0</span></div>
        </div>

        <div class="nav">
            <a class="btn" href="{{ route('spm.ls.index') }}">Batal</a>
            <button type="submit" class="btn prim">{{ $spmEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>

<?php
    $detailLama = $spmEdit ? $spmEdit->detail->pluck('nominal', 'master_anggaran_id')->map(fn ($v) => (float) $v) : collect();
    $masterAnggaranJs = $masterAnggaran->map(function ($m) use ($detailLama) {
        // Nominal baris ini pada SPM yang sedang disunting dikreditkan kembali,
        // supaya sisa yang tampil sama dengan yang divalidasi Spm::updateLs().
        $sisaTambahan = (float) ($detailLama[$m->id] ?? 0);

        return [
            'id' => $m->id,
            'program' => $m->program_lengkap,
            'kegiatan' => $m->kegiatan_lengkap,
            'sub_kegiatan' => $m->sub_kegiatan_lengkap,
            'kode_rekening' => $m->rekening_lengkap,
            'kode_rekening_bersih' => $m->kode_rekening_bersih,
            'uraian_rekening' => $m->uraian_rekening,
            'tagging_id' => $m->tagging_id,
            'tagging' => $m->tagging->nama ?? 'Tanpa Tagging',
            'pagu' => (float) $m->pagu,
            'dana_terikat' => $m->danaTerikatNpd(),
            'realisasi_aktual' => $m->realisasiAktual(),
            'sisa' => $m->sisaTersedia() + $sisaTambahan,
        ];
    })->values();

    $barisAwal = collect(old(
        'baris',
        $spmEdit ? $spmEdit->detail->map(fn ($d) => [
            'master_anggaran_id' => $d->master_anggaran_id,
            'nominal' => (float) $d->nominal,
        ])->all() : []
    ))->values();
?>
<script>
(function () {
    const MA = @json($masterAnggaranJs);
    const AWAL = @json($barisAwal);
    const NONE_TAG = '__none__';

    /** Angka di balik isian rupiah berformat (komponen layouts/partials/input-rupiah). */
    function nilaiRupiah(el) {
        return window.InputRupiah ? window.InputRupiah.nilai(el) : (parseFloat(el.value) || 0);
    }

    function formatRupiah(n) {
        return 'Rp ' + (Number(n) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function uniq(arr) {
        const seen = new Set();
        const out = [];
        arr.forEach(v => { if (v !== '' && v != null && ! seen.has(v)) { seen.add(v); out.push(v); } });
        return out;
    }

    /** Mata anggaran tanpa tagging tetap butuh nilai <option> yang bukan string kosong. */
    function taggingValue(m) {
        return m.tagging_id === null || m.tagging_id === undefined ? NONE_TAG : String(m.tagging_id);
    }

    function cariMa(id) {
        return MA.find(m => String(m.id) === String(id)) || null;
    }

    function konteksDari(m) {
        return { program: m.program, kegiatan: m.kegiatan, sub: m.sub_kegiatan };
    }

    function dalamKonteks(k) {
        return MA.filter(m => m.program === k.program && m.kegiatan === k.kegiatan && m.sub_kegiatan === k.sub);
    }

    function fillOptions(sel, options, placeholder) {
        sel.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>'
            + options.map(o => '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + '</option>').join('');
        sel.disabled = options.length === 0;
    }

    // ================= Pemilih mata anggaran (sekali untuk satu dokumen) =================
    const selProgram = document.getElementById('ma-program');
    const selKegiatan = document.getElementById('ma-kegiatan');
    const selSub = document.getElementById('ma-sub');
    const btnTambah = document.getElementById('baris-add');

    function konteksAktif() {
        return selSub.value
            ? { program: selProgram.value, kegiatan: selKegiatan.value, sub: selSub.value }
            : null;
    }

    function perbaruiTombolTambah() {
        btnTambah.disabled = konteksAktif() === null;
        tandaiKonteksBeda();
    }

    function muatProgram() {
        fillOptions(selProgram, uniq(MA.map(m => m.program)).map(v => ({ value: v, label: v })), '— Pilih Program —');
    }

    function onProgram() {
        const k = uniq(MA.filter(m => m.program === selProgram.value).map(m => m.kegiatan));
        fillOptions(selKegiatan, k.map(v => ({ value: v, label: v })), k.length ? '— Pilih Kegiatan —' : 'Pilih program dulu');
        fillOptions(selSub, [], 'Pilih kegiatan dulu');
        perbaruiTombolTambah();
    }

    function onKegiatan() {
        const s = uniq(MA.filter(m => m.program === selProgram.value && m.kegiatan === selKegiatan.value).map(m => m.sub_kegiatan));
        fillOptions(selSub, s.map(v => ({ value: v, label: v })), s.length ? '— Pilih Sub Kegiatan —' : 'Pilih kegiatan dulu');
        perbaruiTombolTambah();
    }

    selProgram.addEventListener('change', onProgram);
    selKegiatan.addEventListener('change', onKegiatan);
    selSub.addEventListener('change', perbaruiTombolTambah);

    // ================= Baris kode rekening =================
    const list = document.getElementById('baris-list');
    const kosong = document.getElementById('baris-kosong');
    const totalEl = document.getElementById('total-nominal');
    const rekapBruto = document.getElementById('rekap-bruto');
    const rekapPajak = document.getElementById('rekap-pajak');
    const rekapNetto = document.getElementById('rekap-netto');
    const isianPajak = Array.from(document.querySelectorAll('[data-pajak]'));
    let seq = 0;

    function semuaBaris() {
        return Array.from(list.querySelectorAll('[data-baris-row]'));
    }

    /** Mata anggaran yang sudah dipakai baris lain — satu SPM tidak boleh memuatnya dua kali. */
    function idTerpakai(kecuali) {
        return semuaBaris()
            .filter(r => r !== kecuali)
            .map(r => r.querySelector('[data-ma-id]').value)
            .filter(Boolean);
    }

    function barisHtml(i, k) {
        const rows = dalamKonteks(k);
        const opsiKode = uniq(rows.map(m => m.kode_rekening_bersih)).map(kode => {
            const m = rows.find(x => x.kode_rekening_bersih === kode);
            return '<option value="' + escapeHtml(kode) + '"'
                + (m.uraian_rekening ? ' data-sub="' + escapeHtml(m.uraian_rekening) + '"' : '') + '>'
                + escapeHtml(m.kode_rekening) + '</option>';
        }).join('');

        return '<div class="spm-baris" data-baris-row>'
            + '<div data-l="Kode Rekening">'
            + '<select data-cari data-ma-kode><option value="">— Pilih Kode Rekening —</option>' + opsiKode + '</select>'
            + '<span class="sb-konteks" data-konteks hidden></span>'
            + '</div>'
            + '<div data-l="Tagging">'
            + '<select data-cari data-ma-tagging disabled><option value="">Pilih kode rekening dulu</option></select>'
            + '</div>'
            + '<div class="sb-sisa kosong" data-l="Sisa Anggaran (sistem)" data-ma-sisa>&mdash;</div>'
            + '<div data-l="Nominal Bruto (Rp)">'
            + '<input type="text" data-rupiah data-ma-nominal name="baris[' + i + '][nominal]" value="">'
            + '<span class="sb-nota" data-nota hidden></span>'
            + '</div>'
            + '<button type="button" class="ic-btn danger" data-baris-remove title="Hapus kode rekening" aria-label="Hapus kode rekening">'
            + '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            + '<input type="hidden" data-ma-id name="baris[' + i + '][master_anggaran_id]" value="">'
            + '</div>';
    }

    function tambahBaris(k, awal) {
        const bungkus = document.createElement('div');
        bungkus.innerHTML = barisHtml(seq++, k);
        const row = bungkus.firstElementChild;
        row.konteks = k;

        const selKode = row.querySelector('[data-ma-kode]');
        const selTagging = row.querySelector('[data-ma-tagging]');
        const sisaEl = row.querySelector('[data-ma-sisa]');
        const idEl = row.querySelector('[data-ma-id]');
        const nominalEl = row.querySelector('[data-ma-nominal]');

        function bersihkanPilihan() {
            idEl.value = '';
            sisaEl.textContent = '—';
            sisaEl.classList.add('kosong');
            sisaEl.removeAttribute('title');
            periksaBaris(row);
        }

        function onKode() {
            const cocok = dalamKonteks(row.konteks).filter(m => m.kode_rekening_bersih === selKode.value);
            fillOptions(
                selTagging,
                cocok.map(m => ({ value: taggingValue(m), label: m.tagging })),
                cocok.length ? '— Pilih Tagging —' : 'Pilih kode rekening dulu'
            );
            // Tagging mengikat kode rekening: kalau hanya ada satu, langsung
            // dipakai supaya tidak perlu memilih hal yang tak punya alternatif.
            if (cocok.length === 1) {
                selTagging.value = taggingValue(cocok[0]);
                onTagging();
                return;
            }
            bersihkanPilihan();
        }

        function onTagging() {
            const m = dalamKonteks(row.konteks).find(x => x.kode_rekening_bersih === selKode.value && taggingValue(x) === selTagging.value);
            if (! m) {
                bersihkanPilihan();
                return;
            }
            idEl.value = m.id;
            sisaEl.textContent = formatRupiah(m.sisa);
            sisaEl.classList.remove('kosong');
            // Barisnya sengaja ringkas 4 kolom; angka pendukungnya muncul
            // sebagai keterangan saat kursor menyentuh sel ini.
            sisaEl.title = 'Pagu ' + formatRupiah(m.pagu)
                + ' · Dana Terikat NPD ' + formatRupiah(m.dana_terikat)
                + ' · Realisasi Aktual ' + formatRupiah(m.realisasi_aktual);
            periksaSemua();
        }

        selKode.addEventListener('change', onKode);
        selTagging.addEventListener('change', onTagging);
        nominalEl.addEventListener('input', () => { periksaBaris(row); hitungTotal(); });
        row.querySelector('[data-baris-remove]').addEventListener('click', () => {
            row.remove();
            perbaruiKosong();
            periksaSemua();
            hitungTotal();
        });

        list.appendChild(row);
        // Dipasang saat itu juga supaya dropdown pencariannya sudah siap
        // sebelum nilai awal di bawah disetel dan sebelum baris difokuskan.
        if (window.SelectCari) window.SelectCari.pasang(row);
        if (window.InputRupiah) window.InputRupiah.pasang(row);

        if (awal) {
            const m = cariMa(awal.master_anggaran_id);
            if (m) {
                selKode.value = m.kode_rekening_bersih;
                onKode();
                selTagging.value = taggingValue(m);
                onTagging();
            }
            if (awal.nominal !== null && awal.nominal !== undefined && awal.nominal !== '') {
                nominalEl.value = awal.nominal;
                nominalEl.dispatchEvent(new Event('blur'));
            }
        }

        perbaruiKosong();
        tandaiKonteksBeda();
        periksaBaris(row);
        hitungTotal();

        return row;
    }

    /** Peringatan per baris: mata anggaran dobel, atau nominal melebihi sisa sistem. */
    function periksaBaris(row) {
        const idEl = row.querySelector('[data-ma-id]');
        const nota = row.querySelector('[data-nota]');
        const nominal = nilaiRupiah(row.querySelector('[data-ma-nominal]'));
        const m = idEl.value ? cariMa(idEl.value) : null;
        let pesan = '';

        if (idEl.value && idTerpakai(row).includes(idEl.value)) {
            pesan = 'Kode rekening + tagging ini sudah ada di baris lain.';
        } else if (m && nominal > m.sisa) {
            pesan = 'Melebihi sisa anggaran ' + formatRupiah(m.sisa) + '.';
        }

        nota.textContent = pesan;
        nota.hidden = pesan === '';
    }

    function periksaSemua() {
        semuaBaris().forEach(periksaBaris);
    }

    /** SPM lama bisa mencakup lebih dari satu sub kegiatan; barisnya diberi keterangan. */
    function tandaiKonteksBeda() {
        const aktif = konteksAktif();
        semuaBaris().forEach(row => {
            const el = row.querySelector('[data-konteks]');
            const beda = ! aktif || row.konteks.sub !== aktif.sub;
            el.textContent = beda ? row.konteks.sub : '';
            el.hidden = ! beda;
        });
    }

    function perbaruiKosong() {
        kosong.hidden = semuaBaris().length > 0;
    }

    function totalBruto() {
        return semuaBaris().reduce((n, row) => n + nilaiRupiah(row.querySelector('[data-ma-nominal]')), 0);
    }

    /** Bruto - pajak = netto. Definisi yang sama dengan Spm::nominalNetto(). */
    function hitungRekap() {
        const bruto = totalBruto();
        const pajak = isianPajak.reduce((n, el) => n + nilaiRupiah(el), 0);
        rekapBruto.textContent = formatRupiah(bruto);
        rekapPajak.textContent = '− ' + formatRupiah(pajak);
        rekapNetto.textContent = formatRupiah(bruto - pajak);
    }

    function hitungTotal() {
        totalEl.textContent = formatRupiah(totalBruto());
        hitungRekap();
    }

    btnTambah.addEventListener('click', () => {
        const k = konteksAktif();
        if (! k) return;
        const row = tambahBaris(k);
        const fokus = row.querySelector('.scari .sc-inp') || row.querySelector('[data-ma-kode]');
        if (fokus) fokus.focus();
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });

    // ================= Nilai awal (edit / old input) =================
    muatProgram();

    // Tiap baris membawa konteksnya sendiri, jadi SPM lama yang terlanjur
    // mencakup lebih dari satu sub kegiatan tetap bisa disunting utuh.
    let konteksTerakhir = null;
    AWAL.forEach(b => {
        const m = cariMa(b.master_anggaran_id);
        const k = m ? konteksDari(m) : konteksTerakhir;
        if (! k) return;
        konteksTerakhir = k;
        tambahBaris(k, b);
    });

    // Pemilih di atas mengikuti baris pertama supaya tombol tambah langsung
    // menambah pada mata anggaran yang sama.
    const pertama = semuaBaris()[0];
    if (pertama) {
        selProgram.value = pertama.konteks.program;
        onProgram();
        selKegiatan.value = pertama.konteks.kegiatan;
        onKegiatan();
        selSub.value = pertama.konteks.sub;
    }

    perbaruiTombolTambah();
    perbaruiKosong();
    periksaSemua();

    // ================= Penerima =================
    const selPenerima = document.getElementById('penerima_sumber');
    const inpNama = document.getElementById('penerima');
    const inpRekening = document.getElementById('nomor_rekening');
    const selBank = document.getElementById('bank_pilih');
    const inpBank = document.getElementById('bank_tujuan');

    /**
     * Penerima dari master: nama & nomor rekening ikut isinya dan dikunci,
     * supaya tidak menyimpang dari Data Pegawai/Vendor. "Isi Manual" membuka
     * ketiganya. (Server tetap mengambil ulang dari master - lihat
     * Spm::penerimaDariInput.)
     */
    function terapkanPenerima(isiUlang) {
        const opsi = selPenerima.options[selPenerima.selectedIndex];
        const dariMaster = !!(opsi && opsi.value && opsi.value !== 'manual');

        inpNama.readOnly = dariMaster;
        inpRekening.readOnly = dariMaster;

        if (dariMaster && isiUlang) {
            inpNama.value = opsi.textContent.trim();
            inpRekening.value = opsi.dataset.rekening || '';
        }
    }

    selPenerima.addEventListener('change', () => terapkanPenerima(true));
    terapkanPenerima(false);

    // Bank tujuan: daftar pilihan mengisi isian teks; "Isi Manual" membukanya
    // untuk diketik sendiri. Yang terkirim selalu satu isian, bank_tujuan.
    function terapkanBank(isiUlang) {
        const manual = selBank.value === 'manual';
        inpBank.hidden = ! manual;
        if (manual) {
            if (isiUlang) inpBank.focus();
        } else if (isiUlang) {
            inpBank.value = selBank.value;
        }
    }

    selBank.addEventListener('change', () => terapkanBank(true));
    terapkanBank(false);

    isianPajak.forEach(el => el.addEventListener('input', hitungRekap));

    hitungTotal();
})();
</script>
@endsection
