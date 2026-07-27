@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Buat Simulasi Baru')

@section('content')
<style>
    .sim-create-head{display:flex;align-items:center;gap:14px;margin-bottom:20px}
    .sim-create-icon{width:48px;height:48px;flex:0 0 48px;border-radius:14px;background:var(--navy-l);color:var(--navy);display:flex;align-items:center;justify-content:center}
    .sim-create-icon svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8}
    .sim-create-title{font-size:22px;font-weight:800;color:var(--navy);line-height:1.2}
    .sim-create-sub{font-size:12.5px;color:var(--mut);margin-top:3px}
    .sim-create-form{padding:20px;border:1px solid var(--line);border-radius:13px;background:#f8fafc}
    .sim-create-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px}
    .sim-create-grid .fg{min-width:0}
    .sim-create-grid label.fl{margin-top:0}
    .sim-create-grid input,.sim-create-grid textarea{width:100%;box-sizing:border-box;background:#fff}
    .sim-create-grid textarea{resize:vertical;min-height:42px}
    .sim-create-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}
    @media(max-width:720px){.sim-create-grid{grid-template-columns:1fr}.sim-create-actions .btn{flex:1;text-align:center}}
</style>

<div class="page-head">
    <div>
        <div class="ph-crumb">Analisis / Simulasi Pergeseran/Perubahan / <b>Buat Baru</b></div>
        <div class="ph-title">Buat Simulasi Baru</div>
    </div>
</div>

<div class="dash-card wf-card">
    <div class="sim-create-head">
        <div class="sim-create-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"/><path d="M6 16V8"/><path d="M12 16V4"/><path d="M18 16v-5"/><path d="m16 7 2-2 2 2"/></svg>
        </div>
        <div>
            <div class="sim-create-title">Identitas Simulasi</div>
            <div class="sim-create-sub">Berikan nama yang mudah dikenali agar simulasi dapat ditemukan kembali.</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('simulasi-anggaran.store') }}">
        @csrf

        <div class="sim-create-form">
            <div class="sim-create-grid">
                <div class="fg">
                    <label class="fl" for="sim-nama">Nama Simulasi</label>
                    <input id="sim-nama" type="text" name="nama" value="{{ old('nama') }}" required maxlength="150" autofocus placeholder="Contoh: Simulasi Pergeseran Semester 2">
                </div>
                <div class="fg">
                    <label class="fl" for="sim-keterangan">Keterangan <span style="color:var(--mut);font-weight:400;">(opsional)</span></label>
                    <textarea id="sim-keterangan" name="keterangan" rows="1" maxlength="1000" placeholder="Tujuan atau catatan singkat simulasi">{{ old('keterangan') }}</textarea>
                </div>
            </div>
            <div class="sub" style="margin:12px 0 0;">Seluruh mata anggaran aktif akan disalin dengan Pagu Simulasi awal sama seperti Pagu Eksisting. Nilainya dapat diubah pada langkah berikutnya.</div>
        </div>

        <div class="sim-create-actions">
            <a class="btn" href="{{ route('simulasi-anggaran.index') }}">Batal</a>
            <button type="submit" class="btn prim">Buat &amp; Lanjutkan</button>
        </div>
    </form>
</div>
@endsection
