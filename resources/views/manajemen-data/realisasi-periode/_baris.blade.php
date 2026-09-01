{{--
    Satu baris pohon realisasi. Indentasi dan bobot hurufnya datang dari
    $gaya supaya kelima level memakai markup yang sama persis dan tidak
    perlu diulang lima kali di halaman induknya.
--}}
<tr style="background:{{ $gaya['bg'] }};">
    <td style="padding-left:{{ 12 + $gaya['indent'] }}px;font-weight:{{ $gaya['weight'] }};color:{{ $gaya['color'] }};">
        {{ $nama }}
        @if (! empty($uraian))
            <span class="sub">{{ $uraian }}</span>
        @endif
    </td>
    <td class="num">Rp {{ fmt_rupiah($angka['pagu']) }}</td>
    <td class="num">Rp {{ fmt_rupiah($angka['realisasi_npd']) }}</td>
    <td class="num">Rp {{ fmt_rupiah($angka['realisasi_ls']) }}</td>
    <td class="num" style="font-weight:{{ max($gaya['weight'], 600) }};">Rp {{ fmt_rupiah($angka['realisasi_aktual']) }}</td>
    <td class="num">{{ number_format($angka['persentase_realisasi'], 2, ',', '.') }}%</td>
</tr>
