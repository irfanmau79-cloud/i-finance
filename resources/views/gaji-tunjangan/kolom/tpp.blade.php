{{--
    Header TPP Beban Kerja & TPP Kondisi Kerja - susunan persis gtTabelTPP()
    di GAS. Kolom "Pengurang IKP" hanya ada di Kondisi Kerja, sehingga grup
    Potongan membentang 2 kolom di sana dan 1 kolom di Beban Kerja (colPot).
--}}
@php($isKondisi = $jenis === 'kondisi')
<thead>
    <tr>
        <th rowspan="2">Nama / NIP<br>Jabatan</th>
        <th rowspan="2">Golongan</th>
        <th colspan="3">Perhitungan</th>
        <th rowspan="2">Tunjangan<br>PPh 21</th>
        <th rowspan="2">Jumlah<br>TPP Bruto</th>
        <th colspan="{{ $isKondisi ? 2 : 1 }}">Potongan</th>
        <th rowspan="2">Jumlah<br>Diterima<br>Netto</th>
    </tr>
    <tr>
        <th>Besaran TPP<br>100%</th>
        <th>Prosentase<br>Kinerja</th>
        <th>Jumlah Penilaian<br>Kinerja (TPP)</th>
        <th>PPh 21</th>
        @if ($isKondisi)
            <th>Pengurang<br>IKP</th>
        @endif
    </tr>
</thead>
