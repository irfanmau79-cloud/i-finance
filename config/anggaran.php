<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tahun Anggaran Operasional
    |--------------------------------------------------------------------------
    |
    | Satu sumber nilai sementara untuk seluruh guard import Prompt 12A.
    | Ganti mekanisme ini hanya melalui migrasi dan workflow rollover
    | multi-tahun yang khusus, bukan dengan menambah hardcode di controller.
    |
    */
    'tahun_aktif' => 2026,

    'catatan_scope' => 'Current operational scope is Tahun Anggaran 2026. Before importing master anggaran, RAK, NPD, or SPM for another fiscal year, the application must receive a dedicated multi-year master-data migration and rollover workflow.',
];
