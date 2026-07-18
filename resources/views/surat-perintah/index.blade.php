@extends('layouts.app')

@section('activeNav', 'sp-data')
@section('title', 'Data Surat Perintah')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Surat Perintah</h3>
    <div class="sub">Daftar seluruh Surat Perintah yang telah diinput.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('surat-perintah.create') }}" class="btn prim" style="white-space:nowrap;">+ Tambah SP</a>
        <a href="{{ route('surat-perintah.export-pdf') }}" class="btn" style="white-space:nowrap;">Export PDF</a>
    </div>

    @php
        $bolehEditHapus = in_array(auth()->user()->role, ['pptk', 'bendahara'], true);
    @endphp

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi" id="spd-table">
            <thead>
                <tr>
                    <th>Nomor SP</th>
                    <th>Tanggal SP</th>
                    <th>Unit Kerja</th>
                    <th>Lokasi</th>
                    <th>Nama Pengirim</th>
                    <th>Tujuan Transfer</th>
                    <th>Status SP</th>
                    <th>Status</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suratPerintahs as $suratPerintah)
                    <tr>
                        <td>{{ $suratPerintah->nomor_sp }}</td>
                        <td>{{ $suratPerintah->tanggal_sp->format('d-m-Y') }}</td>
                        <td>{{ $suratPerintah->unit_kerja }}</td>
                        <td>{{ $suratPerintah->lokasi }}</td>
                        <td>{{ $suratPerintah->nama_pengirim }}</td>
                        <td>{{ $suratPerintah->tujuan_transfer }}</td>
                        <td>{{ $suratPerintah->status_sp }}</td>
                        <td><span class="badge st-diterima">{{ $suratPerintah->status }}</span></td>
                        <td style="text-align:center;">
                            @php
                                $fileTersedia = filled($suratPerintah->file_url) && \Illuminate\Support\Facades\Storage::disk('public')->exists($suratPerintah->file_url);
                            @endphp
                            <div style="display:flex;align-items:center;justify-content:center;gap:10px;">
                                <div class="aksi-wrap" style="width:auto;grid-template-columns:repeat({{ $bolehEditHapus ? 3 : 1 }},30px);">
                                    @if ($fileTersedia)
                                        <a class="ic-btn" title="Lihat SP" href="{{ asset('storage/'.$suratPerintah->file_url) }}" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                    @else
                                        <button type="button" class="ic-btn" title="File tidak tersedia" style="opacity:.5;cursor:not-allowed;" onclick="alert('File tidak tersedia.');"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    @endif
                                    @if ($bolehEditHapus)
                                        <a class="ic-btn" title="Edit" href="{{ route('surat-perintah.edit', $suratPerintah) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                        <form method="POST" action="{{ route('surat-perintah.destroy', $suratPerintah) }}" onsubmit="return confirm('Yakin ingin menghapus Surat Perintah {{ $suratPerintah->nomor_sp }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ic-btn danger" title="Hapus"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                        </form>
                                    @endif
                                </div>
                                @if ($bolehEditHapus)
                                    <label class="sw" title="Pantau di Monitoring SP">
                                        <input type="checkbox" class="sp-toggle-pantau" data-url="{{ route('surat-perintah.toggle-pantau', $suratPerintah) }}" @checked($suratPerintah->dipantau)>
                                        <span class="sl"></span>
                                    </label>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data surat perintah.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
  document.querySelectorAll('.sp-toggle-pantau').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      const url = checkbox.dataset.url;
      const checkedBefore = ! checkbox.checked;

      checkbox.disabled = true;

      fetch(url, {
        method: 'PATCH',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
        },
      })
        .then(function (res) {
          if (! res.ok) throw new Error('Gagal memperbarui status pemantauan.');
          return res.json();
        })
        .then(function (data) {
          checkbox.checked = data.dipantau;
        })
        .catch(function () {
          checkbox.checked = checkedBefore;
          alert('Gagal memperbarui status pemantauan. Silakan coba lagi.');
        })
        .finally(function () {
          checkbox.disabled = false;
        });
    });
  });
</script>
@endsection
