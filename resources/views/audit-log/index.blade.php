@extends('layouts.app')

@section('activeNav', 'audit-log')
@section('title', 'Log Aktivitas')

@section('content')
<div class="dash-card wf-card">
    <h3>Log Aktivitas</h3>
    <div class="sub">Riwayat aktivitas pengguna pada sistem i-Finance.</div>

    <form method="GET" action="{{ route('audit-log.index') }}" class="tbl-tools">
        <input type="text" name="username" placeholder="Cari username..." value="{{ request('username') }}">
        <input type="date" name="tanggal" value="{{ request('tanggal') }}" style="max-width:180px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Filter</button>
        @if (request()->hasAny(['username', 'tanggal']))
            <a href="{{ route('audit-log.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                    <th>Keterangan</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d-m-Y H:i:s') }}</td>
                        <td>{{ $log->username }}</td>
                        <td>{{ $log->role }}</td>
                        <td>{{ $log->aktivitas }}</td>
                        <td>{{ $log->keterangan ?? '—' }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">Belum ada aktivitas tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $logs->firstItem() }}&ndash;{{ $logs->lastItem() }} dari {{ $logs->total() }} data</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $logs->previousPageUrl() ?? '#' }}"@if (! $logs->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $logs->nextPageUrl() ?? '#' }}"@if (! $logs->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
@endsection
