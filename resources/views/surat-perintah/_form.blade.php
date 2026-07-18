@php
    $sp = $suratPerintah ?? null;
@endphp

<div class="field">
    <label for="nomor_sp">Nomor SP</label>
    <input type="text" id="nomor_sp" name="nomor_sp" value="{{ old('nomor_sp', $sp->nomor_sp ?? '') }}">
</div>

<div class="field">
    <label for="tanggal_sp">Tanggal SP</label>
    <input type="date" id="tanggal_sp" name="tanggal_sp" value="{{ old('tanggal_sp', $sp?->tanggal_sp?->format('Y-m-d')) }}">
</div>

<div class="field">
    <label for="unit_kerja">Unit Kerja</label>
    <select id="unit_kerja" name="unit_kerja">
        <option value="">-- Pilih Unit Kerja --</option>
        @foreach ([
            'Inspektur Pembantu I',
            'Inspektur Pembantu II',
            'Inspektur Pembantu III',
            'Inspektur Pembantu IV',
            'Inspektur Pembantu Investigasi',
            'Sekretariat',
            'Subbagian Tata Usaha',
        ] as $unit)
            <option value="{{ $unit }}" @selected(old('unit_kerja', $sp->unit_kerja ?? null) === $unit)>{{ $unit }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="lokasi">Lokasi</label>
    <input type="text" id="lokasi" name="lokasi" value="{{ old('lokasi', $sp->lokasi ?? '') }}">
</div>

<div class="field">
    <label for="nama_pengirim">Nama Pengirim</label>
    <input type="text" id="nama_pengirim" name="nama_pengirim" value="{{ old('nama_pengirim', $sp->nama_pengirim ?? '') }}">
</div>

<div class="field">
    <label for="tujuan_transfer">Tujuan Transfer</label>
    <input type="text" id="tujuan_transfer" name="tujuan_transfer" value="{{ old('tujuan_transfer', $sp->tujuan_transfer ?? '') }}">
</div>

<div class="field">
    <label for="irban_dibayar">Irban Dibayar</label>
    <select id="irban_dibayar" name="irban_dibayar">
        <option value="">-- Pilih --</option>
        <option value="1" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : null) === '1')>Ya</option>
        <option value="0" @selected(old('irban_dibayar', isset($sp) ? ($sp->irban_dibayar ? '1' : '0') : null) === '0')>Tidak</option>
    </select>
</div>

<div class="field">
    <label for="rincian_tgl_bayar">Rincian Tanggal Bayar</label>
    <input type="text" id="rincian_tgl_bayar" name="rincian_tgl_bayar" value="{{ old('rincian_tgl_bayar', $sp->rincian_tgl_bayar ?? '') }}">
</div>

<div class="field">
    <label for="keterangan">Keterangan</label>
    <textarea id="keterangan" name="keterangan">{{ old('keterangan', $sp->keterangan ?? '') }}</textarea>
</div>

<div class="field">
    <label for="status_sp">Status SP</label>
    <select id="status_sp" name="status_sp">
        <option value="">-- Pilih Status SP --</option>
        <option value="Baru" @selected(old('status_sp', $sp->status_sp ?? null) === 'Baru')>Baru</option>
        <option value="Revisi" @selected(old('status_sp', $sp->status_sp ?? null) === 'Revisi')>Revisi</option>
    </select>
</div>

<div class="field">
    <label for="file_url">File SP (PDF)</label>
    @if ($sp)
        <p>File saat ini: <a href="{{ asset('storage/' . $sp->file_url) }}" target="_blank">Lihat PDF</a></p>
        <p class="hint">Kosongkan jika tidak ingin mengganti file yang sudah ada.</p>
    @endif
    <input type="file" id="file_url" name="file_url" accept="application/pdf">
</div>
