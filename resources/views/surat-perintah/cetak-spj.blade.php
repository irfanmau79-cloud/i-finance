@extends((auth()->check() || \App\Helpers\GuestSession::isActive()) ? 'layouts.app' : 'layouts.standalone')

@section('activeNav', 'sp-cetakspj')
@section('title', 'Cetak SPJ Perjalanan Dinas')

@section('content')
<div class="dash-card">
    <h3>Cetak SPJ Perjalanan Dinas</h3>
    <div class="sub">Masukkan Nomor Surat Perintah untuk mengunduh Daftar Penerimaan dan SPD Rampung.</div>

    <form method="GET" action="{{ route('cetak-spj.index') }}"
          style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:16px;max-width:640px;">
        <div class="fg" style="flex:1;min-width:240px;">
            <label class="fl" for="nomor_sp">Nomor Surat Perintah</label>
            <input type="text" id="nomor_sp" name="nomor_sp" value="{{ $nomorSp }}"
                   placeholder="Contoh: 87/PW.02.01/Sekre" autocomplete="off" autofocus>
        </div>
        <button type="submit" class="btn prim">Cari</button>
    </form>

    <div style="margin-top:18px;">
        @if ($hasil === null)
            <div class="sub">Dokumen hanya bisa diunduh setelah Nota Pencairan Dana terkait berstatus <strong>Selesai</strong>.</div>
        @elseif (! $hasil['ok'])
            <div class="err-box" style="display:block;">{{ $hasil['pesan'] }}</div>
        @else
            <div class="sumbar ok" style="margin-bottom:14px;">
                <span>Ditemukan {{ count($hasil['daftar']) }} Nota Pencairan Dana selesai untuk SP {{ $hasil['nomor_sp'] }}.</span>
            </div>

            @foreach ($hasil['daftar'] as $item)
                @php($npd = $item['npd'])
                <div class="dash-card" style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                        <div>
                            <h3 style="margin:0;">{{ $npd->nomor_lengkap ?: 'NPD #'.$npd->id }}</h3>
                            <div class="sub" style="margin-top:2px;">
                                {{ $item['jenis'] }} &middot; {{ $npd->tanggal_npd?->format('d-m-Y') }}
                                &middot; Rp {{ fmt_rupiah((float) $npd->nominal) }}
                            </div>
                        </div>
                        <div class="nav" style="gap:8px;">
                            <a class="btn" href="{{ route('cetak-spj.daftar', $npd) }}" target="_blank" rel="noopener">Daftar Penerimaan</a>
                            <a class="btn prim" href="{{ route('cetak-spj.spd', $npd) }}" target="_blank" rel="noopener">SPD Rampung</a>
                        </div>
                    </div>

                    @if ($item['anggota'] !== [])
                        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;margin-top:12px;">
                            <table class="realisasi">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">No</th>
                                        <th>Nama</th>
                                        <th>Jabatan</th>
                                        <th>NIP</th>
                                        <th class="num">Diterima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item['anggota'] as $i => $anggota)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $anggota['nama'] }}</td>
                                            <td>{{ $anggota['jabatan'] ?: '—' }}</td>
                                            <td>{{ $anggota['nip'] ?: '—' }}</td>
                                            <td class="num">Rp {{ fmt_rupiah($anggota['nominal']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
