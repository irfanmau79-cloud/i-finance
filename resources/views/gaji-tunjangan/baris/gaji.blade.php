{{-- Baris Gaji Induk - susunan sel persis gtTabelGaji() di GAS. --}}
<tr>
    <td>@include('gaji-tunjangan._peg', ['r' => $r, 'norek' => true])</td>
    <td class="gt-ctr">{{ $r['status'] }}<br>{{ $r['gol'] }}</td>
    <td class="gt-num">
        {{ $rp($r['gaji_pokok']) }}<br>
        {{ $rp($r['suami_istri']) }}<br>
        {{ $rp($r['anak']) }}<br>
        <span class="gt-strong">{{ $rp($r['bruto1']) }}</span>
    </td>
    <td class="gt-num">
        {{ $rp($r['tj_umum']) }}<br>
        {{ $rp($r['tj_struktural']) }}<br>
        {{ $rp($r['tj_fungsional']) }}
    </td>
    <td class="gt-num">
        {{ $rp($r['tj_beras']) }}<br>
        {{ $rp($r['tj_pph']) }}<br>
        {{ $rp($r['pembulatan']) }}
    </td>
    <td class="gt-num gt-strong">{{ $rp($r['bruto2']) }}</td>
    <td class="gt-num">
        {{ $rp($r['pot_beras']) }} / {{ $rp($r['pot_iwp8']) }}<br>
        {{ $rp($r['pot_iwp1']) }} / {{ $rp($r['pot_pph']) }}
    </td>
    <td class="gt-num">
        {{ $rp($r['rumah_tanah']) }}<br>
        {{ $rp($r['lain_lain']) }}
    </td>
    <td class="gt-num">{{ $rp($r['jml_potongan']) }}</td>
    <td class="gt-num gt-strong">{{ $rp($r['jml_dibayarkan']) }}</td>
</tr>
