@extends('layouts.app')

@section('activeNav', $activeNav)
@section('title', 'Detail NPD')

@section('content')
@if($npd->sumber_data === 'import_historis')
<div class="err-box" style="display:block;background:#eef6ff;color:#15314a;border-color:#bfdbfe;">
    Dokumen historis dari batch #{{ $npd->import_historis_id }}, baris sumber {{ $npd->import_baris }}. Detail khusus perjalanan/peserta/narasumber/barang tidak dibuat secara fiktif; Lampiran memakai snapshot penerima, rekening, bruto, dan pajak hasil import.
</div>
@endif
<div class="dash-card wf-card">
    <h3>Detail NPD &mdash; {{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }}</h3>
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

    <div class="rev">
        <div class="grp">
            <div class="gt">Informasi Umum</div>
            <div class="li"><span class="k">Status</span><span class="v"><span class="badge {{ \App\Models\Npd::STATUS_BADGE_CLASS[$npd->status] ?? 'st-diterima' }}">{{ $npd->status }}</span></span></div>
            <div class="li"><span class="k">Tanggal NPD</span><span class="v">{{ $npd->tanggal_npd->format('d-m-Y') }}</span></div>
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
        </div>

        <div class="grp">
            <div class="gt">Sumber Dana</div>
            <div class="li"><span class="k">Program</span><span class="v">{{ $npd->masterAnggaran->program }}</span></div>
            <div class="li"><span class="k">Kegiatan</span><span class="v">{{ $npd->masterAnggaran->kegiatan }}</span></div>
            <div class="li"><span class="k">Sub Kegiatan</span><span class="v">{{ $npd->masterAnggaran->sub_kegiatan }}</span></div>
            <div class="li"><span class="k">Kode Rekening</span><span class="v">{{ $npd->masterAnggaran->kode_rekening }}</span></div>
            <div class="li"><span class="k">Tagging</span><span class="v">{{ $npd->tagging_snapshot ?: ($npd->masterAnggaran->tagging->nama ?? '-') }}</span></div>
            <div class="li"><span class="k">Pagu</span><span class="v">Rp {{ number_format((float) $npd->masterAnggaran->pagu, 2, ',', '.') }}</span></div>
        </div>

        <div class="grp">
            <div class="gt">Nominal</div>
            <div class="li"><span class="k">Nominal NPD</span><span class="v">Rp {{ number_format((float) $npd->nominal, 2, ',', '.') }}</span></div>
            <div class="li"><span class="k">Terbilang</span><span class="v">{{ $npd->terbilang }}</span></div>
        </div>

        @if ($npd->catatan)
        <div class="grp">
            <div class="gt">Catatan</div>
            <div class="li"><span class="v">{{ $npd->catatan }}</span></div>
        </div>
        @endif

        @php
            $wfClass = [
                'ajukan_bpp' => 'wf-teruskan',
                'teruskan' => 'wf-teruskan',
                'verifikasi' => 'wf-verif',
                'kembali_bpp' => 'wf-kembali',
                'kembali_pptk' => 'wf-kembali',
                'setuju' => 'wf-setuju',
                'selesai' => 'wf-selesai',
                'batal_selesai' => 'wf-kembali',
            ];
            $aksiButuhForm = ['verifikasi', 'kembali_bpp', 'kembali_pptk', 'batal_selesai'];
        @endphp
        @if (count($aksiTersedia))
        <div class="grp">
            <div class="gt">Aksi Workflow</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach ($aksiTersedia as $aksi)
                    @php $rule = \App\Models\Npd::TRANSISI[$aksi]; @endphp
                    @if (in_array($aksi, $aksiButuhForm, true))
                        <button type="button" class="wf-btn {{ $wfClass[$aksi] }}" data-wf-open="{{ $aksi }}">{{ $rule['label'] }}</button>
                    @else
                        <form method="POST" action="{{ route('npd.transisi', $npd) }}" onsubmit="return confirm('Yakin {{ $rule['label'] }}?');" style="display:inline;">
                            @csrf
                            <input type="hidden" name="aksi" value="{{ $aksi }}">
                            <button type="submit" class="wf-btn {{ $wfClass[$aksi] }}">{{ $rule['label'] }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @if (in_array($npd->jenis, ['pd', 'tr'], true))
        <h3 style="margin-top:22px;">Anggota Tim</h3>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
            <table class="realisasi">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Paket Tujuan</th>
                        <th>UH + Akomodasi</th>
                        <th>Transport</th>
                        <th>Representatif</th>
                        <th>Jumlah</th>
                        <th>Penerima</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($npd->tim as $t)
                        @php
                            $h = $t->hitung();
                        @endphp
                        <tr>
                            <td>{{ $t->nama }}</td>
                            <td>{{ $t->jabatan ?? '—' }}</td>
                            <td>
                                @foreach ($t->paket as $p)
                                    {{ $p->cluster }} &mdash; {{ $p->wilayah }} ({{ $p->lama_hari }} hari, {{ $p->malam }} malam)<br>
                                @endforeach
                            </td>
                            <td>Rp {{ number_format($h['jml_harian'] + $h['jml_akom'], 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($h['jml_transport'], 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($h['representatif'], 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($h['jumlah'], 2, ',', '.') }}</td>
                            <td>{{ $t->is_penerima ? 'Ya' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">Belum ada anggota tim.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($npd->jenis === 'ns')
        <h3 style="margin-top:22px;">Daftar Narasumber</h3>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
            <table class="realisasi">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>JP</th>
                        <th>Tarif/JP</th>
                        <th>Honor</th>
                        <th>Transport</th>
                        <th>Bruto</th>
                        <th>PPh 21</th>
                        <th>Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($npd->narasumber as $n)
                        <tr>
                            <td>{{ $n->nama }}</td>
                            <td>{{ $n->jabatan ?? '—' }}</td>
                            <td>{{ $n->jumlah_jp }}</td>
                            <td>Rp {{ number_format((float) $n->tarif_jp, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($n->honor, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $n->transport, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($n->bruto, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $n->pph21, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($n->netto, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">Belum ada narasumber.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($npd->jenis === 'kd')
        <h3 style="margin-top:22px;">Daftar Peserta</h3>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
            <table class="realisasi">
                @if ($npd->mode_kd === 'perjalanan')
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pangkat</th>
                            <th>Hari UH</th>
                            <th>Jumlah Harian</th>
                            <th>Akomodasi</th>
                            <th>Uang Saku</th>
                            <th>Transport</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($npd->peserta as $p)
                            <tr>
                                <td>{{ $p->nama }}</td>
                                <td>{{ $p->pangkat ?? '—' }}</td>
                                <td>{{ $p->hari_uh }}</td>
                                <td>Rp {{ number_format($p->jumlah_harian, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->jumlah_akomodasi, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->jumlah_saku, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $p->transport, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->sub_perjalanan, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">Belum ada peserta.</td></tr>
                        @endforelse
                    </tbody>
                @else
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pangkat</th>
                            <th>Volume Kontribusi</th>
                            <th>Jumlah Kontribusi</th>
                            <th>Volume MOOC</th>
                            <th>Jumlah MOOC</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($npd->peserta as $p)
                            <tr>
                                <td>{{ $p->nama }}</td>
                                <td>{{ $p->pangkat ?? '—' }}</td>
                                <td>{{ $p->volume_kontribusi }}</td>
                                <td>Rp {{ number_format($p->jumlah_kontribusi, 2, ',', '.') }}</td>
                                <td>{{ $p->volume_mooc }}</td>
                                <td>Rp {{ number_format($p->jumlah_mooc, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format($p->sub_kontribusi, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada peserta.</td></tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
    @else
        <h3 style="margin-top:22px;">Daftar Penerima</h3>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
            <table class="realisasi">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Rekening</th>
                        <th>Bruto</th>
                        <th>PPN</th>
                        <th>PPh</th>
                        <th>Biaya KU/RTGS</th>
                        <th>Netto</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($npd->penerima as $p)
                        <tr>
                            <td>{{ $p->nama }}</td>
                            <td>{{ $p->rekening ?? '—' }}</td>
                            <td>Rp {{ number_format((float) $p->bruto, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $p->ppn, 2, ',', '.') }}</td>
                            <td>
                                @forelse ($p->pphList as $pph)
                                    {{ $pph->jenis }}: Rp {{ number_format((float) $pph->nilai, 2, ',', '.') }}<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>Rp {{ number_format((float) $p->biaya_ku_rtgs, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($p->netto, 2, ',', '.') }}</td>
                            <td>{{ $p->keterangan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--mut);padding:20px;">Belum ada penerima.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <h3 style="margin-top:22px;">Lokasi Arsip SPJ</h3>
    <div class="dash-card" style="box-shadow:none;border:1px solid var(--line);">
        @if ($bolehKelolaArsip && $npd->status === 'Selesai')
        <form method="POST" action="{{ route('npd.arsip-spj.store', $npd) }}" class="row" style="align-items:end;margin-bottom:16px;">
            @csrf
            <div><label class="fl">Jenis Dokumen</label><select name="jenis_dokumen" required>@foreach(\App\Services\InventarisasiSpjService::JENIS_DOKUMEN as $jenis)<option>{{ $jenis }}</option>@endforeach</select></div>
            <div><label class="fl">Lokasi Bantex/Box</label><input name="lokasi" maxlength="100" required placeholder="Contoh: Bantex A-01"></div>
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
        <h3 style="margin-top:22px;">Histori Status</h3>
        <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
            <table class="realisasi">
                <thead><tr><th>#</th><th>Waktu</th><th>Aksi</th><th>Perubahan Status</th><th>Pengguna</th><th>Catatan</th></tr></thead>
                <tbody>
                    @foreach ($npd->historiStatus as $histori)
                        <tr>
                            <td>{{ $histori->nomor_urut }}</td>
                            <td>{{ $histori->created_at->format('d-m-Y H:i') }}</td>
                            <td>{{ str($histori->aksi)->replace('_', ' ')->title() }}</td>
                            <td>{{ $histori->status_asal ?? 'Awal' }} &rarr; {{ $histori->status_tujuan }}</td>
                            <td>{{ $histori->user->nama ?? 'Sistem' }}</td>
                            <td>{{ $histori->catatan ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $bolehBatalkan = $npd->dapatDihapusOleh(auth()->user());
        $ruteEdit = match ($npd->jenis) {
            'pd' => 'npd.pd.edit',
            'ns' => 'npd.ns.edit',
            'kd' => 'npd.kd.edit',
            'tr' => 'npd.tr.edit',
            default => 'npd.bj.edit',
        };
    @endphp
    @if ($npd->dapatDieditOleh(auth()->user()) || $bolehBatalkan)
        <div class="grp" style="margin-top:22px;">
            <div class="gt">Kelola Draft</div>
            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                @if ($npd->dapatDieditOleh(auth()->user()))
                    <a class="btn" href="{{ route($ruteEdit, $npd) }}">Edit NPD</a>
                @endif
                @if ($bolehBatalkan)
                    <form method="POST" action="{{ route('npd.destroy', $npd) }}" onsubmit="return confirm('Batalkan NPD ini? Tindakan dicatat dalam histori.');" style="display:flex;gap:6px;align-items:center;">
                        @csrf
                        @method('DELETE')
                        <input type="text" name="alasan" required maxlength="500" placeholder="Alasan pembatalan" style="min-width:240px;">
                        <button type="submit" class="btn">Batalkan NPD</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
        @if (in_array($npd->jenis, ['pd', 'tr'], true))
            <a class="btn" href="{{ route('npd.cetak-daftar', $npd) }}" target="_blank">Cetak Daftar Pembayaran</a>
            <a class="btn" href="{{ route('npd.cetak-spd', $npd) }}" target="_blank">Cetak SPD Rampung</a>
        @elseif ($npd->jenis === 'ns')
            <a class="btn" href="{{ route('npd.cetak-daftar-nara', $npd) }}" target="_blank">Cetak Daftar Pembayaran</a>
        @elseif ($npd->jenis === 'kd')
            <a class="btn" href="{{ route('npd.cetak-daftar-kd', $npd) }}" target="_blank">Cetak Daftar Bayar</a>
        @endif
        <a class="btn" href="{{ route('npd.cetak-npd', $npd) }}" target="_blank">Cetak NPD</a>
        <a class="btn" href="{{ route('npd.cetak-lampiran', $npd) }}" target="_blank">Cetak Lampiran</a>
        <a class="btn" href="{{ route($ruteDaftar) }}">Kembali ke Daftar NPD</a>
    </div>
</div>

@if (count($aksiTersedia))
<div class="mdl-ov" id="wf-mdl-ov">
  <div class="mdl">
    <div class="mdl-h" id="wf-mdl-title">Aksi</div>
    <div class="mdl-b">
      <form method="POST" action="{{ route('npd.transisi', $npd) }}" id="wf-mdl-form">
        @csrf
        <input type="hidden" name="aksi" id="wf-mdl-aksi" value="">
        <div id="wf-mdl-nomor-wrap" style="display:none;">
          <label class="fl">Nomor Urut NPD (1&ndash;999)</label>
          <input type="number" name="nomor_urut" id="wf-mdl-nomor" min="1" max="999">
        </div>
        <div id="wf-mdl-catatan-wrap">
          <label class="fl" id="wf-mdl-catatan-label">Catatan</label>
          <textarea name="catatan" id="wf-mdl-catatan" rows="3" style="width:100%;box-sizing:border-box;"></textarea>
        </div>
        <div class="mdl-f" style="padding:14px 0 0;">
          <button type="button" class="btn" onclick="wfModalClose()">Batal</button>
          <button type="submit" class="btn prim">Kirim</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
    var WF_FORM_META = {
        verifikasi: { title: 'Verifikasi NPD', nomor: true, catatanLabel: 'Catatan (opsional)', catatanRequired: false },
        kembali_bpp: { title: 'Kembalikan ke BPP', nomor: false, catatanLabel: 'Catatan Revisi (wajib)', catatanRequired: true },
        kembali_pptk: { title: 'Kembalikan ke PPTK', nomor: false, catatanLabel: 'Catatan Revisi (wajib)', catatanRequired: true },
        batal_selesai: { title: 'Batalkan Status Selesai', nomor: false, catatanLabel: 'Alasan Pembatalan (wajib)', catatanRequired: true }
    };

    var ov = document.getElementById('wf-mdl-ov');
    var aksiField = document.getElementById('wf-mdl-aksi');
    var nomorWrap = document.getElementById('wf-mdl-nomor-wrap');
    var nomorInput = document.getElementById('wf-mdl-nomor');
    var catatanLabel = document.getElementById('wf-mdl-catatan-label');
    var catatanInput = document.getElementById('wf-mdl-catatan');
    var titleEl = document.getElementById('wf-mdl-title');

    function wfModalOpen(aksi) {
        var meta = WF_FORM_META[aksi];
        if (! meta) return;

        titleEl.textContent = meta.title;
        aksiField.value = aksi;

        nomorWrap.style.display = meta.nomor ? 'block' : 'none';
        nomorInput.required = meta.nomor;
        nomorInput.value = '';

        catatanLabel.textContent = meta.catatanLabel;
        catatanInput.required = meta.catatanRequired;
        catatanInput.value = '';

        ov.classList.add('show');
    }

    window.wfModalClose = function () {
        ov.classList.remove('show');
    };

    document.querySelectorAll('[data-wf-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wfModalOpen(btn.getAttribute('data-wf-open'));
        });
    });
})();
</script>
@endif
@endsection
