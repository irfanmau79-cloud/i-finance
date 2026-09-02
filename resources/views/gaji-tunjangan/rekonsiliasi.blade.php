@extends('layouts.app')

@section('activeNav', 'gt-rekon')
@section('title', 'Rekonsiliasi Gaji Induk')

@section('content')
@php
    $rp = fn ($nilai) => number_format((float) $nilai, 0, ',', '.');
    $periode = $namaBulan[$bulan].' '.$tahun;
@endphp

@include('gaji-tunjangan._styles')

<style>
    /* Selisih status ditandai supaya barisnya langsung terlihat saat menyisir
       ratusan pegawai; tanpa ini kolom rupiah harus dibaca satu per satu. */
    .rk-beda { background:var(--warn-bg) !important; }
    .rk-beda:hover { background:var(--warn-bg) !important; }
    .rk-status { display: inline-block; padding: 2px 9px; border-radius: 50px; font-size: 11.5px; font-weight: 700; }
    .rk-status.sama { background: var(--navy-l); color: var(--tegas); }
    .rk-status.beda { background:var(--err-bg); color:var(--err); }
    .rk-status.kosong { background:var(--surface-3); color: #94a3b8; }
    .rk-kunci { display: flex; flex-wrap: wrap; gap: 10px 22px; align-items: center;
        border: 1px solid var(--line); background:var(--surface-2); border-radius: 12px;
        padding: 13px 16px; margin: 6px 0 4px; font-size: 12.5px; }
    .rk-kunci b { color: var(--tegas); }
    .rk-ringkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px; margin: 12px 0 4px; }
    .rk-ringkas .k { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; background:var(--surface); }
    .rk-ringkas .k .l { font-size: 11px; font-weight: 700; letter-spacing: .3px;
        text-transform: uppercase; color: var(--mut); }
    .rk-ringkas .k .v { font-size: 18px; font-weight: 700; color: var(--tegas); margin-top: 3px; }
    .rk-ringkas .k.tekan .v { color:var(--err); }
    :root[data-tema="gelap"] .rk-ringkas .k,
    :root[data-tema="gelap"] .rk-kunci { background: var(--surface); border-color: var(--line); }
    :root[data-tema="gelap"] .rk-ringkas .k .v { color: var(--ink); }
    :root[data-tema="gelap"] .rk-beda { background: rgba(180, 83, 9, .18) !important; }
</style>

<div class="dash-card gt-card">
    <h3>Rekonsiliasi Gaji Induk</h3>
    <div class="sub">
        Membandingkan Status Tunjangan Keluarga yang dikunci di awal bulan dengan
        status yang tersirat dari nominal gaji bulan itu.
    </div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;margin:8px 0 4px;">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="gt-toolbar">
        <div class="gt-field">
            <label for="rk-bulan">Bulan</label>
            <select id="rk-bulan" name="bulan" class="gt-inp">
                @foreach ($namaBulan as $nomor => $nama)
                    <option value="{{ $nomor }}" @selected($nomor === $bulan)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="gt-field">
            <label for="rk-tahun">Tahun</label>
            <select id="rk-tahun" name="tahun" class="gt-inp">
                @foreach ($tahunTersedia as $t)
                    <option value="{{ $t }}" @selected($t === $tahun)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="gt-field gt-field-search">
            <label for="rk-cari">Cari</label>
            <div class="gt-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input id="rk-cari" class="gt-inp" type="text" name="q" value="{{ $cari }}"
                       placeholder="Nama / NIP&hellip;">
            </div>
        </div>
        <button class="gt-btn-tampil" type="submit">Tampilkan</button>
    </form>

    @if ($kunci === null)
        {{-- Periode belum dikunci: tidak ada yang bisa direkonsiliasi, karena
             status per awal bulan tidak dapat dihitung ulang belakangan. --}}
        <div class="rk-kunci">
            <span>
                Periode <b>{{ $periode }}</b> belum dikunci. Status Tunjangan Keluarga
                harus dipotret lebih dulu; tanggal penggajiannya
                <b>{{ $tanggalPenggajian->translatedFormat('l, d F Y') }}</b>.
                @if ($bolehKelola && $tanggalPenggajian->isPast())
                    {{-- Potret memakai data Tunjangan Keluarga SAAT TOMBOL
                         DITEKAN. Mengunci terlambat berarti perubahan yang
                         sudah terjadi ikut terpotret, sehingga selisihnya
                         bisa hilang. --}}
                    <br><b style="color:var(--err);">Perhatian:</b> tanggal itu sudah lewat
                    {{ $tanggalPenggajian->diffForHumans(['parts' => 1]) }}. Potret memakai data
                    Tunjangan Keluarga <b>saat ini</b>, jadi perubahan yang terjadi sejak
                    awal bulan sudah ikut terekam dan selisihnya bisa tidak terlihat lagi.
                @endif
            </span>
            @if ($bolehKelola)
                <form method="POST" action="{{ route('gaji-tunjangan.rekonsiliasi.kunci') }}" style="margin:0;"
                      onsubmit="return confirm('Kunci periode {{ $periode }}?\n\nStatus Tunjangan Keluarga seluruh pegawai aktif akan dipotret per {{ $tanggalPenggajian->format('d-m-Y') }} dan tidak berubah lagi walau datanya disunting nanti.')">
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                    <button class="gt-btn-tampil" type="submit">Kunci Periode</button>
                </form>
            @endif
        </div>

        @unless ($bolehKelola)
            <div class="gt-tabel-box">
                <div class="gt-tabel-wrap">
                    <div class="gt-empty">Hubungi superadmin untuk mengunci periode ini.</div>
                </div>
            </div>
        @endunless
    @else
        <div class="rk-kunci">
            <span>Dikunci per <b>{{ $kunci->tanggal_penggajian->translatedFormat('l, d F Y') }}</b></span>
            <span>oleh <b>{{ $kunci->dikunci_oleh_nama ?: '-' }}</b></span>
            <span>pada <b>{{ $kunci->dikunci_at->format('d-m-Y H:i') }}</b></span>
            @if ($bolehKelola)
                <form method="POST" action="{{ route('gaji-tunjangan.rekonsiliasi.hapus', $kunci) }}"
                      style="margin:0 0 0 auto;"
                      onsubmit="return confirm('Hapus kunci periode {{ $periode }} beserta seluruh log statusnya?\n\nSetelah dihapus, potret hanya bisa dibuat ulang memakai data Tunjangan Keluarga HARI INI - bukan kondisi awal bulan itu.\n\nTindakan ini tidak dapat dibatalkan.')">
                    @csrf @method('DELETE')
                    <button class="gtd-del" type="submit">Hapus Kunci</button>
                </form>
            @endif
        </div>

        <div class="rk-ringkas">
            <div class="k">
                <div class="l">Pegawai Dikunci</div>
                <div class="v">{{ $ringkasan['pegawai'] }}</div>
            </div>
            <div class="k">
                <div class="l">Status Berselisih</div>
                <div class="v">{{ $ringkasan['selisih'] }}</div>
            </div>
            <div class="k">
                <div class="l">Kelebihan Jiwa</div>
                <div class="v">{{ $ringkasan['jiwa'] }}</div>
            </div>
            <div class="k tekan">
                <div class="l">Potensi Kelebihan</div>
                <div class="v">Rp {{ $rp($ringkasan['kelebihan']) }}</div>
            </div>
        </div>

        <div class="gt-info">
            {{ $baris->total() }} pegawai &middot; {{ $periode }}@if ($cari !== '') &middot; pencarian "{{ $cari }}" @endif
        </div>

        <div class="gt-tabel-box">
            <div class="gt-tabel-wrap">
                @if ($baris->total() === 0)
                    <div class="gt-empty">
                        Tidak ada pegawai pada log periode <b>{{ $periode }}</b>{{ $cari !== '' ? ' dengan kata kunci tersebut' : '' }}.
                    </div>
                @else
                    <table class="gt-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th style="text-align:left;">Nama Pegawai</th>
                                <th>NIP</th>
                                <th>Status Tunjangan<br>Keluarga</th>
                                <th>Status<br>Penggajian</th>
                                <th>Potensi Kelebihan<br>Pembayaran</th>
                                @if ($bolehKelola)
                                    <th>Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $i => $r)
                                <tr class="{{ $r['selisih_jiwa'] > 0 ? 'rk-beda' : '' }}">
                                    <td class="gt-ctr">{{ $baris->firstItem() + $i }}</td>
                                    <td>
                                        <div class="gt-peg">
                                            <div class="n">{{ $r['nama'] }}</div>
                                            @if ($r['baris']->disunting_at)
                                                <div class="m" title="{{ $r['baris']->catatan_suntingan }}">
                                                    log disunting {{ $r['baris']->disunting_at->format('d-m-Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="gt-ctr">{{ $r['nip'] }}</td>
                                    <td class="gt-ctr">
                                        <span class="rk-status {{ $r['selisih_jiwa'] > 0 ? 'beda' : 'sama' }}">{{ $r['status_tk'] }}</span>
                                    </td>
                                    <td class="gt-ctr">
                                        @if (! $r['ada_gaji'])
                                            <span class="rk-status kosong" title="Tidak ada baris Gaji Induk untuk periode ini">&mdash;</span>
                                        @else
                                            <span class="rk-status {{ $r['selisih_jiwa'] > 0 ? 'beda' : 'sama' }}">{{ $r['status_penggajian'] }}</span>
                                        @endif
                                    </td>
                                    <td class="gt-num {{ $r['kelebihan'] > 0 ? 'gt-strong' : '' }}">
                                        {{ $r['kelebihan'] > 0 ? $rp($r['kelebihan']) : '0' }}
                                    </td>
                                    @if ($bolehKelola)
                                        <td class="gt-ctr">
                                            <button class="btn" type="button" style="padding:4px 12px;font-size:12px;"
                                                    onclick="rkSunting({{ $r['baris']->id }}, @js($r['nama']), @js($r['status_tk']))">Sunting</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @include('gaji-tunjangan._pager')
    @endif
</div>

@if ($bolehKelola && $kunci !== null)
    {{-- Penyuntingan log wajib berkas alasan; siapa & kapannya dicatat di
         baris itu sendiri dan di audit log. --}}
    <div id="rk-modal" hidden
         style="position:fixed;inset:0;background:rgba(15,39,64,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;">
        <div class="dash-card" style="width:420px;max-width:100%;">
            <h3>Sunting Log Status</h3>
            <div class="sub" id="rk-modal-nama"></div>

            <form method="POST" id="rk-modal-form">
                @csrf @method('PUT')

                <label class="fl" for="rk-status">Status Tunjangan Keluarga</label>
                <select id="rk-status" name="status_tk" required>
                    @foreach ($pilihanStatus as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>

                <label class="fl" for="rk-alasan">Alasan Penyuntingan</label>
                <textarea id="rk-alasan" name="catatan_suntingan" rows="3" required
                          placeholder="Contoh: SK kelahiran anak kedua terbit 3 Januari, terlambat diinput."></textarea>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button class="btn prim" type="submit">Simpan</button>
                    <button class="btn" type="button" onclick="rkTutup()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var RK_URL = @js(route('gaji-tunjangan.rekonsiliasi.sunting', ['baris' => '__ID__']));

        function rkSunting(id, nama, status) {
            var modal = document.getElementById('rk-modal');
            document.getElementById('rk-modal-form').action = RK_URL.replace('__ID__', id);
            document.getElementById('rk-modal-nama').textContent = nama;
            document.getElementById('rk-status').value = status;
            document.getElementById('rk-alasan').value = '';
            modal.hidden = false;
        }

        function rkTutup() {
            document.getElementById('rk-modal').hidden = true;
        }

        document.getElementById('rk-modal').addEventListener('click', function (e) {
            if (e.target === this) rkTutup();
        });
    </script>
@endif
@endsection
