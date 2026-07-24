@extends('layouts.app')
@section('activeNav','dash-tk')
@section('title','Dashboard Tunjangan Keluarga')
@section('content')
<style>.tk-row{cursor:pointer}.tk-detail{display:none;background:#f8fafc}.tk-detail.open{display:table-row}</style>

<div class="page-head">
  <div>
    <div class="ph-crumb">Beranda / <b>Dashboard Tunjangan Keluarga</b></div>
    <div class="ph-title">Dashboard Tunjangan Keluarga</div>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="kpi-lbl">Jumlah Pegawai</div></div></div>
    <div class="kpi-val">{{ $dashboard['jumlah_pegawai'] }}</div>
  </div>
  <div class="kpi" style="--kc:#7c3aed;--kbg:#7c3aed14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="16" cy="7" r="3"/><path d="M2 21v-2a4 4 0 0 1 4-4h4M14 21v-2a4 4 0 0 1 4-4"/></svg></div><div><div class="kpi-lbl">Jumlah Pasangan</div></div></div>
    <div class="kpi-val">{{ $dashboard['jumlah_pasangan'] }}</div>
  </div>
  <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/></svg></div><div><div class="kpi-lbl">Anak Tanggungan</div></div></div>
    <div class="kpi-val">{{ $dashboard['jumlah_anak_aktif'] }}</div>
    <div class="kpi-note">hanya tunjangan aktif</div>
  </div>
  <div class="kpi" style="--kc:#b45309;--kbg:#b4530914;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-3-3.87M7 21v-2a4 4 0 0 1 3-3.87"/><circle cx="12" cy="7" r="4"/></svg></div><div><div class="kpi-lbl">Total Jiwa</div></div></div>
    <div class="kpi-val">{{ $dashboard['total_jiwa'] }}</div>
    <div class="kpi-note">pegawai + pasangan + anak</div>
  </div>
  <div class="kpi" style="--kc:#166534;--kbg:#16653414;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="kpi-lbl">Anak &lt; 21 Tahun</div></div></div>
    <div class="kpi-val">{{ $dashboard['bucket']['lt21'] }}</div>
    <div class="kpi-note">tunjangan valid</div>
  </div>
  <div class="kpi" style="--kc:#b45309;--kbg:#b4530914;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/><circle cx="12" cy="12" r="5"/></svg></div><div><div class="kpi-lbl">Anak 21 &ndash; 25 Tahun</div></div></div>
    <div class="kpi-val">{{ $dashboard['bucket']['21to25'] }}</div>
    <div class="kpi-note">wajib surat kuliah</div>
  </div>
  <div class="kpi" style="--kc:#b3261e;--kbg:#b3261e14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="kpi-lbl">Anak &gt; 25 Tahun</div></div></div>
    <div class="kpi-val">{{ $dashboard['bucket']['gt25'] }}</div>
    <div class="kpi-note">tidak berhak</div>
  </div>
  <div class="kpi" style="--kc:#64748b;--kbg:#64748b14;">
    <div class="kpi-top"><div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div><div><div class="kpi-lbl">Tgl Lahir Belum Lengkap</div></div></div>
    <div class="kpi-val">{{ $dashboard['bucket']['invalid'] }}</div>
    <div class="kpi-note">anak tanpa tanggal lahir</div>
  </div>
</div>

<div class="dash-card">
  <h3>Rincian Tunjangan Keluarga</h3>
  <div class="tbl-tools"><input type="text" id="tk-search" placeholder="Cari Nama / NIP / Pangkat / Jabatan / Status…"></div>
  <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;"><table class="realisasi"><thead><tr><th>Nama</th><th>NIP</th><th>Golongan/Pangkat</th><th>Jabatan</th><th>Pasangan</th><th>Anak Aktif</th><th>Status</th></tr></thead><tbody>@forelse($dashboard['rincian'] as $i=>$r)<tr class="tk-row" data-row="{{ $i }}"><td><strong>&#9656; {{ $r['nama'] }}</strong></td><td>{{ $r['nip'] }}</td><td>{{ $r['golongan'] }} / {{ $r['pangkat'] }}</td><td>{{ $r['jabatan'] }}</td><td>{{ $r['pasangan'] ?: 'Tidak' }}</td><td>{{ $r['jumlah_anak_aktif'] }}</td><td><span class="badge st-aktif">{{ $r['status'] }}</span></td></tr><tr class="tk-detail" data-detail="{{ $i }}"><td colspan="7"><strong>Rincian Anak</strong>@forelse($r['anak'] as $anak)<div style="padding:5px 0;border-bottom:1px dashed var(--line)">{{ $anak['nama'] }} · {{ $anak['tanggal_lahir'] ?: 'Tanggal invalid' }} · {{ $anak['umur']['teks'] ?? '-' }}<br><span style="color:{{ $anak['kelayakan']['aktif']?'var(--ok)':'var(--err)' }}">{{ $anak['kelayakan']['aktif']?'Aktif':'Tidak aktif' }}{{ $anak['kelayakan']['alasan']?' — '.$anak['kelayakan']['alasan']:'' }}</span></div>@empty<div class="sub">Tidak ada anak.</div>@endforelse</td></tr>@empty<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--mut)">Belum ada data tunjangan keluarga.</td></tr>@endforelse</tbody></table></div>
</div>
<script>document.querySelectorAll('.tk-row').forEach(r=>r.addEventListener('click',()=>document.querySelector('[data-detail="'+r.dataset.row+'"]').classList.toggle('open')));document.getElementById('tk-search').addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('.tk-row').forEach(r=>{const show=r.textContent.toLowerCase().includes(q);r.style.display=show?'':'none';if(!show)document.querySelector('[data-detail="'+r.dataset.row+'"]').classList.remove('open')})});</script>
@endsection
