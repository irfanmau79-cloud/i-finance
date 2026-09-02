@extends('layouts.app')

@section('activeNav', 'spm-ls')
@section('title', 'Realisasi SP2D LS')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Realisasi SP2D LS</h3>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if (boleh_ubah())
    <div class="tbl-tools">
        <a href="{{ route('spm.ls.create') }}" class="btn prim" style="white-space:nowrap;">Tambah Realisasi SP2D LS</a>
    </div>
    @endif

    <form method="GET" action="{{ route('spm.ls.index') }}" class="tbl-tools">
        <input type="text" name="cari" placeholder="Cari nomor SPM, nomor SP2D, penerima, atau uraian..." value="{{ request('cari') }}" style="min-width:300px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
        @if (request()->hasAny(['cari']))
            <a href="{{ route('spm.ls.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed">
            <colgroup>
                <col style="width:9%;"><col style="width:16%;"><col style="width:9%;"><col style="width:11%;">
                <col style="width:13%;"><col style="width:15%;"><col style="width:19%;"><col style="width:8%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Tanggal SPM</th>
                    <th>Nomor SPM</th>
                    <th>Tanggal SP2D</th>
                    <th>Nomor SP2D</th>
                    <th class="num">Nominal</th>
                    <th>Penerima</th>
                    <th>Uraian</th>
                    <th class="mid">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spms as $i => $spm)
                    @php $multi = $spm->detail->count() > 1; @endphp
                    <tr @if ($multi) data-spm-toggle="spm-ls-{{ $i }}" aria-expanded="false" style="cursor:pointer;" @endif>
                        <td>{{ $spm->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td>
                            {{ $spm->nomor_dokumen }}
                            @if ($multi)
                                <span class="pill" data-spm-caret style="margin-top:4px;background:var(--navy-l);color:var(--tegas);">&#9656; {{ $spm->detail->count() }} mata anggaran</span>
                            @endif
                        </td>
                        <td>{{ $spm->tanggal_sp2d?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $spm->nomor_sp2d ?? '—' }}</td>
                        <td class="num">Rp {{ number_format($spm->totalNominal(), 2, ',', '.') }}</td>
                        <td>
                            {{ $spm->penerima ?? '—' }}
                            @if ($spm->bank_tujuan || $spm->nomor_rekening)
                                <span class="sub">{{ trim(($spm->bank_tujuan ?? '').' · '.($spm->nomor_rekening ?? ''), ' ·') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($spm->uraian)
                                <span class="tbl-clamp" title="{{ $spm->uraian }}">{{ $spm->uraian }}</span>
                            @else
                                <span class="tbl-kosong">—</span>
                            @endif
                        </td>
                        <td class="mid" onclick="event.stopPropagation();">
                            @if (boleh_ubah())
                                <div style="display:inline-flex;gap:6px;">
                                    <a class="ic-btn" title="Edit" href="{{ route('spm.ls.edit', $spm) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                                    <form method="POST" action="{{ route('spm.destroy', $spm) }}" onsubmit="return confirm('Hapus SPM {{ $spm->nomor_dokumen }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ic-btn danger" title="Hapus"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </form>
                                </div>
                            @else
                                <span class="tbl-kosong">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                    @if ($multi)
                        <tr data-spm-member="spm-ls-{{ $i }}" style="display:none;background:var(--surface-2);">
                            <td colspan="8" style="padding:10px 16px 10px 34px;">
                                <table style="width:100%;table-layout:fixed;">
                                    <colgroup><col style="width:78%;"><col style="width:22%;"></colgroup>
                                    @foreach ($spm->detail as $baris)
                                        <tr>
                                            <td style="padding:3px 0;overflow-wrap:anywhere;">{{ $baris->masterAnggaran?->rekening_lengkap }} &mdash; {{ $baris->masterAnggaran?->sub_kegiatan_lengkap }}</td>
                                            <td style="padding:3px 0;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">Rp {{ number_format((float) $baris->nominal, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data SPM LS.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($spms->hasPages())
    <div class="pager">
        <div class="pager-info">Menampilkan {{ $spms->firstItem() }}&ndash;{{ $spms->lastItem() }} dari {{ $spms->total() }} data</div>
        <div class="pager-btns">
            <a class="pg-btn" href="{{ $spms->previousPageUrl() ?? '#' }}"@if (! $spms->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
            <a class="pg-btn" href="{{ $spms->nextPageUrl() ?? '#' }}"@if (! $spms->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
        </div>
    </div>
    @endif
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-spm-toggle]').forEach(row => row.addEventListener('click', () => {
        const open = row.getAttribute('aria-expanded') === 'true';
        row.setAttribute('aria-expanded', String(! open));
        const caret = row.querySelector('[data-spm-caret]');
        caret.innerHTML = (open ? '&#9656; ' : '&#9662; ') + caret.textContent.replace(/^[▶▼]\s*/, '');
        document.querySelectorAll('[data-spm-member="' + row.dataset.spmToggle + '"]').forEach(member => {
            member.style.display = open ? 'none' : 'table-row';
        });
    }));
});
</script>
@endsection
