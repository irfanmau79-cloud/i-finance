@extends('layouts.app')

@section('activeNav', 'pelimpahan')
@section('title', 'Pelimpahan')

@section('content')
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

{{-- ===== 1. Pejabat OPD ===== --}}
<div class="dash-card">
    <h3>Pejabat OPD</h3>
    <div class="sub">Pengguna Anggaran (PA) dan Bendahara Pengeluaran level OPD — masing-masing 1 orang, berlaku untuk seluruh dokumen.</div>

    <form method="POST" action="{{ route('pelimpahan.opd.update') }}">
        @csrf
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="pa_pegawai_id">Pengguna Anggaran (PA)</label>
                <select id="pa_pegawai_id" name="pa_pegawai_id">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawaiList as $p)
                        <option value="{{ $p->id }}" @selected(old('pa_pegawai_id', $pejabatOpd?->pa_pegawai_id) == $p->id)>{{ $p->nama }}{{ $p->jabatan ? ' — '.$p->jabatan : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="bendahara_pengeluaran_pegawai_id">Bendahara Pengeluaran</label>
                <select id="bendahara_pengeluaran_pegawai_id" name="bendahara_pengeluaran_pegawai_id">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawaiList as $p)
                        <option value="{{ $p->id }}" @selected(old('bendahara_pengeluaran_pegawai_id', $pejabatOpd?->bendahara_pengeluaran_pegawai_id) == $p->id)>{{ $p->nama }}{{ $p->jabatan ? ' — '.$p->jabatan : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <button type="submit" class="btn prim">Simpan Pejabat OPD</button>
        </div>
    </form>
</div>

{{-- ===== 2. Daftar KPA & BPP ===== --}}
<div class="dash-card" style="margin-top:16px;">
    <h3>Daftar KPA &amp; BPP</h3>
    <div class="sub">Kuasa Pengguna Anggaran bisa banyak; tiap KPA punya tepat 1 Bendahara Pengeluaran Pembantu (BPP).</div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-bottom:16px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>KPA</th>
                    <th>Nama Jabatan</th>
                    <th>BPP</th>
                    <th>Status</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kpaList as $kpa)
                    <tr>
                        <td>{{ $kpa->kpaPegawai->nama }}</td>
                        <td>{{ $kpa->nama_jabatan ?: '-' }}</td>
                        <td>{{ $kpa->bppPegawai->nama }}</td>
                        <td><span class="badge {{ $kpa->aktif ? 'st-aktif' : 'st-danger' }}">{{ $kpa->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:10px;">
                                <button type="button" class="ic-btn" title="Ubah"
                                    data-kpa-edit
                                    data-url="{{ route('pelimpahan.kpa.update', $kpa) }}"
                                    data-kpa-pegawai="{{ $kpa->kpa_pegawai_id }}"
                                    data-bpp-pegawai="{{ $kpa->bpp_pegawai_id }}"
                                    data-nama-jabatan="{{ $kpa->nama_jabatan }}">
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('pelimpahan.kpa.toggle-aktif', $kpa) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sw" title="{{ $kpa->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <input type="checkbox" onchange="this.form.requestSubmit()" @checked($kpa->aktif)>
                                        <span class="sl"></span>
                                    </label>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--mut);padding:20px;">Belum ada KPA.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h4 id="kpa-form-title" style="margin:0 0 8px;font-size:13px;color:var(--navy);">Tambah KPA</h4>
    <form method="POST" action="{{ route('pelimpahan.kpa.store') }}" id="kpa-form">
        @csrf
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="kpa_pegawai_id">Pegawai KPA</label>
                <select id="kpa_pegawai_id" name="kpa_pegawai_id">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawaiList as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}{{ $p->jabatan ? ' — '.$p->jabatan : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="bpp_pegawai_id">Pegawai BPP</label>
                <select id="bpp_pegawai_id" name="bpp_pegawai_id">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawaiList as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}{{ $p->jabatan ? ' — '.$p->jabatan : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="nama_jabatan">Nama Jabatan (opsional)</label>
                <input type="text" id="nama_jabatan" name="nama_jabatan" placeholder="mis. KPA Bidang Investigasi">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button type="button" class="btn" id="kpa-form-cancel" style="display:none;">Batal Ubah</button>
            <button type="submit" class="btn prim" id="kpa-form-submit">Simpan</button>
        </div>
    </form>
</div>

{{-- ===== 3. Pelimpahan Sub Kegiatan ===== --}}
<div class="dash-card" style="margin-top:16px;">
    <h3>Pelimpahan Sub Kegiatan</h3>
    <div class="sub">Tugaskan KPA (BPP ikut otomatis) + PPTK ke sub kegiatan. Pilih KPA &amp; PPTK, centang sub kegiatan yang dituju (bisa banyak sekaligus), lalu terapkan.</div>

    <form method="POST" action="{{ route('pelimpahan.sub-kegiatan.set') }}" id="sub-kegiatan-form">
        @csrf
        <div class="form-grid">
            <div class="fg">
                <label class="fl" for="borongan-kpa">KPA</label>
                <select id="borongan-kpa" name="kpa_id" required>
                    <option value="">-- Pilih KPA --</option>
                    @foreach ($kpaList->where('aktif', true) as $kpa)
                        <option value="{{ $kpa->id }}">{{ $kpa->kpaPegawai->nama }} (BPP: {{ $kpa->bppPegawai->nama }})</option>
                    @endforeach
                </select>
            </div>
            <div class="fg">
                <label class="fl" for="borongan-pptk">PPTK</label>
                <select id="borongan-pptk" name="pptk_pegawai_id" required>
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($pegawaiList as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}{{ $p->jabatan ? ' — '.$p->jabatan : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="tbl-tools">
            <select id="filter-program">
                <option value="">-- Semua Program --</option>
                @foreach ($subKegiatanList->pluck('program')->unique() as $prog)
                    <option value="{{ $prog }}">{{ $prog }}</option>
                @endforeach
            </select>
            <select id="filter-kegiatan">
                <option value="">-- Semua Kegiatan --</option>
            </select>
            <input type="text" id="filter-cari" placeholder="Cari sub kegiatan...">
        </div>

        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;margin-bottom:8px;">
            <input type="checkbox" id="check-all-visible" style="width:auto;"> Centang semua sub kegiatan yang tampil (sesuai filter di atas)
        </label>

        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;max-height:480px;overflow-y:auto;">
            <table class="realisasi" id="sub-kegiatan-table">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th>Sub Kegiatan</th>
                        <th>Program / Kegiatan</th>
                        <th>KPA</th>
                        <th>BPP</th>
                        <th>PPTK</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subKegiatanList as $row)
                        @php
                            $p = $pelimpahanMap[$row->sub_kegiatan] ?? null;
                        @endphp
                        <tr data-program="{{ $row->program }}" data-kegiatan="{{ $row->kegiatan }}" data-cari="{{ mb_strtolower($row->sub_kegiatan) }}">
                            <td><input type="checkbox" name="kode_sub_kegiatan[]" value="{{ $row->sub_kegiatan }}" class="row-check"></td>
                            <td>{{ $row->sub_kegiatan }}</td>
                            <td style="font-size:11px;color:var(--mut);">{{ $row->program }}<br>{{ $row->kegiatan }}</td>
                            <td>{{ $p->kpa->kpaPegawai->nama ?? '-' }}</td>
                            <td>{{ $p->kpa->bppPegawai->nama ?? '-' }}</td>
                            <td>{{ $p->pptkPegawai->nama ?? '-' }}</td>
                            <td><span class="badge {{ $p ? 'st-aktif' : 'st-danger' }}">{{ $p ? 'Sudah diset' : 'Belum' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada sub kegiatan di Sumber Dana.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <button type="submit" class="btn prim">Terapkan ke Sub Kegiatan Tercentang</button>
        </div>
    </form>
</div>

@php
    $subKegiatanJs = $subKegiatanList->map(fn ($row) => [
        'program' => $row->program,
        'kegiatan' => $row->kegiatan,
    ])->values();
@endphp
<script>
(function () {
    const subKegiatanData = @json($subKegiatanJs);

    // ---- Tambah/Ubah KPA (satu form dipakai dua mode) ----
    const kpaForm = document.getElementById('kpa-form');
    const kpaCreateUrl = kpaForm.action;
    const kpaFormTitle = document.getElementById('kpa-form-title');
    const kpaSubmitBtn = document.getElementById('kpa-form-submit');
    const kpaCancelBtn = document.getElementById('kpa-form-cancel');
    const kpaPegawaiSelect = document.getElementById('kpa_pegawai_id');
    const bppPegawaiSelect = document.getElementById('bpp_pegawai_id');
    const namaJabatanInput = document.getElementById('nama_jabatan');

    function ensureMethodField(value) {
        let el = kpaForm.querySelector('input[name="_method"]');
        if (! el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.name = '_method';
            kpaForm.appendChild(el);
        }
        el.value = value;
    }
    function removeMethodField() {
        const el = kpaForm.querySelector('input[name="_method"]');
        if (el) el.remove();
    }

    function setKpaCreateMode() {
        kpaForm.action = kpaCreateUrl;
        removeMethodField();
        kpaPegawaiSelect.value = '';
        bppPegawaiSelect.value = '';
        namaJabatanInput.value = '';
        kpaFormTitle.textContent = 'Tambah KPA';
        kpaSubmitBtn.textContent = 'Simpan';
        kpaCancelBtn.style.display = 'none';
    }

    document.querySelectorAll('[data-kpa-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            kpaForm.action = btn.dataset.url;
            ensureMethodField('PUT');
            kpaPegawaiSelect.value = btn.dataset.kpaPegawai;
            bppPegawaiSelect.value = btn.dataset.bppPegawai;
            namaJabatanInput.value = btn.dataset.namaJabatan || '';
            kpaFormTitle.textContent = 'Ubah KPA';
            kpaSubmitBtn.textContent = 'Update';
            kpaCancelBtn.style.display = 'inline-block';
            kpaForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
    kpaCancelBtn.addEventListener('click', setKpaCreateMode);

    // ---- Filter Program -> Kegiatan (cascade) ----
    const filterProgram = document.getElementById('filter-program');
    const filterKegiatan = document.getElementById('filter-kegiatan');
    const filterCari = document.getElementById('filter-cari');
    const rows = Array.from(document.querySelectorAll('#sub-kegiatan-table tbody tr[data-program]'));

    function uniq(arr) {
        return Array.from(new Set(arr));
    }

    filterProgram.addEventListener('change', function () {
        const kegiatanList = this.value
            ? uniq(subKegiatanData.filter(function (r) { return r.program === filterProgram.value; }).map(function (r) { return r.kegiatan; }))
            : uniq(subKegiatanData.map(function (r) { return r.kegiatan; }));
        filterKegiatan.innerHTML = '<option value="">-- Semua Kegiatan --</option>'
            + kegiatanList.map(function (k) { return '<option value="' + k.replace(/"/g, '&quot;') + '">' + k + '</option>'; }).join('');
        applyFilter();
    });
    filterKegiatan.addEventListener('change', applyFilter);
    filterCari.addEventListener('input', applyFilter);

    function applyFilter() {
        const prog = filterProgram.value;
        const keg = filterKegiatan.value;
        const cari = filterCari.value.trim().toLowerCase();
        rows.forEach(function (row) {
            const matchProg = ! prog || row.dataset.program === prog;
            const matchKeg = ! keg || row.dataset.kegiatan === keg;
            const matchCari = ! cari || row.dataset.cari.indexOf(cari) >= 0;
            row.style.display = (matchProg && matchKeg && matchCari) ? '' : 'none';
        });
    }

    // ---- Centang semua yang tampil (tidak menyentuh baris yang sedang disembunyikan filter) ----
    document.getElementById('check-all-visible').addEventListener('change', function () {
        const checked = this.checked;
        rows.forEach(function (row) {
            if (row.style.display !== 'none') {
                row.querySelector('.row-check').checked = checked;
            }
        });
    });

    // ---- Validasi ringan sebelum submit borongan ----
    document.getElementById('sub-kegiatan-form').addEventListener('submit', function (e) {
        const adaTercentang = document.querySelectorAll('.row-check:checked').length > 0;
        if (! adaTercentang) {
            e.preventDefault();
            alert('Centang minimal satu sub kegiatan sebelum menerapkan.');
        }
    });
})();
</script>
@endsection
