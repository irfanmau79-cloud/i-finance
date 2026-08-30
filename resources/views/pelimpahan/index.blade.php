@extends('layouts.app')

@section('activeNav', 'pelimpahan')
@section('title', 'Pelimpahan')

@section('content')
<style>
  .pl-sub-form{display:grid;grid-template-columns:1fr 1fr;gap:22px;}
  @media(max-width:680px){.pl-sub-form{grid-template-columns:1fr;}}
  .pl-add-toggle{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
  .pl-add-toggle h4{margin:0;}
  .pl-add-form{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px;}
  .pl-dsk-filter{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px;align-items:end;}
  @media(max-width:1100px){.pl-dsk-filter{grid-template-columns:1fr 1fr;}}
  @media(max-width:620px){.pl-dsk-filter{grid-template-columns:1fr;}}
  .pl-dsk-filter .pl-dsk-filter-actions{display:flex;gap:7px;}
  table.pl-dsk select{width:100%;padding:7px 9px;border:1px solid var(--line);border-radius:8px;font-size:13px;background:#fff;}
  table.pl-dsk td{vertical-align:top;}
  table.pl-dsk .sub{display:block;color:var(--mut);font-size:11px;margin-top:4px;}
  #assignment-form .inv-pager{margin-top:12px;border-radius:9px;}

  /* Nama sub kegiatan dan judul kolomnya panjang-panjang. Tanpa ini judulnya
     ikut aturan umum table.realisasi th (nowrap) sehingga kolom PPTK terdorong
     lebar dan nama sub kegiatannya terpotong ke samping. */
  table.pl-dsk th{white-space:normal;line-height:1.35;}
  table.pl-dsk td{white-space:normal;overflow-wrap:anywhere;word-break:break-word;}
  table.pl-dsk th:first-child,table.pl-dsk td:first-child{min-width:260px;}
  table.pl-dsk .dsk-kpa,table.pl-dsk .dsk-pptk{min-width:170px;}

  /* Identitas pejabat yang dipilih. Sebelumnya memakai kelas .profil-info-*
     yang gayanya hanya ada di halaman Profil, jadi di sini tampil polos tanpa
     gaya sama sekali - label dan isinya jadi tidak terbedakan. */
  .pl-info{margin-top:10px;border:1px solid var(--line);border-radius:10px;background:var(--surface-2);overflow:hidden;}
  .pl-info-baris{display:grid;grid-template-columns:minmax(96px,34%) 1fr;gap:10px;align-items:baseline;padding:9px 12px;}
  .pl-info-baris + .pl-info-baris{border-top:1px solid var(--line);}
  .pl-info-baris .lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--navy);}
  .pl-info-baris .val{font-size:13px;font-weight:400;color:var(--ink);overflow-wrap:anywhere;}
  @media(max-width:520px){
    .pl-info-baris{grid-template-columns:1fr;gap:2px;}
  }
</style>

@php
    $pegawaiOptionsJs = $pegawaiList->map(fn ($p) => ['value' => $p->id, 'label' => $p->nama])->values();
    $pegawaiInfoJs = $pegawaiList->keyBy('id')->map(fn ($p) => [
        'nip' => $p->nip, 'jabatan' => $p->jabatan, 'pangkat' => $p->pangkat, 'golongan' => $p->golongan,
    ]);
@endphp

<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Pelimpahan</b></div>
        <div class="ph-title">Pelimpahan</div>
    </div>
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block"><strong>Terjadi kesalahan:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="dash-card" style="margin-top:16px">
    <h3>Ringkasan Distribusi Sub Kegiatan</h3>
    <div class="form-grid">
        <div><strong>{{ $ringkasan['total'] }}</strong><div class="sub">Total Sub Kegiatan</div></div>
        <div><strong>{{ $ringkasan['assigned'] }}</strong><div class="sub">Sudah ditugaskan</div></div>
        <div><strong>{{ $ringkasan['unassigned'] }}</strong><div class="sub">Belum ditugaskan</div></div>
        <div><strong>{{ number_format($ringkasan['percentage'], 1, ',', '.') }}%</strong><div class="sub">Cakupan penugasan</div></div>
    </div>
    @if ($ringkasan['unassigned'] > 0)
        <div class="err-box" style="display:block;margin-top:12px"><strong>{{ $ringkasan['unassigned'] }} Sub Kegiatan belum memiliki PPTK.</strong> Gunakan filter "Belum ditugaskan" di tabel bawah untuk menindaklanjuti.</div>
    @endif
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>Data Pejabat Pengelola Keuangan</h3>

    {{-- ===== Pengguna Anggaran ===== --}}
    <div class="profil-sec-title" style="margin-top:6px">Pengguna Anggaran</div>
    <form method="POST" action="{{ route('pelimpahan.opd.update') }}" id="opd-form">
        @csrf
        <div class="pl-sub-form">
            <div>
                <label class="fl" for="pa-inp">Pengguna Anggaran (PA)</label>
                <div class="nsearch" id="pa-wrap">
                    <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="ns-inp" id="pa-inp" autocomplete="off" placeholder="Cari nama pegawai..." value="{{ $pejabatOpd?->paPegawai?->nama }}">
                    <input type="hidden" name="pa_pegawai_id" id="pa-id" value="{{ old('pa_pegawai_id', $pejabatOpd?->pa_pegawai_id) }}">
                    <div class="ns-drop" id="pa-drop"></div>
                </div>
                <div class="pl-info">
                    <div class="pl-info-baris"><div class="lbl">NIP</div><div class="val" id="pa-nip">-</div></div>
                    <div class="pl-info-baris"><div class="lbl">Pangkat/Golongan</div><div class="val" id="pa-pg">-</div></div>
                    <div class="pl-info-baris"><div class="lbl">Jabatan</div><div class="val" id="pa-jab">-</div></div>
                </div>
            </div>
            <div>
                <label class="fl" for="bpopd-inp">Bendahara Pengeluaran</label>
                <div class="nsearch" id="bpopd-wrap">
                    <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="ns-inp" id="bpopd-inp" autocomplete="off" placeholder="Cari nama pegawai..." value="{{ $pejabatOpd?->bendaharaPengeluaranPegawai?->nama }}">
                    <input type="hidden" name="bendahara_pengeluaran_pegawai_id" id="bpopd-id" value="{{ old('bendahara_pengeluaran_pegawai_id', $pejabatOpd?->bendahara_pengeluaran_pegawai_id) }}">
                    <div class="ns-drop" id="bpopd-drop"></div>
                </div>
                <div class="pl-info">
                    <div class="pl-info-baris"><div class="lbl">NIP</div><div class="val" id="bpopd-nip">-</div></div>
                    <div class="pl-info-baris"><div class="lbl">Pangkat/Golongan</div><div class="val" id="bpopd-pg">-</div></div>
                    <div class="pl-info-baris"><div class="lbl">Jabatan</div><div class="val" id="bpopd-jab">-</div></div>
                </div>
            </div>
        </div>
        <div style="text-align:right;margin-top:14px"><button class="btn prim">Simpan Pejabat OPD</button></div>
    </form>

    <div class="profil-divider"></div>

    {{-- ===== KPA dan BPP ===== --}}
    <div class="pl-add-toggle">
        <h4 class="profil-sec-title" style="margin:0">Kuasa Pengguna Anggaran dan Bendahara Pengeluaran Pembantu</h4>
        <button type="button" class="btn prim" id="kpa-toggle">+ Tambah KPA dan BPP</button>
    </div>
    <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>KPA</th><th>BPP</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($kpaList as $kpa)
            <tr>
                <td>{{ $kpa->kpaPegawai->nama }}</td>
                <td>{{ $kpa->bppPegawai->nama }}</td>
                <td><span class="badge {{ $kpa->aktif ? 'st-aktif' : 'st-danger' }}">{{ $kpa->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td>
                    <button type="button" class="btn" data-kpa-edit
                        data-url="{{ route('pelimpahan.kpa.update', $kpa) }}"
                        data-kpa="{{ $kpa->kpa_pegawai_id }}" data-kpa-label="{{ $kpa->kpaPegawai->nama }}"
                        data-bpp="{{ $kpa->bpp_pegawai_id }}" data-bpp-label="{{ $kpa->bppPegawai->nama }}">Ubah</button>
                    <form method="POST" action="{{ route('pelimpahan.kpa.toggle-aktif', $kpa) }}" style="display:inline">@csrf @method('PATCH')<button class="btn">{{ $kpa->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                </td>
            </tr>
        @empty <tr><td colspan="4">Belum ada KPA.</td></tr>@endforelse
    </tbody></table></div>

    <div class="pl-add-form" id="kpa-form-wrap" hidden>
        <div class="pl-add-toggle"><h4 id="kpa-form-title" style="margin:0;font-size:13px;color:var(--navy)">Tambah KPA dan BPP</h4><button type="button" class="btn" id="kpa-cancel">Batal</button></div>
        <form method="POST" action="{{ route('pelimpahan.kpa.store') }}" id="kpa-form">
            @csrf
            <div class="pl-sub-form">
                <div>
                    <label class="fl" for="kpaf-inp">Kuasa Pengguna Anggaran</label>
                    <div class="nsearch" id="kpaf-wrap">
                        <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input class="ns-inp" id="kpaf-inp" autocomplete="off" placeholder="Cari nama pegawai...">
                        <input type="hidden" name="kpa_pegawai_id" id="kpaf-id">
                        <div class="ns-drop" id="kpaf-drop"></div>
                    </div>
                    <div class="pl-info">
                        <div class="pl-info-baris"><div class="lbl">NIP</div><div class="val" id="kpaf-nip">-</div></div>
                        <div class="pl-info-baris"><div class="lbl">Pangkat/Golongan</div><div class="val" id="kpaf-pg">-</div></div>
                        <div class="pl-info-baris"><div class="lbl">Jabatan</div><div class="val" id="kpaf-jab">-</div></div>
                    </div>
                </div>
                <div>
                    <label class="fl" for="bppf-inp">Bendahara Pengeluaran Pembantu</label>
                    <div class="nsearch" id="bppf-wrap">
                        <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input class="ns-inp" id="bppf-inp" autocomplete="off" placeholder="Cari nama pegawai...">
                        <input type="hidden" name="bpp_pegawai_id" id="bppf-id">
                        <div class="ns-drop" id="bppf-drop"></div>
                    </div>
                    <div class="pl-info">
                        <div class="pl-info-baris"><div class="lbl">NIP</div><div class="val" id="bppf-nip">-</div></div>
                        <div class="pl-info-baris"><div class="lbl">Pangkat/Golongan</div><div class="val" id="bppf-pg">-</div></div>
                        <div class="pl-info-baris"><div class="lbl">Jabatan</div><div class="val" id="bppf-jab">-</div></div>
                    </div>
                </div>
            </div>
            <div style="text-align:right;margin-top:12px"><button class="btn prim" id="kpa-submit">Simpan</button></div>
        </form>
    </div>

    <div class="profil-divider"></div>

    {{-- ===== Pejabat Pelaksana Teknis Kegiatan ===== --}}
    <div class="pl-add-toggle">
        <h4 class="profil-sec-title" style="margin:0">Pejabat Pelaksana Teknis Kegiatan</h4>
        <button type="button" class="btn prim" id="pptk-toggle">+ Tambah PPTK</button>
    </div>
    <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>PPTK</th><th>NIP</th><th>Jabatan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($pptkRoster as $item)
            <tr>
                <td>{{ $item->pegawai->nama }}</td>
                <td>{{ $item->pegawai->nip ?: '-' }}</td>
                <td>{{ $item->pegawai->jabatan ?: '-' }}</td>
                <td><span class="badge st-aktif">Aktif</span></td>
                <td><form method="POST" action="{{ route('pelimpahan.pptk.toggle-aktif', $item) }}">@csrf @method('PATCH')<button class="btn">Nonaktifkan</button></form></td>
            </tr>
        @empty <tr><td colspan="5">Belum ada PPTK terdaftar.</td></tr>@endforelse
    </tbody></table></div>

    <div class="pl-add-form" id="pptk-form-wrap" hidden>
        <div class="pl-add-toggle"><h4 style="margin:0;font-size:13px;color:var(--navy)">Tambah PPTK</h4><button type="button" class="btn" id="pptk-cancel">Batal</button></div>
        <form method="POST" action="{{ route('pelimpahan.pptk.store') }}" id="pptk-form">
            @csrf
            <label class="fl" for="pptkf-inp">Pejabat Pelaksana Teknis Kegiatan</label>
            <div class="nsearch" id="pptkf-wrap">
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="ns-inp" id="pptkf-inp" autocomplete="off" placeholder="Cari nama pegawai...">
                <input type="hidden" name="pegawai_id" id="pptkf-id">
                <div class="ns-drop" id="pptkf-drop"></div>
            </div>
            <div class="pl-info">
                <div class="pl-info-baris"><div class="lbl">NIP</div><div class="val" id="pptkf-nip">-</div></div>
                <div class="pl-info-baris"><div class="lbl">Pangkat/Golongan</div><div class="val" id="pptkf-pg">-</div></div>
                <div class="pl-info-baris"><div class="lbl">Jabatan</div><div class="val" id="pptkf-jab">-</div></div>
            </div>
            <div style="text-align:right;margin-top:12px"><button class="btn prim">Simpan</button></div>
        </form>
    </div>
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>Distribusi Sub Kegiatan</h3>
    <form method="GET" action="{{ route('pelimpahan.index') }}" class="pl-dsk-filter">
        <div class="fg"><label class="fl">Status</label><select name="status"><option value="">Semua</option><option value="assigned" @selected(request('status') === 'assigned')>Ditugaskan</option><option value="unassigned" @selected(request('status') === 'unassigned')>Belum ditugaskan</option></select></div>
        <div class="fg"><label class="fl">KPA</label><select name="kpa_id" data-cari><option value="">Semua</option>@foreach ($kpaList as $kpa)<option value="{{ $kpa->id }}" @selected((string) request('kpa_id') === (string) $kpa->id)>{{ $kpa->kpaPegawai->nama }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">PPTK</label><select name="pptk_pegawai_id" data-cari><option value="">Semua</option>@foreach ($pptkRoster as $item)<option value="{{ $item->pegawai_id }}" @selected((string) request('pptk_pegawai_id') === (string) $item->pegawai_id)>{{ $item->pegawai->nama }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">Program</label><select name="program" data-cari><option value="">Semua</option>@foreach ($programList as $program)<option value="{{ $program->program_normal }}" @selected(request('program') === $program->program_normal)>{{ $program->program_normal }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">Cari Sub Kegiatan</label><input name="cari" value="{{ request('cari') }}"></div>
        <div class="fg pl-dsk-filter-actions" style="grid-column:1/-1;justify-content:flex-end"><a class="btn" href="{{ route('pelimpahan.index') }}">Reset</a> <button class="btn prim">Filter</button></div>
    </form>

    <form method="POST" action="{{ route('pelimpahan.sub-kegiatan.set') }}" id="assignment-form">
        @csrf
        <div class="sp-table-wrap" style="margin-top:16px"><table class="realisasi pl-dsk"><thead><tr><th>Sub Kegiatan</th><th>Kuasa Pengguna Anggaran</th><th>Pejabat Pelaksana Teknis Kegiatan</th><th>Status</th></tr></thead><tbody id="dsk-tbody">
        @forelse ($subKegiatanList as $row)
            @php
                $key = $row->program_kunci.'|'.$row->sub_kegiatan_kunci;
                $p = $pelimpahanMap->get($key);
                $scope = base64_encode(json_encode(['program' => $row->program_normal, 'sub_kegiatan' => $row->sub_kegiatan_normal]));
            @endphp
            <tr data-scope="{{ $scope }}">
                <td>{{ $row->sub_kegiatan_normal }}<span class="sub">{{ $row->program_normal }} &middot; {{ $row->kegiatan_normal }}</span></td>
                <td>
                    <select class="dsk-kpa">
                        <option value="">-- Pilih KPA --</option>
                        @foreach ($kpaList->where('aktif', true) as $kpa)
                            <option value="{{ $kpa->id }}" data-bpp="{{ $kpa->bppPegawai->nama }}" @selected($p && $p->kpa_id === $kpa->id)>{{ $kpa->kpaPegawai->nama }}</option>
                        @endforeach
                    </select>
                    <span class="sub" data-bpp-preview>{{ $p ? 'BPP: '.$p->kpa->bppPegawai->nama : '' }}</span>
                </td>
                <td>
                    <select class="dsk-pptk">
                        <option value="">-- Pilih PPTK --</option>
                        @foreach ($pptkRoster as $item)
                            <option value="{{ $item->pegawai_id }}" @selected($p && $p->pptk_pegawai_id === $item->pegawai_id)>{{ $item->pegawai->nama }}</option>
                        @endforeach
                    </select>
                </td>
                <td><span class="badge {{ $p ? 'st-aktif' : 'st-danger' }}" data-status-badge>{{ $p ? 'DITUGASKAN' : 'BELUM DITUGASKAN' }}</span></td>
            </tr>
        @empty <tr><td colspan="4">Tidak ada Sub Kegiatan sesuai filter.</td></tr>@endforelse
        </tbody></table></div>
        {{ $subKegiatanList->links() }}
        <div style="text-align:right;margin-top:12px"><button class="btn prim">Simpan Perubahan</button></div>
    </form>
</div>

<script>
(function () {
    const pegawaiOptions = {{ Illuminate\Support\Js::from($pegawaiOptionsJs) }};
    const pegawaiInfo = {{ Illuminate\Support\Js::from($pegawaiInfoJs) }};

    function initSearchSelect(inputId, hiddenId, dropId, options, onSelect) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const drop = document.getElementById(dropId);
        let selectedLabel = input.value;

        function renderList(query) {
            const q = (query || '').toLowerCase().trim();
            const items = options.filter(o => !q || o.label.toLowerCase().includes(q));
            drop.innerHTML = items.length
                ? items.map(o => '<div class="ns-item" data-value="'+String(o.value).replace(/"/g,'&quot;')+'">'+o.label.replace(/</g,'&lt;')+'</div>').join('')
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
            if (onSelect) onSelect(item.dataset.value);
        });

        return {
            setValue(value, label) {
                hidden.value = value || '';
                selectedLabel = label || '';
                input.value = selectedLabel;
                if (onSelect) onSelect(hidden.value);
            },
        };
    }

    function wirePicker(prefix) {
        const nip = document.getElementById(prefix + '-nip');
        const pg = document.getElementById(prefix + '-pg');
        const jab = document.getElementById(prefix + '-jab');
        function fill(id) {
            const p = pegawaiInfo[id];
            nip.textContent = p ? (p.nip || '-') : '-';
            pg.textContent = p ? ([p.pangkat, p.golongan].filter(Boolean).join(' / ') || '-') : '-';
            jab.textContent = p ? (p.jabatan || '-') : '-';
        }
        const picker = initSearchSelect(prefix + '-inp', prefix + '-id', prefix + '-drop', pegawaiOptions, fill);
        fill(document.getElementById(prefix + '-id').value);
        return picker;
    }

    const paPicker = wirePicker('pa');
    const bpopdPicker = wirePicker('bpopd');
    const kpafPicker = wirePicker('kpaf');
    const bppfPicker = wirePicker('bppf');
    const pptkfPicker = wirePicker('pptkf');

    // Tambah/Ubah KPA dan BPP
    const kpaFormWrap = document.getElementById('kpa-form-wrap');
    const kpaForm = document.getElementById('kpa-form');
    const kpaCreateUrl = kpaForm.action;
    function resetKpaForm() {
        kpaForm.action = kpaCreateUrl;
        kpaForm.querySelector('[name="_method"]')?.remove();
        kpaForm.reset();
        kpafPicker.setValue('', '');
        bppfPicker.setValue('', '');
        document.getElementById('kpa-form-title').textContent = 'Tambah KPA dan BPP';
    }
    document.getElementById('kpa-toggle').addEventListener('click', () => { resetKpaForm(); kpaFormWrap.hidden = false; });
    document.getElementById('kpa-cancel').addEventListener('click', () => { kpaFormWrap.hidden = true; resetKpaForm(); });
    document.querySelectorAll('[data-kpa-edit]').forEach(btn => btn.addEventListener('click', () => {
        kpaForm.action = btn.dataset.url;
        let method = kpaForm.querySelector('[name="_method"]');
        if (!method) { method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; kpaForm.appendChild(method); }
        method.value = 'PUT';
        kpafPicker.setValue(btn.dataset.kpa, btn.dataset.kpaLabel);
        bppfPicker.setValue(btn.dataset.bpp, btn.dataset.bppLabel);
        document.getElementById('kpa-form-title').textContent = 'Ubah KPA dan BPP';
        kpaFormWrap.hidden = false;
    }));

    // Tambah PPTK
    const pptkFormWrap = document.getElementById('pptk-form-wrap');
    document.getElementById('pptk-toggle').addEventListener('click', () => { pptkFormWrap.hidden = false; });
    document.getElementById('pptk-cancel').addEventListener('click', () => {
        pptkFormWrap.hidden = true;
        document.getElementById('pptk-form').reset();
        pptkfPicker.setValue('', '');
    });

    // Tabel Distribusi Sub Kegiatan: dropdown per baris
    function syncRow(select) {
        const tr = select.closest('tr');
        const kpaSel = tr.querySelector('.dsk-kpa');
        const pptkSel = tr.querySelector('.dsk-pptk');
        const bppPreview = tr.querySelector('[data-bpp-preview]');
        const opt = kpaSel.selectedOptions[0];
        bppPreview.textContent = (opt && opt.value) ? ('BPP: ' + opt.dataset.bpp) : '';
        const badge = tr.querySelector('[data-status-badge]');
        const filled = !!(kpaSel.value && pptkSel.value);
        badge.textContent = filled ? 'DITUGASKAN' : 'BELUM DITUGASKAN';
        badge.classList.toggle('st-aktif', filled);
        badge.classList.toggle('st-danger', !filled);
    }
    document.querySelectorAll('.dsk-kpa, .dsk-pptk').forEach(sel => sel.addEventListener('change', () => syncRow(sel)));

    document.getElementById('assignment-form').addEventListener('submit', function (e) {
        let idx = 0;
        document.querySelectorAll('#dsk-tbody tr[data-scope]').forEach(tr => {
            const kpaId = tr.querySelector('.dsk-kpa')?.value;
            const pptkId = tr.querySelector('.dsk-pptk')?.value;
            if (!kpaId || !pptkId) return;
            const mk = (name, val) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'rows[' + idx + '][' + name + ']'; i.value = val; tr.appendChild(i); };
            mk('scope', tr.dataset.scope);
            mk('kpa_id', kpaId);
            mk('pptk_pegawai_id', pptkId);
            idx++;
        });
        if (idx === 0) {
            e.preventDefault();
            alert('Belum ada perubahan. Pilih KPA dan PPTK pada minimal satu baris Sub Kegiatan.');
        }
    });
})();
</script>
@endsection
