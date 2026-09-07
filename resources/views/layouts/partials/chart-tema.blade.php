{{--
    Tema grafik bersama untuk seluruh halaman yang memakai Chart.js.

    Disertakan SETELAH <script> Chart.js dan SEBELUM skrip yang membuat
    grafiknya. Isinya dua hal:

    1. Warna bawaan Chart.js (tulisan sumbu, label, legenda, garis kisi)
       diambil dari token tema, bukan dari nilai abu-abu bawaan pustaka yang
       tidak terbaca di atas latar gelap.

    2. window.warnaGrafik() menyerahkan palet batang/segmen yang sudah sesuai
       mode aktif, supaya tiap halaman tidak lagi menulis '#0f172a' sendiri.
--}}
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    var akar = getComputedStyle(document.documentElement);
    var token = function (nama, cadangan) {
        return (akar.getPropertyValue(nama) || '').trim() || cadangan;
    };

    Chart.defaults.color = token('--ink', '#1e293b');
    Chart.defaults.borderColor = token('--line', '#e2e8f0');
    Chart.defaults.font.family = "system-ui,-apple-system,'Segoe UI Variable Text','Segoe UI',Roboto,Arial,sans-serif";

    /* Ujung batang dibulatkan dan lebarnya dibatasi. Batang bersudut siku
       selebar kolom adalah ciri grafik bawaan pustaka; membulatkan
       ujungnya menyamakan bahasa bentuknya dengan kartu dan tombol di
       sekelilingnya, dan batas lebar menahan satu batang tunggal
       melebar jadi balok. */
    if (Chart.defaults.datasets && Chart.defaults.datasets.bar) {
        Chart.defaults.datasets.bar.borderRadius = 6;
        Chart.defaults.datasets.bar.borderSkipped = false;
        Chart.defaults.datasets.bar.maxBarThickness = 46;
    }

    /* Balon keterangan: permukaan gelap membulat, bukan kotak hitam
       bawaan yang sudutnya tajam. */
    if (Chart.defaults.plugins && Chart.defaults.plugins.tooltip) {
        var t = Chart.defaults.plugins.tooltip;
        t.backgroundColor = 'rgba(15,23,42,.94)';
        t.cornerRadius = 10;
        t.padding = 10;
        t.displayColors = true;
        t.boxPadding = 4;
        t.titleFont = { weight: '600', size: 12 };
        t.bodyFont = { size: 12 };
    }

    /* Titik pada grafik garis baru muncul saat disentuh - garis yang
       penuh bulatan sulit dibaca kalau datanya 12 bulan penuh. */
    if (Chart.defaults.elements) {
        if (Chart.defaults.elements.point) {
            Chart.defaults.elements.point.radius = 0;
            Chart.defaults.elements.point.hoverRadius = 5;
            Chart.defaults.elements.point.hitRadius = 12;
        }
        if (Chart.defaults.elements.line) {
            Chart.defaults.elements.line.tension = 0.32;
            Chart.defaults.elements.line.borderWidth = 2.5;
        }
    }

    if (Chart.defaults.scales) {
        ['linear', 'category', 'logarithmic'].forEach(function (jenis) {
            if (! Chart.defaults.scales[jenis]) return;
            Chart.defaults.scales[jenis].ticks = Chart.defaults.scales[jenis].ticks || {};
            Chart.defaults.scales[jenis].ticks.color = token('--mut', '#64748b');
            Chart.defaults.scales[jenis].grid = Chart.defaults.scales[jenis].grid || {};
            Chart.defaults.scales[jenis].grid.color = token('--line', '#e2e8f0');
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
            utama: token('--chart-utama', '#0f172a'),
            emas: token('--gold', '#f59e0b'),
            sisa: token('--chart-sisa', '#e2e8f0'),
            teks: token('--ink', '#1e293b'),
            redup: token('--mut', '#64748b'),
        };
    };
})();
</script>
