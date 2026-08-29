@extends('layouts.app')

@section('activeNav', 'simulasi-pergeseran')
@section('title', 'Buat Simulasi Baru')

@section('content')
<style>
    .sim-profil{max-width:720px}
    .sim-profil-head{display:flex;align-items:center;gap:14px;margin-bottom:22px}
    .sim-profil-icon{width:46px;height:46px;flex:0 0 46px;border-radius:14px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center}
    .sim-profil-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .sim-profil-title{font-size:20px;font-weight:800;color:var(--navy);line-height:1.2}
    .sim-profil-sub{font-size:12.5px;color:var(--mut);margin-top:3px}

    .sim-field{margin-bottom:18px}
    .sim-field-top{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px}
    .sim-field label{font-size:12.5px;font-weight:700;color:var(--navy)}
    .sim-opsional{font-size:11px;font-weight:600;color:var(--mut);background:#f1f5f9;border-radius:20px;padding:2px 9px}
    .sim-hitung{font-size:11px;color:var(--mut);font-variant-numeric:tabular-nums}

    .sim-field input[type=text],.sim-field textarea{
        width:100%;box-sizing:border-box;background:#fff;border:1.5px solid var(--line);border-radius:11px;
        padding:12px 14px;font-family:inherit;font-size:14px;color:var(--ink);
        transition:border-color .15s,box-shadow .15s;
    }
    .sim-field input[type=text]{font-weight:600}
    .sim-field textarea{resize:vertical;min-height:118px;line-height:1.55;font-size:13px;font-weight:400}
    .sim-field input[type=text]:hover,.sim-field textarea:hover{border-color:#c3d2e1}
    .sim-field input[type=text]:focus,.sim-field textarea:focus{
        outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.11);
    }
    .sim-bantu{font-size:11.5px;color:var(--mut);margin-top:6px}

    .sim-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:24px;padding-top:18px;border-top:1px solid var(--line)}
    @media(max-width:620px){.sim-actions{flex-direction:column-reverse}.sim-actions .btn{width:100%;text-align:center}}
</style>

<div class="page-head">
    <div>
        <div class="ph-crumb">Analisis / Simulasi Pergeseran/Perubahan / <b>Buat Baru</b></div>
        <div class="ph-title">Buat Simulasi Baru</div>
    </div>
</div>

<div class="dash-card wf-card">
    <div class="sim-profil">
        <div class="sim-profil-head">
            <div class="sim-profil-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"/><path d="M6 16V8"/><path d="M12 16V4"/><path d="M18 16v-5"/><path d="m16 7 2-2 2 2"/></svg>
            </div>
            <div>
                <div class="sim-profil-title">Profil Simulasi</div>
                <div class="sim-profil-sub">Berikan nama yang mudah dikenali agar simulasi dapat ditemukan kembali.</div>
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

            <div class="sim-field">
                <div class="sim-field-top">
                    <label for="sim-nama">Nama Simulasi</label>
                    <span class="sim-hitung" id="sim-nama-hitung">0/150</span>
                </div>
                <input id="sim-nama" type="text" name="nama" value="{{ old('nama') }}" required maxlength="150" autofocus
                       placeholder="Contoh: Simulasi Pergeseran Semester 2">
            </div>

            <div class="sim-field">
                <div class="sim-field-top">
                    <label for="sim-keterangan">Keterangan <span class="sim-opsional">opsional</span></label>
                    <span class="sim-hitung" id="sim-ket-hitung">0/1000</span>
                </div>
                <textarea id="sim-keterangan" name="keterangan" maxlength="1000"
                          placeholder="Tujuan, dasar usulan, atau catatan lain tentang simulasi ini.">{{ old('keterangan') }}</textarea>
                <div class="sim-bantu">Boleh dikosongkan. Berguna saat simulasinya dibuka lagi beberapa bulan kemudian.</div>
            </div>

            <div class="sim-actions">
                <a class="btn" href="{{ route('simulasi-anggaran.index') }}">Batal</a>
                <button type="submit" class="btn prim">Buat &amp; Lanjutkan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    [['sim-nama', 'sim-nama-hitung', 150], ['sim-keterangan', 'sim-ket-hitung', 1000]].forEach(function (p) {
        var isian = document.getElementById(p[0]);
        var hitung = document.getElementById(p[1]);
        if (!isian || !hitung) return;

        var perbarui = function () { hitung.textContent = isian.value.length + '/' + p[2]; };
        isian.addEventListener('input', perbarui);
        perbarui();
    });
})();
</script>
@endsection
