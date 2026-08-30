{{--
    Nota saat dropdown Mata Anggaran tidak punya satu pun pilihan.

    Tanpa ini dropdown Program hanya tampil kosong tanpa keterangan, dan
    pengguna tidak tahu apakah datanya belum ada atau halamannya gagal
    memuat. Untuk PPTK penyebabnya hampir selalu pelimpahan yang belum
    diset — lihat App\Support\AnggaranNpd.
--}}
@if ($masterAnggaran->isEmpty())
    <div class="nota-peringatan">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
            @if (auth()->user()?->role === \App\Models\User::ROLE_PPTK)
                <strong>Belum ada Sub Kegiatan yang dilimpahkan ke akun ini.</strong>
                NPD hanya dapat dibuat untuk Sub Kegiatan yang menjadi tanggung jawab Anda.
                Hubungi Superadmin untuk menetapkan pelimpahannya lewat menu Pelimpahan.
            @else
                <strong>Belum ada Mata Anggaran aktif.</strong>
                Lengkapi Master Anggaran terlebih dahulu lewat menu Manajemen Data.
            @endif
        </div>
    </div>
@endif
