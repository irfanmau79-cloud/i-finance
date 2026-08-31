<tr>
    <td class="ident"><strong>{{ $r['nama'] }}</strong></td>
    <td>{{ $r['nip'] }}</td>
    <td class="ident"><span class="sub">{{ $r['jabatan'] ?: '-' }}</span></td>
    <td>{{ $r['gol'] ?: '-' }}</td>
    <td class="num">{{ $rp($r['besaran100']) }}</td>
    {{-- Prosentase Kinerja tampil apa adanya (mis. 98,74%), bukan dihitung
         ulang dari penilaian/besaran - nilainya diisi manual di berkas. --}}
    <td class="num"><span class="gt-persen">{{ number_format($r['persen'], 2, ',', '.') }}%</span></td>
    <td class="num">{{ $rp($r['penilaian']) }}</td>
    <td class="num">{{ $rp($r['tunj_pph21']) }}</td>
    <td class="num"><strong>{{ $rp($r['tpp_bruto']) }}</strong></td>
    <td class="num">{{ $rp($r['pot_pph21']) }}</td>
    <td class="num">{{ $rp($r['pengurang_ikp']) }}</td>
    <td class="num"><strong>{{ $rp($r['netto']) }}</strong></td>
</tr>
