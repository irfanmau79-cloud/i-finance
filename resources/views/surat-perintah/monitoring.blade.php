@extends('layouts.app')

@section('activeNav', 'sp-monitor')
@section('title', 'Monitoring SP')

@section('content')
<div class="dash-card wf-card">
    <h3>Monitoring SP</h3>
    <div class="sub">Orderan Surat Perintah yang masih dipantau. Nonaktifkan pemantauan lewat halaman Data SP.</div>

    @php
        $bolehEditPengajuan = in_array(auth()->user()->role, ['pptk', 'bendahara'], true);
        $statusBadgeClass = ['Diterima PPTK' => 'st-diterima'] + \App\Models\Npd::STATUS_BADGE_CLASS;
    @endphp

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi" id="spm-table">
            <thead>
                <tr>
                    <th>Nomor SP</th>
                    <th>Tanggal SP</th>
                    <th>Unit Kerja</th>
                    <th>Lokasi</th>
                    <th>Nama Pengirim</th>
                    <th>Tujuan Transfer</th>
                    <th>Status</th>
                    <th>Pengajuan</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suratPerintahs as $suratPerintah)
                    <tr data-pengajuan-url="{{ route('surat-perintah.pengajuan', $suratPerintah) }}">
                        <td>{{ $suratPerintah->nomor_sp }}</td>
                        <td>{{ $suratPerintah->tanggal_sp->format('d-m-Y') }}</td>
                        <td>{{ $suratPerintah->unit_kerja }}</td>
                        <td>{{ $suratPerintah->lokasi }}</td>
                        <td>{{ $suratPerintah->nama_pengirim }}</td>
                        <td>{{ $suratPerintah->tujuan_transfer }}</td>
                        <td>
                            @if (filled($suratPerintah->status))
                                <span class="badge {{ $statusBadgeClass[$suratPerintah->status] ?? 'st-diterima' }}">{{ $suratPerintah->status }}</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>
                            @if ($bolehEditPengajuan)
                                @php($terpilih = $suratPerintah->pengajuanArray())
                                <div style="display:flex;flex-direction:column;gap:4px;white-space:nowrap;">
                                    @foreach (\App\Models\SuratPerintah::PENGAJUAN_OPTIONS as $opsi)
                                        <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;cursor:pointer;">
                                            <input type="checkbox" class="sp-pengajuan-checkbox" value="{{ $opsi }}" @checked(in_array($opsi, $terpilih, true))>
                                            {{ $opsi }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                {{ $suratPerintah->pengajuan ?: '-' }}
                            @endif
                        </td>
                        <td>{{ $suratPerintah->catatan ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">Belum ada SP yang dipantau.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($bolehEditPengajuan)
<script>
  document.querySelectorAll('.sp-pengajuan-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      const row = checkbox.closest('tr');
      const url = row.dataset.pengajuanUrl;
      const checkedBefore = ! checkbox.checked;
      const checkboxesInRow = row.querySelectorAll('.sp-pengajuan-checkbox');

      const params = new URLSearchParams();
      row.querySelectorAll('.sp-pengajuan-checkbox:checked').forEach(function (cb) {
        params.append('pengajuan[]', cb.value);
      });

      checkboxesInRow.forEach(function (cb) { cb.disabled = true; });

      fetch(url, {
        method: 'PATCH',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
        },
        body: params.toString(),
      })
        .then(function (res) {
          if (! res.ok) throw new Error('Gagal menyimpan Pengajuan.');
          return res.json();
        })
        .catch(function () {
          checkbox.checked = checkedBefore;
          alert('Gagal menyimpan Pengajuan. Silakan coba lagi.');
        })
        .finally(function () {
          checkboxesInRow.forEach(function (cb) { cb.disabled = false; });
        });
    });
  });
</script>
@endif
@endsection
