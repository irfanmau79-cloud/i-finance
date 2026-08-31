{{--
    Blok identitas pegawai di kolom pertama - salinan gtSelPeg() dari GAS.
    Nomor rekening hanya ikut di tabel Gaji Induk (di GAS dipanggil dengan
    argumen tanpaNorek untuk TPP dan Total Penghasilan).

    @var array<string, mixed> $r
    @var bool $norek
--}}
<div class="gt-peg">
    <div class="n">{{ $r['nama'] }}</div>
    <div class="m">{{ $r['nip'] }}</div>
    @if ($norek ?? false)
        <div class="m">{{ $r['norek'] }}</div>
    @endif
    <div class="m">{{ $r['jabatan'] }}</div>
</div>
