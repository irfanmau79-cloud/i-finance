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
            {{-- Cukup ketik awalan nomornya: saran muncul sendiri setelah
                 {{ $minCari }} karakter, jadi nomor yang mirip-mirip tidak perlu
                 dihafal utuh. --}}
            <div class="nsearch" data-sp-cari>
                <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="ns-inp" id="nomor_sp" name="nomor_sp" value="{{ $nomorSp }}"
                       placeholder="Ketik {{ $minCari }} angka awal, mis. 87" autocomplete="off" autofocus
                       role="combobox" aria-expanded="false" aria-autocomplete="list">
                <div class="ns-drop" data-sp-drop role="listbox"></div>
            </div>
        </div>
        <button type="submit" class="btn prim">Cari</button>
    </form>

    <div style="margin-top:18px;">
        @if ($hasil === null)
            <div class="sub">Dokumen hanya bisa diunduh setelah Nota Pencairan Dana terkait berstatus <strong>Selesai</strong>.</div>
        @elseif (! $hasil['ok'] && $hasil['kode'] === \App\Http\Controllers\CetakSpjPerjalananController::KODE_BANYAK)
            {{-- Beberapa SP berawalan sama: dipilih dulu yang mana, baru
                 dokumennya ditampilkan. --}}
            <div class="sumbar" style="background:var(--navy-l);color:var(--tegas);margin-bottom:12px;">
                <span>{{ $hasil['pesan'] }}</span>
            </div>
            <div class="spj-pilihan">
                @foreach ($hasil['pilihan'] as $sp)
                    <a class="spj-pilihan-item" href="{{ route('cetak-spj.index', ['nomor_sp' => $sp->nomor_sp]) }}">
                        <span class="nomor">{{ $sp->nomor_sp }}</span>
                        <span class="ket">{{ trim(collect([$sp->tanggal_sp?->format('d-m-Y'), $sp->unit_kerja, $sp->lokasi])->filter()->implode(' · ')) }}</span>
                    </a>
                @endforeach
            </div>
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

<script>
(function () {
    var wrap = document.querySelector('[data-sp-cari]');
    if (! wrap) return;

    var inp = wrap.querySelector('#nomor_sp');
    var drop = wrap.querySelector('[data-sp-drop]');
    var MIN = @json($minCari);
    var URL_SARAN = @json(route('cetak-spj.saran'));
    var tunda, permintaanKe = 0;

    function esc(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : t;
        return d.innerHTML;
    }

    function tutup() {
        drop.classList.remove('show');
        inp.setAttribute('aria-expanded', 'false');
    }

    function gambar(daftar) {
        if (! daftar.length) { tutup(); return; }
        drop.innerHTML = daftar.map(function (sp) {
            return '<div class="ns-item" role="option" data-nomor="' + esc(sp.nomor_sp) + '">'
                + '<div><div>' + esc(sp.nomor_sp) + '</div>'
                + (sp.keterangan ? '<div class="sub" style="margin:2px 0 0;">' + esc(sp.keterangan) + '</div>' : '')
                + '</div></div>';
        }).join('');
        drop.classList.add('show');
        inp.setAttribute('aria-expanded', 'true');
    }

    function cari() {
        var q = inp.value.trim();
        if (q.length < MIN) { tutup(); return; }

        // Jawaban yang datang terlambat diabaikan supaya hasil ketikan lama
        // tidak menimpa hasil ketikan terbaru.
        var nomorPermintaan = ++permintaanKe;

        fetch(URL_SARAN + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (daftar) { if (nomorPermintaan === permintaanKe) gambar(daftar); })
            .catch(function () { tutup(); });
    }

    inp.addEventListener('input', function () {
        clearTimeout(tunda);
        tunda = setTimeout(cari, 180);
    });
    inp.addEventListener('focus', cari);

    drop.addEventListener('mousedown', function (e) {
        var item = e.target.closest('.ns-item[data-nomor]');
        if (! item) return;
        e.preventDefault();
        inp.value = item.dataset.nomor;
        tutup();
        inp.form.submit();
    });

    document.addEventListener('click', function (e) {
        if (! wrap.contains(e.target)) tutup();
    });
    inp.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tutup();
    });
})();
</script>

@endsection
