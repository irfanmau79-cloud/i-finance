@extends('layouts.app')

@section('activeNav', 'verifikasi')
@section('title', 'Coret Dokumen Nota Pencairan Dana')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / Verifikasi NPD / <b>Coret Dokumen NPD</b></div>
        <div class="ph-title">Coret Dokumen Nota Pencairan Dana &mdash; {{ $npd->nomor_lengkap ?? 'Belum bernomor (masih Draft)' }}</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub" style="margin-bottom:14px;">
        Gambar bebas (freehand) langsung di atas dokumen PDF di bawah ({{ collect($dokumenList)->pluck('label')->implode(', ') }}), lalu isi Catatan Revisi dan klik &ldquo;Kembalikan ke BPP&rdquo;. Coretan akan tersimpan langsung ke masing-masing file PDF &mdash; hanya halaman 1 tiap dokumen yang bisa dicoret.
    </div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Gagal memproses aksi:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:14px;padding:12px;border:1px solid var(--line);border-radius:8px;">
        <label class="fl" style="margin:0;">Warna Pena</label>
        <input type="color" id="coret-color" value="#e11d48">
        <button type="button" class="ic-btn" id="coret-undo-btn" title="Undo Coretan Terakhir"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg></button>
        <button type="button" class="ic-btn danger" id="coret-clear-btn" title="Hapus Semua Coretan Baru"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        <span class="sub" id="coret-status" style="margin-left:auto;">Memuat dokumen&hellip;</span>
    </div>

    <div id="coret-dokumen-list"></div>

    <form method="POST" action="{{ route('npd.transisi', $npd) }}" id="coret-form" style="margin-top:16px;max-width:560px;">
        @csrf
        <input type="hidden" name="aksi" value="kembali_bpp">
        <input type="hidden" name="coretan_json" id="coret-json-field" value="">

        <label class="fl">Catatan Revisi (wajib)</label>
        <textarea name="catatan" id="coret-catatan" rows="3" required style="width:100%;box-sizing:border-box;">{{ old('catatan') }}</textarea>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
            <a class="btn" href="{{ route('npd.show', $npd) }}">Batal</a>
            <button type="submit" class="btn prim">Kembalikan ke BPP</button>
        </div>
    </form>
</div>

<script type="module">
import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.mjs';

const dokumenList = @json($dokumenList);
const strokesSebelumnya = @json($strokesSebelumnya);
const listEl = document.getElementById('coret-dokumen-list');
const statusEl = document.getElementById('coret-status');

let color = '#e11d48';
let newStrokes = [];
const pageStates = [];

document.getElementById('coret-color').addEventListener('input', function (e) {
    color = e.target.value;
});
document.getElementById('coret-undo-btn').addEventListener('click', function () {
    newStrokes.pop();
    redrawAll();
});
document.getElementById('coret-clear-btn').addEventListener('click', function () {
    newStrokes = [];
    redrawAll();
});

function redrawAll() {
    pageStates.forEach(function (ps) {
        ps.ctx.clearRect(0, 0, ps.canvas.width, ps.canvas.height);
        newStrokes
            .filter(function (s) { return s.dokumen === ps.dokumen && s.page === ps.pageNumber; })
            .forEach(function (s) { drawStroke(ps, s); });
    });
}

function drawStroke(ps, stroke) {
    if (!stroke.points || stroke.points.length < 2) return;
    ps.ctx.strokeStyle = stroke.color;
    ps.ctx.lineWidth = stroke.width * ps.canvas.width;
    ps.ctx.lineCap = 'round';
    ps.ctx.lineJoin = 'round';
    ps.ctx.beginPath();
    stroke.points.forEach(function (p, i) {
        var x = p[0] * ps.canvas.width, y = p[1] * ps.canvas.height;
        if (i === 0) { ps.ctx.moveTo(x, y); } else { ps.ctx.lineTo(x, y); }
    });
    ps.ctx.stroke();
}

function posFromEvent(ps, e) {
    var rect = ps.canvas.getBoundingClientRect();
    var clientX = e.touches ? e.touches[0].clientX : e.clientX;
    var clientY = e.touches ? e.touches[0].clientY : e.clientY;
    var x = rect.width ? (clientX - rect.left) / rect.width : 0;
    var y = rect.height ? (clientY - rect.top) / rect.height : 0;
    return [Math.min(Math.max(x, 0), 1), Math.min(Math.max(y, 0), 1)];
}

function withTimeout(promise, ms, pesan) {
    return Promise.race([
        promise,
        new Promise(function (_, reject) { setTimeout(function () { reject(new Error(pesan)); }, ms); }),
    ]);
}

async function renderDokumen(dok) {
    var section = document.createElement('div');
    section.style.marginBottom = '24px';

    var heading = document.createElement('h3');
    heading.style.marginBottom = '8px';
    heading.textContent = dok.label;
    section.appendChild(heading);

    var pagesWrap = document.createElement('div');
    pagesWrap.style.display = 'flex';
    pagesWrap.style.flexDirection = 'column';
    pagesWrap.style.alignItems = 'center';
    pagesWrap.style.gap = '16px';
    pagesWrap.style.background = '#e5e7eb';
    pagesWrap.style.padding = '16px';
    pagesWrap.style.borderRadius = '8px';
    pagesWrap.style.overflow = 'auto';
    section.appendChild(pagesWrap);

    listEl.appendChild(section);

    try {
        var resp = await fetch(dok.url);
        var bytes = await resp.arrayBuffer();
        var pdf = await withTimeout(pdfjsLib.getDocument({ data: bytes }).promise, 20000, 'Render PDF terlalu lama (timeout).');
    } catch (err) {
        pagesWrap.innerHTML = '';
        pagesWrap.style.background = 'transparent';
        pagesWrap.style.padding = '0';
        var errBox = document.createElement('div');
        errBox.className = 'err-box';
        errBox.style.display = 'block';
        errBox.innerHTML = 'Gagal menampilkan pratinjau &ldquo;' + dok.label + '&rdquo; untuk dicoret (' + err.message + '). '
            + 'Dokumen ini tidak akan dicoret pada pengiriman ini &mdash; Anda tetap bisa membukanya lewat '
            + '<a href="' + dok.url + '" target="_blank">tautan PDF asli</a>.';
        pagesWrap.appendChild(errBox);
        return;
    }

    for (var n = 1; n <= pdf.numPages; n++) {
        var page = await pdf.getPage(n);
        var viewport = page.getViewport({ scale: 1.5 });

        var wrap = document.createElement('div');
        wrap.style.position = 'relative';
        wrap.style.background = '#fff';
        wrap.style.boxShadow = '0 2px 10px rgba(15,23,42,.15)';
        wrap.style.width = viewport.width + 'px';
        wrap.style.height = viewport.height + 'px';

        var bg = document.createElement('canvas');
        bg.width = viewport.width;
        bg.height = viewport.height;
        bg.style.position = 'absolute';
        bg.style.top = '0';
        bg.style.left = '0';

        var draw = document.createElement('canvas');
        draw.width = viewport.width;
        draw.height = viewport.height;
        draw.style.position = 'absolute';
        draw.style.top = '0';
        draw.style.left = '0';
        draw.style.cursor = n === 1 ? 'crosshair' : 'not-allowed';

        wrap.appendChild(bg);
        wrap.appendChild(draw);

        var label = document.createElement('div');
        label.className = 'sub';
        label.style.textAlign = 'center';
        label.textContent = 'Halaman ' + n + (n > 1 ? ' (coretan hanya didukung di halaman 1)' : '');

        var col = document.createElement('div');
        col.appendChild(wrap);
        col.appendChild(label);
        pagesWrap.appendChild(col);

        try {
            await withTimeout(page.render({ canvasContext: bg.getContext('2d'), viewport: viewport }).promise, 20000, 'Render halaman terlalu lama (timeout).');
        } catch (err) {
            label.textContent = 'Halaman ' + n + ' gagal dirender (' + err.message + ') — tidak bisa dicoret.';
            console.error(err);
            continue;
        }

        var ctx = draw.getContext('2d');
        var ps = { dokumen: dok.key, pageNumber: n, canvas: draw, ctx: ctx };
        pageStates.push(ps);

        if (n === 1) {
            (function (ps, draw) {
                var drawing = false, current = null;

                function begin(e) {
                    drawing = true;
                    current = { dokumen: ps.dokumen, page: 1, color: color, width: 3 / draw.width, points: [posFromEvent(ps, e)] };
                    newStrokes.push(current);
                }
                function move(e) {
                    if (!drawing) return;
                    current.points.push(posFromEvent(ps, e));
                    redrawAll();
                }
                function end() {
                    drawing = false;
                    current = null;
                }

                draw.addEventListener('mousedown', begin);
                draw.addEventListener('touchstart', function (e) { e.preventDefault(); begin(e); }, { passive: false });
                draw.addEventListener('mousemove', move);
                draw.addEventListener('touchmove', function (e) { e.preventDefault(); move(e); }, { passive: false });
                window.addEventListener('mouseup', end);
                window.addEventListener('touchend', end);
            })(ps, draw);
        }
    }
}

async function init() {
    try {
        for (var i = 0; i < dokumenList.length; i++) {
            statusEl.textContent = 'Memuat ' + dokumenList[i].label + '… (' + (i + 1) + '/' + dokumenList.length + ')';
            await renderDokumen(dokumenList[i]);
        }
        statusEl.textContent = 'Semua dokumen siap — coret langsung di halaman 1 tiap dokumen.';
    } catch (err) {
        statusEl.textContent = 'Gagal memuat dokumen: ' + err.message;
        console.error(err);
    }
}

document.getElementById('coret-form').addEventListener('submit', function () {
    var finalStrokes = strokesSebelumnya.concat(newStrokes);
    document.getElementById('coret-json-field').value = finalStrokes.length ? JSON.stringify({ strokes: finalStrokes }) : '';
});

init();
</script>
@endsection
