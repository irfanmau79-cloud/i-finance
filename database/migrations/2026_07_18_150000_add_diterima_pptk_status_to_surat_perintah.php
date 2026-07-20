<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Diterima PPTK" adalah status awal SP sendiri (sebelum tertaut NPD
     * mana pun), terpisah dari status workflow NPD yang di-mirror lewat
     * Npd::mirrorStatusKeSuratPerintah(). Kolom ini sebelumnya cuma berisi
     * status workflow NPD, jadi SP yang baru dibuat tidak punya nilai awal.
     */
    public function up(): void
    {
        $this->ubahStatusEnum([
            'Diterima PPTK',
            'Draft NPD - PPTK',
            'Draft NPD - BPP',
            'Verifikasi - Verifikator',
            'Dikembalikan',
            'NPD Disetujui - BPP',
            'Selesai',
        ]);

        // Data lama tanpa status (belum pernah tertaut NPD) dianggap "Diterima PPTK".
        DB::table('surat_perintah')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'Diterima PPTK']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('surat_perintah')->where('status', 'Diterima PPTK')->update(['status' => null]);

        $this->ubahStatusEnum([
            'Draft NPD - PPTK',
            'Draft NPD - BPP',
            'Verifikasi - Verifikator',
            'Dikembalikan',
            'NPD Disetujui - BPP',
            'Selesai',
        ]);
    }

    /**
     * MySQL/MariaDB membutuhkan MODIFY untuk mengganti daftar ENUM. Driver
     * lain (terutama SQLite in-memory pada test) harus melalui schema builder
     * agar Laravel membangun ulang constraint kolom secara portabel.
     */
    private function ubahStatusEnum(array $nilai): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $enum = collect($nilai)
                ->map(fn (string $status) => DB::getPdo()->quote($status))
                ->implode(', ');

            DB::statement("ALTER TABLE surat_perintah MODIFY status ENUM({$enum}) NULL");

            return;
        }

        Schema::table('surat_perintah', function (Blueprint $table) use ($nilai) {
            $table->enum('status', $nilai)->nullable()->change();
        });
    }
};
