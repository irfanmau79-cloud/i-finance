{{-- Satu baris pohon realisasi untuk cetakan PDF. --}}
<tr class="{{ $kelas }}">
    <td style="padding-left:{{ 5 + $pad }}pt;">
        {{ $nama }}
        @if (! empty($uraian))
            <br><span class="uraian">{{ $uraian }}</span>
        @endif
    </td>
    <td class="num">{{ fmt_rupiah($angka['pagu']) }}</td>
    <td class="num">{{ fmt_rupiah($angka['realisasi_npd']) }}</td>
    <td class="num">{{ fmt_rupiah($angka['realisasi_ls']) }}</td>
    <td class="num">{{ fmt_rupiah($angka['realisasi_aktual']) }}</td>
    <td class="num">{{ number_format($angka['persentase_realisasi'], 2, ',', '.') }}%</td>
</tr>
