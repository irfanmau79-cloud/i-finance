@php
    $row = ($timAwal ?? [])[$i] ?? [];
    $penerimaIndexVal = (string) old('penerima_index', ($npdEdit->tim[$i]->is_penerima ?? false) ? $i : null);
@endphp
<div class="pen" data-tim-row>
    <h4>{{ $t->nama }} <span style="font-weight:400;color:var(--mut);">{{ $t->jabatan ?? '' }}</span></h4>
    <div class="form-grid">
        <div class="fg"><label class="fl">Penerima Dana</label>
            <label style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                <input type="radio" name="penerima_index" value="{{ $i }}" @checked($penerimaIndexVal === (string) $i)>
                <span>Jadikan penerima transfer</span>
            </label>
        </div>
        <div class="fg"><label class="fl">BBM (liter)</label><input type="number" step="0.01" min="0" data-bbm-liter name="tim[{{ $i }}][bbm_liter]" value="{{ old("tim.$i.bbm_liter", $row['bbm_liter'] ?? '') }}"></div>
        <div class="fg"><label class="fl">Tarif BBM (Rp/liter)</label><input type="number" step="0.01" min="0" data-bbm-tarif name="tim[{{ $i }}][bbm_tarif]" value="{{ old("tim.$i.bbm_tarif", $row['bbm_tarif'] ?? '') }}"></div>
        <div class="fg"><label class="fl">Tol (Rp)</label><input type="number" step="0.01" min="0" data-tol name="tim[{{ $i }}][tol]" value="{{ old("tim.$i.tol", $row['tol'] ?? '') }}"></div>
        <div class="fg"><label class="fl">Tiket (Rp)</label><input type="number" step="0.01" min="0" data-tiket name="tim[{{ $i }}][tiket]" value="{{ old("tim.$i.tiket", $row['tiket'] ?? '') }}"></div>
        <div class="fg"><label class="fl">Representatif (Rp)</label><input type="number" step="0.01" min="0" data-representatif name="tim[{{ $i }}][representatif]" value="{{ old("tim.$i.representatif", $row['representatif'] ?? '') }}"></div>
        <div class="fg"><label class="fl">Subtotal (otomatis)</label><input type="text" data-subtotal readonly value="Rp 0" style="background:var(--surface-2);font-weight:700;"></div>
    </div>
</div>
