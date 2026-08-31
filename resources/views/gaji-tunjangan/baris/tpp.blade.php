{{-- Baris TPP Beban Kerja / TPP Kondisi Kerja - susunan sel gtTabelTPP(). --}}
@php
    $isKondisi = $jenis === 'kondisi';

    // GAS: pv % 1 === 0 ? pv+'%' : pv.toFixed(2)+'%' - persentase bulat tampil
    // tanpa desimal, sisanya dua desimal bertitik mengikuti toFixed().
    $pv = (float) $r['persen'];
    $persen = fmod($pv, 1) === 0.0
        ? (string) (int) $pv
        : number_format($pv, 2, '.', '');
@endphp
<tr>
    <td>@include('gaji-tunjangan._peg', ['r' => $r, 'norek' => false])</td>
    <td class="gt-ctr">{{ $r['gol'] }}</td>
    <td class="gt-num">{{ $rp($r['besaran100']) }}</td>
    <td class="gt-ctr"><span class="gt-pill">{{ $persen }}%</span></td>
    <td class="gt-num gt-strong">{{ $rp($r['penilaian']) }}</td>
    <td class="gt-num">{{ $rp($r['tunj_pph21']) }}</td>
    <td class="gt-num gt-strong">{{ $rp($r['tpp_bruto']) }}</td>
    <td class="gt-num">{{ $rp($r['pot_pph21']) }}</td>
    @if ($isKondisi)
        <td class="gt-num">{{ $rp($r['pengurang_ikp']) }}</td>
    @endif
    <td class="gt-num gt-strong">{{ $rp($r['netto']) }}</td>
</tr>
