{{--
    Tema grafik bersama untuk seluruh halaman yang memakai Chart.js.

    Disertakan SETELAH <script> Chart.js dan SEBELUM skrip yang membuat
    grafiknya. Isinya dua hal:

    1. Warna bawaan Chart.js (tulisan sumbu, label, legenda, garis kisi)
       diambil dari token tema, bukan dari nilai abu-abu bawaan pustaka yang
       tidak terbaca di atas latar gelap.

    2. window.warnaGrafik() menyerahkan palet batang/segmen yang sudah sesuai
       mode aktif, supaya tiap halaman tidak lagi menulis '#15314a' sendiri.
--}}
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    var akar = getComputedStyle(document.documentElement);
    var token = function (nama, cadangan) {
        return (akar.getPropertyValue(nama) || '').trim() || cadangan;
    };

    Chart.defaults.color = token('--ink', '#1f2937');
    Chart.defaults.borderColor = token('--line', '#e5e9f0');
    Chart.defaults.font.family = "'Segoe UI',system-ui,-apple-system,Arial,sans-serif";

    if (Chart.defaults.scales) {
        ['linear', 'category', 'logarithmic'].forEach(function (jenis) {
            if (! Chart.defaults.scales[jenis]) return;
            Chart.defaults.scales[jenis].ticks = Chart.defaults.scales[jenis].ticks || {};
            Chart.defaults.scales[jenis].ticks.color = token('--mut', '#64748b');
            Chart.defaults.scales[jenis].grid = Chart.defaults.scales[jenis].grid || {};
            Chart.defaults.scales[jenis].grid.color = token('--line', '#e5e9f0');
        });
    }

    /**
     * Palet grafik menurut mode tampilan yang sedang aktif.
     *
     * utama  - batang dan segmen pokok (navy di mode terang, biru cerah di gelap)
     * emas   - garis target, sama di semua mode
     * sisa   - segmen "sisa"/"belum terpakai" pada grafik donat
     */
    window.warnaGrafik = function () {
        return {
            utama: token('--chart-utama', '#15314a'),
            emas: token('--gold', '#d9a938'),
            sisa: token('--chart-sisa', '#dbe5ee'),
            teks: token('--ink', '#1f2937'),
            redup: token('--mut', '#64748b'),
        };
    };
})();
</script>
