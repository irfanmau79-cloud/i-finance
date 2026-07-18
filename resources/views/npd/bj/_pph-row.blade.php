@php
    $jenisVal = is_array($pp) ? ($pp['jenis'] ?? '') : '';
    $nilaiVal = is_array($pp) ? ($pp['nilai'] ?? '') : '';
    $jenisOptions = ['PPh Pasal 21', 'PPh Pasal 22', 'PPh Pasal 23', 'PPh Pasal 4(2)'];
@endphp
<div class="pph-row" data-pph-row style="display:flex;gap:8px;align-items:flex-end;margin-top:6px;">
    <div style="flex:1.3;">
        <select name="penerima[{{ $i }}][pph_list][{{ $j }}][jenis]" data-pph-jenis>
            <option value="">&mdash; jenis &mdash;</option>
            @foreach ($jenisOptions as $opt)
                <option value="{{ $opt }}" @selected($jenisVal === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;">
        <input type="number" step="0.01" min="0" placeholder="Rp" data-pph-nilai
               name="penerima[{{ $i }}][pph_list][{{ $j }}][nilai]" value="{{ $nilaiVal }}">
    </div>
    <button type="button" class="del" style="position:static;width:30px;height:34px;flex:0 0 30px;" data-pph-remove>&times;</button>
</div>
