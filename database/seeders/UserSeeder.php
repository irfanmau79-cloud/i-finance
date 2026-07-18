<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();
        $password = Hash::make('ifinance2026');

        $users = [
            ['username' => 'bendahara', 'role' => 'bendahara', 'nama' => 'Bendahara Pengeluaran'],
            ['username' => 'pptk', 'role' => 'pptk', 'nama' => 'Pejabat Pelaksana Teknis Kegiatan'],
            ['username' => 'bpp', 'role' => 'bpp', 'nama' => 'Rani Puspandari, A.Md.'],
            ['username' => 'verifikator', 'role' => 'verifikator', 'nama' => 'Marfuathun Nurul Hasanah, A.Md.'],
            ['username' => 'verifikator-2', 'role' => 'verifikator', 'nama' => 'M. Iqbal Rizquloh, S.Tr.I.P.'],
            ['username' => 'sekretaris', 'role' => 'sekretaris', 'nama' => 'Oky Putranto, S.STP., M.A.P.'],
            ['username' => 'kasubbag', 'role' => 'kasubbag', 'nama' => 'Verri Riyanto, M.S.P.'],
            ['username' => 'inspektur', 'role' => 'inspektur', 'nama' => 'Eman Sulaeman'],
            ['username' => 'irban1', 'role' => 'inspektur_pembantu', 'nama' => 'Dr. ATI HOEROWATI, SH., M.Si'],
            ['username' => 'irban2', 'role' => 'inspektur_pembantu', 'nama' => 'DADANG SUHERNA, ST., M.T., M.Eng'],
            ['username' => 'irban3', 'role' => 'inspektur_pembantu', 'nama' => 'TITO MAHESA SENJAYA, S.P., M.P.'],
            ['username' => 'irban4', 'role' => 'inspektur_pembantu', 'nama' => 'MUHAMAD YUSUF, S.Sos., M.Si.'],
            ['username' => 'irbaninv', 'role' => 'inspektur_pembantu', 'nama' => 'Dr. AKHMAD MUKHLIS, SE, M.Si'],
            ['username' => 'perencanaan', 'role' => 'perencanaan', 'nama' => 'Perencanaan'],
        ];

        foreach ($users as &$user) {
            $user['password'] = $password;
            $user['created_at'] = $now;
            $user['updated_at'] = $now;
        }

        DB::table('users')->insert($users);
    }
}
