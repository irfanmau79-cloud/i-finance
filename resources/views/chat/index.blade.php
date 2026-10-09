@extends('layouts.app')

@section('activeNav', 'chat')
@section('title', 'Chat')

@section('content')
{{--
    Chat internal antar akun: percakapan pribadi dan ruang per role.

    Pesan baru diambil dengan polling ringan (lihat skrip di bawah), bukan
    WebSocket. Isi pesan SELALU dipasang lewat textContent, tidak pernah
    innerHTML - pesannya tulisan bebas pengguna lain.
--}}
<div class="dash-card chat-kartu{{ $ruang ? ' ada-ruang' : '' }}">
    <div class="chat">
        {{-- ============ Sisi kiri: daftar ruang + chat baru ============ --}}
        <aside class="chat-sisi" aria-label="Daftar percakapan">
            <div class="chat-sisi-kepala">
                <h3>Chat</h3>
                <button type="button" class="btn prim chat-baru-tombol" id="chat-baru-tombol" aria-expanded="false" aria-controls="chat-baru">+ Chat Baru</button>
            </div>

            <div class="chat-baru" id="chat-baru" hidden>
                <input type="text" id="chat-baru-cari" placeholder="Cari nama atau role&hellip;" autocomplete="off" aria-label="Cari akun atau ruang role">

                <div class="chat-baru-daftar">
                    <div class="chat-baru-judul">Ruang Role</div>
                    @foreach ($roleList as $role => $label)
                        <form method="POST" action="{{ route('chat.role') }}" data-chat-cari="{{ Str::lower('ruang '.$label) }}">
                            @csrf
                            <input type="hidden" name="role" value="{{ $role }}">
                            <button type="submit" class="chat-baru-item">
                                <span class="chat-avatar role" aria-hidden="true">#</span>
                                <span><b>Ruang {{ $label }}</b><small>Dibaca semua akun {{ $label }}</small></span>
                            </button>
                        </form>
                    @endforeach

                    @forelse ($kontak as $labelRole => $akun)
                        <div class="chat-baru-judul" data-chat-grup>{{ $labelRole }}</div>
                        @foreach ($akun as $orang)
                            <form method="POST" action="{{ route('chat.pribadi', $orang) }}" data-chat-cari="{{ Str::lower($orang->nama.' '.$labelRole) }}">
                                @csrf
                                <button type="submit" class="chat-baru-item">
                                    <span class="chat-avatar" aria-hidden="true">{{ Str::upper(Str::substr($orang->nama, 0, 1)) }}</span>
                                    <span><b>{{ $orang->nama }}</b><small>{{ $labelRole }}</small></span>
                                </button>
                            </form>
                        @endforeach
                    @empty
                        <div class="chat-kosong-kecil">Belum ada akun lain yang aktif.</div>
                    @endforelse
                </div>
            </div>

            <nav class="chat-daftar" id="chat-daftar" aria-label="Percakapan">
                @forelse ($daftar as $satu)
                    <a class="chat-item{{ $ruang && $ruang->id === $satu['id'] ? ' aktif' : '' }}{{ $satu['belum'] > 0 ? ' belum' : '' }}" href="{{ $satu['url'] }}" data-ruang="{{ $satu['id'] }}">
                        <span class="chat-avatar{{ $satu['jenis'] === 'role' ? ' role' : '' }}" aria-hidden="true">{{ $satu['jenis'] === 'role' ? '#' : Str::upper(Str::substr($satu['nama'], 0, 1)) }}</span>
                        <span class="chat-item-isi">
                            <span class="chat-item-nama">{{ $satu['nama'] }}</span>
                            <span class="chat-item-cuplikan">{{ $satu['cuplikan'] !== '' ? $satu['cuplikan'] : $satu['keterangan'] }}</span>
                        </span>
                        @if ($satu['belum'] > 0)
                            <span class="chat-item-angka">{{ $satu['belum'] > 99 ? '99+' : $satu['belum'] }}</span>
                        @endif
                    </a>
                @empty
                    <div class="chat-kosong-kecil">Belum ada percakapan. Tekan <b>+ Chat Baru</b> untuk memulai.</div>
                @endforelse
            </nav>
        </aside>

        {{-- ============ Sisi kanan: ruang yang dibuka ============ --}}
        <section class="chat-utama" aria-label="Percakapan terbuka">
            @if ($ruang)
                <header class="chat-kepala">
                    <a class="chat-kembali" href="{{ route('chat.index') }}" aria-label="Kembali ke daftar percakapan">&larr;</a>
                    <span class="chat-avatar{{ $ruang->isRole() ? ' role' : '' }}" aria-hidden="true">{{ $ruang->isRole() ? '#' : Str::upper(Str::substr($namaRuang, 0, 1)) }}</span>
                    <div>
                        <div class="chat-kepala-nama">{{ $namaRuang }}</div>
                        @if ($keteranganRuang)
                            <div class="chat-kepala-ket">{{ $keteranganRuang }}</div>
                        @endif
                    </div>
                </header>

                <div class="chat-pesan" id="chat-pesan" role="log" aria-live="polite" aria-label="Isi percakapan"></div>

                <form method="POST" action="{{ route('chat.kirim', $ruang) }}" class="chat-tulis" id="chat-tulis">
                    @csrf
                    <textarea name="isi" id="chat-isi" rows="1" maxlength="{{ \App\Models\ChatPesan::MAKS_PANJANG }}" required
                              placeholder="Tulis pesan&hellip; (Enter untuk kirim, Shift+Enter baris baru)" aria-label="Tulis pesan"></textarea>
                    <button type="submit" class="btn prim" id="chat-kirim">Kirim</button>
                </form>
                @error('isi')
                    <div class="err-box" style="display:block;margin:0 16px 12px;">{{ $message }}</div>
                @enderror
                <div class="chat-galat" id="chat-galat" hidden></div>
            @else
                <div class="chat-kosong">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <div class="chat-kosong-judul">Pilih percakapan</div>
                    <div class="chat-kosong-ket">Buka percakapan di sebelah kiri, atau tekan <b>+ Chat Baru</b> untuk menyapa satu akun atau seluruh pemegang sebuah role.</div>
                </div>
            @endif
        </section>
    </div>
</div>

<script>
(function () {
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const URL_RINGKAS = @json(route('chat.ringkas'));
    const RUANG_AKTIF = @json($ruang?->id);

    // ---------------- Panel "Chat Baru" ----------------
    const tombolBaru = document.getElementById('chat-baru-tombol');
    const panelBaru = document.getElementById('chat-baru');
    const cariBaru = document.getElementById('chat-baru-cari');

    tombolBaru.addEventListener('click', function () {
        const buka = panelBaru.hidden;
        panelBaru.hidden = ! buka;
        // Selama terbuka, panelnya memakai seluruh tinggi kolom kiri -
        // daftar percakapan disembunyikan lewat kelas ini (lihat styles).
        panelBaru.closest('.chat-sisi').classList.toggle('baru-terbuka', buka);
        tombolBaru.setAttribute('aria-expanded', String(buka));
        tombolBaru.textContent = buka ? 'Tutup' : '+ Chat Baru';
        if (buka) cariBaru.focus();
    });

    cariBaru.addEventListener('input', function () {
        const q = cariBaru.value.trim().toLowerCase();
        panelBaru.querySelectorAll('[data-chat-cari]').forEach(function (el) {
            el.hidden = q !== '' && ! el.dataset.chatCari.includes(q);
        });
        // Judul kelompok ikut disembunyikan bila seluruh isinya tersaring.
        panelBaru.querySelectorAll('.chat-baru-judul').forEach(function (judul) {
            let ada = false;
            for (let el = judul.nextElementSibling; el && ! el.classList.contains('chat-baru-judul'); el = el.nextElementSibling) {
                if (! el.hidden) { ada = true; break; }
            }
            judul.hidden = ! ada;
        });
    });

    // ---------------- Waktu ----------------
    const fmtJam = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
    const fmtHari = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const kunciHari = (d) => d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();

    // ---------------- Daftar ruang (polling) ----------------
    const elDaftar = document.getElementById('chat-daftar');
    const lencana = document.getElementById('tb-chat-angka');

    function setLencana(n) {
        if (! lencana) return;
        lencana.textContent = n > 99 ? '99+' : String(n);
        lencana.hidden = n === 0;
    }

    function gambarDaftar(ruang) {
        if (! ruang.length) return;

        elDaftar.textContent = '';
        ruang.forEach(function (r) {
            const a = document.createElement('a');
            a.className = 'chat-item' + (r.id === RUANG_AKTIF ? ' aktif' : '') + (r.belum > 0 ? ' belum' : '');
            a.href = r.url;
            a.dataset.ruang = r.id;

            const av = document.createElement('span');
            av.className = 'chat-avatar' + (r.jenis === 'role' ? ' role' : '');
            av.setAttribute('aria-hidden', 'true');
            av.textContent = r.jenis === 'role' ? '#' : (r.nama || '?').charAt(0).toUpperCase();

            const isi = document.createElement('span');
            isi.className = 'chat-item-isi';
            const nama = document.createElement('span');
            nama.className = 'chat-item-nama';
            nama.textContent = r.nama;
            const cup = document.createElement('span');
            cup.className = 'chat-item-cuplikan';
            cup.textContent = r.cuplikan !== '' ? r.cuplikan : r.keterangan;
            isi.append(nama, cup);

            a.append(av, isi);

            if (r.belum > 0) {
                const angka = document.createElement('span');
                angka.className = 'chat-item-angka';
                angka.textContent = r.belum > 99 ? '99+' : String(r.belum);
                a.append(angka);
            }

            elDaftar.append(a);
        });
    }

    function segarkanDaftar() {
        if (document.hidden) return;

        fetch(URL_RINGKAS, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(function (data) {
                if (! data) return;
                gambarDaftar(data.ruang || []);
                setLencana(data.belum || 0);
            })
            .catch(function () {});
    }

    setInterval(segarkanDaftar, 8000);

    // ---------------- Ruang terbuka ----------------
    const elPesan = document.getElementById('chat-pesan');
    if (! elPesan) return;

    const URL_PESAN = @json($ruang ? route('chat.pesan', $ruang) : null);
    const RUANG_ROLE = @json((bool) $ruang?->isRole());
    const form = document.getElementById('chat-tulis');
    const isian = document.getElementById('chat-isi');
    const tombolKirim = document.getElementById('chat-kirim');
    const elGalat = document.getElementById('chat-galat');

    let idTerakhir = 0;
    let hariTerakhir = null;
    const sudahAda = new Set();

    function diBawah() {
        return elPesan.scrollHeight - elPesan.scrollTop - elPesan.clientHeight < 80;
    }

    function tambah(p) {
        if (sudahAda.has(p.id)) return;
        sudahAda.add(p.id);
        idTerakhir = Math.max(idTerakhir, p.id);

        const waktu = new Date(p.waktu);

        if (kunciHari(waktu) !== hariTerakhir) {
            hariTerakhir = kunciHari(waktu);
            const pemisah = document.createElement('div');
            pemisah.className = 'chat-hari';
            pemisah.textContent = fmtHari.format(waktu);
            elPesan.append(pemisah);
        }

        const baris = document.createElement('div');
        baris.className = 'chat-b' + (p.milik_saya ? ' saya' : '');

        // Nama pengirim hanya perlu di ruang role, dan hanya untuk orang lain.
        if (RUANG_ROLE && ! p.milik_saya) {
            const nama = document.createElement('div');
            nama.className = 'chat-b-nama';
            nama.textContent = p.pengirim;
            baris.append(nama);
        }

        const isi = document.createElement('div');
        isi.className = 'chat-b-isi';
        isi.textContent = p.isi;

        const jam = document.createElement('div');
        jam.className = 'chat-b-jam';
        jam.textContent = fmtJam.format(waktu);

        baris.append(isi, jam);
        elPesan.append(baris);
    }

    function tampilGalat(teks) {
        elGalat.textContent = teks || '';
        elGalat.hidden = ! teks;
    }

    const awal = @json($pesan);
    if (awal.length) {
        awal.forEach(tambah);
    } else {
        const kosong = document.createElement('div');
        kosong.className = 'chat-kosong-kecil';
        kosong.id = 'chat-belum-ada';
        kosong.textContent = 'Belum ada pesan. Tulis pesan pertama di bawah.';
        elPesan.append(kosong);
    }
    elPesan.scrollTop = elPesan.scrollHeight;

    function ambilBaru() {
        if (document.hidden) return;

        fetch(URL_PESAN + '?sesudah=' + idTerakhir, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(function (data) {
                if (! data || ! data.pesan.length) return;

                const ikutTurun = diBawah();
                const belumAda = document.getElementById('chat-belum-ada');
                if (belumAda) belumAda.remove();
                data.pesan.forEach(tambah);
                if (ikutTurun) elPesan.scrollTop = elPesan.scrollHeight;
            })
            .catch(function () {});
    }

    // sesudah=0 berarti "muat pesan terakhir", jadi polling baru dimulai
    // setelah ada patokan; ruang yang masih kosong memakai patokan 0 dan
    // server mengembalikan pesan pertama begitu ada.
    setInterval(ambilBaru, 4000);

    function kirim() {
        const teks = isian.value.trim();
        if (teks === '' || tombolKirim.disabled) return;

        tombolKirim.disabled = true;
        tampilGalat('');

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ isi: teks }),
        })
            .then(function (r) {
                return r.json().catch(() => ({})).then(function (data) {
                    if (! r.ok) {
                        const pesan = r.status === 429
                            ? 'Terlalu banyak pesan dalam waktu singkat. Tunggu sebentar lalu coba lagi.'
                            : ((data.errors && data.errors.isi && data.errors.isi[0]) || data.message || 'Pesan gagal terkirim. Coba lagi.');
                        throw new Error(pesan);
                    }

                    // Jawaban sukses tetapi bukan pesan: biasanya sesi sudah
                    // berakhir dan permintaannya dialihkan ke halaman login.
                    if (! data.pesan) {
                        throw new Error('Pesan tidak terkirim. Sesi mungkin sudah berakhir - muat ulang halaman lalu coba lagi.');
                    }

                    return data;
                });
            })
            .then(function (data) {
                const belumAda = document.getElementById('chat-belum-ada');
                if (belumAda) belumAda.remove();
                tambah(data.pesan);
                isian.value = '';
                aturTinggi();
                elPesan.scrollTop = elPesan.scrollHeight;
                segarkanDaftar();
            })
            .catch(function (e) { tampilGalat(e.message); })
            .finally(function () {
                tombolKirim.disabled = false;
                isian.focus();
            });
    }

    function aturTinggi() {
        isian.style.height = 'auto';
        isian.style.height = Math.min(isian.scrollHeight, 140) + 'px';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        kirim();
    });

    isian.addEventListener('input', aturTinggi);
    isian.addEventListener('keydown', function (e) {
        // Enter mengirim; Shift+Enter baris baru. isComposing: jangan kirim
        // saat pengguna masih memilih huruf lewat papan ketik IME.
        if (e.key === 'Enter' && ! e.shiftKey && ! e.isComposing) {
            e.preventDefault();
            kirim();
        }
    });

    isian.focus();
})();
</script>
@endsection
