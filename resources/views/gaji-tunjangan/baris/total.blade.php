<tr>
    <td class="ident"><strong>{{ $r['nama'] }}</strong></td>
    <td>{{ $r['nip'] }}</td>
    <td class="ident"><span class="sub">{{ $r['jabatan'] ?: '-' }}</span></td>
    <td>{{ $r['gol'] ?: '-' }}</td>
    <td class="num">{{ $rp($r['gaji_bruto']) }}</td>
    <td class="num">{{ $rp($r['tpp_bruto']) }}</td>
    <td class="num">{{ $rp($r['tol_bruto']) }}</td>
    <td class="num"><strong>{{ $rp($r['total_bruto']) }}</strong></td>
    <td class="num">{{ $rp($r['pot_iuran']) }}</td>
    <td class="num">{{ $rp($r['pot_koperasi']) }}</td>
    <td class="num">{{ $rp($r['pot_zakat']) }}</td>
    <td class="num">{{ $rp($r['gaji_netto']) }}</td>
    <td class="num">{{ $rp($r['tpp_netto']) }}</td>
    <td class="num">{{ $rp($r['tol_netto']) }}</td>
    <td class="num"><strong>{{ $rp($r['total_netto']) }}</strong></td>
</tr>
