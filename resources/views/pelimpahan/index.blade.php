@extends('layouts.app')

@section('activeNav', 'pelimpahan')
@section('title', 'Pelimpahan')

@section('content')
@if (session('success'))
    <div class="sumbar ok"><span>{{ session('success') }}</span></div>
@endif
@if ($errors->any())
    <div class="err-box" style="display:block"><strong>Terjadi kesalahan:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="dash-card">
    <h3>Ringkasan Distribusi Sub Kegiatan</h3>
    <div class="form-grid">
        <div><strong>{{ $ringkasan['total'] }}</strong><div class="sub">Total Sub Kegiatan</div></div>
        <div><strong>{{ $ringkasan['assigned'] }}</strong><div class="sub">Sudah ditugaskan</div></div>
        <div><strong>{{ $ringkasan['unassigned'] }}</strong><div class="sub">Belum ditugaskan</div></div>
        <div><strong>{{ number_format($ringkasan['percentage'], 1, ',', '.') }}%</strong><div class="sub">Cakupan penugasan</div></div>
    </div>
    @if ($ringkasan['unassigned'] > 0)
        <div class="err-box" style="display:block;margin-top:12px"><strong>{{ $ringkasan['unassigned'] }} Sub Kegiatan belum memiliki PPTK.</strong> Gunakan filter “Belum ditugaskan” untuk menindaklanjuti.</div>
    @endif
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>Pejabat OPD</h3>
    <div class="sub">Satu PA dan satu Bendahara Pengeluaran aktif untuk seluruh OPD.</div>
    <form method="POST" action="{{ route('pelimpahan.opd.update') }}">@csrf
        <div class="form-grid">
            <div class="fg"><label class="fl" for="pa_pegawai_id">Pengguna Anggaran (PA)</label><select id="pa_pegawai_id" name="pa_pegawai_id" required><option value="">-- Pilih Pegawai --</option>@foreach ($pegawaiList as $p)<option value="{{ $p->id }}" @selected(old('pa_pegawai_id', $pejabatOpd?->pa_pegawai_id) == $p->id)>{{ $p->nama }}</option>@endforeach</select></div>
            <div class="fg"><label class="fl" for="bendahara_pengeluaran_pegawai_id">Bendahara Pengeluaran</label><select id="bendahara_pengeluaran_pegawai_id" name="bendahara_pengeluaran_pegawai_id" required><option value="">-- Pilih Pegawai --</option>@foreach ($pegawaiList as $p)<option value="{{ $p->id }}" @selected(old('bendahara_pengeluaran_pegawai_id', $pejabatOpd?->bendahara_pengeluaran_pegawai_id) == $p->id)>{{ $p->nama }}</option>@endforeach</select></div>
        </div><div style="text-align:right;margin-top:12px"><button class="btn prim">Simpan Pejabat OPD</button></div>
    </form>
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>KPA dan BPP</h3>
    <div class="sub">Setiap KPA aktif wajib memiliki tepat satu BPP aktif.</div>
    <div class="sp-table-wrap"><table class="realisasi"><thead><tr><th>KPA</th><th>Jabatan</th><th>BPP</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($kpaList as $kpa)<tr><td>{{ $kpa->kpaPegawai->nama }}</td><td>{{ $kpa->nama_jabatan ?: '-' }}</td><td>{{ $kpa->bppPegawai->nama }}</td><td><span class="badge {{ $kpa->aktif ? 'st-aktif' : 'st-danger' }}">{{ $kpa->aktif ? 'Aktif' : 'Nonaktif' }}</span></td><td><button type="button" class="btn" data-kpa-edit data-url="{{ route('pelimpahan.kpa.update', $kpa) }}" data-kpa="{{ $kpa->kpa_pegawai_id }}" data-bpp="{{ $kpa->bpp_pegawai_id }}" data-jabatan="{{ $kpa->nama_jabatan }}">Ubah</button> <form method="POST" action="{{ route('pelimpahan.kpa.toggle-aktif', $kpa) }}" style="display:inline">@csrf @method('PATCH')<button class="btn">{{ $kpa->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
        @empty <tr><td colspan="5">Belum ada KPA.</td></tr>@endforelse
    </tbody></table></div>
    <h4 id="kpa-form-title">Tambah KPA</h4>
    <form method="POST" action="{{ route('pelimpahan.kpa.store') }}" id="kpa-form">@csrf
        <div class="form-grid"><div class="fg"><label class="fl">Pegawai KPA</label><select name="kpa_pegawai_id" id="kpa_pegawai_id" required><option value="">-- Pilih --</option>@foreach ($pegawaiList as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach</select></div><div class="fg"><label class="fl">Pegawai BPP</label><select name="bpp_pegawai_id" id="bpp_pegawai_id" required><option value="">-- Pilih --</option>@foreach ($pegawaiList as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach</select></div><div class="fg"><label class="fl">Nama Jabatan</label><input name="nama_jabatan" id="nama_jabatan"></div></div>
        <div style="text-align:right;margin-top:12px"><button type="button" class="btn" id="kpa-cancel" hidden>Batal</button> <button class="btn prim" id="kpa-submit">Simpan</button></div>
    </form>
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>PPTK di bawah KPA</h3>
    <form method="POST" action="{{ route('pelimpahan.pptk.store') }}">@csrf<div class="form-grid"><div class="fg"><label class="fl">KPA</label><select name="kpa_id" required><option value="">-- Pilih KPA --</option>@foreach ($kpaList->where('aktif', true) as $kpa)<option value="{{ $kpa->id }}">{{ $kpa->kpaPegawai->nama }}</option>@endforeach</select></div><div class="fg"><label class="fl">PPTK</label><select name="pptk_pegawai_id" required><option value="">-- Pilih PPTK --</option>@foreach ($pegawaiList as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach</select></div></div><div style="text-align:right;margin-top:12px"><button class="btn prim">Tempatkan PPTK</button></div></form>
    <div class="sp-table-wrap" style="margin-top:12px"><table class="realisasi"><thead><tr><th>KPA</th><th>PPTK</th><th>Aksi</th></tr></thead><tbody>@forelse ($pptkAssignments as $item)<tr><td>{{ $item->kpa->kpaPegawai->nama }}</td><td>{{ $item->pptkPegawai->nama }}</td><td><form method="POST" action="{{ route('pelimpahan.pptk.toggle-aktif', $item) }}">@csrf @method('PATCH')<button class="btn">Nonaktifkan</button></form></td></tr>@empty<tr><td colspan="3">Belum ada PPTK di bawah KPA.</td></tr>@endforelse</tbody></table></div>
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>Hierarki Aktif</h3>
    <div class="sub">PA/Bendahara Pengeluaran → KPA/BPP → PPTK → Sub Kegiatan.</div>
    @forelse ($kpaHierarki as $kpa)
        <div style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-top:10px"><strong>KPA: {{ $kpa->kpaPegawai->nama }}</strong> · BPP: {{ $kpa->bppPegawai->nama }}
        @forelse ($kpa->pptkAktif as $mapping)<div style="margin:8px 0 0 18px"><strong>PPTK: {{ $mapping->pptkPegawai->nama }}</strong><ul>@forelse ($mapping->pelimpahan as $item)<li>{{ $item->program_normal }} — {{ $item->kode_sub_kegiatan }}</li>@empty<li><span class="badge st-danger">BELUM ADA SUB KEGIATAN</span></li>@endforelse</ul></div>@empty<div class="err-box" style="display:block;margin-top:8px">KPA ini belum memiliki PPTK aktif.</div>@endforelse
        </div>
    @empty <div class="sub">Belum ada hierarki aktif.</div>@endforelse
</div>

<div class="dash-card" style="margin-top:16px">
    <h3>Distribusi Sub Kegiatan</h3>
    <form method="GET" action="{{ route('pelimpahan.index') }}"><div class="form-grid">
        <div class="fg"><label class="fl">Status</label><select name="status"><option value="">Semua</option><option value="assigned" @selected(request('status') === 'assigned')>Ditugaskan</option><option value="unassigned" @selected(request('status') === 'unassigned')>Belum ditugaskan</option></select></div>
        <div class="fg"><label class="fl">KPA</label><select name="kpa_id"><option value="">Semua</option>@foreach ($kpaList as $kpa)<option value="{{ $kpa->id }}" @selected((string) request('kpa_id') === (string) $kpa->id)>{{ $kpa->kpaPegawai->nama }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">BPP</label><select name="bpp_pegawai_id"><option value="">Semua</option>@foreach ($kpaList as $kpa)<option value="{{ $kpa->bpp_pegawai_id }}" @selected((string) request('bpp_pegawai_id') === (string) $kpa->bpp_pegawai_id)>{{ $kpa->bppPegawai->nama }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">PPTK</label><select name="pptk_pegawai_id"><option value="">Semua</option>@foreach ($pptkAssignments as $item)<option value="{{ $item->pptk_pegawai_id }}" @selected((string) request('pptk_pegawai_id') === (string) $item->pptk_pegawai_id)>{{ $item->pptkPegawai->nama }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">Program</label><select name="program"><option value="">Semua</option>@foreach ($programList as $program)<option value="{{ $program->program_normal }}" @selected(request('program') === $program->program_normal)>{{ $program->program_normal }}</option>@endforeach</select></div>
        <div class="fg"><label class="fl">Cari Sub Kegiatan</label><input name="cari" value="{{ request('cari') }}"></div>
    </div><div style="text-align:right;margin-top:12px"><a class="btn" href="{{ route('pelimpahan.index') }}">Reset</a> <button class="btn prim">Filter</button></div></form>

    <form method="POST" action="{{ route('pelimpahan.sub-kegiatan.set') }}" id="assignment-form">@csrf
        <div class="form-grid" style="margin-top:16px"><div class="fg"><label class="fl">KPA</label><select name="kpa_id" id="assign-kpa" required><option value="">-- Pilih KPA --</option>@foreach ($kpaList->where('aktif', true) as $kpa)<option value="{{ $kpa->id }}" data-bpp="{{ $kpa->bpp_pegawai_id }}">{{ $kpa->kpaPegawai->nama }}</option>@endforeach</select></div><div class="fg"><label class="fl">BPP</label><select name="bpp_pegawai_id" id="assign-bpp" required><option value="">-- Mengikuti KPA --</option>@foreach ($kpaList->where('aktif', true) as $kpa)<option value="{{ $kpa->bpp_pegawai_id }}" data-kpa="{{ $kpa->id }}">{{ $kpa->bppPegawai->nama }}</option>@endforeach</select></div><div class="fg"><label class="fl">PPTK</label><select name="pptk_pegawai_id" id="assign-pptk" required><option value="">-- Pilih PPTK --</option>@foreach ($pptkAssignments as $item)<option value="{{ $item->pptk_pegawai_id }}" data-kpa="{{ $item->kpa_id }}">{{ $item->pptkPegawai->nama }}</option>@endforeach</select></div></div>
        <div class="sp-table-wrap" style="margin-top:12px"><table class="realisasi"><thead><tr><th></th><th>Sub Kegiatan</th><th>Program / Kegiatan</th><th>Rantai Aktif</th><th>Status</th></tr></thead><tbody>
        @forelse ($subKegiatanList as $row) @php($key = $row->program_kunci.'|'.$row->sub_kegiatan_kunci) @php($p = $pelimpahanMap->get($key)) @php($scope = base64_encode(json_encode(['program' => $row->program_normal, 'sub_kegiatan' => $row->sub_kegiatan_normal])))
            <tr><td><input type="checkbox" name="scope[]" value="{{ $scope }}"></td><td>{{ $row->sub_kegiatan_normal }}</td><td>{{ $row->program_normal }}<br><span class="sub">{{ $row->kegiatan_normal }}</span></td><td>@if ($p){{ $p->kpa->kpaPegawai->nama }} / {{ $p->kpa->bppPegawai->nama }} / {{ $p->pptkPegawai->nama }}@else - @endif</td><td>@if ($p)<span class="badge st-aktif">DITUGASKAN</span>@else<span class="badge st-danger">BELUM DITUGASKAN</span>@endif</td></tr>
        @empty <tr><td colspan="5">Tidak ada Sub Kegiatan sesuai filter.</td></tr>@endforelse
        </tbody></table></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px"><div>{{ $subKegiatanList->links() }}</div><button class="btn prim">Tetapkan / Reassign yang Dipilih</button></div>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('kpa-form'); const createUrl = form.action; const cancel = document.getElementById('kpa-cancel');
    document.querySelectorAll('[data-kpa-edit]').forEach(btn => btn.addEventListener('click', () => { form.action = btn.dataset.url; let method = form.querySelector('[name="_method"]'); if (!method) { method = document.createElement('input'); method.type='hidden'; method.name='_method'; form.appendChild(method); } method.value='PUT'; document.getElementById('kpa_pegawai_id').value=btn.dataset.kpa; document.getElementById('bpp_pegawai_id').value=btn.dataset.bpp; document.getElementById('nama_jabatan').value=btn.dataset.jabatan || ''; document.getElementById('kpa-form-title').textContent='Ubah KPA'; document.getElementById('kpa-submit').textContent='Perbarui'; cancel.hidden=false; }));
    cancel.addEventListener('click', () => { form.action=createUrl; form.querySelector('[name="_method"]')?.remove(); form.reset(); document.getElementById('kpa-form-title').textContent='Tambah KPA'; document.getElementById('kpa-submit').textContent='Simpan'; cancel.hidden=true; });
    const kpa = document.getElementById('assign-kpa'), bpp = document.getElementById('assign-bpp'), pptk = document.getElementById('assign-pptk');
    function syncChain() { const id=kpa.value; Array.from(bpp.options).forEach(o => o.hidden=!!o.dataset.kpa && o.dataset.kpa !== id); Array.from(pptk.options).forEach(o => o.hidden=!!o.dataset.kpa && o.dataset.kpa !== id); bpp.value=kpa.selectedOptions[0]?.dataset.bpp || ''; if (pptk.selectedOptions[0]?.dataset.kpa !== id) pptk.value=''; }
    kpa.addEventListener('change', syncChain); syncChain();
    document.getElementById('assignment-form').addEventListener('submit', e => { if (!document.querySelector('[name="scope[]"]:checked')) { e.preventDefault(); alert('Pilih minimal satu Sub Kegiatan.'); } });
})();
</script>
@endsection
