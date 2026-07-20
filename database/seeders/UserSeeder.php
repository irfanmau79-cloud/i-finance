<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $passwordPlain = env('IFINANCE_SEED_PASSWORD', 'development-only-change-me');

        if (app()->environment('production') && ! env('IFINANCE_SEED_PASSWORD')) {
            throw new RuntimeException('IFINANCE_SEED_PASSWORD wajib diisi saat menjalankan UserSeeder di production.');
        }

        $now = now();
        $password = Hash::make($passwordPlain);

        $users = [
            ['username' => 'dev-superadmin', 'role' => 'superadmin', 'nama' => 'Development Superadmin'],
            ['username' => 'dev-bendahara-pengeluaran', 'role' => 'bendahara_pengeluaran', 'nama' => 'Development Bendahara Pengeluaran'],
            ['username' => 'dev-pptk', 'role' => 'pptk', 'nama' => 'Development PPTK'],
            ['username' => 'dev-bpp', 'role' => 'bpp', 'nama' => 'Development BPP'],
            ['username' => 'dev-verifikator', 'role' => 'verifikator', 'nama' => 'Development Verifikator'],
            ['username' => 'dev-sekretaris', 'role' => 'sekretaris', 'nama' => 'Development Sekretaris'],
            ['username' => 'dev-kasubbag', 'role' => 'kasubbag', 'nama' => 'Development Kasubbag'],
            ['username' => 'dev-inspektur', 'role' => 'inspektur', 'nama' => 'Development Inspektur'],
            ['username' => 'dev-irban', 'role' => 'inspektur_pembantu', 'nama' => 'Development Inspektur Pembantu'],
            ['username' => 'dev-perencanaan', 'role' => 'perencanaan', 'nama' => 'Development Perencanaan'],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['username' => $user['username']],
                $user + ['password' => $password, 'aktif' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
