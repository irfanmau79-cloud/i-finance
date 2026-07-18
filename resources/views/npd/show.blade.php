@extends('layouts.app')

@section('activeNav', $activeNav)
@section('title', 'Detail NPD')

@section('content')
<div class="dash-card wf-card">
    <h3>Detail NPD &mdash; {{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }}</h3>
    <div class="sub">{{ $npd->nomor_lengkap ?? 'Belum bernomor (masih Draft)' }}</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
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
            <div class="li"><span class="k">Dibuat oleh</span><span class="v">{{ $npd->dibuatOleh->nama ?? '—' }}</span></div>
        </div>

        <div class="grp">
            <div class="gt">Sumber Dana</div>
            <div class="li"><span class="k">Program</span><span class="v">{{ $npd->masterAnggaran->program }}</span></div>
            <div class="li"><span class="k">Kegiatan</span><span class="v">{{ $npd->masterAnggaran->kegiatan }}</span></div>
            <div class="li"><span class="k">Sub Kegiatan</span><span class="v">{{ $npd->masterAnggaran->sub_kegiatan }}</span></div>
            <div class="li"><span class="k">Kode Rekening</span><span class="v">{{ $npd->masterAnggaran->kode_rekening }}</span></div>
            <div class="li"><span class="k">Tagging</span><span class="v">{{ $npd->masterAnggaran->tagging->nama ?? '-' }}</span></div>
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

    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
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
