@php
    $u = $user ?? null;
    $pegawaiTerpilih = $u?->pegawai;
    $pegawaiIdVal = old('pegawai_id', $u->pegawai_id ?? '');
    $pegawaiLabel = old('pegawai_id') !== null
        ? ''
        : ($pegawaiTerpilih ? $pegawaiTerpilih->nama.' — '.$pegawaiTerpilih->nip : '');

    $pegawaiDataJs = $pegawaiList->map(fn ($p) => [
        'id' => $p->id,
        'nama' => $p->nama,
        'nip' => $p->nip,
        'sub' => trim($p->jabatan.' — '.$p->bidang, ' —'),
    ]);
@endphp

<div class="form-grid">
    <div class="fg">
        <label class="fl" for="username">Username</label>
        @if ($u)
            <input type="text" id="username" value="{{ $u->username }}" disabled style="background:#f1f3f5;">
        @else
            <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="mis. pptk2" autofocus>
        @endif
    </div>

    <div class="fg">
        <label class="fl" for="nama">Nama</label>
        <input type="text" id="nama" name="nama" value="{{ old('nama', $u->nama ?? '') }}">
    </div>

    <div class="fg">
        <label class="fl" for="role">Role</label>
        <select id="role" name="role">
            <option value="">-- Pilih Role --</option>
            @foreach (config('akses.role_label') as $key => $label)
                @continue($key === 'layanan')
                <option value="{{ $key }}" @selected(old('role', $u->role ?? null) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="fg span2">
        <label class="fl">Kaitkan ke Pegawai (opsional)</label>
        <div class="nsearch" data-pegawai-search>
            <svg class="ns-ic" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="ns-inp" data-pegawai-input autocomplete="off" placeholder="Cari nama atau NIP pegawai..." value="{{ $pegawaiLabel }}">
            <div class="ns-drop" data-pegawai-drop></div>
        </div>
        <input type="hidden" name="pegawai_id" data-pegawai-id value="{{ $pegawaiIdVal }}">
        <p class="mini" data-pegawai-hint style="{{ $pegawaiIdVal ? '' : 'display:none;' }}">Terkait pegawai — NIP otomatis mengikuti data pegawai. <a href="#" data-pegawai-clear>Lepas kaitan</a></p>
    </div>

    <div class="fg">
        <label class="fl" for="nip">NIP</label>
        <input type="text" id="nip" name="nip" data-nip-input maxlength="30" value="{{ old('nip', $u->nip ?? '') }}" @if($pegawaiIdVal) readonly @endif>
    </div>

    <div class="fg">
        <label class="fl" for="password">{{ $u ? 'Reset Password (opsional)' : 'Password' }}</label>
        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="{{ $u ? 'Kosongkan jika tidak ingin mengganti' : 'Minimal 6 karakter' }}">
    </div>
</div>

<script>
(function () {
    const pegawaiData = @json($pegawaiDataJs);

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    const wrap = document.querySelector('[data-pegawai-search]');
    const input = wrap.querySelector('[data-pegawai-input]');
    const drop = wrap.querySelector('[data-pegawai-drop]');
    const idField = document.querySelector('[data-pegawai-id]');
    const nipInput = document.querySelector('[data-nip-input]');
    const hint = document.querySelector('[data-pegawai-hint]');

    function render(query) {
        const q = query.trim().toLowerCase();
        let items = q ? pegawaiData.filter(function (p) {
            return p.nama.toLowerCase().indexOf(q) >= 0 || p.nip.toLowerCase().indexOf(q) >= 0;
        }) : pegawaiData;
        items = items.slice(0, 30);

        drop.innerHTML = '';
        if (! items.length) {
            drop.innerHTML = '<div class="ns-empty">Tidak ditemukan.</div>';
        } else {
            items.forEach(function (p) {
                const el = document.createElement('div');
                el.className = 'ns-item';
                el.innerHTML = '<div><div>' + escapeHtml(p.nama) + '</div><div class="sub">' + escapeHtml(p.nip + ' — ' + p.sub) + '</div></div>';
                el.addEventListener('click', function () {
                    input.value = p.nama + ' — ' + p.nip;
                    idField.value = p.id;
                    nipInput.value = p.nip;
                    nipInput.readOnly = true;
                    hint.style.display = '';
                    drop.classList.remove('show');
                });
                drop.appendChild(el);
            });
        }
        drop.classList.add('show');
    }

    input.addEventListener('input', function () {
        idField.value = '';
        render(input.value);
    });
    input.addEventListener('focus', function () { render(input.value); });

    document.querySelectorAll('[data-pegawai-clear]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = '';
            idField.value = '';
            nipInput.readOnly = false;
            hint.style.display = 'none';
        });
    });

    document.addEventListener('click', function (e) {
        if (drop.classList.contains('show') && ! wrap.contains(e.target)) {
            drop.classList.remove('show');
        }
    });
})();
</script>
