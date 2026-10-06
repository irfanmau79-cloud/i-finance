@extends('layouts.app')

@section('activeNav', 'tk-form')
@section('title', 'Perubahan Data Tunjangan Keluarga')

@section('content')
<div class="page-head">
    <div>
        <div class="ph-crumb">Beranda / <b>Tunjangan Keluarga</b> / Perubahan Data</div>
        <div class="ph-title">Perubahan Data Tunjangan Keluarga</div>
    </div>
</div>

<div class="dash-card">
    <div class="sub">Gunakan form ini untuk pemutakhiran data keluarga yang mendapatkan tunjangan. Berkas disimpan secara private dan hanya dapat diakses petugas berwenang.</div>

    @if (session('success'))
        <div class="sumbar ok" style="margin-top:14px"><span>{{ session('success') }}</span></div>
    @endif

    @if ($pegawai === null)
        {{-- Langkah 1: tautkan formulir ke satu pegawai. Data keluarga yang
             sekarang tersimpan baru dibaca dan ditampilkan SESUDAH langkah
             ini lolos di server (TunjanganKeluargaController::bukaFormulir) -
             panel ini hanya antarmukanya. --}}
        <form method="POST" action="{{ route('tunjangan.form.buka') }}" class="tkf-gerbang">
            @csrf
            <div class="tkf-gerbang-judul">Masukkan NIP Pegawai</div>
            <div class="sub" style="margin-bottom:12px;">
                @if ($bebasGerbang)
                    Perubahan akan ditautkan ke pegawai ber-NIP ini. Nama dan data tunjangan keluarganya tampil setelah NIP ditemukan.
                @else
                    Perubahan akan ditautkan ke pegawai ber-NIP ini. Untuk menjaga privasi, data keluarga hanya dibuka bagi pegawai
                    yang bersangkutan: masukkan NIP dan 4 digit terakhir nomor rekening Anda.
                @endif
            </div>
            <div class="tkf-gerbang-baris">
                <div class="tkf-gerbang-isian" style="flex:1;min-width:220px;">
                    <label for="tkf-nip">NIP</label>
                    <input id="tkf-nip" type="text" name="nip" inputmode="numeric" autocomplete="off" required
                           placeholder="Contoh: 199907022021021001" value="{{ old('nip') }}">
                </div>
                @unless ($bebasGerbang)
                    <div class="tkf-gerbang-isian" style="width:180px;">
                        <label for="tkf-rek">4 Digit Akhir Rekening</label>
                        <input id="tkf-rek" type="text" name="rek4" inputmode="numeric" maxlength="4" required
                               autocomplete="off" placeholder="Contoh: 1234" value="{{ old('rek4') }}">
                    </div>
                @endunless
                <button class="btn prim" type="submit">Tampilkan Data</button>
            </div>
            @if ($nipSesi !== null)
                <div class="tkf-gerbang-galat">
                    NIP {{ $nipSesi }} belum ada di Data Pegawai, jadi perubahannya belum bisa ditautkan. Hubungi Kepegawaian, atau coba NIP lain.
                </div>
            @endif
            @if ($errors->any())
                <div class="tkf-gerbang-galat">{{ $errors->first() }}</div>
            @endif
        </form>
    @else
        @if ($errors->any())
            <div class="err-box" style="display:block;margin-top:14px"><strong>Terjadi kesalahan:</strong>
                <ul style="margin:6px 0 0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Langkah 2: pegawainya sudah pasti. Identitasnya tidak diketik
             ulang - server mengambilnya dari NIP yang sudah dibuka di atas. --}}
        <div class="tkf-pegawai">
            <div class="tkf-pegawai-isi">
                <div class="k">Nama Pegawai</div>
                <div class="v">{{ $pegawai->nama }}</div>
            </div>
            <div class="tkf-pegawai-isi">
                <div class="k">NIP</div>
                <div class="v">{{ $pegawai->nip }}</div>
            </div>
            <div class="tkf-pegawai-isi">
                <div class="k">Status Tunjangan Saat Ini</div>
                <div class="v"><span class="badge st-aktif">{{ $statusSekarang }}</span></div>
            </div>
            <form method="POST" action="{{ route('tunjangan.form.ganti-nip') }}" style="margin-left:auto;">
                @csrf
                <button type="submit" class="btn" style="padding:6px 14px;font-size:12.5px;">Ganti NIP</button>
            </form>
        </div>

        @php
            // Saat validasi gagal, isian yang baru diketik menggantikan data
            // tersimpan dan semua kartu dibuka supaya tidak ada yang hilang.
            $isiLama = session()->hasOldInput();
            $pasanganIsi = $isiLama ? (array) old('pasangan', []) : $keluargaAwal['pasangan'];
            $anakIsi = array_values($isiLama ? (array) old('anak', []) : $keluargaAwal['anak']);
        @endphp

        <form method="POST" action="{{ route('tunjangan.submit') }}" enctype="multipart/form-data" style="margin-top:6px">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important">

            <div style="font-weight:600;color:var(--tegas);font-size:13.5px;margin:20px 0 4px;">Data Tunjangan Keluarga</div>
            <div class="sub" style="margin-bottom:12px;">
                Ini data yang sekarang tersimpan. Tekan <b>Ubah Data</b> pada anggota yang berubah lalu isi data barunya.
                Anggota yang tidak diubah tetap seperti semula. Perubahan baru berlaku setelah di-approve Kepegawaian.
            </div>

            <div class="fam-grid" id="fam-grid">
                @include('tunjangan-keluarga._anggota-card', [
                    'nama' => 'pasangan', 'id' => 'pasangan', 'judul' => 'Pasangan', 'ikon' => '&hearts;',
                    'data' => $pasanganIsi, 'anak' => false, 'buka' => $isiLama,
                ])

                @foreach ($anakIsi as $i => $anak)
                    @include('tunjangan-keluarga._anggota-card', [
                        'nama' => 'anak['.$i.']', 'id' => 'anak-'.$i, 'judul' => 'Anak Ke-'.($i + 1), 'ikon' => $i + 1,
                        'data' => (array) $anak, 'anak' => true, 'buka' => $isiLama,
                    ])
                @endforeach
            </div>

            <div style="margin-top:14px">
                <button type="button" class="btn" id="add-anak">+ Tambah Anak</button>
            </div>

            <div class="fg" style="margin-top:18px">
                <label class="fl" for="keterangan">Keterangan Perubahan Data</label>
                <textarea id="keterangan" name="keterangan" placeholder="Contoh: Menikah / Cerai / Lahir Anak" required>{{ old('keterangan') }}</textarea>
            </div>

            <div class="fg" style="margin-top:14px;max-width:520px;">
                <label class="fl" for="lampiran">Lampiran Bukti Dukung</label>
                <input id="lampiran" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png" required>
                <div style="font-size:11px;color:var(--mut);margin-top:4px;font-style:italic;">Upload dokumen pendukung (Buku Nikah / Akta Lahir / Surat Keterangan Kuliah) &mdash; PDF/JPG/PNG, maks. 5 MB.</div>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:18px;">
                <button type="submit" class="btn prim">Kirim Data Perubahan</button>
            </div>
        </form>

        {{-- Cetakan kartu anak baru untuk "+ Tambah Anak". Angka urutnya
             diganti di peramban; isinya kosong dan langsung dalam rupa isi. --}}
        <template id="tpl-anak">
            @include('tunjangan-keluarga._anggota-card', [
                'nama' => 'anak[__I__]', 'id' => 'anak-__I__', 'judul' => 'Anak Ke-__N__', 'ikon' => '__N__',
                'data' => [], 'anak' => true, 'buka' => true,
            ])
        </template>
    @endif
</div>

<style>
    .tkf-gerbang{margin-top:14px;border:1px solid var(--line);background:var(--surface-2);border-radius:12px;padding:16px 18px;max-width:680px;}
    .tkf-gerbang-judul{font-weight:700;color:var(--tegas);font-size:14px;margin-bottom:4px;}
    .tkf-gerbang-baris{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
    .tkf-gerbang-isian{display:flex;flex-direction:column;gap:5px;}
    .tkf-gerbang-isian label{font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--mut);margin:0;}
    .tkf-gerbang-isian input{height:40px;border:1.5px solid var(--line);border-radius:11px;padding:0 13px;font-size:13.5px;color:var(--tegas);background:var(--surface);font-family:inherit;}
    .tkf-gerbang-galat{margin-top:10px;font-size:12.5px;color:var(--err);}

    .tkf-pegawai{display:flex;flex-wrap:wrap;align-items:center;gap:14px 34px;margin-top:14px;padding:14px 18px;border:1px solid var(--line);
        border-radius:12px;background:var(--surface-2);}
    .tkf-pegawai-isi .k{font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--mut);}
    .tkf-pegawai-isi .v{margin-top:3px;font-size:14px;font-weight:700;color:var(--tegas);}

    /* Kartu anggota: rupa LIHAT (data tersimpan) dan rupa ISI (isian). */
    .tkf-kartu .fam-head{margin-bottom:10px;}
    .tkf-tanda{display:none;margin-left:auto;padding:2px 9px;border-radius:50px;background:var(--warn-bg);color:var(--warn-teks);font-size:10.5px;font-weight:700;}
    .tkf-kartu.diubah{border-color:var(--aksen);}
    .tkf-kartu.diubah .tkf-tanda{display:inline-block;}
    .tkf-nama{font-size:14px;font-weight:700;color:var(--tegas);line-height:1.35;overflow-wrap:anywhere;}
    .tkf-lahir{margin-top:2px;font-size:12px;color:var(--mut);}
    .tkf-lencana{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
    .tkf-cat{margin-top:8px;font-size:12px;color:var(--ink);line-height:1.45;}
    .tkf-kosong{font-size:12.5px;color:var(--mut);font-style:italic;}
    .tkf-ubah{margin-top:12px;padding:7px 14px;font-size:12.5px;}
    .tkf-kaki{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;}
    .tkf-kaki span{font-size:11px;color:var(--mut);font-style:italic;line-height:1.4;}
    .tkf-kaki .btn{flex:0 0 auto;padding:6px 12px;font-size:12px;}
</style>

@if ($pegawai !== null)
<script>
(function () {
    const grid = document.getElementById('fam-grid');

    /* Buka/tutup rupa isi. "Batal Ubah" mengembalikan isian ke data
       tersimpan (nilai bawaan tiap isian), bukan sekadar menyembunyikannya -
       kalau tidak, perubahan yang dibatalkan tetap ikut terkirim. */
    grid.addEventListener('click', function (e) {
        const kartu = e.target.closest('[data-ubah-kartu]');
        if (!kartu) return;
        const lihat = kartu.querySelector('[data-ubah-lihat]');
        const isi = kartu.querySelector('[data-ubah-isi]');

        if (e.target.closest('[data-ubah-buka]')) {
            lihat.hidden = true;
            isi.hidden = false;
            kartu.classList.add('diubah');
            const pertama = isi.querySelector('input');
            if (pertama) pertama.focus();
        }

        if (e.target.closest('[data-ubah-batal]')) {
            // Kartu anak yang baru ditambahkan tidak punya data tersimpan:
            // membatalkannya berarti membuang kartunya.
            if (kartu.hasAttribute('data-anak-baru')) {
                kartu.remove();
                return;
            }
            isi.querySelectorAll('input').forEach(function (el) { el.value = el.defaultValue; });
            isi.querySelectorAll('select').forEach(function (el) {
                Array.prototype.forEach.call(el.options, function (o) { o.selected = o.defaultSelected; });
            });
            isi.hidden = true;
            lihat.hidden = false;
            kartu.classList.remove('diubah');
        }
    });

    document.getElementById('add-anak').addEventListener('click', function () {
        const jumlah = grid.querySelectorAll('[data-anak-card]').length;
        if (jumlah >= 10) return;

        // Indeks isian harus unik walau ada kartu yang sempat dibuang.
        let indeks = jumlah;
        while (grid.querySelector('[name="anak[' + indeks + '][nama]"]')) indeks++;

        const html = document.getElementById('tpl-anak').innerHTML
            .replace(/__I__/g, String(indeks))
            .replace(/__N__/g, String(jumlah + 1));
        const wadah = document.createElement('div');
        wadah.innerHTML = html.trim();
        const kartu = wadah.firstElementChild;
        kartu.setAttribute('data-anak-baru', '');
        grid.appendChild(kartu);
        kartu.querySelector('input').focus();
    });
})();
</script>
@endif
@endsection
