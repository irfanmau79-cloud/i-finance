<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'i-Finance') &mdash; Inspektorat Jabar</title>
@include('layouts.partials.styles')
</head>
<body>

<div class="sb-overlay" id="sb-overlay"></div>
<div class="shell" id="app-shell">
  <aside class="sidebar">
    <div class="sb-head">
      <div class="ic"><img src="data:image/webp;base64,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" alt="Logo Inspektorat Jabar" style="width:100%;height:100%;object-fit:contain;"></div>

      <div>
        <div class="t1">i-Finance</div>
        <div class="t2">Inspektorat Daerah<br>Provinsi Jawa Barat</div>
      </div>
      <div class="sb-close" id="sb-close"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
    </div>
    <nav class="sb-menu">
      @php
        $akses = config('akses.menu')[auth()->user()->role] ?? [];
        $activeNav = trim($__env->yieldContent('activeNav'));
        $navHref = [
            'sp-input' => route('surat-perintah.create'),
            'sp-data' => route('surat-perintah.index'),
            'sp-monitor' => route('surat-perintah.monitoring'),
            'audit-log' => route('audit-log.index'),
            'npd' => route('npd.index'),
            'persetujuan' => route('npd.persetujuan'),
            'verifikasi' => route('npd.verifikasi'),
        ];
        $href = fn ($key) => $navHref[$key] ?? route('menu.placeholder', $key);
        $group = function (array $subs) use ($akses, $activeNav) {
            return [
                'visible' => (bool) array_intersect($subs, $akses),
                'open' => in_array($activeNav, $subs, true),
            ];
        };
      @endphp

      @php($g = $group(['dashboard', 'dashpd', 'tk-monitor', 'dashspj']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-dashboard-parent">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
          Dashboard
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('dashboard', $akses)) <a class="sb-item sub{{ $activeNav === 'dashboard' ? ' active' : '' }}" href="{{ $href('dashboard') }}">Dashboard Realisasi Anggaran</a> @endif
          @if (in_array('dashpd', $akses)) <a class="sb-item sub{{ $activeNav === 'dashpd' ? ' active' : '' }}" href="{{ $href('dashpd') }}">Dashboard Perjalanan Dinas</a> @endif
          @if (in_array('tk-monitor', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-monitor' ? ' active' : '' }}" href="{{ $href('tk-monitor') }}">Dashboard Tunjangan Keluarga</a> @endif
          @if (in_array('dashspj', $akses)) <a class="sb-item sub{{ $activeNav === 'dashspj' ? ' active' : '' }}" href="{{ $href('dashspj') }}">Dashboard SPJ Pengawasan</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('rincian', $akses))
      <a class="sb-item{{ $activeNav === 'rincian' ? ' active' : '' }}" href="{{ $href('rincian') }}">
        <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Rincian Realisasi
      </a>
      @endif

      @if (in_array('analisis', $akses))
      <a class="sb-item{{ $activeNav === 'analisis' ? ' active' : '' }}" href="{{ $href('analisis') }}">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/></svg>
        Analisis dan Tren
      </a>
      @endif

      @if (in_array('invspj', $akses))
      <a class="sb-item{{ $activeNav === 'invspj' ? ' active' : '' }}" href="{{ $href('invspj') }}">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v6H4z"/><path d="M4 10h16v10H4z"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
        Inventarisasi SPJ
      </a>
      @endif

      @php($g = $group(['npd', 'npd-selesai']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-npd-parent">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
          Pembuatan NPD
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('npd', $akses)) <a class="sb-item sub{{ $activeNav === 'npd' ? ' active' : '' }}" href="{{ $href('npd') }}">NPD Dalam Proses</a> @endif
          @if (in_array('npd-selesai', $akses)) <a class="sb-item sub{{ $activeNav === 'npd-selesai' ? ' active' : '' }}" href="{{ $href('npd-selesai') }}">NPD Selesai</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['persetujuan', 'persetujuan-selesai']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-persetujuan-parent">
          <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Persetujuan NPD
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('persetujuan', $akses)) <a class="sb-item sub{{ $activeNav === 'persetujuan' ? ' active' : '' }}" href="{{ $href('persetujuan') }}">NPD Dalam Proses</a> @endif
          @if (in_array('persetujuan-selesai', $akses)) <a class="sb-item sub{{ $activeNav === 'persetujuan-selesai' ? ' active' : '' }}" href="{{ $href('persetujuan-selesai') }}">NPD Selesai</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['verifikasi', 'verifikasi-selesai']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-verifikasi-parent">
          <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Verifikasi NPD
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('verifikasi', $akses)) <a class="sb-item sub{{ $activeNav === 'verifikasi' ? ' active' : '' }}" href="{{ $href('verifikasi') }}">NPD Dalam Proses</a> @endif
          @if (in_array('verifikasi-selesai', $akses)) <a class="sb-item sub{{ $activeNav === 'verifikasi-selesai' ? ' active' : '' }}" href="{{ $href('verifikasi-selesai') }}">NPD Selesai</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['sp-input', 'sp-data', 'sp-monitor']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-sp-parent">
          <svg viewBox="0 0 24 24"><path d="M9 2h6a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v0a2 2 0 0 1 2-2z"/><path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
          Surat Perintah
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('sp-input', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-input' ? ' active' : '' }}" href="{{ $href('sp-input') }}">Input SP</a> @endif
          @if (in_array('sp-data', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-data' ? ' active' : '' }}" href="{{ $href('sp-data') }}">Data SP</a> @endif
          @if (in_array('sp-monitor', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-monitor' ? ' active' : '' }}" href="{{ $href('sp-monitor') }}">Monitoring SP</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['tk-form']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-tk-parent">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Tunjangan Keluarga
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('tk-form', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-form' ? ' active' : '' }}" href="{{ $href('tk-form') }}">Perubahan Data</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('audit-log', $akses))
      <a class="sb-item{{ $activeNav === 'audit-log' ? ' active' : '' }}" href="{{ $href('audit-log') }}">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Log Aktivitas
      </a>
      @endif

      @if (in_array('users', $akses))
      <a class="sb-item{{ $activeNav === 'users' ? ' active' : '' }}" href="{{ $href('users') }}">
        <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><circle cx="18" cy="15" r="3"/><path d="M18 11.5v.9M18 17.6v.9M21.03 13.25l-.77.45M15.74 16.3l-.77.45M21.03 16.75l-.77-.45M15.74 13.7l-.77-.45"/></svg>
        Manajemen Users
      </a>
      @endif

      @if (in_array('profil', $akses))
      <a class="sb-item{{ $activeNav === 'profil' ? ' active' : '' }}" href="{{ $href('profil') }}">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profil Saya
      </a>
      @endif
    </nav>
    <div style="margin-top:auto;">
      <div id="sb-userinfo" style="padding:8px 20px;font-size:11.5px;color:#9db8d6;border-top:1px solid rgba(255,255,255,.1);">{{ auth()->user()->nama }} &mdash; {{ config('akses.role_label')[auth()->user()->role] ?? auth()->user()->role }}</div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="sb-logout" style="width:100%;border:none;background:none;font:inherit;text-align:left;cursor:pointer;">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Keluar
        </button>
      </form>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="burger" id="sb-burger"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></div>
      <div class="tt">@yield('title', 'i-Finance')</div>
    </div>

    <div class="page show">
      @yield('content')
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('.sb-parent').forEach(function (parent) {
    parent.addEventListener('click', function () {
      parent.closest('.sb-group').classList.toggle('open');
    });
  });
</script>

</body>
</html>
