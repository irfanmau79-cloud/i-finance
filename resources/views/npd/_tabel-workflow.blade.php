{{--
    Tabel Daftar NPD gas-lama-style (Sub Kegiatan/Kode Rekening/Tagging/Penerima/
    Nominal/Status/Aksi) — dipakai bersama oleh Pembuatan NPD, Persetujuan NPD,
    dan Verifikasi NPD. Aksi transisi workflow (Ajukan/Teruskan/Verifikasi/
    Setujui/dst) dihitung generik lewat $npd->aksiTersedia($role), jadi satu
    partial ini otomatis menampilkan tombol yang tepat di ketiga halaman tanpa
    perlu tahu halaman mana yang memanggilnya. $tampilkanKelola (Edit + Hapus)
    cuma relevan di Pembuatan NPD, sesuai gas-lama (Persetujuan/Verifikasi
    tidak punya aksi itu).
--}}
@php
    $tampilkanKelola = $tampilkanKelola ?? false;

    /**
     * Mode "Pilih NPD" (aksi massal) - khusus superadmin, sepadan dengan
     * role bendahara di GAS. Aksi yang ditawarkan diturunkan dari NPD yang
     * BENAR-BENAR ada di halaman ini: percuma menawarkan "Setujui Final"
     * kalau tidak satu pun barisnya berstatus Draft NPD - BPP.
     *
     * Aksi yang wajib catatan sengaja tidak ikut - alasannya harus ditulis
     * per NPD (lihat NpdController::transisiMassal).
     */
    $bolehMassal = auth()->user()->isSuperadmin();
    $aksiMassal = [];

    if ($bolehMassal) {
        foreach ($npds as $npdBaris) {
            foreach ($npdBaris->aksiTersedia(auth()->user()->role) as $kunci) {
                if (in_array($kunci, \App\Models\Npd::AKSI_WAJIB_CATATAN, true)) {
                    continue;
                }

                $aksiMassal[$kunci] = \App\Models\Npd::TRANSISI[$kunci];
            }
        }
    }

    $bolehMassal = $bolehMassal && $aksiMassal !== [];
    $editRouteMap = ['bj' => 'npd.bj.edit', 'pd' => 'npd.pd.edit', 'tr' => 'npd.tr.edit', 'ns' => 'npd.ns.edit', 'kd' => 'npd.kd.edit'];
    $wfIkon = [
        'ajukan_bpp' => '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'terima_npd' => '<svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
        'teruskan' => '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'verifikasi' => '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'kembali_bpp' => '<svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
        'kembali_pptk' => '<svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>',
        'setuju' => '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        'selesai' => '<svg viewBox="0 0 24 24"><path d="M4 22V4a1 1 0 0 1 1-1h13l-2 5 2 5H6"/></svg>',
        'batal_selesai' => '<svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><polyline points="3 3 3 8 8 8"/></svg>',
    ];
    $wfDanger = ['kembali_bpp', 'kembali_pptk', 'batal_selesai'];
    $aksiButuhForm = ['verifikasi', 'kembali_pptk', 'batal_selesai'];
    $role = auth()->user()->role;
@endphp


@if ($bolehMassal)
<form method="POST" action="{{ route('npd.transisi-massal') }}" id="npd-massal-form">
@csrf
<div class="npd-massal" data-massal-bar>
    <button type="button" class="btn" data-massal-toggle>Pilih NPD</button>

    <div data-massal-kendali hidden style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <span class="sub"><b data-massal-jumlah>0</b> dipilih</span>
        <select name="aksi" data-massal-aksi style="max-width:260px;">
            @foreach ($aksiMassal as $kunci => $rule)
                <option value="{{ $kunci }}">{{ $rule['label'] }} &mdash; dari "{{ $rule['from'] }}"</option>
            @endforeach
        </select>
        <button type="submit" class="btn prim" data-massal-jalan disabled>Jalankan</button>
        <span class="sub" data-massal-ket></span>
    </div>
</div>
</form>
@endif

<div class="npd-scroll" style="border:1px solid var(--line);border-radius:8px;overflow:auto;">
    <table class="realisasi npd-table" id="npd-tabel" style="width:100%;table-layout:fixed;">
        <colgroup>
            {{-- Lebar kolom hasil ukur, bukan kira-kira:
                 - Status 13%: pil terpanjang ("Verifikasi - Verifikator") butuh
                   123px + padding sel. Di 9% pilnya tumpah ke kolom Aksi.
                 - Nominal 12,5%: nominal NPD terbesar yang ada bernilai sembilan
                   angka dan butuh 130px. JANGAN dipersempit lagi tanpa mengukur
                   ulang - angkanya nowrap, jadi kelebihannya langsung tumpah. --}}
            <col style="width:9%;"><col style="width:12%;"><col style="width:11%;"><col style="width:10.5%;">
            <col style="width:12%;"><col style="width:12.5%;"><col style="width:13%;"><col style="width:12%;"><col style="width:8%;">
        </colgroup>
        <thead>
            <tr>
                <th>@if ($bolehMassal)<span data-massal-kolom hidden style="margin-right:6px;"><input type="checkbox" class="bulk-ck" data-massal-semua aria-label="Pilih semua"></span>@endif No. Dokumen</th><th>Sub Kegiatan</th><th>Kode Rekening</th><th>Tagging</th>
                <th>Penerima</th><th class="num">Nominal</th><th class="st">Status</th>
                <th>Uraian</th><th style="text-align:center;">Aksi</th>
            </tr>
            {{-- Penyaring ketik-manual per kolom, seperti di Data NPD. Bekerja
                 seketika tanpa tombol Terapkan dan tanpa memuat ulang halaman. --}}
            <tr class="kolom-saring">
                <th><input type="text" data-kolom="0" placeholder="Ketik nomor&hellip;" aria-label="Saring No. Dokumen"></th>
                <th><input type="text" data-kolom="1" placeholder="Ketik sub kegiatan&hellip;" aria-label="Saring Sub Kegiatan"></th>
                <th><input type="text" data-kolom="2" placeholder="Ketik kode&hellip;" aria-label="Saring Kode Rekening"></th>
                <th><input type="text" data-kolom="3" placeholder="Ketik tagging&hellip;" aria-label="Saring Tagging"></th>
                <th><input type="text" data-kolom="4" placeholder="Ketik penerima&hellip;" aria-label="Saring Penerima"></th>
                <th><input type="text" data-kolom="5" placeholder="Ketik nominal&hellip;" aria-label="Saring Nominal"></th>
                <th><input type="text" data-kolom="6" placeholder="Ketik status&hellip;" aria-label="Saring Status"></th>
                <th><input type="text" data-kolom="7" placeholder="Ketik uraian&hellip;" aria-label="Saring Uraian"></th>
                <th>
                    <div class="saring-kosong">
                        <button type="button" id="npd-saring-reset" title="Kosongkan penyaring" aria-label="Kosongkan penyaring">
                            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody id="npd-tabel-body">
            @forelse ($npds as $npd)
                @php
                    $aksiTersedia = $npd->aksiTersedia($role);
                    $bisaEdit = $tampilkanKelola && $npd->dapatDieditOleh(auth()->user());
                    $bisaHapus = $tampilkanKelola && $npd->dapatDihapusOleh(auth()->user());
                @endphp
                <tr>
                    {{-- Nomor NPD baru terbit setelah diverifikasi; sebelum itu
                         yang dikenal petugas adalah nomor Surat Perintahnya. --}}
                    <td class="kol-npd">
                        @if ($bolehMassal)
                            <span data-massal-kolom hidden style="margin-right:6px;">
                                <input type="checkbox" class="bulk-ck" data-massal-pilih
                                       value="{{ $npd->id }}" data-status="{{ $npd->status }}"
                                       aria-label="Pilih {{ $npd->nomorDokumen() }}">
                            </span>
                        @endif
                        {{ $npd->nomorDokumen() }}
                    </td>
                    <td>{{ $npd->masterAnggaran->sub_kegiatan_lengkap }}</td>
                    <td>{{ $npd->masterAnggaran->rekening_lengkap }}</td>
                    <td>{{ $npd->masterAnggaran->tagging->nama ?? '-' }}</td>
                    <td>
                        <div class="pen-nm">{{ $npd->ringkasanPenerima() }}</div>
                        <div class="pen-sub">({{ \App\Models\Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper($npd->jenis) }})</div>
                    </td>
                    <td class="num">Rp {{ number_format((float) $npd->nominal, 2, ',', '.') }}</td>
                    <td class="kol-status">
                        {{-- Pil status sama persis dengan Data NPD, ditambah pil
                             Catatan bernada emas di bawahnya. --}}
                        <div class="stat-kolom">
                            <span class="badge {{ \App\Models\Npd::STATUS_BADGE_CLASS[$npd->status] ?? 'st-diterima' }}">{{ $npd->status }}</span>
                            @if ($npd->catatan)
                                <span class="stat-cat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Catatan</span>
                            @endif
                        </div>
                    </td>
                    <td class="kol-uraian">{{ $npd->uraianRingkas() }}</td>
                    <td style="text-align:center;">
                        <div class="aksi-wrap">
                            @foreach ($aksiTersedia as $aksi)
                                @php($rule = \App\Models\Npd::TRANSISI[$aksi])
                                @if ($aksi === 'kembali_bpp')
                                    <a class="ic-btn danger" title="{{ $rule['label'] }} (bisa beri coretan pada dokumen PDF)" href="{{ route('npd.coret', $npd) }}">{!! $wfIkon[$aksi] !!}</a>
                                @elseif (in_array($aksi, $aksiButuhForm, true))
                                    <button type="button" class="ic-btn {{ in_array($aksi, $wfDanger, true) ? 'danger' : '' }}" title="{{ $rule['label'] }}"
                                        data-wf-open="{{ $aksi }}" data-wf-url="{{ route('npd.transisi', $npd) }}">{!! $wfIkon[$aksi] !!}</button>
                                @else
                                    <button type="button" class="ic-btn" title="{{ $rule['label'] }}"
                                        data-wf-confirm="{{ $aksi }}" data-wf-confirm-url="{{ route('npd.transisi', $npd) }}"
                                        data-wf-confirm-label="{{ $rule['label'] }}">{!! $wfIkon[$aksi] !!}</button>
                                @endif
                            @endforeach
                            @if ($bisaEdit)
                                <a class="ic-btn" title="Edit NPD" href="{{ route($editRouteMap[$npd->jenis] ?? 'npd.bj.edit', $npd) }}"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg></a>
                            @endif
                            <a class="ic-btn" title="Lihat NPD" href="{{ route('npd.show', $npd) }}"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            @if ($bisaHapus)
                                <details class="npd-hapus-pop">
                                    <summary class="ic-btn danger" title="Hapus NPD"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></summary>
                                    <form method="POST" action="{{ route('npd.destroy', $npd) }}" class="npd-hapus-form">
                                        @csrf
                                        @method('DELETE')
                                        <label class="fl" style="margin-top:0;">Alasan pembatalan</label>
                                        <input type="text" name="alasan" required maxlength="500" placeholder="Wajib diisi">
                                        <button type="submit" class="btn" style="margin-top:8px;width:100%;">Batalkan NPD</button>
                                    </form>
                                </details>
                            @endif
                            @if (auth()->user()->isSuperadmin())
                                <details class="npd-hapus-pop">
                                    <summary class="ic-btn danger" title="Hapus Permanen NPD"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/><path d="M10 11v5M14 11v5"/></svg></summary>
                                    <form method="POST" action="{{ route('npd.destroy-permanent', $npd) }}" class="npd-hapus-form"
                                        onsubmit="return confirm('Hapus permanen NPD ini beserta seluruh data turunannya? Tindakan ini TIDAK DAPAT dibatalkan.');">
                                        @csrf
                                        @method('DELETE')
                                        <label class="fl" style="margin-top:0;color:var(--err);">Alasan hapus permanen</label>
                                        <input type="text" name="alasan_permanen" required minlength="5" maxlength="500" placeholder="Minimal 5 karakter">
                                        <button type="submit" class="btn danger" style="margin-top:8px;width:100%;">Hapus Permanen</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;color:var(--mut);padding:20px;">Belum ada NPD.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="dn-kaki">
    <div class="dn-perpage">
        <span>Tampilkan</span>
        <select id="npd-perpage">
            @foreach ([10, 25, 50, 100, 250] as $n)
                <option value="{{ $n }}" @selected($n === 25)>{{ $n }}</option>
            @endforeach
        </select>
        <span>data</span>
    </div>
    <div class="inv-pager" id="npd-pager" style="padding:0;"></div>
</div>

<div class="mdl-ov" id="wf-mdl-ov">
    <div class="mdl">
        <div class="mdl-h" id="wf-mdl-title">Aksi</div>
        <div class="mdl-b">
            <form method="POST" id="wf-mdl-form">
                @csrf
                <input type="hidden" name="aksi" id="wf-mdl-aksi" value="">
                <div id="wf-mdl-nomor-wrap" style="display:none;">
                    <label class="fl" for="wf-mdl-nomor">Nomor NPD</label>
                    <input type="text" name="nomor_lengkap" id="wf-mdl-nomor" maxlength="100"
                           autocomplete="off" placeholder="Contoh: {{ \App\Models\Npd::CONTOH_NOMOR }}">
                    <div class="sub" style="margin-top:5px;">
                        Ditulis lengkap apa adanya - inilah yang tercetak di dokumen. Contohnya sekadar
                        acuan bentuk, bukan aturan. Nomor yang sudah dipakai NPD lain akan ditolak.
                    </div>
                </div>
                <div id="wf-mdl-catatan-wrap">
                    <label class="fl" id="wf-mdl-catatan-label">Catatan</label>
                    <textarea name="catatan" id="wf-mdl-catatan" rows="3" style="width:100%;box-sizing:border-box;"></textarea>
                </div>
                <div class="mdl-f" style="padding:14px 0 0;">
                    <button type="button" class="btn" onclick="wfModalClose()">Batal</button>
                    <button type="submit" class="btn prim">Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="mdl-ov" id="wf-confirm-ov">
    <div class="mdl" style="max-width:380px;">
        <div class="mdl-b" style="padding:24px 20px 6px;text-align:center;">
            <div style="width:52px;height:52px;border-radius:50%;background:var(--info-bg);color:var(--tegas);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
            <div style="font-size:16px;font-weight:700;color:var(--tegas);margin-bottom:6px;" id="wf-confirm-title">Konfirmasi</div>
            <div style="color:var(--mut);font-size:14px;line-height:1.5;" id="wf-confirm-msg">Yakin melanjutkan aksi ini?</div>
        </div>
        <form method="POST" id="wf-confirm-form">
            @csrf
            <input type="hidden" name="aksi" id="wf-confirm-aksi" value="">
            <div class="mdl-f" style="padding:18px 20px 20px;justify-content:center;">
                <button type="button" class="btn" onclick="wfConfirmClose()">Batal</button>
                <button type="submit" class="btn prim">Ya, Lanjutkan</button>
            </div>
        </form>
    </div>
</div>

<style>
    .npd-hapus-pop{position:relative;display:inline-block;}
    .npd-hapus-pop summary{list-style:none;}
    .npd-hapus-pop summary::-webkit-details-marker{display:none;}
    .npd-hapus-pop .npd-hapus-form{position:absolute;right:0;top:calc(100% + 6px);z-index:20;background:var(--surface);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.13);padding:12px;width:220px;text-align:left;}
    .npd-hapus-pop .npd-hapus-form input{width:100%;box-sizing:border-box;}
</style>

<script>
(function () {
    /* ===== Penyaring per kolom + penomoran halaman (sisi peramban) =====
       Baris tetap digambar peladen karena memuat tombol aksi beserta token
       formulirnya - yang dikerjakan di sini hanya menyaring dan memilah
       halaman atas baris-baris tersebut. */
    (function () {
        const tbody = document.getElementById('npd-tabel-body');
        if (!tbody) return;

        const semua = Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function (tr) {
            return tr.children.length > 1;   // lewati baris "Belum ada NPD"
        });
        if (!semua.length) return;

        const saring = Array.prototype.slice.call(document.querySelectorAll('tr.kolom-saring input[data-kolom]'));
        const pilihanPer = document.getElementById('npd-perpage');
        const pager = document.getElementById('npd-pager');

        let halaman = 1;
        let perHalaman = 25;

        function lolos(tr) {
            return saring.every(function (inp) {
                const q = inp.value.trim().toLowerCase();
                if (!q) return true;
                const sel = tr.children[Number(inp.dataset.kolom)];

                return sel ? sel.textContent.toLowerCase().includes(q) : false;
            });
        }

        function gambar() {
            const cocok = semua.filter(lolos);
            const total = cocok.length;
            const halamanTotal = Math.max(1, Math.ceil(total / perHalaman));
            halaman = Math.min(Math.max(halaman, 1), halamanTotal);
            const mulai = (halaman - 1) * perHalaman;

            semua.forEach(function (tr) { tr.style.display = 'none'; });
            cocok.slice(mulai, mulai + perHalaman).forEach(function (tr) { tr.style.display = ''; });

            if (!total) { pager.innerHTML = '<div class="pg-info">Tidak ada data.</div>'; return; }

            const tampil = Math.min(perHalaman, total - mulai);
            let info = '<div class="pg-info">Menampilkan ' + (mulai + 1) + '&ndash;' + (mulai + tampil) + ' dari ' + total + ' NPD</div>';
            let btns = '<button class="inv-pg" ' + (halaman <= 1 ? 'disabled' : '') + ' data-go="' + (halaman - 1) + '">&lsaquo;</button>';
            const daftar = [];
            for (let i = 1; i <= halamanTotal; i++) {
                if (i === 1 || i === halamanTotal || (i >= halaman - 1 && i <= halaman + 1)) daftar.push(i);
                else if (daftar[daftar.length - 1] !== '…') daftar.push('…');
            }
            daftar.forEach(function (i) {
                btns += i === '…' ? '<span class="inv-pg dots">…</span>'
                    : '<button class="inv-pg' + (i === halaman ? ' active' : '') + '" data-go="' + i + '">' + i + '</button>';
            });
            btns += '<button class="inv-pg" ' + (halaman >= halamanTotal ? 'disabled' : '') + ' data-go="' + (halaman + 1) + '">&rsaquo;</button>';

            pager.innerHTML = info + '<div class="pg-btns">' + btns + '</div>';
            pager.querySelectorAll('[data-go]').forEach(function (b) {
                b.addEventListener('click', function () { halaman = Number(b.dataset.go); gambar(); });
            });
        }

        saring.forEach(function (inp) {
            inp.addEventListener('input', function () { halaman = 1; gambar(); });
        });
        document.getElementById('npd-saring-reset').addEventListener('click', function () {
            saring.forEach(function (inp) { inp.value = ''; });
            halaman = 1;
            gambar();
        });
        pilihanPer.addEventListener('change', function () {
            perHalaman = Number(this.value);
            halaman = 1;
            gambar();
        });

        gambar();
    })();

    // Cuma satu popover Hapus yang boleh terbuka sekaligus.
    document.querySelectorAll('.npd-hapus-pop').forEach(function (pop) {
        pop.addEventListener('toggle', function () {
            if (!pop.open) return;
            document.querySelectorAll('.npd-hapus-pop').forEach(function (other) {
                if (other !== pop) other.open = false;
            });
        });
    });

    // Modal aksi workflow yang butuh input (catatan wajib/opsional, nomor urut verifikasi).
    var WF_FORM_META = {
        verifikasi: { title: 'Verifikasi NPD', nomor: true, catatanLabel: 'Catatan untuk BPP (opsional)', catatanRequired: false },
        kembali_pptk: { title: 'Kembalikan ke PPTK', nomor: false, catatanLabel: 'Catatan Revisi (wajib)', catatanRequired: true },
        batal_selesai: { title: 'Batalkan Status Selesai', nomor: false, catatanLabel: 'Alasan Pembatalan (wajib)', catatanRequired: true },
    };

    var ov = document.getElementById('wf-mdl-ov');
    var form = document.getElementById('wf-mdl-form');
    var aksiField = document.getElementById('wf-mdl-aksi');
    var nomorWrap = document.getElementById('wf-mdl-nomor-wrap');
    var nomorInput = document.getElementById('wf-mdl-nomor');
    var catatanLabel = document.getElementById('wf-mdl-catatan-label');
    var catatanInput = document.getElementById('wf-mdl-catatan');
    var titleEl = document.getElementById('wf-mdl-title');

    function wfModalOpen(aksi, url) {
        var meta = WF_FORM_META[aksi];
        if (!meta) return;

        titleEl.textContent = meta.title;
        form.action = url;
        aksiField.value = aksi;

        nomorWrap.style.display = meta.nomor ? 'block' : 'none';
        nomorInput.required = meta.nomor;
        nomorInput.value = '';

        catatanLabel.textContent = meta.catatanLabel;
        catatanInput.required = meta.catatanRequired;
        catatanInput.value = '';

        ov.classList.add('show');
    }

    window.wfModalClose = function () {
        ov.classList.remove('show');
    };

    document.querySelectorAll('[data-wf-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wfModalOpen(btn.getAttribute('data-wf-open'), btn.getAttribute('data-wf-url'));
        });
    });

    // Modal konfirmasi sederhana (ya/batal) - pengganti confirm() bawaan
    // browser untuk aksi workflow yang tidak butuh input tambahan (aksi di
    // luar $aksiButuhForm, mis. maju ke tahap berikutnya tanpa catatan).
    var confirmOv = document.getElementById('wf-confirm-ov');
    var confirmForm = document.getElementById('wf-confirm-form');
    var confirmAksiField = document.getElementById('wf-confirm-aksi');
    var confirmMsg = document.getElementById('wf-confirm-msg');

    window.wfConfirmClose = function () {
        confirmOv.classList.remove('show');
    };

    document.querySelectorAll('[data-wf-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var label = btn.getAttribute('data-wf-confirm-label');
            confirmForm.action = btn.getAttribute('data-wf-confirm-url');
            confirmAksiField.value = btn.getAttribute('data-wf-confirm');
            confirmMsg.textContent = 'Yakin ' + label + '?';
            confirmOv.classList.add('show');
        });
    });
})();

/* ===== Mode "Pilih NPD" (aksi massal) =====
   Hanya dirender untuk superadmin dan hanya bila ada aksi yang bisa
   dijalankan massal - lihat $bolehMassal di atas. */
(function () {
    const bar = document.querySelector('[data-massal-bar]');
    if (! bar) return;

    const form = document.getElementById('npd-massal-form');
    const tombolMode = bar.querySelector('[data-massal-toggle]');
    const kendali = bar.querySelector('[data-massal-kendali]');
    const pilihAksi = bar.querySelector('[data-massal-aksi]');
    const tombolJalan = bar.querySelector('[data-massal-jalan]');
    const labelJumlah = bar.querySelector('[data-massal-jumlah]');
    const keterangan = bar.querySelector('[data-massal-ket]');
    const kolom = document.querySelectorAll('[data-massal-kolom]');
    const semua = document.querySelector('[data-massal-semua]');

    let aktif = false;

    function kotak() {
        return Array.from(document.querySelectorAll('[data-massal-pilih]'));
    }

    function statusAsal() {
        const opsi = pilihAksi.options[pilihAksi.selectedIndex];
        return opsi ? opsi.textContent.split('dari "')[1]?.replace('"', '').trim() : null;
    }

    // Satu aksi hanya berlaku untuk satu status asal. Baris yang statusnya
    // tidak cocok dinonaktifkan di layar supaya petugas tidak mengirim
    // sesuatu yang pasti dilewati server.
    function selaraskan() {
        const asal = statusAsal();
        let cocok = 0;

        kotak().forEach(function (ck) {
            const bisa = ! asal || ck.dataset.status === asal;
            ck.disabled = ! bisa;
            if (! bisa) ck.checked = false;
            ck.closest('tr').style.opacity = (aktif && ! bisa) ? '.45' : '';
            if (bisa) cocok++;
        });

        const terpilih = kotak().filter(ck => ck.checked && ! ck.disabled);
        labelJumlah.textContent = String(terpilih.length);
        tombolJalan.disabled = terpilih.length === 0;
        keterangan.textContent = asal ? cocok + ' NPD berstatus "' + asal + '" di halaman ini' : '';
    }

    tombolMode.addEventListener('click', function () {
        aktif = ! aktif;
        kendali.hidden = ! aktif;
        kendali.style.display = aktif ? 'flex' : 'none';
        kolom.forEach(function (el) { el.hidden = ! aktif; });
        tombolMode.textContent = aktif ? 'Batal Pilih' : 'Pilih NPD';
        tombolMode.classList.toggle('prim', aktif);
        if (! aktif) kotak().forEach(function (ck) { ck.checked = false; ck.closest('tr').style.opacity = ''; });
        if (semua) semua.checked = false;
        selaraskan();
    });

    pilihAksi.addEventListener('change', selaraskan);

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-massal-pilih]')) selaraskan();
    });

    if (semua) {
        semua.addEventListener('change', function () {
            kotak().forEach(function (ck) {
                // Baris yang tersembunyi penyaring kolom tidak ikut terpilih -
                // yang terlihat itulah yang dimaksud "semua".
                const tampil = ck.closest('tr').style.display !== 'none';
                if (! ck.disabled && tampil) ck.checked = semua.checked;
            });
            selaraskan();
        });
    }

    // Isian dikirim sebagai npd[] - hanya yang tercentang dan tidak
    // dinonaktifkan yang ikut.
    form.addEventListener('submit', function (e) {
        form.querySelectorAll('input[name="npd[]"]').forEach(function (el) { el.remove(); });

        const terpilih = kotak().filter(ck => ck.checked && ! ck.disabled);

        if (terpilih.length === 0) {
            e.preventDefault();
            return;
        }

        const label = pilihAksi.options[pilihAksi.selectedIndex].textContent.split(' \u2014 ')[0];

        if (! window.confirm('Jalankan "' + label + '" untuk ' + terpilih.length + ' NPD?')) {
            e.preventDefault();
            return;
        }

        terpilih.forEach(function (ck) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'npd[]';
            hidden.value = ck.value;
            form.appendChild(hidden);
        });
    });

    selaraskan();
})();
</script>
