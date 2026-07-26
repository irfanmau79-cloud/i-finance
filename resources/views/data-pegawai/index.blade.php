@extends('layouts.app')

@section('activeNav', 'data-pegawai')
@section('title', 'Data Pegawai')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Pegawai</h3>
    <div class="sub">Daftar pegawai (hasil import Manajemen Data + Nomor Handphone). Semua role bisa melihat; hanya Superadmin yang bisa mengedit.</div>

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

    <form method="GET" action="{{ route('data-pegawai.index') }}" class="tbl-tools">
        <input type="text" name="cari" placeholder="Cari nama, NIP, jabatan, atau bidang..." value="{{ $filters['cari'] }}" style="min-width:280px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
        @if ($filters['cari'] !== '')
            <a href="{{ route('data-pegawai.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Bidang</th>
                    <th>Golongan</th>
                    <th>Pangkat</th>
                    <th>Rekening</th>
                    <th>Nomor Handphone</th>
                    <th>Aktif</th>
                    @if ($bolehEdit)<th style="text-align:center;">Aksi</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($pegawai as $p)
                    <tr>
                        <td>{{ $p->nama }}</td>
                        <td>{{ $p->nip }}</td>
                        <td>{{ $p->jabatan }}</td>
                        <td>{{ $p->bidang }}</td>
                        <td>{{ $p->golongan ?? '—' }}</td>
                        <td>{{ $p->pangkat ?? '—' }}</td>
                        <td>{{ $p->rekening ?? '—' }}</td>
                        <td>{{ $p->nomor_handphone ?? '—' }}</td>
                        <td><span class="badge {{ $p->aktif ? 'st-aktif' : 'st-danger' }}">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                        @if ($bolehEdit)
                            <td style="text-align:center;">
                                <button type="button" class="ic-btn" title="Edit" data-peg-edit="{{ $p->id }}"
                                    data-peg-nama="{{ $p->nama }}" data-peg-nip="{{ $p->nip }}" data-peg-jabatan="{{ $p->jabatan }}"
                                    data-peg-bidang="{{ $p->bidang }}" data-peg-golongan="{{ $p->golongan }}" data-peg-pangkat="{{ $p->pangkat }}"
                                    data-peg-rekening="{{ $p->rekening }}" data-peg-hp="{{ $p->nomor_handphone }}" data-peg-aktif="{{ $p->aktif ? 1 : 0 }}"
                                    data-peg-url="{{ route('data-pegawai.update', $p) }}">
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $bolehEdit ? 10 : 9 }}" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data pegawai.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pegawai->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $pegawai->firstItem() }}&ndash;{{ $pegawai->lastItem() }} dari {{ $pegawai->total() }} data</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $pegawai->previousPageUrl() ?? '#' }}"@if (! $pegawai->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $pegawai->nextPageUrl() ?? '#' }}"@if (! $pegawai->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>

@if ($bolehEdit)
<div class="mdl-ov" id="peg-mdl-ov">
  <div class="mdl">
    <div class="mdl-h">Edit Data Pegawai</div>
    <div class="mdl-b">
      <form method="POST" id="peg-edit-form">
        @csrf
        @method('PUT')
        <div class="form-grid">
          <div class="fg"><label class="fl">Nama</label><input type="text" name="nama" id="peg-f-nama"></div>
          <div class="fg"><label class="fl">NIP</label><input type="text" name="nip" id="peg-f-nip"></div>
        </div>
        <div class="form-grid">
          <div class="fg"><label class="fl">Jabatan</label><input type="text" name="jabatan" id="peg-f-jabatan"></div>
          <div class="fg"><label class="fl">Bidang</label><input type="text" name="bidang" id="peg-f-bidang"></div>
        </div>
        <div class="form-grid">
          <div class="fg"><label class="fl">Golongan</label><input type="text" name="golongan" id="peg-f-golongan"></div>
          <div class="fg"><label class="fl">Pangkat</label><input type="text" name="pangkat" id="peg-f-pangkat"></div>
        </div>
        <div class="form-grid">
          <div class="fg"><label class="fl">Rekening</label><input type="text" name="rekening" id="peg-f-rekening"></div>
          <div class="fg"><label class="fl">Nomor Handphone</label><input type="text" name="nomor_handphone" id="peg-f-hp" placeholder="mis. 0812xxxxxxxx"></div>
        </div>
        <div class="fg">
          <input type="hidden" name="aktif" value="0">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:14px;">
            <input type="checkbox" name="aktif" id="peg-f-aktif" value="1" style="width:auto;">
            <span>Aktif</span>
          </label>
        </div>
      </form>
      <div class="mdl-f" style="padding:14px 0 0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn" onclick="pegModalClose()">Batal</button>
        <button type="submit" form="peg-edit-form" class="btn prim">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ov = document.getElementById('peg-mdl-ov');
    const form = document.getElementById('peg-edit-form');
    const fields = {
        nama: document.getElementById('peg-f-nama'),
        nip: document.getElementById('peg-f-nip'),
        jabatan: document.getElementById('peg-f-jabatan'),
        bidang: document.getElementById('peg-f-bidang'),
        golongan: document.getElementById('peg-f-golongan'),
        pangkat: document.getElementById('peg-f-pangkat'),
        rekening: document.getElementById('peg-f-rekening'),
        hp: document.getElementById('peg-f-hp'),
        aktif: document.getElementById('peg-f-aktif'),
    };

    window.pegModalClose = function () { ov.classList.remove('show'); };

    document.querySelectorAll('[data-peg-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.pegUrl;
            fields.nama.value = btn.dataset.pegNama || '';
            fields.nip.value = btn.dataset.pegNip || '';
            fields.jabatan.value = btn.dataset.pegJabatan || '';
            fields.bidang.value = btn.dataset.pegBidang || '';
            fields.golongan.value = btn.dataset.pegGolongan || '';
            fields.pangkat.value = btn.dataset.pegPangkat || '';
            fields.rekening.value = btn.dataset.pegRekening || '';
            fields.hp.value = btn.dataset.pegHp || '';
            fields.aktif.checked = btn.dataset.pegAktif === '1';
            ov.classList.add('show');
        });
    });
});
</script>
@endif
@endsection
