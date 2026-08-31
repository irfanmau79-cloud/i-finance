{{-- Baris rincian: nomor, uraian, dan nominal di KOLOM TENGAH. Kolom kanan
     sengaja dikosongkan - di situlah subtotal & total berada. Port _rowKet(). --}}
<tr>
    <td class="kt-no">{{ $no }}</td>
    <td class="kt-lbl">{{ $label }}</td>
    <td class="rpC">Rp</td>
    <td class="numC">{{ (float) $nilai === 0.0 ? '-' : fmt_rupiah($nilai) }}</td>
    <td class="rpR"></td>
    <td class="numR"></td>
</tr>
