@php
    /**
     * Timeline progres satu SP. Port tampilan renderTimelineSP() di
     * gas-lama/index.html: tujuh titik mendatar, garis penghubung mewarisi
     * warna titik sebelumnya, dan titik "Revisi" memakai warna oranye karena
     * artinya berbeda dari titik kemajuan biasa.
     */
    $titik = $tl['titik'] ?? [];
    $jumlah = count($titik);
@endphp

<div class="sp-tl-head">
    Timeline Progres
    @if (($tl['nomor_npd'] ?? '') !== '')
        &mdash; <span style="color:#475569;">NPD {{ $tl['nomor_npd'] }}</span>
    @endif
    @unless ($tl['ada_npd'] ?? false)
        <span style="color:#b45309;font-weight:600;">(NPD belum dibuat)</span>
    @endunless
</div>

<div class="sp-tl-scroll">
    <div class="sp-tl" style="min-width:{{ $jumlah * 118 }}px;">
        @foreach ($titik as $i => $p)
            @php
                $tercapai = (bool) ($p['tercapai'] ?? false);
                $isRevisi = ($p['label'] ?? '') === 'Revisi';
                $warna = $tercapai ? ($isRevisi ? '#b45309' : '#0f6e56') : '#cbd5e1';
            @endphp
            <div class="sp-tl-node">
                @unless ($loop->last)
                    <span class="sp-tl-line" style="background:{{ $tercapai ? $warna : '#e2e8f0' }};"></span>
                @endunless
                <span class="sp-tl-dot" style="background:{{ $tercapai ? $warna : '#fff' }};border:2px solid {{ $warna }};"></span>
                <div class="sp-tl-lbl" style="color:{{ $tercapai ? 'var(--navy)' : '#94a3b8' }};">{{ $p['label'] }}</div>
                @if (! empty($p['ts']))
                    <div class="sp-tl-ts">{{ $p['ts'] }}</div>
                @endif
                @if (! empty($p['catatan']))
                    <div class="sp-tl-note">{{ $p['catatan'] }}</div>
                @endif
                @if (! empty($p['peran']) && $tercapai)
                    <div class="sp-tl-role">{{ $p['peran'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

@php
    /**
     * Inti keadaan SP saat ini dalam satu kalimat. Timeline memberi tahu
     * SUDAH SAMPAI MANA; blok ini memberi tahu APA ARTINYA sekarang - yang
     * dicari orang begitu membuka barisnya.
     */
    $ringkasan = $tl['ringkasan'] ?? null;
@endphp

@if ($ringkasan)
    <div class="sp-tl-ringkas nada-{{ $ringkasan['nada'] }}">
        <span class="sp-tl-ringkas-dot" aria-hidden="true"></span>
        <div>
            <div class="sp-tl-ringkas-lbl">{{ $ringkasan['label'] }}</div>
            <div class="sp-tl-ringkas-teks">{{ $ringkasan['narasi'] }}</div>
        </div>
    </div>
@endif
