<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Aplikasi berjalan dengan locale 'id' (config/app.php). Tanpa berkas ini
| Laravel tidak menemukan terjemahannya dan MENAMPILKAN KUNCINYA MENTAH -
| itulah asal pesan "validation.required" yang sempat muncul di formulir NPD.
|
| Nama field pada :attribute diambil dari method attributes() pada tiap
| FormRequest (mis. 'Sumber Dana', 'Tanggal NPD'), jadi bagian 'attributes'
| di bawah sengaja dibiarkan kosong - satu tempat saja yang mengatur nama
| field, yaitu FormRequest-nya sendiri.
|
| lang/en/ tetap ada sebagai fallback (config/app.php), supaya kunci yang
| belum sempat diterjemahkan tampil dalam bahasa Inggris, bukan mentah.
|
*/

return [

    'accepted' => ':attribute wajib disetujui.',
    'accepted_if' => ':attribute wajib disetujui bila :other bernilai :value.',
    'active_url' => ':attribute harus berupa URL yang sah.',
    'after' => ':attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'any_of' => ':attribute tidak sah.',
    'array' => ':attribute harus berupa daftar.',
    'ascii' => ':attribute hanya boleh berisi huruf, angka, dan simbol satu byte.',
    'before' => ':attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus terdiri dari :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'can' => ':attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute belum memuat nilai yang diperlukan.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute harus berupa tanggal yang sah.',
    'date_equals' => ':attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => ':attribute harus sesuai format :format.',
    'decimal' => ':attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => ':attribute wajib ditolak.',
    'declined_if' => ':attribute wajib ditolak bila :other bernilai :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Dimensi gambar :attribute tidak sah.',
    'distinct' => ':attribute tidak boleh diisi lebih dari satu kali.',
    'doesnt_contain' => ':attribute tidak boleh memuat salah satu dari: :values.',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri salah satu dari: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali salah satu dari: :values.',
    'email' => ':attribute harus berupa alamat email yang sah.',
    'encoding' => ':attribute harus memakai penyandian :encoding.',
    'ends_with' => ':attribute harus diakhiri salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak sah.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'extensions' => ':attribute harus berekstensi salah satu dari: :values.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',
    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus :value kilobyte atau lebih.',
        'numeric' => ':attribute harus bernilai :value atau lebih.',
        'string' => ':attribute harus terdiri dari :value karakter atau lebih.',
    ],
    'hex_color' => ':attribute harus berupa kode warna heksadesimal yang sah.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak sah.',
    'in_array' => ':attribute harus ada di dalam :other.',
    'in_array_keys' => ':attribute harus memuat sedikitnya salah satu kunci berikut: :values.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang sah.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang sah.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang sah.',
    'json' => ':attribute harus berupa teks JSON yang sah.',
    'list' => ':attribute harus berupa daftar berurut.',
    'lowercase' => ':attribute harus ditulis dengan huruf kecil.',
    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute tidak boleh lebih dari :value kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :value.',
        'string' => ':attribute tidak boleh lebih dari :value karakter.',
    ],
    'mac_address' => ':attribute harus berupa alamat MAC yang sah.',
    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => ':attribute tidak boleh lebih dari :max angka.',
    'mimes' => ':attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => ':attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => ':attribute harus berisi sedikitnya :min item.',
        'file' => ':attribute harus berukuran sedikitnya :min kilobyte.',
        'numeric' => ':attribute harus bernilai sedikitnya :min.',
        'string' => ':attribute harus terdiri dari sedikitnya :min karakter.',
    ],
    'min_digits' => ':attribute harus terdiri dari sedikitnya :min angka.',
    'missing' => ':attribute tidak boleh ada.',
    'missing_if' => ':attribute tidak boleh ada bila :other bernilai :value.',
    'missing_unless' => ':attribute tidak boleh ada kecuali :other bernilai :value.',
    'missing_with' => ':attribute tidak boleh ada bila :values terisi.',
    'missing_with_all' => ':attribute tidak boleh ada bila :values semuanya terisi.',
    'multiple_of' => ':attribute harus kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak sah.',
    'not_regex' => 'Format :attribute tidak sah.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus memuat sedikitnya satu huruf.',
        'mixed' => ':attribute harus memuat sedikitnya satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus memuat sedikitnya satu angka.',
        'symbols' => ':attribute harus memuat sedikitnya satu simbol.',
        'uncompromised' => ':attribute yang dimasukkan pernah bocor di internet. Silakan pilih :attribute lain.',
    ],
    'present' => ':attribute harus ada.',
    'present_if' => ':attribute harus ada bila :other bernilai :value.',
    'present_unless' => ':attribute harus ada kecuali :other bernilai :value.',
    'present_with' => ':attribute harus ada bila :values terisi.',
    'present_with_all' => ':attribute harus ada bila :values semuanya terisi.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'prohibited_if' => ':attribute tidak boleh diisi bila :other bernilai :value.',
    'prohibited_if_accepted' => ':attribute tidak boleh diisi bila :other disetujui.',
    'prohibited_if_declined' => ':attribute tidak boleh diisi bila :other ditolak.',
    'prohibited_unless' => ':attribute tidak boleh diisi kecuali :other bernilai salah satu dari :values.',
    'prohibits' => ':attribute membuat :other tidak boleh diisi.',
    'regex' => 'Format :attribute tidak sah.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute wajib memuat isian untuk: :values.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => ':attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => ':attribute wajib diisi bila :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai salah satu dari :values.',
    'required_with' => ':attribute wajib diisi bila :values terisi.',
    'required_with_all' => ':attribute wajib diisi bila :values semuanya terisi.',
    'required_without' => ':attribute wajib diisi bila :values tidak terisi.',
    'required_without_all' => ':attribute wajib diisi bila tidak satu pun dari :values terisi.',
    'same' => ':attribute harus sama dengan :other.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus terdiri dari :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus berupa zona waktu yang sah.',
    'unique' => ':attribute sudah dipakai.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus ditulis dengan huruf besar.',
    'url' => ':attribute harus berupa URL yang sah.',
    'ulid' => ':attribute harus berupa ULID yang sah.',
    'uuid' => ':attribute harus berupa UUID yang sah.',

    'custom' => [],

    // Sengaja kosong: nama field diatur di method attributes() tiap FormRequest.
    'attributes' => [],

];
