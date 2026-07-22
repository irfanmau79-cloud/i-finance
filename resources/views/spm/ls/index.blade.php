@extends('layouts.app')

@section('activeNav', 'spm-ls')
@section('title', 'SPM LS')

@section('content')
<div class="dash-card wf-card">
    <h3>SPM LS</h3>
    <div class="sub">Dicairkan langsung ke pihak ketiga tanpa NPD — langsung mengurangi pagu anggaran.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <a href="{{ route('spm.ls.create') }}" class="btn prim" style="white-space:nowrap;">+ SPM LS</a>
    </div>

    <form method="GET" action="{{ route('spm.ls.index') }}" class="tbl-tools">
        <input type="text" name="cari" placeholder="Cari nomor SPM, penerima, atau uraian..." value="{{ request('cari') }}" style="min-width:280px;">
        <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
        @if (request()->hasAny(['cari']))
            <a href="{{ route('spm.ls.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
        @endif
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Tanggal SPM</th>
                    <th>Nomor SPM</th>
                    <th>Mata Anggaran</th>
                    <th>Total Nominal</th>
                    <th>Penerima</th>
                    <th>Uraian</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spms as $i => $spm)
                    <tr>
                        <td>{{ $spm->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td>{{ $spm->nomor_dokumen }}</td>
                        <td>
                            @if ($spm->detail->count() > 1)
                                <button type="button" class="pd-toggle" data-spm-toggle="spm-ls-{{ $i }}" aria-expanded="false" style="border:0;background:transparent;color:var(--navy);cursor:pointer;font-weight:800;">&#9656; {{ $spm->detail->count() }} mata anggaran</button>
                            @else
                                {{ $spm->detail->first()?->masterAnggaran?->kode_rekening ?? '—' }} &mdash; {{ $spm->detail->first()?->masterAnggaran?->sub_kegiatan ?? '' }}
                            @endif
                        </td>
                        <td>Rp {{ number_format($spm->totalNominal(), 2, ',', '.') }}</td>
                        <td>{{ $spm->penerima ?? '—' }}</td>
                        <td>{{ $spm->uraian ?? '—' }}</td>
                        <td style="display:flex;gap:6px;">
                            <a class="btn" href="{{ route('spm.ls.edit', $spm) }}">Edit</a>
                            <form method="POST" action="{{ route('spm.destroy', $spm) }}" onsubmit="return confirm('Hapus SPM {{ $spm->nomor_dokumen }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @if ($spm->detail->count() > 1)
                        <tr data-spm-member="spm-ls-{{ $i }}" style="display:none;background:#fafbfd;">
                            <td colspan="7" style="padding:10px 16px 10px 34px;">
                                <table style="width:100%;">
                                    @foreach ($spm->detail as $baris)
                                        <tr>
                                            <td style="padding:3px 0;">{{ $baris->masterAnggaran?->kode_rekening }} &mdash; {{ $baris->masterAnggaran?->sub_kegiatan }}</td>
                                            <td style="padding:3px 0;text-align:right;white-space:nowrap;">Rp {{ number_format((float) $baris->nominal, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data SPM LS.</td>
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
    document.querySelectorAll('[data-spm-toggle]').forEach(btn => btn.addEventListener('click', () => {
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(! open));
        btn.innerHTML = (open ? '&#9656; ' : '&#9662; ') + btn.textContent.replace(/^[▶▼]\s*/, '');
        document.querySelectorAll('[data-spm-member="' + btn.dataset.spmToggle + '"]').forEach(row => {
            row.style.display = open ? 'none' : 'table-row';
        });
    }));
});
</script>
@endsection
