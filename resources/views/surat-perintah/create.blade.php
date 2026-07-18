@extends(($isPublicForm ?? false) ? 'layouts.standalone' : 'layouts.app')

@section('activeNav', 'sp-input')
@section('title', 'Input Surat Perintah')

@section('content')
<div class="dash-card">
    <h3>Input Surat Perintah</h3>
    <div class="sub">Lengkapi data Surat Perintah lalu unggah file PDF-nya.</div>

    @if ($errors->any())
        <div class="err-box" style="display:block;">
            <strong>Terjadi kesalahan:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route(($isPublicForm ?? false) ? 'sp.input.store' : 'surat-perintah.store') }}" enctype="multipart/form-data">
        @csrf

        @include('surat-perintah._form')

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            @unless ($isPublicForm ?? false)
                <a class="btn" href="{{ route('surat-perintah.index') }}">Batal</a>
            @endunless
            <button type="submit" class="btn prim">Kirim Data</button>
        </div>
    </form>
</div>
@endsection
