@extends('layouts.app')

@section('activeNav', 'tk-monitor')
@section('title', 'Monitoring Pengajuan Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Monitoring Pengajuan</div>
        <div class="ph-title">Monitoring Pengajuan Tunjangan Keluarga</div>
    </div>
    @if (auth()->user()->role === 'superadmin')
        <div class="ph-actions"><a class="btn" href="{{ route('tunjangan.import.create') }}">Import Awal (Dry-run)</a></div>
    @endif
</div>

@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block">{{ $errors->first() }}</div>
@endif

<div class="dash-card wf-card">
    <h3>Daftar Pengajuan</h3>
    <div class="sub">Lampiran private dan perubahan belum memengaruhi master sebelum disetujui.</div>

    <div class="tbl-tools">
        <input type="text" id="tk-mon-search" placeholder="Cari Nama Pegawai / NIP / Status…">
    </div>

    <div class="sp-table-wrap wf-scroll" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pegawai</th>
                    <th>Perubahan</th>
                    <th>Lampiran</th>
                    <th>Status</th>
                    <th>Proses</th>
                </tr>
            </thead>
            <tbody id="tk-mon-body">
                @forelse ($pengajuan as $p)
                    <tr>
                        <td>{{ $p->diajukan_at->format('d-m-Y H:i') }}</td>
                        <td><strong>{{ $p->nama_pegawai }}</strong><br><span class="sub">{{ $p->nip ?: '-' }}</span></td>
                        <td>
                            {{ $p->keterangan }}
                            @php($pasangan = $p->payload['pasangan'] ?? [])
                            @php($anakList = $p->payload['anak'] ?? [])
                            @if (filled($pasangan['nama'] ?? null) || count(array_filter($anakList, fn ($a) => filled($a['nama'] ?? null))) > 0)
                                <details style="margin-top:6px">
                                    <summary style="cursor:pointer;color:var(--navy);font-size:12px;font-weight:600;">Data keluarga</summary>
                                    <div style="margin-top:6px;font-size:12px;color:var(--ink);">
                                        @if (filled($pasangan['nama'] ?? null))
                                            <div style="padding:4px 0;border-bottom:1px dashed var(--line)">
                                                <strong>Pasangan:</strong> {{ $pasangan['nama'] }}
                                                &middot; {{ $pasangan['tanggal_lahir'] ?? '-' }}
                                                &middot; Tunjangan: <span class="badge {{ ! empty($pasangan['status_tunjangan']) ? 'st-aktif' : 'st-diterima' }}">{{ ! empty($pasangan['status_tunjangan']) ? 'Ya' : 'Tidak' }}</span>
                                            </div>
                                        @endif
                                        @foreach ($anakList as $i => $anak)
                                            @continue(blank($anak['nama'] ?? null))
                                            <div style="padding:4px 0;border-bottom:1px dashed var(--line)">
                                                <strong>Anak Ke-{{ $i + 1 }}:</strong> {{ $anak['nama'] }}
                                                &middot; {{ $anak['tanggal_lahir'] ?? '-' }}
                                                &middot; Tunjangan: <span class="badge {{ ! empty($anak['status_tunjangan']) ? 'st-aktif' : 'st-diterima' }}">{{ ! empty($anak['status_tunjangan']) ? 'Ya' : 'Tidak' }}</span>
                                                @if (! empty($anak['perpanjangan_kuliah']))
                                                    &middot; <span class="badge st-verifikasi">Perpanjangan Kuliah</span>
                                                @endif
                                                @if (filled($anak['keterangan'] ?? null))
                                                    <br><span class="sub">{{ $anak['keterangan'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td>
                            @foreach ($p->lampiran as $lampiran)
                                @if (in_array(auth()->user()->role, ['superadmin', 'bendahara_pengeluaran']))
                                    <a href="{{ route('tunjangan.lampiran.download', $lampiran) }}">{{ $lampiran->nama_asli }}</a><br>
                                @else
                                    <span class="sub">Private</span><br>
                                @endif
                            @endforeach
                        </td>
                        <td><span class="badge {{ $p->status === 'disetujui' ? 'st-aktif' : ($p->status === 'ditolak' ? 'st-danger' : 'st-verifikasi') }}">{{ strtoupper($p->status) }}</span></td>
                        <td>
                            @if ($p->status === 'diajukan' && in_array(auth()->user()->role, ['superadmin', 'bendahara_pengeluaran']))
                                <form method="POST" action="{{ route('tunjangan.pengajuan.proses', $p) }}" style="min-width:200px">
                                    @csrf
                                    <div class="fg">
                                        <label class="fl" style="margin-top:0">Pegawai master</label>
                                        <select name="pegawai_id" required>
                                            <option value="">-- Pilih Pegawai --</option>
                                            @foreach ($pegawai as $pg)
                                                <option value="{{ $pg->id }}" @selected($p->pegawai_id === $pg->id)>{{ $pg->nama }} &middot; {{ $pg->nip }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label class="fl">Catatan proses</label>
                                        <input name="catatan" placeholder="Opsional">
                                    </div>
                                    <div style="display:flex;gap:6px;margin-top:8px">
                                        <button class="btn prim" name="aksi" value="setujui">Setujui</button>
                                        <button class="btn" name="aksi" value="tolak" formnovalidate>Tolak</button>
                                    </div>
                                </form>
                            @else
                                {{ $p->diprosesOleh?->nama ?? '-' }}
                                @if ($p->catatan_proses)
                                    <br><span class="sub">{{ $p->catatan_proses }}</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--mut)">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pengajuan->links() }}
</div>

<script>
document.getElementById('tk-mon-search').addEventListener('input', function (e) {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#tk-mon-body > tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
@endsection
