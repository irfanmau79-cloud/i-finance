@extends('layouts.app')

@section('activeNav', 'simulasi-realisasi')
@section('title', 'Buat Simulasi Realisasi')

@section('content')
<style>
    .sim-profil{max-width:720px}
    .sim-profil-head{display:flex;align-items:center;gap:14px;margin-bottom:22px}
    .sim-profil-icon{width:46px;height:46px;flex:0 0 46px;border-radius:14px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center}
    .sim-profil-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .sim-profil-title{font-size:20px;font-weight:800;color:var(--tegas);line-height:1.2}
    .sim-profil-sub{font-size:12.5px;color:var(--mut);margin-top:3px}
    .sim-field{margin-bottom:18px}
    .sim-field-top{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px}
    .sim-field label{font-size:12.5px;font-weight:700;color:var(--tegas)}
    .sim-opsional{font-size:11px;font-weight:600;color:var(--mut);background:var(--surface-3);border-radius:20px;padding:2px 9px}
    .sim-field input[type=text],.sim-field textarea{
        width:100%;box-sizing:border-box;background:var(--surface);border:1.5px solid var(--line);border-radius:11px;
        padding:12px 14px;font-family:inherit;font-size:14px;color:var(--ink);
        transition:border-color .15s,box-shadow .15s;
    }
    .sim-field input[type=text]{font-weight:600}
    .sim-field textarea{resize:vertical;min-height:118px;line-height:1.55;font-size:13px;font-weight:400}
    .sim-field input[type=text]:focus,.sim-field textarea:focus{
        outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.11);
    }
    .sim-bantu{font-size:11.5px;color:var(--mut);margin-top:6px}
    .sim-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:24px;padding-top:18px;border-top:1px solid var(--line)}
    @media(max-width:620px){.sim-actions{flex-direction:column-reverse}.sim-actions .btn{width:100%;text-align:center}}
</style>

<div class="page-head">
    <div>
        <div class="ph-crumb">Analisis / Simulasi Realisasi / <b>Buat Baru</b></div>
        <div class="ph-title">Buat Simulasi Realisasi</div>
    </div>
</div>

<div class="dash-card wf-card">
    <div class="sim-profil">
        <div class="sim-profil-head">
            <div class="sim-profil-icon">
                <svg viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
            </div>
            <div>
                <div class="sim-profil-title">Simulasi Realisasi</div>
                <div class="sim-profil-sub">Memperkirakan capaian anggaran sampai akhir tahun.</div>
            </div>
        </div>

        @if ($errors->any())
            <div class="err-box" style="display:block;margin-bottom:16px;">
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('simulasi-realisasi.store') }}">
            @csrf

            <div class="sim-field">
                <div class="sim-field-top"><label for="nama">Nama Simulasi</label></div>
                <input type="text" id="nama" name="nama" value="{{ old('nama') }}" maxlength="150" required
                       placeholder="Contoh: Proyeksi Capaian s.d. Desember 2026">
                <div class="sim-bantu">Dipakai untuk membedakan simulasi ini dari yang lain. Bisa dibuka dan diubah lagi kapan saja.</div>
            </div>

            <div class="sim-field">
                <div class="sim-field-top">
                    <label for="keterangan">Keterangan</label>
                    <span class="sim-opsional">Opsional</span>
                </div>
                <textarea id="keterangan" name="keterangan" maxlength="1000"
                          placeholder="Asumsi yang dipakai, misalnya kegiatan mana saja yang diperhitungkan.">{{ old('keterangan') }}</textarea>
            </div>

            <div class="sub" style="background:var(--info-bg);color:var(--info);border-radius:10px;padding:11px 13px;">
                Simulasi dibuat dari seluruh mata anggaran yang aktif saat ini, lengkap dengan pagu dan
                realisasi berjalannya. Setelah tersimpan, tiap mata anggaran bisa diisi beberapa rencana
                belanja bernama untuk melihat proyeksi capaiannya. Simulasi tidak pernah mengubah data
                anggaran maupun transaksi.
            </div>

            <div class="sim-actions">
                <a class="btn" href="{{ route('simulasi-realisasi.index') }}">Batal</a>
                <button type="submit" class="btn prim">Buat Simulasi</button>
            </div>
        </form>
    </div>
</div>
@endsection
