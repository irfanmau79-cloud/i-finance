@extends((auth()->check() || \App\Helpers\GuestSession::isActive()) ? 'layouts.app' : 'layouts.standalone-wide')

@section('activeNav', 'sp-monitor')
@section('title', 'Monitoring SP')

@section('content')
@php
    $role = \App\Helpers\GuestSession::role() ?? 'layanan';
    $bolehEditPengajuan = in_array($role, ['pptk', 'superadmin'], true);
    $bolehEditPengumuman = in_array($role, ['superadmin', 'pptk', 'bpp', 'verifikator'], true);
    $statusBadgeClass = ['Diterima PPTK' => 'st-diterima'] + \App\Models\Npd::STATUS_BADGE_CLASS;
@endphp
<div class="dash-card wf-card">
    <h3>Monitoring SP</h3>
    <div class="sub">Status pengajuan SP Pengawasan.</div>

    @if (session('success'))
        <div class="sumbar ok"><span>{{ session('success') }}</span></div>
    @endif

    <div class="tbl-tools">
        <input type="text" id="spm-search" placeholder="Cari Nomor SP, Unit Kerja, atau Keterangan…">
    </div>

    <div class="sp-table-wrap" style="border:1px solid var(--line);border-radius:8px;">
        <table class="realisasi" id="spm-table">
            <thead>
                <tr>
                    <th>Tanggal Input SP</th>
                    <th>Nomor SP</th>
                    <th>Unit Kerja</th>
                    <th>Keterangan</th>
                    <th>Koordinator</th>
                    <th>Pengajuan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suratPerintahs as $suratPerintah)
                    @php($cariTeks = mb_strtolower($suratPerintah->nomor_sp.' '.$suratPerintah->unit_kerja.' '.$suratPerintah->keterangan))
                    <tr data-pengajuan-url="{{ route('surat-perintah.pengajuan', $suratPerintah) }}" data-search="{{ $cariTeks }}">
                        <td>{{ $suratPerintah->created_at->format('d-m-Y H:i') }}</td>
                        <td style="font-weight:600;">{{ $suratPerintah->nomor_sp }}</td>
                        <td>{{ $suratPerintah->unit_kerja }}</td>
                        <td>{{ $suratPerintah->keterangan }}</td>
                        <td>{{ $suratPerintah->tujuan_transfer ?: '-' }}</td>
                        <td>
                            @php($terpilih = $suratPerintah->pengajuanArray())
                            @if ($bolehEditPengajuan)
                                <div class="peng-cell" style="min-width:150px;">
                                    <div class="peng-chips">
                                        @forelse ($terpilih as $t)
                                            <span class="peng-chip">&check; {{ $t }}</span>
                                        @empty
                                            <span class="peng-chips-empty">&mdash;</span>
                                        @endforelse
                                    </div>
                                    <div class="peng-trigger">
                                        <span>Pilih pengajuan</span>
                                        <svg viewBox="0 0 24 24" width="12" height="12" style="stroke:#64748b;fill:none;stroke-width:2;"><polyline points="6 9 12 15 18 9"/></svg>
                                    </div>
                                    <div class="peng-menu">
                                        @foreach (\App\Models\SuratPerintah::PENGAJUAN_OPTIONS as $opsi)
                                            <label>
                                                <input type="checkbox" class="sp-pengajuan-checkbox" value="{{ $opsi }}" @checked(in_array($opsi, $terpilih, true))>
                                                {{ $opsi }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="peng-chips">
                                    @forelse ($terpilih as $t)
                                        <span class="peng-chip">&check; {{ $t }}</span>
                                    @empty
                                        <span class="peng-chips-empty">&mdash;</span>
                                    @endforelse
                                </div>
                            @endif
                        </td>
                        <td>
                            @if (filled($suratPerintah->status))
                                <span class="badge {{ $statusBadgeClass[$suratPerintah->status] ?? 'st-diterima' }}">{{ $suratPerintah->status }}</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:var(--mut);padding:20px;">Belum ada SP yang dipantau.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pengumuman-box" id="pengumuman-box">
        <div class="pengumuman-head">
            <div class="pengumuman-title">
                <svg viewBox="0 0 24 24" width="15" height="15" style="stroke:var(--navy);fill:none;stroke-width:2;"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                Pemberitahuan dari Tim Keuangan
            </div>
            @if ($bolehEditPengumuman)
                <button type="button" class="pengumuman-edit-btn" id="pengumuman-edit-btn" onclick="editPengumuman()">Edit</button>
            @endif
        </div>
        <div class="pengumuman-isi" id="pengumuman-isi">
            <span class="pengumuman-kosong">Tidak ada pemberitahuan saat ini</span>
        </div>
    </div>

    <div class="flow-grid">
        <div class="flow-card"><div class="num">1</div><div><b>Diterima PPTK</b><p>SP diterima, menunggu NPD dibuat &amp; ditaut oleh PPTK</p></div></div>
        <div class="flow-card"><div class="num">2</div><div><b>Draft NPD - PPTK</b><p>NPD dibuat PPTK, menunggu diajukan ke BPP</p></div></div>
        <div class="flow-card"><div class="num">3</div><div><b>Draft NPD - BPP</b><p>NPD di BPP: diteruskan ke Verifikator / dikembalikan ke PPTK</p></div></div>
        <div class="flow-card"><div class="num">4</div><div><b>Verifikasi - Verifikator</b><p>NPD sedang diverifikasi; bisa dikembalikan ke BPP</p></div></div>
        <div class="flow-card"><div class="num">5</div><div><b>NPD Disetujui - BPP</b><p>NPD disetujui final, menunggu transaksi IBC</p></div></div>
        <div class="flow-card"><div class="num">6</div><div><b>Selesai</b><p>NPD telah selesai ditransaksikan</p></div></div>
    </div>
</div>

<script>
  // Search filter, client-side, dari data yang sudah dimuat di tabel.
  document.getElementById('spm-search').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#spm-table tbody tr[data-search]').forEach(function (row) {
      row.style.display = row.dataset.search.indexOf(q) >= 0 ? '' : 'none';
    });
  });

  // Dropdown "Pilih pengajuan"
  document.querySelectorAll('.peng-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var menu = trigger.closest('.peng-cell').querySelector('.peng-menu');
      var open = menu.classList.contains('show');
      document.querySelectorAll('.peng-menu.show').forEach(function (m) { m.classList.remove('show'); });
      if (! open) menu.classList.add('show');
    });
  });
  document.addEventListener('click', function (e) {
    if (! e.target.closest('.peng-cell')) {
      document.querySelectorAll('.peng-menu.show').forEach(function (m) { m.classList.remove('show'); });
    }
  });

  @if ($bolehEditPengajuan)
  document.querySelectorAll('.sp-pengajuan-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var row = checkbox.closest('tr');
      var url = row.dataset.pengajuanUrl;
      var checkedBefore = ! checkbox.checked;
      var cell = checkbox.closest('.peng-cell');
      var checkboxesInCell = cell.querySelectorAll('.sp-pengajuan-checkbox');

      var params = new URLSearchParams();
      var dipilih = [];
      cell.querySelectorAll('.sp-pengajuan-checkbox:checked').forEach(function (cb) {
        params.append('pengajuan[]', cb.value);
        dipilih.push(cb.value);
      });

      checkboxesInCell.forEach(function (cb) { cb.disabled = true; });

      fetch(url, {
        method: 'PATCH',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
        },
        body: params.toString(),
      })
        .then(function (res) {
          if (! res.ok) throw new Error('Gagal menyimpan Pengajuan.');
          return res.json();
        })
        .then(function () {
          var chips = cell.querySelector('.peng-chips');
          chips.innerHTML = dipilih.length
            ? dipilih.map(function (v) { return '<span class="peng-chip">&check; ' + v + '</span>'; }).join('')
            : '<span class="peng-chips-empty">&mdash;</span>';
        })
        .catch(function () {
          checkbox.checked = checkedBefore;
          alert('Gagal menyimpan Pengajuan. Silakan coba lagi.');
        })
        .finally(function () {
          checkboxesInCell.forEach(function (cb) { cb.disabled = false; });
        });
    });
  });
  @endif

  // Pemberitahuan dari Tim Keuangan (GET/POST /pengumuman)
  var PENGUMUMAN_TEKS = '';
  var BOLEH_EDIT_PENGUMUMAN = @json($bolehEditPengumuman);

  function renderPengumuman() {
    var el = document.getElementById('pengumuman-isi');
    if (PENGUMUMAN_TEKS && PENGUMUMAN_TEKS.trim()) {
      el.textContent = PENGUMUMAN_TEKS;
    } else {
      el.innerHTML = '<span class="pengumuman-kosong">Tidak ada pemberitahuan saat ini</span>';
    }
  }

  function loadPengumuman() {
    fetch('{{ route('pengumuman.show') }}', { headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        PENGUMUMAN_TEKS = data.teks || '';
        renderPengumuman();
      })
      .catch(function () { renderPengumuman(); });
  }

  function editPengumuman() {
    var el = document.getElementById('pengumuman-isi');
    var btn = document.getElementById('pengumuman-edit-btn');
    if (btn) btn.style.display = 'none';
    el.innerHTML = '<textarea class="pengumuman-edit-area" id="pengumuman-textarea" maxlength="1000" placeholder="Tulis pemberitahuan untuk seluruh tim…"></textarea>'
      + '<div class="pengumuman-edit-actions">'
      + '<button type="button" class="btn" onclick="batalPengumuman()">Batal</button>'
      + '<button type="button" class="btn prim" id="pengumuman-simpan-btn" onclick="simpanPengumumanUi()">Simpan</button></div>';
    var ta = document.getElementById('pengumuman-textarea');
    ta.value = PENGUMUMAN_TEKS;
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
  }

  function batalPengumuman() {
    renderPengumuman();
    var btn = document.getElementById('pengumuman-edit-btn');
    if (btn && BOLEH_EDIT_PENGUMUMAN) btn.style.display = 'inline-block';
  }

  function simpanPengumumanUi() {
    var ta = document.getElementById('pengumuman-textarea');
    if (! ta) return;
    var teks = ta.value;
    var btn = document.getElementById('pengumuman-simpan-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan…'; }

    fetch('{{ route('pengumuman.store') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: 'teks=' + encodeURIComponent(teks),
    })
      .then(function (res) {
        if (! res.ok) throw new Error('Gagal menyimpan.');
        return res.json();
      })
      .then(function (data) {
        PENGUMUMAN_TEKS = data.teks || '';
        renderPengumuman();
        batalPengumuman();
      })
      .catch(function () {
        if (btn) { btn.disabled = false; btn.textContent = 'Simpan'; }
        alert('Gagal menyimpan pemberitahuan. Silakan coba lagi.');
      });
  }

  loadPengumuman();
</script>
@endsection
