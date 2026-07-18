<form method="GET" action="{{ route($routeName) }}" class="tbl-tools">
    <select name="jenis" style="max-width:220px;">
        <option value="">-- Semua Jenis --</option>
        @foreach (\App\Models\Npd::JENIS_LABEL as $kode => $label)
            <option value="{{ $kode }}" @selected(request('jenis') === $kode)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="status" style="max-width:260px;">
        <option value="">-- Semua Status --</option>
        @foreach (\App\Models\Npd::STATUS_LIST as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn prim" style="white-space:nowrap;">Filter</button>
    @if (request()->hasAny(['jenis', 'status']))
        <a href="{{ route($routeName) }}" class="btn" style="white-space:nowrap;">Reset</a>
    @endif
</form>

<div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
    <table class="realisasi">
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Jenis</th>
                <th>Tanggal</th>
                <th>Sumber Dana</th>
                <th>Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($npds as $npd)
                <tr>
                    <td><a href="{{ route('npd.show', $npd) }}">{{ $npd->nomor_lengkap ?? '— (Draft)' }}</a></td>
                    <td>{{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }}</td>
                    <td>{{ $npd->tanggal_npd->format('d-m-Y') }}</td>
                    <td>{{ $npd->masterAnggaran->kode_rekening }} &mdash; {{ $npd->masterAnggaran->sub_kegiatan }}</td>
                    <td>Rp {{ number_format((float) $npd->nominal, 2, ',', '.') }}</td>
                    <td><span class="badge {{ \App\Models\Npd::STATUS_BADGE_CLASS[$npd->status] ?? 'st-diterima' }}">{{ $npd->status }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--mut);padding:20px;">Belum ada data NPD.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($npds->hasPages())
<div class="pager">
    <div class="pager-info">Menampilkan {{ $npds->firstItem() }}&ndash;{{ $npds->lastItem() }} dari {{ $npds->total() }} data</div>
    <div class="pager-btns">
        <a class="pg-btn" href="{{ $npds->previousPageUrl() ?? '#' }}"@if (! $npds->previousPageUrl()) style="pointer-events:none;opacity:.4;" @endif>&larr; Sebelumnya</a>
        <a class="pg-btn" href="{{ $npds->nextPageUrl() ?? '#' }}"@if (! $npds->nextPageUrl()) style="pointer-events:none;opacity:.4;" @endif>Berikutnya &rarr;</a>
    </div>
</div>
@endif
