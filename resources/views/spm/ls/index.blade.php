@extends('layouts.app')

@section('activeNav', 'spm-ls')
@section('title', 'Realisasi SP2D LS')

@section('content')
<div class="dash-card wf-card">
    <h3>Data Realisasi SP2D LS</h3>
    <div class="sub">
        SPM LS mengurangi pagu mata anggaran dan langsung masuk realisasi. Satu SPM bisa mencakup
        beberapa kode rekening &mdash; klik barisnya untuk membuka rincian mata anggarannya.
        SPM yang sudah <b>divalidasi</b> tidak bisa dihapus.
    </div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="tbl-tools">
        @if (boleh_ubah())
            <a href="{{ route('spm.ls.create') }}" class="btn prim" style="white-space:nowrap;">Tambah Realisasi SP2D LS</a>
        @endif
        <form method="GET" action="{{ route('spm.ls.index') }}" class="spm-cari">
            <input type="text" name="cari" placeholder="Cari nomor SPM, nomor SP2D, penerima, atau uraian&hellip;"
                   value="{{ request('cari') }}">
            <button type="submit" class="btn prim" style="white-space:nowrap;">Cari</button>
            @if (request()->hasAny(['cari']))
                <a href="{{ route('spm.ls.index') }}" class="btn" style="white-space:nowrap;">Reset</a>
            @endif
        </form>
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi tbl-fixed spm-tabel">
            {{-- Kolom Aksi 18%: empat ikon (Lihat, Validasi, Edit, Hapus)
                 beserta jaraknya butuh ~150px, dan pada 14% ikon terakhir
                 terpotong di tepi tabel. --}}
            <colgroup>
                <col style="width:10%;"><col style="width:19%;"><col style="width:10%;">
                <col style="width:13%;"><col style="width:17%;"><col style="width:13%;"><col style="width:18%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Tanggal SPM</th>
                    <th>Nomor SPM</th>
                    <th>Tanggal SP2D</th>
                    <th>Nomor SP2D</th>
                    <th>Penerima</th>
                    <th class="num">Nominal</th>
                    <th class="mid">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spms as $i => $spm)
                    @php($kunci = 'spm-ls-'.$spm->id)
                    @php($jumlahDetail = $spm->detail->count())
                    <tr class="spm-baris{{ $spm->divalidasi() ? ' spm-tervalidasi' : '' }}">
                        <td>{{ $spm->tanggal_dokumen->format('d-m-Y') }}</td>
                        <td>
                            {{-- Pemicu buka-tutup berupa <button> dengan caret
                                 SVG yang DIPUTAR lewat kelas CSS.

                                 Sebelumnya karetnya ditulis ulang tiap klik
                                 dengan menyusun teks: '&#9656; ' + textContent
                                 yang sudah dibersihkan regex /^[▶▼]\s*/. Regex
                                 itu tidak pernah cocok - yang tercetak ▸ (U+25B8)
                                 dan ▾ (U+25BE), sementara yang dicari ▶ (U+25B6)
                                 dan ▼ (U+25BC), dua aksara yang berbeda. Jadi
                                 tiap klik menambah satu panah baru di depan
                                 panah sebelumnya. Bentuk sekarang tidak menyusun
                                 teks sama sekali, jadi tidak ada yang bisa
                                 menumpuk. --}}
                            @if ($jumlahDetail > 1)
                                <button type="button" class="spm-tgl" data-spm-toggle="{{ $kunci }}" aria-expanded="false">
                                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                                    <span>
                                        <span class="spm-nomor">{{ $spm->nomor_dokumen }}</span>
                                        <span class="spm-jml">{{ $jumlahDetail }} mata anggaran</span>
                                    </span>
                                </button>
                            @else
                                <span class="spm-nomor">{{ $spm->nomor_dokumen }}</span>
                                @if ($jumlahDetail === 1)
                                    <span class="spm-sub">{{ $spm->detail->first()->masterAnggaran?->kode_rekening_bersih ?? '—' }}</span>
                                @endif
                            @endif
                            @if ($spm->divalidasi())
                                <span class="spm-lencana" title="Divalidasi {{ $spm->divalidasi_at->format('d-m-Y H:i') }} oleh {{ $spm->divalidasiOleh?->nama ?? '—' }}">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Tervalidasi
                                </span>
                            @endif
                        </td>
                        <td>{{ $spm->tanggal_sp2d?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $spm->nomor_sp2d ?? '—' }}</td>
                        <td>
                            {{ $spm->penerima ?? '—' }}
                            @if ($spm->bank_tujuan || $spm->nomor_rekening)
                                <span class="spm-sub">{{ trim(($spm->bank_tujuan ?? '').' · '.($spm->nomor_rekening ?? ''), ' ·') }}</span>
                            @endif
                        </td>
                        <td class="num">{{ fmt_rupiah($spm->totalNominal()) }}</td>
                        <td class="mid">
                            <div class="spm-aksi">
                                <a class="ic-btn" title="Lihat Detail" aria-label="Lihat detail SPM" href="{{ route('spm.ls.show', $spm) }}">
                                    <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>

                                @if (boleh_ubah())
                                    @unless ($spm->divalidasi())
                                        <form method="POST" action="{{ route('spm.validasi', $spm) }}">
                                            @csrf
                                            <button type="submit" class="ic-btn ok" title="Validasi" aria-label="Validasi SPM">
                                                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                            </button>
                                        </form>
                                    @endunless

                                    <a class="ic-btn" title="Edit" aria-label="Edit SPM" href="{{ route('spm.ls.edit', $spm) }}">
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                    </a>

                                    {{-- Hapus hilang begitu divalidasi. Tombolnya
                                         disembunyikan DAN rutenya dijaga di
                                         SpmController::destroy - yang pertama
                                         demi kejelasan, yang kedua demi
                                         keamanan. --}}
                                    @unless ($spm->divalidasi())
                                        <details class="spm-hapus">
                                            <summary class="ic-btn danger" title="Hapus" aria-label="Hapus SPM">
                                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </summary>
                                            <form method="POST" action="{{ route('spm.destroy', $spm) }}" class="spm-hapus-form">
                                                @csrf
                                                @method('DELETE')
                                                <div class="sub" style="margin:0 0 8px;">Hapus SPM <b>{{ $spm->nomor_dokumen }}</b> beserta seluruh baris mata anggarannya?</div>
                                                <button class="btn danger" type="submit">Ya, hapus</button>
                                            </form>
                                        </details>
                                    @endunless
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if ($jumlahDetail > 1)
                        <tr class="spm-rincian" data-spm-member="{{ $kunci }}" hidden>
                            <td colspan="7">
                                <table class="spm-rincian-tabel">
                                    <colgroup><col style="width:18%;"><col style="width:52%;"><col style="width:30%;"></colgroup>
                                    @foreach ($spm->detail as $baris)
                                        <tr>
                                            <td class="spm-kode">{{ $baris->masterAnggaran?->kode_rekening_bersih ?? '—' }}</td>
                                            <td>
                                                {{ $baris->masterAnggaran?->uraian_rekening ?? '—' }}
                                                <span class="spm-sub">{{ $baris->masterAnggaran?->subKegiatanNormal() ?? '—' }}</span>
                                            </td>
                                            <td class="num">{{ fmt_rupiah((float) $baris->nominal) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="spm-rincian-total">
                                        <td colspan="2">Jumlah</td>
                                        <td class="num">{{ fmt_rupiah($spm->totalNominal()) }}</td>
                                    </tr>
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
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-spm-toggle]').forEach(function (pemicu) {
        pemicu.addEventListener('click', function () {
            var kunci = pemicu.dataset.spmToggle;
            var buka = pemicu.getAttribute('aria-expanded') !== 'true';

            // Status buka disimpan HANYA di aria-expanded, dan tampilannya
            // (arah caret) mengikuti atribut itu lewat CSS. Tidak ada teks
            // yang disusun ulang, jadi tidak ada yang bisa menumpuk.
            pemicu.setAttribute('aria-expanded', buka ? 'true' : 'false');

            document.querySelectorAll('[data-spm-member="' + kunci + '"]').forEach(function (baris) {
                baris.hidden = ! buka;
            });
        });
    });
});
</script>
@endsection
