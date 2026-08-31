{{--
    Paginasi Data Gaji & Tunjangan - salinan gtRenderPager() dari GAS:
    « ‹ [5 nomor di sekitar halaman aktif] › » lalu "Hal x/y", dan saat
    hanya ada satu halaman cukup "Menampilkan N pegawai".

    Di GAS tombolnya memanggil gtGoto(); di sini tiap nomor jadi tautan yang
    membawa seluruh query (mode/bulan/tahun/q) supaya filternya tidak hilang.

    @var \Illuminate\Pagination\LengthAwarePaginator $baris
    @var string $satuan  kata benda pada teks "Menampilkan N ..."
--}}
@php
    $satuan ??= 'pegawai';
    $halaman = $baris->currentPage();
    $totalHal = $baris->lastPage();
    $url = fn (int $n) => $baris->url($n);
    $dari = max(1, $halaman - 2);
    $sampai = min($totalHal, $halaman + 2);
@endphp

<div class="gt-pager">
    @if ($totalHal <= 1)
        <span class="gt-pg-info">Menampilkan {{ $baris->total() }} {{ $satuan }}</span>
    @else
        @if ($halaman <= 1)
            <button type="button" disabled>&laquo;</button>
            <button type="button" disabled>&lsaquo;</button>
        @else
            <a href="{{ $url(1) }}">&laquo;</a>
            <a href="{{ $url($halaman - 1) }}">&lsaquo;</a>
        @endif

        @if ($dari > 1)
            <span class="gt-pg-info">&hellip;</span>
        @endif

        @for ($i = $dari; $i <= $sampai; $i++)
            <a class="{{ $i === $halaman ? 'active' : '' }}" href="{{ $url($i) }}">{{ $i }}</a>
        @endfor

        @if ($sampai < $totalHal)
            <span class="gt-pg-info">&hellip;</span>
        @endif

        @if ($halaman >= $totalHal)
            <button type="button" disabled>&rsaquo;</button>
            <button type="button" disabled>&raquo;</button>
        @else
            <a href="{{ $url($halaman + 1) }}">&rsaquo;</a>
            <a href="{{ $url($totalHal) }}">&raquo;</a>
        @endif

        <span class="gt-pg-info">Hal {{ $halaman }}/{{ $totalHal }}</span>
    @endif
</div>
