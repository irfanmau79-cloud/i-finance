{{--
    Baris "Sisa Anggaran Kas" pada kotak mata anggaran formulir NPD.

    Sisa Anggaran di atasnya dihitung per TAGGING terhadap pagu setahun.
    Baris ini melengkapinya dengan sisa per KODE REKENING dalam Sub Kegiatan
    terhadap Rencana Anggaran Kas (RAK) kumulatif s.d. bulan berjalan -
    hitungannya di AnggaranRealisasiService::sisaAnggaranKas().

    Sifatnya INFORMASI. Batas nominal NPD tetap Sisa Anggaran (sisa_tersedia);
    sisa kas yang habis tidak menolak penyimpanan.

    Dipakai bersama oleh formulir NPD yang punya kotak mata anggaran. Angkanya
    ikut di data tiap mata anggaran ('sisa_kas', 'rak_kas'); formulir cukup
    memanggil window.NpdSisaKas.tampil(m) saat mata anggaran dipilih dan
    window.NpdSisaKas.teks(m) untuk halaman Review.
--}}
@php
    $bulanKas = \App\Services\AnggaranRealisasiService::BULAN[now()->month - 1].' '.now()->year;
@endphp
<div class="ai sisa-kas">
    <span class="k">
        Sisa Anggaran Kas
        <span class="sisa-kas-ket">kode rekening ini, semua tagging &middot; RAK s.d. {{ $bulanKas }}</span>
    </span>
    <span class="v" id="ma-sisa-kas"></span>
</div>

<script>
window.NpdSisaKas = (function () {
    function rupiah(n) {
        return 'Rp ' + (Number(n) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // RAK yang belum diimpor TIDAK diperkirakan dari pagu - ditulis apa adanya.
    function teks(m) {
        return m.sisa_kas === null || m.sisa_kas === undefined ? 'RAK belum tersedia' : rupiah(m.sisa_kas);
    }

    function tampil(m) {
        var el = document.getElementById('ma-sisa-kas');
        if (! el) return;

        var kosong = m.sisa_kas === null || m.sisa_kas === undefined;
        el.textContent = teks(m);
        el.className = 'v sisa-kas-nilai' + (kosong ? ' kosong' : (Number(m.sisa_kas) < 0 ? ' minus' : ''));
        el.title = kosong ? '' : 'RAK s.d. {{ $bulanKas }}: ' + rupiah(m.rak_kas) + ' — terpakai: ' + rupiah(m.rak_kas - m.sisa_kas);
    }

    return { teks: teks, tampil: tampil };
})();
</script>
