<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Bank Tujuan Pencairan
    |--------------------------------------------------------------------------
    |
    | Dipakai dropdown "Bank Tujuan" pada formulir SP2D LS. Disimpan sebagai
    | daftar teks, bukan tabel, karena tidak ada CRUD-nya - yang tersimpan di
    | spm.bank_tujuan adalah NAMANYA, jadi menambah/mengubah baris di sini
    | tidak pernah merusak data SPM yang sudah ada.
    |
    | Urutannya sengaja tidak alfabetis murni: bank yang paling sering dipakai
    | Pemda Jawa Barat ditaruh lebih dulu, sisanya alfabetis. Dropdown-nya bisa
    | diketik untuk mencari, jadi daftar panjang tetap cepat dipakai. Bila
    | banknya tidak ada di daftar, pengguna memilih "Isi Manual" (lihat
    | App\Models\Spm::BANK_MANUAL) lalu mengetik sendiri.
    |
    | Tambahkan baris baru di bagian alfabetis bila ada bank yang belum masuk.
    |
    */
    'daftar' => [
        // Paling sering dipakai di lingkungan Pemda Jawa Barat.
        'Bank BJB',
        'Bank BJB Syariah',
        'Bank Mandiri',
        'Bank BRI (Bank Rakyat Indonesia)',
        'Bank BNI (Bank Negara Indonesia)',
        'Bank BTN (Bank Tabungan Negara)',
        'Bank BCA (Bank Central Asia)',
        'Bank BSI (Bank Syariah Indonesia)',

        // Selebihnya alfabetis.
        'Allo Bank Indonesia',
        'Bangkok Bank',
        'Bank Aceh Syariah',
        'Bank Aladin Syariah',
        'Bank Amar Indonesia',
        'Bank ANZ Indonesia',
        'Bank Artha Graha Internasional',
        'Bank BCA Syariah',
        'Bank Bengkulu',
        'Bank BNP Paribas Indonesia',
        'Bank BPD Bali',
        'Bank BPD DIY',
        'Bank BTPN Syariah',
        'Bank Bumi Arta',
        'Bank Banten',
        'Bank Capital Indonesia',
        'Bank CCB Indonesia (China Construction Bank)',
        'Bank CIMB Niaga',
        'Bank Citibank Indonesia',
        'Bank CTBC Indonesia',
        'Bank Danamon Indonesia',
        'Bank DBS Indonesia',
        'Bank Deutsche Bank Indonesia',
        'Bank DKI',
        'Bank Ganesha',
        'Bank Hana Indonesia (KEB Hana)',
        'Bank HSBC Indonesia',
        'Bank IBK Indonesia',
        'Bank ICBC Indonesia',
        'Bank Ina Perdana',
        'Bank Index Selindo',
        'Bank Jago',
        'Bank Jambi',
        'Bank Jateng',
        'Bank Jatim',
        'Bank JTrust Indonesia',
        'Bank Kalbar',
        'Bank Kalsel',
        'Bank Kalteng',
        'Bank Kaltimtara',
        'Bank KB Bank (dahulu Bukopin)',
        'Bank KB Bukopin Syariah',
        'Bank Krom Indonesia',
        'Bank Lampung',
        'Bank Maluku Malut',
        'Bank Mandiri Taspen',
        'Bank Maybank Indonesia',
        'Bank Mayapada Internasional',
        'Bank Mega',
        'Bank Mega Syariah',
        'Bank Mizuho Indonesia',
        'Bank MNC Internasional',
        'Bank MUFG Bank Indonesia',
        'Bank Multiarta Sentosa',
        'Bank Muamalat Indonesia',
        'Bank Nagari (BPD Sumatera Barat)',
        'Bank Nationalnobu',
        'Bank Neo Commerce',
        'Bank NTB Syariah',
        'Bank NTT',
        'Bank OCBC Indonesia',
        'Bank OKE Indonesia',
        'Bank Panin (PaninBank)',
        'Bank Panin Dubai Syariah',
        'Bank Papua',
        'Bank Permata',
        'Bank QNB Indonesia',
        'Bank Raya Indonesia',
        'Bank Resona Perdania',
        'Bank Riau Kepri Syariah',
        'Bank Sahabat Sampoerna',
        'Bank SeaBank Indonesia',
        'Bank Shinhan Indonesia',
        'Bank Sinarmas',
        'Bank SMBC Indonesia (dahulu BTPN)',
        'Bank Standard Chartered Indonesia',
        'Bank Sulselbar',
        'Bank Sulteng',
        'Bank Sultra',
        'Bank SulutGo',
        'Bank Sumsel Babel',
        'Bank Sumut',
        'Bank Sumitomo Mitsui Indonesia',
        'Bank Superbank Indonesia',
        'Bank UOB Indonesia',
        'Bank Victoria International',
        'Bank Victoria Syariah',
        'Bank Woori Saudara Indonesia',
        'Bank of America',
        'Bank of China (Hong Kong) Limited',
        'JPMorgan Chase Bank',
    ],
];
