@extends('layouts.app')

@section('activeNav', 'tk-monitor')
@section('title', 'Monitoring Pengajuan Tunjangan Keluarga')

@section('content')
@php
    // Halaman ini juga dibuka Pengguna Layanan yang tidak punya akun, jadi
    // role-nya diambil dari GuestSession - auth()->user() bernilai null di sana.
    $roleTk = \App\Helpers\GuestSession::role();
    // Kolom Aksi (Approve/Tolak) hanya untuk Kepegawaian dan superadmin;
    // role lain melihat tabelnya sampai kolom Status saja.
    $bolehProses = in_array($roleTk, \App\Models\PengajuanPerubahanTunjangan::ROLE_PEMROSES, true);
    $bolehLampiran = in_array($roleTk, \App\Models\PengajuanPerubahanTunjangan::ROLE_LAMPIRAN, true);
@endphp
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Monitoring Pengajuan</div>
        <div class="ph-title">Monitoring Pengajuan Tunjangan Keluarga</div>
    </div>
    @if ($roleTk === 'superadmin')
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
        <table class="realisasi tk-mon">
            <colgroup>
                @if ($bolehProses)
                    <col style="width:9%;"><col style="width:17%;"><col style="width:33%;">
                    <col style="width:14%;"><col style="width:9%;"><col style="width:18%;">
                @else
                    <col style="width:11%;"><col style="width:21%;"><col style="width:40%;">
                    <col style="width:17%;"><col style="width:11%;">
                @endif
            </colgroup>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pegawai</th>
                    <th>Perubahan</th>
                    <th>Lampiran</th>
                    <th>Status</th>
                    @if ($bolehProses)
                        <th>Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody id="tk-mon-body">
                @forelse ($pengajuan as $p)
                    @php
                        // Anggota keluarga yang diisi pada pengajuan ini, dalam
                        // satu daftar: pasangan lebih dulu, lalu anak berurutan.
                        $pasangan = $p->payload['pasangan'] ?? [];
                        $keluarga = [];

                        if (filled($pasangan['nama'] ?? null)) {
                            $keluarga[] = ['peran' => 'Pasangan'] + $pasangan;
                        }

                        foreach ($p->payload['anak'] ?? [] as $i => $anak) {
                            if (filled($anak['nama'] ?? null)) {
                                $keluarga[] = ['peran' => 'Anak ke-'.($i + 1)] + $anak;
                            }
                        }

                        // Tanggal lahir disimpan apa adanya dari formulir;
                        // yang tidak terbaca sebagai tanggal ditampilkan mentah.
                        $tanggalLahir = function ($nilai) {
                            if (blank($nilai)) {
                                return null;
                            }

                            try {
                                return \Illuminate\Support\Carbon::parse($nilai)->format('d-m-Y');
                            } catch (\Throwable) {
                                return (string) $nilai;
                            }
                        };
                    @endphp
                    <tr>
                        <td class="tk-waktu"><b>{{ $p->diajukan_at->format('d-m-Y') }}</b><span>{{ $p->diajukan_at->format('H:i') }}</span></td>
                        <td>
                            <span class="tk-nama">{{ $p->nama_pegawai }}</span>
                            <span class="tk-nip">{{ $p->nip ?: '-' }}</span>
                        </td>
                        <td>
                            <div class="tk-ket">{{ $p->keterangan ?: '-' }}</div>
                            @if ($keluarga !== [])
                                <details class="tk-kel">
                                    <summary>
                                        <svg class="ik" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        Data Keluarga
                                        <span class="jml">{{ count($keluarga) }}</span>
                                        <svg class="panah" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                    </summary>
                                    <div class="tk-kel-isi">
                                        @foreach ($keluarga as $anggota)
                                            <div class="tk-kel-baris">
                                                <div class="peran">{{ $anggota['peran'] }}</div>
                                                <div class="orang">
                                                    <span class="nm">{{ $anggota['nama'] }}</span>
                                                    <span class="tgl">Lahir {{ $tanggalLahir($anggota['tanggal_lahir'] ?? null) ?? '-' }}</span>
                                                    @if (filled($anggota['keterangan'] ?? null))
                                                        <span class="cat">{{ $anggota['keterangan'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="tanda">
                                                    @if (! empty($anggota['status_tunjangan']))
                                                        <span class="badge st-aktif">Tunjangan</span>
                                                    @else
                                                        <span class="badge st-diterima">Tanpa Tunjangan</span>
                                                    @endif
                                                    @if (! empty($anggota['perpanjangan_kuliah']))
                                                        <span class="badge st-verifikasi">Perpanjangan Kuliah</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td>
                            @forelse ($p->lampiran as $lampiran)
                                @if ($bolehLampiran)
                                    <a class="tk-berkas" href="{{ route('tunjangan.lampiran.download', $lampiran) }}" title="{{ $lampiran->nama_asli }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                        <span>{{ $lampiran->nama_asli }}</span>
                                    </a>
                                @else
                                    <span class="tk-berkas kunci" title="Lampiran hanya bisa dibuka petugas pemroses">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <span>Private</span>
                                    </span>
                                @endif
                            @empty
                                <span class="tk-kosong">&mdash;</span>
                            @endforelse
                        </td>
                        <td><span class="badge {{ $p->status === 'disetujui' ? 'st-aktif' : ($p->status === 'ditolak' ? 'st-danger' : 'st-verifikasi') }}">{{ strtoupper($p->status) }}</span></td>
                        @if ($bolehProses)
                        <td>
                            @if ($p->status === 'diajukan')
                                <form method="POST" action="{{ route('tunjangan.pengajuan.proses', $p) }}" class="tk-proses">
                                    @csrf
                                    {{-- Pegawainya sudah ditautkan lewat NIP saat diajukan.
                                         Pilihan manual hanya muncul untuk pengajuan yang
                                         NIP-nya kosong atau tidak cocok dengan Data Pegawai. --}}
                                    @if ($p->pegawai)
                                        <div class="tk-taut">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                            <span>Tertaut ke <b>{{ $p->pegawai->nama }}</b></span>
                                        </div>
                                    @else
                                        <label class="fl">Belum tertaut &mdash; pilih pegawai</label>
                                        <select name="pegawai_id" required data-cari>
                                            <option value="">-- Pilih Pegawai --</option>
                                            @foreach ($pegawai as $pg)
                                                <option value="{{ $pg->id }}">{{ $pg->nama }} &middot; {{ $pg->nip }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <input name="catatan" placeholder="Catatan (opsional)" aria-label="Catatan">
                                    <div class="tombol">
                                        <button class="btn prim" name="aksi" value="setujui">Approve</button>
                                        <button class="btn" name="aksi" value="tolak" formnovalidate>Tolak</button>
                                    </div>
                                </form>
                            @elseif ($p->diprosesOleh || $p->catatan_proses)
                                <span class="tk-nama">{{ $p->diprosesOleh?->nama ?? '-' }}</span>
                                @if ($p->catatan_proses)
                                    <span class="tk-nip">{{ $p->catatan_proses }}</span>
                                @endif
                            @else
                                <span class="tk-kosong">&mdash;</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $bolehProses ? 6 : 5 }}" style="text-align:center;padding:30px;color:var(--mut)">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $pengajuan->links() }}
</div>

<style>
    /* Tabel Daftar Pengajuan. Lebar kolom dikunci supaya membuka "Data
       Keluarga" di satu baris tidak menggeser kolom baris lain. */
    table.tk-mon{width:100%;min-width:920px;table-layout:fixed;}
    table.tk-mon td{vertical-align:top;overflow-wrap:anywhere;}
    .tk-waktu b{display:block;color:var(--tegas);font-weight:700;white-space:nowrap;}
    .tk-waktu span{display:block;margin-top:2px;font-size:11.5px;color:var(--mut);}
    .tk-nama{display:block;font-weight:700;color:var(--tegas);line-height:1.35;}
    .tk-nip{display:block;margin-top:2px;font-size:11.5px;color:var(--mut);line-height:1.4;}
    .tk-ket{line-height:1.5;color:var(--ink);}
    .tk-kosong{color:var(--mut);}

    /* "Data Keluarga": tombol pil yang membuka daftar anggota keluarga. */
    .tk-kel{margin-top:8px;}
    .tk-kel > summary{display:inline-flex;align-items:center;gap:7px;padding:5px 9px 5px 10px;list-style:none;cursor:pointer;
        user-select:none;font-size:12px;font-weight:600;color:var(--tegas);background:var(--surface-2);
        border:1px solid var(--line);border-radius:50px;transition:background .15s,border-color .15s;}
    .tk-kel > summary::-webkit-details-marker{display:none;}
    .tk-kel > summary:hover{background:var(--surface-3);border-color:var(--aksen);}
    .tk-kel > summary:focus-visible{outline:2px solid var(--aksen);outline-offset:2px;}
    .tk-kel > summary svg{width:14px;height:14px;flex:0 0 14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .tk-kel > summary .jml{min-width:18px;padding:1px 6px;border-radius:50px;background:var(--navy-l);color:var(--tegas);
        font-size:10.5px;font-weight:700;text-align:center;}
    .tk-kel > summary .panah{transition:transform .18s;}
    .tk-kel[open] > summary{background:var(--navy-l);border-color:var(--aksen);}
    .tk-kel[open] > summary .panah{transform:rotate(180deg);}

    .tk-kel-isi{margin-top:8px;border:1px solid var(--line);border-radius:10px;background:var(--surface);overflow:hidden;}
    .tk-kel-baris{display:grid;grid-template-columns:74px minmax(0,1fr);gap:4px 10px;align-items:start;padding:9px 11px;
        border-bottom:1px solid var(--line);}
    .tk-kel-baris:last-child{border-bottom:none;}
    .tk-kel-baris .peran{padding-top:2px;font-size:10.5px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--mut);}
    .tk-kel-baris .nm{display:block;font-size:12.5px;font-weight:700;color:var(--tegas);line-height:1.35;}
    .tk-kel-baris .tgl{display:block;margin-top:1px;font-size:11.5px;color:var(--mut);}
    .tk-kel-baris .cat{display:block;margin-top:4px;font-size:11.5px;color:var(--ink);line-height:1.4;}
    /* Tanda tunjangan di bawah nama, bukan di kolom ketiga: di kolom sendiri
       ia menjepit nama sampai terpotong per kata. */
    .tk-kel-baris .tanda{grid-column:2;display:flex;flex-wrap:wrap;gap:5px;margin-top:2px;}

    /* Lampiran sebagai keping berkas, satu per baris, nama panjang dipotong. */
    .tk-berkas{display:flex;align-items:center;gap:6px;max-width:100%;margin-bottom:5px;padding:5px 9px;font-size:12px;font-weight:600;
        color:var(--tegas);text-decoration:none;background:var(--surface-2);border:1px solid var(--line);border-radius:8px;}
    .tk-berkas:last-child{margin-bottom:0;}
    a.tk-berkas:hover{background:var(--surface-3);border-color:var(--aksen);}
    .tk-berkas svg{width:13px;height:13px;flex:0 0 13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .tk-berkas span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .tk-berkas.kunci{display:inline-flex;color:var(--mut);font-weight:500;}

    .tk-proses .fl{margin:0 0 4px;font-size:11.5px;}
    .tk-proses input{margin-top:8px;}
    .tk-taut{display:flex;align-items:flex-start;gap:6px;font-size:11.5px;line-height:1.4;color:var(--mut);}
    .tk-taut b{color:var(--tegas);}
    .tk-taut svg{width:13px;height:13px;flex:0 0 13px;margin-top:2px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .tk-proses select,.tk-proses input{width:100%;box-sizing:border-box;}
    .tk-proses .tombol{display:flex;gap:6px;margin-top:10px;}
    .tk-proses .tombol .btn{flex:1;padding:7px 10px;font-size:12.5px;text-align:center;}

    @media (max-width:720px){
        .tk-kel-baris{grid-template-columns:1fr;gap:4px;}
        .tk-kel-baris .tanda{grid-column:1;}
    }
</style>

<script>
document.getElementById('tk-mon-search').addEventListener('input', function (e) {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#tk-mon-body > tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
@endsection
