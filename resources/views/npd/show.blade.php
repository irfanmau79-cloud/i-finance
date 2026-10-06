@extends('layouts.app')

@section('activeNav', $activeNav)
@section('title', 'Detail Nota Pencairan Dana')

@section('content')
@if($npd->sumber_data === 'import_historis')
<div class="err-box" style="display:block;background:var(--info-bg);color:var(--info);border-color:var(--info-bg);">
    Dokumen historis dari batch #{{ $npd->import_historis_id }}, baris sumber {{ $npd->import_baris }}. Detail khusus perjalanan/peserta/narasumber/barang tidak dibuat secara fiktif; Lampiran memakai snapshot penerima, rekening, bruto, dan pajak hasil import.
</div>
@endif
<div class="dash-card wf-card">
    <h3>Detail Nota Pencairan Dana &mdash; {{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }}</h3>
    <div class="sub">{{ $npd->nomor_lengkap ?? 'Belum bernomor (masih Draft)' }}</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    @if ($peringatanPelimpahan)
        <div class="sumbar" style="background:var(--warn-bg);color:var(--warn);">
            <span>
                {{ $peringatanPelimpahan }} KPA/BPP/PPTK pada dokumen cetak menggunakan sumber cadangan.
                @if (auth()->user()->role === \App\Models\User::ROLE_SUPERADMIN)
                    <a href="{{ route('pelimpahan.index') }}" style="color:inherit;font-weight:700;">Atur di menu Pelimpahan</a>.
                @endif
            </span>
        </div>
    @endif

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Gagal memproses aksi:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $bisaKembaliBpp = in_array('kembali_bpp', $aksiTersedia, true);
        $adaCoretan = $npd->coretanJsonTerbaru() !== null;
    @endphp

    @if ($npd->status === 'Verifikasi - Verifikator' && $verifikatorNpd === null)
        <div class="sumbar" style="background:var(--warn-bg);color:var(--warn-teks);margin-bottom:14px;">
            <span>
                Verifikator untuk Sub Kegiatan NPD ini belum ditetapkan, jadi NPD ini <b>belum bisa diverifikasi</b>.
                @if (auth()->user()->isSuperadmin())
                    <a href="{{ route('pelimpahan.index', ['status' => 'tanpa_verifikator']) }}" style="color:inherit;font-weight:700;">Tetapkan di menu Pelimpahan</a>.
                @else
                    Minta superadmin menetapkannya di menu Pelimpahan.
                @endif
            </span>
        </div>
    @endif

    @if ($bisaKembaliBpp)
        <div class="sumbar" style="background:var(--warn-bg);color:var(--warn);margin-bottom:14px;">
            <span>NPD ini menunggu verifikasi Anda. Buka <b>Verifikasi NPD</b> untuk memeriksa dokumennya, lalu memverifikasi, mengembalikan ke BPP (bisa dengan coretan pada dokumen), atau mengedit NPD-nya langsung.</span>
        </div>
    @elseif ($adaCoretan)
        <div class="sumbar" style="background:var(--info-bg);color:var(--info);margin-bottom:14px;">
            <span>Dokumen PDF NPD ini memuat coretan dari Verifikator &mdash; buka lewat tombol di baris <b>Cetak Draft NPD</b> di bawah (lihat Histori Status untuk catatan revisinya). Versi final tetap bersih tanpa coretan.</span>
        </div>
    @endif

    {{-- Ringkasan: yang paling sering dicari - berapa, statusnya apa, kapan,
         dari mata anggaran mana - terbaca tanpa menyisir daftar di bawahnya. --}}
    <div class="npd-ring">
        <div class="it utama">
            <div class="k">Nominal NPD</div>
            <div class="v">Rp {{ number_format((float) $npd->nominal, 2, ',', '.') }}</div>
            <div class="t">{{ $npd->terbilang }}</div>
        </div>
        <div class="it">
            <div class="k">Status</div>
            <div class="v"><span class="badge {{ \App\Models\Npd::STATUS_BADGE_CLASS[$npd->status] ?? 'st-diterima' }}">{{ $npd->status }}</span></div>
        </div>
        <div class="it">
            <div class="k">Tanggal NPD</div>
            <div class="v">{{ $npd->tanggal_npd->format('d-m-Y') }}</div>
        </div>
        <div class="it">
            <div class="k">Tagging</div>
            <div class="v">{{ $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging->nama ?? '-') }}</div>
        </div>
    </div>

    <div class="rev npd-grid">
        <div class="grp">
            <div class="gt">Informasi Umum</div>
            <div class="li"><span class="k">Bulan / Tahun</span><span class="v">{{ $npd->bulan }} / {{ $npd->tahun }}</span></div>
            <div class="li"><span class="k">KEU</span><span class="v">{{ $npd->keu }}</span></div>
            @if ($npd->jenis_panjar)
                <div class="li"><span class="k">Jenis NPD</span><span class="v">{{ $npd->jenis_panjar }}</span></div>
            @endif
            @if ($npd->jenis === 'kd')
                <div class="li"><span class="k">Mode</span><span class="v">{{ $npd->mode_kd === 'perjalanan' ? 'Perjalanan Dinas' : 'Kontribusi' }}</span></div>
                <div class="li"><span class="k">Nama Pelatihan</span><span class="v">{{ $npd->detail_json['nama_pelatihan'] ?? '—' }}</span></div>
                @if ($npd->referensi)
                    <div class="li"><span class="k">Referensi NPD Kontribusi</span><span class="v"><a href="{{ route('npd.show', $npd->referensi) }}">{{ $npd->referensi->nomor_lengkap ?? '#'.$npd->referensi->id }}</a></span></div>
                @endif
                @if ($npd->turunanPerjalanan->isNotEmpty())
                    <div class="li"><span class="k">NPD Perjalanan Terkait</span><span class="v">
                        @foreach ($npd->turunanPerjalanan as $turunan)
                            <a href="{{ route('npd.show', $turunan) }}">{{ $turunan->nomor_lengkap ?? '#'.$turunan->id }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </span></div>
                @endif
            @endif
            @if ($npd->jenis === 'tr' && $npd->induk)
                <div class="li"><span class="k">Induk NPD Perjalanan Dinas</span><span class="v"><a href="{{ route('npd.show', $npd->induk) }}">{{ $npd->induk->nomor_lengkap ?? '#'.$npd->induk->id }}</a></span></div>
            @endif
            @if ($npd->jenis === 'pd' && $npd->turunanTransport->isNotEmpty())
                <div class="li"><span class="k">NPD Transport Terkait</span><span class="v">
                    @foreach ($npd->turunanTransport as $turunan)
                        <a href="{{ route('npd.show', $turunan) }}">{{ $turunan->nomor_lengkap ?? '#'.$turunan->id }}</a> ({{ $turunan->status }})@if (! $loop->last), @endif
                    @endforeach
                </span></div>
            @endif
            <div class="li"><span class="k">Dibuat oleh</span><span class="v">{{ $npd->dibuatOleh->nama ?? '—' }}</span></div>
            {{-- Verifikator = yang ditetapkan untuk Sub Kegiatannya sekarang;
                 Diverifikasi oleh = yang tercatat di histori, tidak ikut
                 berubah bila penugasannya dipindah belakangan. --}}
            <div class="li">
                <span class="k">Verifikator</span>
                <span class="v">
                    @if ($verifikatorNpd)
                        {{ $verifikatorNpd->nama }}
                    @else
                        <span style="color:var(--err-teks);font-style:italic;font-weight:600;">Belum ditetapkan</span>
                    @endif
                </span>
            </div>
            @if ($diverifikasiOleh)
                <div class="li"><span class="k">Diverifikasi oleh</span><span class="v">{{ $diverifikasiOleh->nama }}</span></div>
            @endif
        </div>

        <div class="grp">
            <div class="gt">Sumber Dana</div>
            <div class="li"><span class="k">Program</span><span class="v">{{ $npd->masterAnggaran->program }}</span></div>
            <div class="li"><span class="k">Kegiatan</span><span class="v">{{ $npd->masterAnggaran->kegiatan }}</span></div>
            <div class="li"><span class="k">Sub Kegiatan</span><span class="v">{{ $npd->masterAnggaran->sub_kegiatan_lengkap }}</span></div>
            <div class="li"><span class="k">Kode Rekening</span><span class="v">{{ $npd->masterAnggaran->rekening_lengkap }}</span></div>
            <div class="li"><span class="k">Tagging</span><span class="v">{{ $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging->nama ?? '-') }}</span></div>
            <div class="li"><span class="k">Pagu</span><span class="v">Rp {{ number_format((float) $npd->masterAnggaran->pagu, 2, ',', '.') }}</span></div>
        </div>

        {{-- Nominal & terbilang sudah ada di ringkasan atas, jadi kotak ini
             hanya muncul bila ada yang perlu ditambahkan: verifikator harus
             tahu PDF-nya memakai angka ketikan, bukan angka sistem. --}}
        @if ($npd->sisa_anggaran_manual !== null)
        <div class="grp">
            <div class="gt">Nominal</div>
            <div class="li">
                <span class="k">Sisa Anggaran di PDF</span>
                <span class="v">Rp {{ number_format((float) $npd->sisa_anggaran_manual, 2, ',', '.') }} <span class="sub">(diketik manual)</span></span>
            </div>
        </div>
        @endif

        @if ($npd->catatan)
        <div class="grp">
            <div class="gt">Catatan</div>
            <div class="li"><span class="v">{{ $npd->catatan }}</span></div>
        </div>
        @endif
    </div>

    @if (in_array($npd->jenis, ['pd', 'tr'], true))
        @php
            // Dihitung sekali di sini: angkanya dipakai baris DAN baris Total.
            $timHitung = $npd->tim->map(fn ($t) => ['t' => $t, 'h' => $t->hitung()]);
            $timTotal = [
                'uh' => $timHitung->sum(fn ($x) => $x['h']['jml_harian'] + $x['h']['jml_akom']),
                'transport' => $timHitung->sum(fn ($x) => $x['h']['jml_transport']),
                'representatif' => $timHitung->sum(fn ($x) => $x['h']['representatif']),
                'jumlah' => $timHitung->sum(fn ($x) => $x['h']['jumlah']),
            ];
        @endphp
        <div class="npd-sek"><h3>Anggota Tim</h3><span class="jml">{{ $timHitung->count() }} orang</span></div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                <thead>
                    <tr>
                        <th class="mid">No</th>
                        <th>Nama / Jabatan</th>
                        <th>Paket Tujuan</th>
                        <th class="num">UH + Akomodasi</th>
                        <th class="num">Transport</th>
                        <th class="num">Representatif</th>
                        <th class="num">Jumlah</th>
                        <th class="mid">Penerima</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($timHitung as $baris)
                        @php
                            $t = $baris['t'];
                            $h = $baris['h'];
                            $uh = $h['jml_harian'] + $h['jml_akom'];
                        @endphp
                        <tr>
                            <td class="no">{{ $loop->iteration }}</td>
                            <td>
                                <span class="nm">{{ $t->nama }}</span>
                                <span class="nm-sub">{{ $t->jabatan ?? '—' }}</span>
                            </td>
                            <td>
                                @forelse ($t->paket as $p)
                                    <div class="npd-paket">
                                        <span class="cl">{{ $p->cluster }}</span>
                                        <span class="wl">{{ $p->wilayah }}</span>
                                        <span class="lm">{{ $p->lama_hari }} hari &middot; {{ $p->malam }} malam</span>
                                    </div>
                                @empty
                                    <span style="color:var(--mut);">—</span>
                                @endforelse
                            </td>
                            <td class="num @if ($uh == 0) nol @endif">Rp {{ number_format($uh, 2, ',', '.') }}</td>
                            <td class="num @if ($h['jml_transport'] == 0) nol @endif">Rp {{ number_format($h['jml_transport'], 2, ',', '.') }}</td>
                            <td class="num @if ($h['representatif'] == 0) nol @endif">Rp {{ number_format($h['representatif'], 2, ',', '.') }}</td>
                            <td class="num jml">Rp {{ number_format($h['jumlah'], 2, ',', '.') }}</td>
                            <td class="mid">
                                @if ($t->is_penerima)
                                    <span class="badge st-aktif">Penerima</span>
                                @else
                                    <span style="color:var(--mut);">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kosong">Belum ada anggota tim.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($timHitung->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="num">Rp {{ number_format($timTotal['uh'], 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($timTotal['transport'], 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($timTotal['representatif'], 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($timTotal['jumlah'], 2, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @elseif ($npd->jenis === 'ns')
        <div class="npd-sek"><h3>Daftar Narasumber</h3></div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>JP</th>
                        <th class="num">Tarif/JP</th>
                        <th class="num">Honor</th>
                        <th class="num">Transport</th>
                        <th class="num">Bruto</th>
                        <th class="num">PPh 21</th>
                        <th class="num">Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($npd->narasumber as $n)
                        <tr>
                            <td><span class="nm">{{ $n->nama }}</span></td>
                            <td>{{ $n->jabatan ?? '—' }}</td>
                            <td>{{ $n->jumlah_jp }}</td>
                            <td class="num">Rp {{ number_format((float) $n->tarif_jp, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($n->honor, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format((float) $n->transport, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($n->bruto, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format((float) $n->pph21, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($n->netto, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="kosong">Belum ada narasumber.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($npd->jenis === 'kd')
        <div class="npd-sek"><h3>Daftar Peserta</h3></div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                @if ($npd->mode_kd === 'perjalanan')
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pangkat</th>
                            <th>Hari UH</th>
                            <th class="num">Jumlah Harian</th>
                            <th class="num">Akomodasi</th>
                            <th class="num">Uang Saku</th>
                            <th class="num">Transport</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($npd->peserta as $p)
                            <tr>
                                <td><span class="nm">{{ $p->nama }}</span></td>
                                <td>{{ $p->pangkat ?? '—' }}</td>
                                <td>{{ $p->hari_uh }}</td>
                                <td class="num">Rp {{ number_format($p->jumlah_harian, 2, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($p->jumlah_akomodasi, 2, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($p->jumlah_saku, 2, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format((float) $p->transport, 2, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($p->sub_perjalanan, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="kosong">Belum ada peserta.</td></tr>
                        @endforelse
                    </tbody>
                @else
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pangkat</th>
                            <th>Volume Kontribusi</th>
                            <th class="num">Jumlah Kontribusi</th>
                            <th>Volume MOOC</th>
                            <th class="num">Jumlah MOOC</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($npd->peserta as $p)
                            <tr>
                                <td><span class="nm">{{ $p->nama }}</span></td>
                                <td>{{ $p->pangkat ?? '—' }}</td>
                                <td>{{ $p->volume_kontribusi }}</td>
                                <td class="num">Rp {{ number_format($p->jumlah_kontribusi, 2, ',', '.') }}</td>
                                <td>{{ $p->volume_mooc }}</td>
                                <td class="num">Rp {{ number_format($p->jumlah_mooc, 2, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($p->sub_kontribusi, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="kosong">Belum ada peserta.</td></tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
    @else
        <div class="npd-sek"><h3>Daftar Penerima</h3></div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                <thead>
                    {{-- Kolom angka selebar isinya; sisa lebarnya dibagi tiga
                         kolom teks 20 : 10 : 70. Dulu Keterangan memakan
                         seluruh sisa itu sehingga Nama dan Rekening terjepit. --}}
                    <tr>
                        <th style="width:20%;">Nama</th>
                        <th style="width:10%;">Rekening</th>
                        <th class="num">Bruto</th>
                        <th class="num">PPN</th>
                        <th>PPh</th>
                        <th class="num">Biaya KU/RTGS</th>
                        <th class="num">Netto</th>
                        <th style="width:70%;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($npd->penerima as $p)
                        <tr>
                            <td><span class="nm">{{ $p->nama }}</span></td>
                            <td>{{ $p->rekening ?? '—' }}</td>
                            <td class="num">Rp {{ number_format((float) $p->bruto, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format((float) $p->ppn, 2, ',', '.') }}</td>
                            <td style="white-space:nowrap;">
                                @forelse ($p->pphList as $pph)
                                    {{ $pph->jenis }}: Rp {{ number_format((float) $pph->nilai, 2, ',', '.') }}<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="num">Rp {{ number_format((float) $p->biaya_ku_rtgs, 2, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($p->netto, 2, ',', '.') }}</td>
                            <td>{{ $p->keterangan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kosong">Belum ada penerima.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Meja Verifikator: satu pintu saja. Verifikasi, Kembalikan ke BPP,
         dan Edit NPD ada di halaman Verifikasi NPD. --}}
    @if ($bisaKembaliBpp)
        <div style="margin-top:10px;">
            <a class="btn gabung" href="{{ route('npd.coret', $npd) }}">Verifikasi NPD</a>
        </div>
    @elseif ($bisaEdit)
        <div style="margin-top:10px;">
            <a class="btn prim" href="{{ route($npd->ruteEdit(), $npd) }}">Edit NPD</a>
        </div>
        @if ($npd->status !== 'Draft NPD - PPTK')
            <div class="sub" style="margin-top:7px;">
                Setiap bagian yang Anda ubah lewat <b>Edit NPD</b> dicatat di Histori Perubahan Data, dan tercoret otomatis pada <b>Cetak Draft NPD</b>.
            </div>
        @endif
    @endif

    @include('npd._spj-berkas', ['npd' => $npd, 'bolehKelola' => $bolehKelolaArsip])

    <div class="npd-sek"><h3>Lokasi Arsip SPJ</h3></div>
    <div class="dash-card" style="box-shadow:none;border:1px solid var(--line);margin-top:10px;">
        @if ($bolehKelolaArsip && $npd->status === 'Selesai')
        <form method="POST" action="{{ route('npd.arsip-spj.store', $npd) }}" class="row" style="align-items:end;margin-bottom:16px;">
            @csrf
            <div><label class="fl">Jenis Dokumen</label><select name="jenis_dokumen" required>@foreach(\App\Services\InventarisasiSpjService::JENIS_DOKUMEN as $jenis)<option>{{ $jenis }}</option>@endforeach</select></div>
            <div><label class="fl">Lokasi Bantex/Box</label><select name="lokasi" required data-cari><option value="">— Pilih Bantex/Box —</option>@foreach($bantexList as $bantex)<option value="{{ $bantex->nama }}">{{ $bantex->nama }}{{ $bantex->keterangan ? ' — '.$bantex->keterangan : '' }}</option>@endforeach</select></div>
            <div><label class="fl">Catatan</label><input name="catatan" maxlength="1000" placeholder="Opsional"></div>
            <div><button class="btn prim" type="submit">Tetapkan / Pindahkan</button></div>
        </form>
        @elseif($npd->status !== 'Selesai')
            <div class="sub" style="margin-bottom:12px;">Lokasi arsip dapat ditetapkan setelah NPD berstatus Selesai.</div>
        @endif
        <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>Waktu</th><th>Jenis Dokumen</th><th>Lokasi</th><th>Status</th><th>Petugas</th><th>Catatan</th></tr></thead><tbody>
        @forelse($npd->arsipSpj as $arsip)<tr><td>{{ $arsip->ditetapkan_at->format('d-m-Y H:i') }}</td><td>{{ $arsip->jenis_dokumen }}</td><td>{{ $arsip->lokasi }}</td><td><span class="badge {{ $arsip->aktif ? 'st-aktif' : 'st-selesai' }}">{{ $arsip->aktif ? 'AKTIF' : 'HISTORI' }}</span></td><td>{{ $arsip->ditetapkanOleh?->nama ?? '-' }}</td><td>{{ $arsip->catatan ?? '-' }}</td></tr>
        @empty<tr><td colspan="6" style="text-align:center;color:var(--mut);padding:18px;">Belum ada lokasi arsip.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    @if ($npd->historiStatus->isNotEmpty())
        <div class="npd-sek"><h3>Histori Status</h3><span class="jml">{{ $npd->historiStatus->count() }} langkah</span></div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                <thead><tr><th class="mid">#</th><th>Waktu</th><th>Aksi</th><th>Perubahan Status</th><th>Pengguna</th><th>Catatan</th></tr></thead>
                <tbody>
                    @foreach ($npd->historiStatus as $histori)
                        <tr>
                            <td class="mid"><span class="hs-no">{{ $histori->nomor_urut }}</span></td>
                            <td class="hs-wkt"><b>{{ $histori->created_at->format('d-m-Y') }}</b><span>{{ $histori->created_at->format('H:i') }}</span></td>
                            <td><span class="hs-aksi">{{ str($histori->aksi)->replace('_', ' ')->title() }}</span></td>
                            {{-- Status akhir yang ditebalkan; status asal dan
                                 kata "menjadi" dibiarkan biasa. --}}
                            <td class="hs-ubah">
                                <span class="asal">{{ $histori->status_asal ?? 'Awal' }}</span>
                                <span class="kata">menjadi</span>
                                <span class="tuju">{{ $histori->status_tujuan }}</span>
                            </td>
                            <td>{{ $histori->user->nama ?? 'Sistem' }}</td>
                            <td>
                                {{ $histori->catatan ?? '—' }}
                                @if ($histori->coretan_json)
                                    <span class="stat-cat-chip" style="margin-left:6px;"><svg viewBox="0 0 24 24"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>Ada Coretan</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($npd->revisi->isNotEmpty())
        <div class="npd-sek"><h3>Histori Perubahan Data</h3><span class="jml">{{ $npd->revisi->sum(fn ($r) => count($r->perubahan ?? [])) }} perubahan</span></div>
        <div class="sub" style="margin-top:7px;">Bagian yang diubah BPP/Verifikator lewat Edit NPD setelah draft diserahkan PPTK.</div>
        <div class="tbl-npd-wrap">
            <table class="tbl-npd">
                <thead><tr><th>Waktu</th><th>Diubah oleh</th><th>Bagian</th><th>Semula</th><th>Menjadi</th></tr></thead>
                <tbody>
                    @foreach ($npd->revisi as $revisi)
                        @foreach ($revisi->perubahan ?? [] as $ubah)
                            <tr>
                                @if ($loop->first)
                                    <td class="hs-wkt" rowspan="{{ count($revisi->perubahan) }}" style="vertical-align:top;"><b>{{ $revisi->created_at->format('d-m-Y') }}</b><span>{{ $revisi->created_at->format('H:i') }}</span></td>
                                    <td rowspan="{{ count($revisi->perubahan) }}" style="vertical-align:top;">
                                        <span class="nm">{{ $revisi->user->nama ?? 'Pengguna dihapus' }}</span>
                                        <span class="nm-sub">{{ config('akses.role_label')[$revisi->peran] ?? $revisi->peran }}</span>
                                    </td>
                                @endif
                                <td>{{ $ubah['bagian'] }}</td>
                                <td style="text-decoration:line-through;color:var(--mut);overflow-wrap:anywhere;">{{ $ubah['lama'] }}</td>
                                <td style="overflow-wrap:anywhere;"><b>{{ $ubah['baru'] }}</b></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="npd-sek" style="margin-bottom:10px;"><h3>Dokumen &amp; Cetak</h3></div>
    @php
        // Draft awal PPTK baru terpisah dari dokumen terkini setelah ada
        // suntingan BPP/Verifikator. Sebelum itu keduanya dokumen yang sama,
        // jadi cukup satu baris tombol.
        $sudahVerifikasi = filled($npd->nomor_lengkap);
        $labelTerkini = $sudahVerifikasi ? 'Cetak NPD Terverifikasi' : 'Cetak NPD Hasil Edit';
        // Coretan tangan Verifikator juga hanya tercetak di versi draft,
        // jadi versi itu ikut ditawarkan begitu ada coretan tersimpan.
        $versiCetak = ($npd->revisi->isNotEmpty() || $adaCoretan)
            ? [
                ['judul' => 'Cetak Draft NPD', 'ket' => 'Draft awal buatan PPTK beserta coretan: bagian yang diubah BPP/Verifikator tercoret otomatis dengan penggantinya tertulis merah, ditambah coretan tangan Verifikator bila ada.', 'q' => ['versi' => 'draft']],
                ['judul' => $labelTerkini, 'ket' => $sudahVerifikasi ? 'Dokumen bersih tanpa coretan, lengkap dengan nomor NPD - siap dicetak.' : 'Dokumen bersih tanpa coretan dengan isi terkini. NPD ini belum diverifikasi, jadi belum bernomor.', 'q' => []],
            ]
            : [
                ['judul' => $sudahVerifikasi ? 'Cetak NPD Terverifikasi' : 'Cetak Draft NPD', 'ket' => null, 'q' => []],
            ];
    @endphp
    @foreach ($versiCetak as $versi)
        @php($q = ['npd' => $npd] + $versi['q'])
        <div style="margin-top:{{ $loop->first ? '0' : '14px' }};">
            <div style="font-weight:700;color:var(--tegas);">{{ $versi['judul'] }}</div>
            @if ($versi['ket'])
                <div class="sub" style="margin:2px 0 8px;">{{ $versi['ket'] }}</div>
            @else
                <div style="height:8px;"></div>
            @endif
            <div class="cetak-bar">
                @if (in_array($npd->jenis, ['pd', 'tr'], true))
                    <a class="btn prim" href="{{ route('npd.cetak-daftar', $q) }}" target="_blank">Cetak Daftar Pembayaran</a>
                    <a class="btn prim" href="{{ route('npd.cetak-spd', $q) }}" target="_blank">Cetak SPD Rampung</a>
                @elseif ($npd->jenis === 'ns')
                    <a class="btn prim" href="{{ route('npd.cetak-daftar-nara', $q) }}" target="_blank">Cetak Daftar Pembayaran</a>
                @elseif ($npd->jenis === 'kd')
                    <a class="btn prim" href="{{ route('npd.cetak-daftar-kd', $q) }}" target="_blank">Cetak Daftar Bayar</a>
                @endif
                <a class="btn prim" href="{{ route('npd.cetak-npd', $q) }}" target="_blank">Cetak NPD</a>
                <a class="btn prim" href="{{ route('npd.cetak-lampiran', $q) }}" target="_blank">Cetak Lampiran</a>

                {{-- Cetak gabungan: sengaja dipisah garis dan berwarna lain karena
                     hasilnya bukan satu dokumen seperti tombol di kirinya, melainkan
                     semuanya sekaligus dalam satu berkas. --}}
                <span class="cetak-pisah" aria-hidden="true"></span>
                <a class="btn gabung" href="{{ route('npd.cetak-gabungan', $q) }}" target="_blank"
                    title="{{ $urutanGabungan }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2 3 7l9 5 9-5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>
                    </svg>
                    Cetak Semua (1 Berkas)
                </a>
            </div>
        </div>
    @endforeach
    <div class="sub" style="margin-top:7px;">Urutan berkas gabungan: {{ $urutanGabungan }}.</div>

    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
        <a class="btn" href="{{ route($ruteDaftar) }}">Kembali ke Daftar NPD</a>
    </div>
</div>
@endsection
