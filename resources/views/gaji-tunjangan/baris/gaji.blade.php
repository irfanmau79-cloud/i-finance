<tr>
    <td class="ident"><strong>{{ $r['nama'] }}</strong></td>
    <td>{{ $r['nip'] }}</td>
    <td>{{ $r['norek'] ?: '-' }}</td>
    <td class="ident"><span class="sub">{{ $r['jabatan'] ?: '-' }}</span></td>
    <td class="num">{{ $rp($r['gaji_pokok']) }}</td>
    <td class="num">{{ $rp($r['suami_istri']) }}</td>
    <td class="num">{{ $rp($r['anak']) }}</td>
    <td class="num"><strong>{{ $rp($r['bruto1']) }}</strong></td>
    <td class="num">{{ $rp($r['tj_umum']) }}</td>
    <td class="num">{{ $rp($r['tj_struktural']) }}</td>
    <td class="num">{{ $rp($r['tj_fungsional']) }}</td>
    <td class="num">{{ $rp($r['tj_beras']) }}</td>
    <td class="num">{{ $rp($r['tj_pph']) }}</td>
    <td class="num">{{ $rp($r['pembulatan']) }}</td>
    <td class="num"><strong>{{ $rp($r['bruto2']) }}</strong></td>
    <td class="num">{{ $rp($r['pot_beras']) }}</td>
    <td class="num">{{ $rp($r['pot_iwp8']) }}</td>
    <td class="num">{{ $rp($r['pot_iwp1']) }}</td>
    <td class="num">{{ $rp($r['pot_pph']) }}</td>
    <td class="num">{{ $rp($r['rumah_tanah']) }}</td>
    <td class="num">{{ $rp($r['lain_lain']) }}</td>
    <td class="num"><strong>{{ $rp($r['jml_potongan']) }}</strong></td>
    <td class="num"><strong>{{ $rp($r['jml_dibayarkan']) }}</strong></td>
</tr>
