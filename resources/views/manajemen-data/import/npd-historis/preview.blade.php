@extends('layouts.app')

@section('activeNav', 'manajemen-data')
@section('title', 'Pemeriksaan Berkas Import Nota Pencairan Dana')

@section('content')

@php
    $sudahDiimpor = $import->status === \App\Models\NpdHistorisImport::STATUS_COMMITTED;
    $masihStaging = $import->status === \App\Models\NpdHistorisImport::STATUS_STAGED;
    $siapDiimpor = $import->jumlah_valid + $import->jumlah_warning;

    // Label Indonesia untuk nilai enum yang disimpan di kolom staging -
    // sebelumnya nilai mentahnya ('valid', 'warning', ...) tampil apa adanya.
    $hasilLabel = [
        'valid' => ['Valid', 'var(--ok-bg)', 'var(--ok)'],
        'warning' => ['Perlu Diperiksa', 'var(--warn-bg)', 'var(--warn)'],
        'error' => ['Bermasalah', 'var(--err-bg)', 'var(--err)'],
        'duplicate' => ['Duplikat', 'var(--navy-l)', 'var(--navy)'],
    ];

    $mappingLabel = [
        'exact' => 'Cocok penuh',
        'rak_exact_tagging_snapshot' => 'Cocok, tagging disimpan sebagai snapshot',
        'error' => 'Gagal dipetakan',
        'tahun_ditolak' => 'Tahun di luar cakupan',
        'tanggal_tidak_valid' => 'Tanggal tidak valid',
    ];

    $namaBulan = function ($b) {
        $b = is_numeric($b) ? (int) $b : 0;

        return $b >= 1 && $b <= 12 ? \App\Services\AnggaranRealisasiService::BULAN[$b - 1] : '—';
    };
    $rp = fn ($n) => $n !== null ? 'Rp '.fmt_rupiah($n) : '—';
@endphp

{{-- ---------------- Identitas batch ---------------- --}}
<div class="dash-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <h3 style="margin-bottom:6px;">Batch #{{ $import->id }} &mdash; {{ $import->nama_file }}</h3>
            <div class="sub" style="margin-bottom:0;">
                Tahun Anggaran {{ config('anggaran.tahun_aktif') }}
                @if ($import->user) &middot; diunggah oleh {{ $import->user->nama }} @endif
                @if ($import->created_at) &middot; {{ $import->created_at->format('d-m-Y H:i') }} @endif
            </div>
        </div>
        @if ($sudahDiimpor)
            <span class="badge" style="background:var(--ok-bg);color:var(--ok);">Sudah Diimpor</span>
        @elseif ($import->kedaluwarsa())
            <span class="badge" style="background:var(--err-bg);color:var(--err);">Kedaluwarsa</span>
        @elseif ($masihStaging)
            <span class="badge" style="background:var(--warn-bg);color:var(--warn);">Menunggu Konfirmasi</span>
        @endif
    </div>

    @if (session('success'))
        <div style="background:var(--ok-bg);color:var(--ok);border-radius:var(--radius-sm);padding:11px 13px;margin-top:14px;font-size:13px;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Import tidak dapat dijalankan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Laporan hasil import. Angka berhasil/dilewati dan waktu eksekusinya
         sudah lama disimpan di tabel batch tetapi tidak pernah ditampilkan. --}}
    @if ($sudahDiimpor)
        <div style="margin-top:16px;border:1px solid var(--line);border-left:3px solid var(--ok);border-radius:var(--radius-sm);padding:14px 16px;">
            <div style="font-size:12.5px;font-weight:700;color:var(--ok);letter-spacing:.2px;">HASIL IMPORT</div>
            @php
                // .kpi-lbl / .kpi-val hanya berlaku di dalam kartu .kpi
                // (selector turunan), jadi di luar kartu gayanya ditulis sendiri.
                $lbl = 'font-size:11px;color:var(--mut);font-weight:600;text-transform:uppercase;letter-spacing:.4px;';
                $val = 'font-size:21px;font-weight:700;color:var(--navy);letter-spacing:-.3px;line-height:1.15;margin-top:3px;';
            @endphp
            <div style="display:flex;flex-wrap:wrap;gap:22px 34px;margin-top:10px;">
                <div>
                    <div style="{{ $lbl }}">Tersimpan</div>
                    <div style="{{ $val }}color:var(--ok);">{{ $import->jumlah_berhasil }} dokumen</div>
                </div>
                <div>
                    <div style="{{ $lbl }}">Dilewati</div>
                    <div style="{{ $val }}">{{ $import->jumlah_dilewati }} baris</div>
                </div>
                <div>
                    <div style="{{ $lbl }}">Waktu Import</div>
                    <div style="{{ $val }}">{{ $import->executed_at?->format('d-m-Y H:i') ?? '—' }}</div>
                </div>
            </div>
            <div class="sub" style="margin:12px 0 0;">
                Baris yang dilewati adalah baris bermasalah dan duplikat &mdash; keduanya memang tidak
                pernah ikut disimpan. Rinciannya ada di tabel di bawah, atau unduh Laporan Final.
            </div>
        </div>
    @else
        <div class="sub" style="margin-top:14px;">
            Penyimpanan dilakukan sekaligus. Bila ada satu baris yang gagal, tidak ada data yang
            tersimpan sama sekali &mdash; jadi tidak akan ada data yang masuk separuh.
        </div>
    @endif

    {{-- Baris tombol. Sebelumnya memakai .nav yang justify-content:space-between,
         sehingga tombolnya terlempar ke ujung kiri dan kanan kartu. --}}
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px;">
        @if ($masihStaging && ! $import->kedaluwarsa())
            <form method="POST"
                  action="{{ route('manajemen-data.import.npd-historis.confirm', $import) }}"
                  onsubmit="return confirm('Impor {{ $siapDiimpor }} dokumen dengan total Rp {{ fmt_rupiah($import->total_nominal) }}? Tindakan ini tidak dapat dibatalkan.');">
                @csrf
                <button type="submit" class="btn prim">Konfirmasi Import {{ $siapDiimpor }} Dokumen</button>
            </form>
        @endif
        @if ($sudahDiimpor)
            <a class="btn prim" href="{{ route('manajemen-data.import.npd-historis.report', [$import, 'final']) }}">Unduh Laporan Final</a>
        @endif
        <a class="btn" href="{{ route('manajemen-data.import.npd-historis.report', [$import, 'validation']) }}">Unduh Laporan Validasi</a>
        <a class="btn" href="{{ route('manajemen-data.import.npd-historis.create') }}">Kembali ke Import</a>
    </div>
</div>

{{-- ---------------- Ringkasan pemeriksaan ---------------- --}}
<div class="kpi-grid">
    <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
        <div class="kpi-top">
            <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div><div class="kpi-lbl">Total Baris</div></div>
        </div>
        <div class="kpi-val">{{ $import->total_baris }}</div>
        <div class="kpi-note">Baris data di berkas</div>
    </div>

    <div class="kpi" style="--kc:#0f6e56;--kbg:#0f6e5614;">
        <div class="kpi-top">
            <div class="kpi-ic"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div><div class="kpi-lbl">{{ $sudahDiimpor ? 'Tersimpan' : 'Siap Diimpor' }}</div></div>
        </div>
        <div class="kpi-val">{{ $sudahDiimpor ? $import->jumlah_berhasil : $siapDiimpor }}</div>
        <div class="kpi-note">{{ $import->jumlah_valid }} valid &middot; {{ $import->jumlah_warning }} perlu diperiksa</div>
    </div>

    <div class="kpi" style="--kc:#b3261e;--kbg:#b3261e14;">
        <div class="kpi-top">
            <div class="kpi-ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div><div class="kpi-lbl">Bermasalah</div></div>
        </div>
        <div class="kpi-val">{{ $import->jumlah_error }}</div>
        <div class="kpi-note">Tidak ikut disimpan</div>
    </div>

    <div class="kpi" style="--kc:#64748b;--kbg:#64748b14;">
        <div class="kpi-top">
            <div class="kpi-ic"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></div>
            <div><div class="kpi-lbl">Duplikat</div></div>
        </div>
        <div class="kpi-val">{{ $import->jumlah_duplikat }}</div>
        <div class="kpi-note">Sudah pernah diimpor</div>
    </div>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi" style="--kc:#15314a;--kbg:#15314a14;">
        <div class="kpi-top"><div><div class="kpi-lbl">Nominal Bruto</div></div></div>
        <div class="kpi-val">Rp {{ fmt_rupiah($import->total_nominal) }}</div>
    </div>
    <div class="kpi" style="--kc:#7c3aed;--kbg:#7c3aed14;">
        <div class="kpi-top"><div><div class="kpi-lbl">PPN</div></div></div>
        <div class="kpi-val">Rp {{ fmt_rupiah($import->total_ppn) }}</div>
    </div>
    <div class="kpi" style="--kc:#b07d1d;--kbg:#b07d1d14;">
        <div class="kpi-top"><div><div class="kpi-lbl">Total PPh</div></div></div>
        <div class="kpi-val">Rp {{ fmt_rupiah($import->total_pph) }}</div>
    </div>
</div>

{{-- ---------------- Penyaring ---------------- --}}
<div class="dash-card" style="margin-top:16px;">
    <h3>Rincian Baris</h3>
    <div class="sub">Saring untuk menelusuri baris tertentu. Filter tidak mengubah data yang disimpan.</div>

    <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:4px;flex:1 1 160px;min-width:150px;">
            <label class="fl" style="margin:0;" for="f-hasil">Hasil Pemeriksaan</label>
            <select id="f-hasil" name="hasil">
                <option value="">Semua hasil</option>
                @foreach ($hasilLabel as $nilai => $info)
                    <option value="{{ $nilai }}" @selected(request('hasil') === $nilai)>{{ $info[0] }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;flex:1 1 160px;min-width:150px;">
            <label class="fl" style="margin:0;" for="f-jenis">Jenis NPD</label>
            <select id="f-jenis" name="jenis_kode">
                <option value="">Semua jenis</option>
                @foreach (\App\Models\Npd::JENIS_LABEL as $kode => $label)
                    <option value="{{ $kode }}" @selected(request('jenis_kode') === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;flex:0 1 110px;min-width:100px;">
            <label class="fl" style="margin:0;" for="f-tahun">Tahun</label>
            <input id="f-tahun" name="tahun" inputmode="numeric" placeholder="{{ config('anggaran.tahun_aktif') }}" value="{{ request('tahun') }}">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;flex:1 1 160px;min-width:150px;">
            <label class="fl" style="margin:0;" for="f-status">Status Tujuan</label>
            <select id="f-status" name="status_target">
                <option value="">Semua status</option>
                <option value="Selesai" @selected(request('status_target') === 'Selesai')>Selesai</option>
                <option value="Dibatalkan" @selected(request('status_target') === 'Dibatalkan')>Dibatalkan</option>
            </select>
        </div>

        <label style="display:flex;align-items:center;gap:7px;font-size:13px;padding-bottom:9px;white-space:nowrap;">
            <input type="checkbox" name="manual" value="1" @checked(request('manual')) style="width:auto;margin:0;">
            Penerima manual saja
        </label>

        <div style="display:flex;gap:8px;padding-bottom:1px;">
            <button type="submit" class="btn prim">Terapkan</button>
            @if (request()->hasAny(['hasil', 'jenis_kode', 'tahun', 'status_target', 'manual']))
                <a class="btn" href="{{ route('manajemen-data.import.npd-historis.preview', $import) }}">Reset</a>
            @endif
        </div>
    </form>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:16px;overflow-x:auto;">
        <table class="realisasi">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Hasil</th>
                    <th>Dokumen</th>
                    <th>Periode</th>
                    <th>Mata Anggaran</th>
                    <th>Penerima</th>
                    <th class="num">Nilai</th>
                    <th class="num">Pagu &amp; RAK</th>
                    <th class="num">Proyeksi Realisasi</th>
                    <th>Pemetaan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $row)
                    @php $h = $hasilLabel[$row->hasil] ?? [$row->hasil, 'var(--navy-l)', 'var(--navy)']; @endphp
                    <tr>
                        <td>{{ $row->nomor_baris }}</td>
                        <td>
                            <span class="badge" style="background:{{ $h[1] }};color:{{ $h[2] }};">{{ $h[0] }}</span>
                            @if (! empty($row->pesan))
                                <div class="sub" style="margin:6px 0 0;max-width:260px;white-space:normal;">
                                    {{ implode(' · ', $row->pesan) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:600;">{{ $row->nomor_npd ?: '—' }}</div>
                            <div class="sub" style="margin:2px 0 0;">
                                {{ $row->tanggal_npd?->format('d-m-Y') ?? 'Tanggal tidak valid' }}
                                &middot; {{ \App\Models\Npd::JENIS_LABEL[$row->jenis_kode] ?? $row->jenis_input ?? '—' }}
                            </div>
                            @if ($row->npd_id)
                                <a class="sub" style="margin:2px 0 0;display:inline-block;font-weight:600;" href="{{ route('npd.show', $row->npd_id) }}">Lihat NPD tersimpan</a>
                            @endif
                        </td>
                        <td>
                            {{ $namaBulan($row->bulan) }}<br>
                            <span class="sub">{{ $row->tahun ?? '—' }} &middot; {{ $row->status_target ?: '—' }}</span>
                        </td>
                        <td style="max-width:280px;white-space:normal;">
                            <div style="font-weight:600;">{{ $row->sub_kegiatan ?: '—' }}</div>
                            <div class="sub" style="margin:2px 0 0;">
                                {{ $row->kode_rekening ?: '—' }}
                                @if ($row->tagging_nama) &middot; {{ $row->tagging_nama }} @endif
                            </div>
                            @if ($row->program || $row->kegiatan)
                                <div class="sub" style="margin:2px 0 0;opacity:.8;">{{ $row->program }}{{ $row->program && $row->kegiatan ? ' · ' : '' }}{{ $row->kegiatan }}</div>
                            @endif
                        </td>
                        <td style="max-width:200px;white-space:normal;">
                            <div>{{ $row->penerima ?: '—' }}</div>
                            <div class="sub" style="margin:2px 0 0;">
                                {{ $row->rekening_penerima ?: 'Tanpa rekening' }}<br>
                                {{ $row->penerima_manual ? 'Snapshot manual' : 'Cocok dengan master' }}
                            </div>
                        </td>
                        <td class="num">
                            <div style="font-weight:600;">Rp {{ fmt_rupiah($row->nominal_bruto) }}</div>
                            <div class="sub" style="margin:2px 0 0;">
                                PPN {{ fmt_rupiah($row->ppn) }}<br>
                                PPh {{ fmt_rupiah((float) $row->pph1 + (float) $row->pph2) }}
                            </div>
                        </td>
                        <td class="num">
                            <div>{{ $rp($row->pagu) }}</div>
                            <div class="sub" style="margin:2px 0 0;">RAK {{ $rp($row->rak_bulan) }}</div>
                        </td>
                        <td class="num">
                            <div class="sub" style="margin:0;">Sebelum {{ $rp($row->realisasi_sebelum) }}</div>
                            <div style="font-weight:600;">{{ $rp($row->realisasi_proyeksi) }}</div>
                            <div class="sub" style="margin:2px 0 0;">Sisa {{ $rp($row->sisa_proyeksi) }}</div>
                        </td>
                        <td style="max-width:170px;white-space:normal;">
                            {{ $mappingLabel[$row->mapping_status] ?? $row->mapping_status ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;color:var(--mut);padding:20px;">Tidak ada baris untuk filter ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $baris->links() }}
</div>

{{-- ---------------- Rekapitulasi ---------------- --}}
<div class="dash-grid" style="margin-top:16px;">
    <div class="dash-card">
        <h3>Total per Jenis NPD</h3>
        <div class="sub">Hanya baris valid dan perlu diperiksa &mdash; yaitu yang akan ikut disimpan.</div>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;overflow-x:auto;">
            <table class="realisasi">
                <thead>
                    <tr><th>Jenis</th><th class="num">Dokumen</th><th class="num">Nominal Bruto</th></tr>
                </thead>
                <tbody>
                    @forelse ($totalsByType as $t)
                        <tr>
                            <td>{{ \App\Models\Npd::JENIS_LABEL[$t->jenis_kode] ?? $t->jenis_kode ?? '—' }}</td>
                            <td class="num">{{ $t->jumlah }}</td>
                            <td class="num">Rp {{ fmt_rupiah($t->nominal) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--mut);padding:16px;">Belum ada baris yang siap disimpan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dash-card">
        <h3>Total per Periode</h3>
        <div class="sub">Dikelompokkan menurut bulan dan status tujuan dokumen.</div>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;overflow-x:auto;">
            <table class="realisasi">
                <thead>
                    <tr><th>Periode</th><th>Status</th><th class="num">Dokumen</th><th class="num">Nominal Bruto</th></tr>
                </thead>
                <tbody>
                    @forelse ($totalsByPeriod as $t)
                        <tr>
                            <td>{{ $namaBulan($t->bulan) }} {{ $t->tahun }}</td>
                            <td>{{ $t->status_target ?: '—' }}</td>
                            <td class="num">{{ $t->jumlah }}</td>
                            <td class="num">Rp {{ fmt_rupiah($t->nominal) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--mut);padding:16px;">Belum ada baris yang siap disimpan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
