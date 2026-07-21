@extends('layouts.app')

@section('activeNav', 'tk-form')
@section('title', 'Perubahan Data Tunjangan Keluarga')

@section('content')
<style>
  .tk-form-page{max-width:900px;margin:0 auto}
  .tk-form-page .tk-form-intro{color:var(--mut);font-size:13px;line-height:1.6;margin:6px 0 0}
  .tk-form-page .tk-form-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
  .tk-form-page .tk-form-field{margin-top:15px}
  .tk-form-page .tk-form-field>label,.tk-form-page .tk-form-label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:6px}
  .tk-form-page input:not([type="checkbox"]),.tk-form-page textarea{box-sizing:border-box;width:100%;padding:10px 11px;border:1px solid var(--line);border-radius:var(--radius-sm);background:#fff;color:var(--ink);font:inherit}
  .tk-form-page input:focus,.tk-form-page textarea:focus{outline:2px solid rgba(21,49,74,.12);border-color:var(--navy)}
  .tk-form-page textarea{min-height:95px;resize:vertical}
  .tk-form-page .tk-form-member{border:1px solid var(--line);border-radius:var(--radius-sm);padding:15px;margin-top:15px;background:#fbfcfe}
  .tk-form-page .tk-form-member>strong{display:block;color:var(--navy);font-size:14px}
  .tk-form-page .tk-form-check{display:flex;align-items:center;gap:8px;color:var(--ink);font-size:13px;font-weight:500;margin-bottom:0}
  .tk-form-page .tk-form-check input{width:auto;flex:0 0 auto}
  .tk-form-page .tk-form-note{display:block;color:var(--mut);font-size:11.5px;margin-top:6px}
  .tk-form-page .tk-form-success{padding:11px 13px;margin-top:14px;background:var(--ok-bg);color:var(--ok);border-radius:var(--radius-sm);font-size:13px}
  .tk-form-page .tk-form-errors{padding:11px 13px;margin-top:14px;background:var(--err-bg);color:var(--err);border-radius:var(--radius-sm);font-size:13px}
  .tk-form-page .tk-form-errors ul{margin:6px 0 0;padding-left:18px}
  .tk-form-page .tk-form-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;flex-wrap:wrap}
  .tk-form-page .tk-form-secondary{background:#e7edf3;color:var(--navy);border-color:#d7e0e9}
  .tk-form-page .tk-form-honeypot{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}
  @media(max-width:650px){.tk-form-page .tk-form-grid{grid-template-columns:1fr}.tk-form-page .dash-card{padding:18px}.tk-form-page .tk-form-actions .btn{width:100%}}
</style>

<div class="tk-form-page">
  <div class="page-head">
    <div>
      <div class="ph-title">Perubahan Data Tunjangan Keluarga</div>
      <div class="tk-form-intro">Isi data terbaru dan unggah dokumen pendukung. Berkas disimpan secara private dan hanya dapat diakses petugas berwenang.</div>
    </div>
  </div>

  <div class="dash-card">
    @if (session('success'))
      <div class="tk-form-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
      <div class="tk-form-errors">
        <strong>Terjadi kesalahan:</strong>
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('tunjangan.submit') }}" enctype="multipart/form-data">
      @csrf
      <input class="tk-form-honeypot" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="tk-form-grid">
        <div class="tk-form-field">
          <label for="nama-pegawai">Nama Pegawai</label>
          <input id="nama-pegawai" name="nama_pegawai" value="{{ old('nama_pegawai') }}" required maxlength="150">
        </div>
        <div class="tk-form-field">
          <label for="nip">NIP</label>
          <input id="nip" name="nip" value="{{ old('nip') }}" maxlength="30">
        </div>
      </div>

      <div class="tk-form-member">
        <strong>Pasangan</strong>
        <div class="tk-form-grid">
          <div class="tk-form-field">
            <label for="pasangan-nama">Nama</label>
            <input id="pasangan-nama" name="pasangan[nama]" value="{{ old('pasangan.nama') }}">
          </div>
          <div class="tk-form-field">
            <label for="pasangan-tanggal-lahir">Tanggal Lahir</label>
            <input id="pasangan-tanggal-lahir" type="date" name="pasangan[tanggal_lahir]" value="{{ old('pasangan.tanggal_lahir') }}">
          </div>
        </div>
        <label class="tk-form-field tk-form-check">
          <input type="checkbox" name="pasangan[status_tunjangan]" value="1" @checked(old('pasangan.status_tunjangan'))>
          Tunjangan pasangan aktif
        </label>
      </div>

      <div id="anak-list">
        @foreach (old('anak', [[]]) as $i => $anak)
          <div class="tk-form-member anak">
            <strong>Anak</strong>
            <div class="tk-form-grid">
              <div class="tk-form-field">
                <label for="anak-{{ $i }}-nama">Nama</label>
                <input id="anak-{{ $i }}-nama" name="anak[{{ $i }}][nama]" value="{{ $anak['nama'] ?? '' }}">
              </div>
              <div class="tk-form-field">
                <label for="anak-{{ $i }}-tanggal-lahir">Tanggal Lahir</label>
                <input id="anak-{{ $i }}-tanggal-lahir" type="date" name="anak[{{ $i }}][tanggal_lahir]" value="{{ $anak['tanggal_lahir'] ?? '' }}">
              </div>
            </div>
            <div class="tk-form-grid">
              <label class="tk-form-field tk-form-check">
                <input type="checkbox" name="anak[{{ $i }}][status_tunjangan]" value="1" @checked($anak['status_tunjangan'] ?? false)>
                Penerima tunjangan
              </label>
              <label class="tk-form-field tk-form-check">
                <input type="checkbox" name="anak[{{ $i }}][perpanjangan_kuliah]" value="1" @checked($anak['perpanjangan_kuliah'] ?? false)>
                Perpanjangan kuliah (usia 21–25)
              </label>
            </div>
            <div class="tk-form-field">
              <label for="anak-{{ $i }}-keterangan">Keterangan</label>
              <input id="anak-{{ $i }}-keterangan" name="anak[{{ $i }}][keterangan]" value="{{ $anak['keterangan'] ?? '' }}">
            </div>
          </div>
        @endforeach
      </div>

      <div class="tk-form-actions">
        <button type="button" class="btn tk-form-secondary" id="add-anak">+ Tambah Anak</button>
      </div>

      <div class="tk-form-field">
        <label for="keterangan">Keterangan Perubahan</label>
        <textarea id="keterangan" name="keterangan" required>{{ old('keterangan') }}</textarea>
      </div>
      <div class="tk-form-field">
        <label for="lampiran">Lampiran private (PDF/JPG/PNG, maks. 5 MB)</label>
        <input id="lampiran" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png" required>
        <small class="tk-form-note">Nama file pada penyimpanan akan diacak.</small>
      </div>

      <div class="tk-form-actions" style="justify-content:flex-end">
        <button type="submit" class="btn prim">Kirim Pengajuan</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('add-anak').addEventListener('click', function () {
  const list = document.getElementById('anak-list');
  const index = list.children.length;
  if (index >= 10) return;

  const member = document.createElement('div');
  member.className = 'tk-form-member anak';
  member.innerHTML = '<strong>Anak</strong>'
    + '<div class="tk-form-grid"><div class="tk-form-field"><label for="anak-' + index + '-nama">Nama</label><input id="anak-' + index + '-nama" name="anak[' + index + '][nama]"></div>'
    + '<div class="tk-form-field"><label for="anak-' + index + '-tanggal-lahir">Tanggal Lahir</label><input id="anak-' + index + '-tanggal-lahir" type="date" name="anak[' + index + '][tanggal_lahir]"></div></div>'
    + '<div class="tk-form-grid"><label class="tk-form-field tk-form-check"><input type="checkbox" name="anak[' + index + '][status_tunjangan]" value="1"> Penerima tunjangan</label>'
    + '<label class="tk-form-field tk-form-check"><input type="checkbox" name="anak[' + index + '][perpanjangan_kuliah]" value="1"> Perpanjangan kuliah (usia 21–25)</label></div>'
    + '<div class="tk-form-field"><label for="anak-' + index + '-keterangan">Keterangan</label><input id="anak-' + index + '-keterangan" name="anak[' + index + '][keterangan]"></div>';
  list.appendChild(member);
});
</script>
@endsection
