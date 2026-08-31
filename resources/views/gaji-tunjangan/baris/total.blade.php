{{-- Baris Total Penghasilan - susunan sel gtTabelTotal() di GAS. --}}
<tr>
    <td>@include('gaji-tunjangan._peg', ['r' => $r, 'norek' => false])</td>
    <td class="gt-ctr">{{ $r['gol'] }}</td>
    <td class="gt-num">{{ $rp($r['gaji_bruto']) }}</td>
    <td class="gt-num">{{ $rp($r['tpp_bruto']) }}</td>
    <td class="gt-num">{{ $rp($r['tol_bruto']) }}</td>
    <td class="gt-num gt-strong">{{ $rp($r['total_bruto']) }}</td>
    <td class="gt-num">{{ $rp($r['pot_iuran']) }}</td>
    <td class="gt-num">{{ $rp($r['pot_koperasi']) }}</td>
    <td class="gt-num">{{ $rp($r['pot_zakat']) }}</td>
    <td class="gt-num">{{ $rp($r['gaji_netto']) }}</td>
    <td class="gt-num">{{ $rp($r['tpp_netto']) }}</td>
    <td class="gt-num">{{ $rp($r['tol_netto']) }}</td>
    <td class="gt-num gt-strong">{{ $rp($r['total_netto']) }}</td>
</tr>
